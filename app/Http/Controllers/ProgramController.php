<?php

namespace App\Http\Controllers;

use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use App\Services\ProjectProgressService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Pemetaan LOP per jenis Program (bucket). View monitoring read-only:
 * mengelompokkan LOP sebuah Program ke dalam bucket status + sebaran per branch,
 * dengan filter helicopter-view per region / branch.
 * Assignment / aksi operasional tetap di Inbox (LopController).
 */
class ProgramController extends Controller
{
    /**
     * Definisi bucket status. Satu sumber kebenaran untuk controller + view.
     * LOP berstatus `draft` = sudah diinput tapi belum di-assign ke teknisi;
     * ditampilkan sebagai bucket "Belum Ditugaskan".
     *
     * @var array<string, array{label: string, statuses: array<int, string>, variant: string}>
     */
    public const BUCKETS = [
        'unassigned' => ['label' => 'Unassigned', 'statuses' => ['draft'], 'variant' => 'neutral'],
        'assigned' => ['label' => 'Assigned', 'statuses' => ['assigned', 'picked_up'], 'variant' => 'info'],
        'progress' => ['label' => 'Dikerjakan', 'statuses' => ['survey', 'progress'], 'variant' => 'info'],
        'review' => ['label' => 'Review', 'statuses' => ['waiting_approval'], 'variant' => 'warning'],
        'done' => ['label' => 'Selesai', 'statuses' => ['completed'], 'variant' => 'success'],
        'rejected' => ['label' => 'Ditolak', 'statuses' => ['rejected'], 'variant' => 'danger'],
    ];

    public function __construct(
        private readonly ProjectProgressService $progressService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasRole(UserRole::TEKNISI)) {
            return redirect()->route('technician.inbox');
        }

        $this->authorize('viewAny', QeLop::class);

        $user = $request->user();
        $isSuperAdmin = $user->hasRole(UserRole::SUPER_ADMIN);
        [$regionFilter, $branchFilter] = $isSuperAdmin ? $this->resolveLocationFilters($request) : ['', ''];

        $summaries = collect(ProgramType::cases())->map(function (ProgramType $type) use ($user, $isSuperAdmin, $regionFilter, $branchFilter) {
            $query = $this->scopeForUser($this->baseQuery($type), $user);

            if ($isSuperAdmin) {
                $this->applyLocationFilter($query, $regionFilter, $branchFilter);
            }

            $counts = $this->statusCounts($query);

            return [
                'label' => $type->label(),
                'slug' => $type->value,
                'total' => array_sum($counts),
                'buckets' => $this->bucketCounts($counts),
            ];
        })->all();

        return view('program.index', [
            'programSummaries' => $summaries,
            ...$this->locationOptions($regionFilter, $branchFilter),
            ...$this->scopeContext($user, $isSuperAdmin, $regionFilter, $branchFilter),
        ]);
    }

    public function show(Request $request, string $program): View|RedirectResponse
    {
        $type = ProgramType::tryFrom($program);

        if ($type === null) {
            abort(404);
        }

        if ($request->user()->hasRole(UserRole::TEKNISI)) {
            return redirect()->route('technician.inbox');
        }

        $this->authorize('viewAny', QeLop::class);

        $user = $request->user();
        $isSuperAdmin = $user->hasRole(UserRole::SUPER_ADMIN);
        [$regionFilter, $branchFilter] = $isSuperAdmin ? $this->resolveLocationFilters($request) : ['', ''];

        $base = $this->scopeForUser($this->baseQuery($type), $user);

        if ($isSuperAdmin) {
            $this->applyLocationFilter($base, $regionFilter, $branchFilter);
        }

        $counts = $this->statusCounts(clone $base);
        $bucketCounts = $this->bucketCounts($counts);
        $total = array_sum($counts);

        $bucketFilter = $request->string('bucket')->trim()->value();
        if (! array_key_exists($bucketFilter, self::BUCKETS)) {
            $bucketFilter = '';
        }

        $branchBreakdown = (clone $base)
            ->selectRaw('branch, COUNT(*) as c')
            ->groupBy('branch')
            ->orderByDesc('c')
            ->get();

        $query = $base
            ->with([
                'creator', 'activeAssignment.technician', 'assignments.technician',
                'assignments.assigner', 'histories.user', 'materialReservation.items',
                'survey', 'evidences',
            ]);

        if ($bucketFilter !== '') {
            $query->whereIn('status_lop', self::BUCKETS[$bucketFilter]['statuses']);
        }

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder
                ->where('incident', 'like', "%{$search}%")
                ->orWhere('nama_lop', 'like', "%{$search}%")
                ->orWhere('sto', 'like', "%{$search}%")
                ->orWhere('branch', 'like', "%{$search}%"));
        }

        $lops = $query->latest()->paginate(20)->withQueryString();
        foreach ($lops->getCollection() as $lop) {
            $lop->setAttribute('progress_summary', $this->progressService->summary($lop));
        }

        $buckets = collect(self::BUCKETS)->map(fn ($def, $key) => [
            'key' => $key,
            'label' => $def['label'],
            'variant' => $def['variant'],
            'count' => $bucketCounts[$key],
            'active' => $bucketFilter === $key,
        ])->values()->all();

        return view('program.show', [
            'programType' => $type,
            'programTypes' => ProgramType::cases(),
            'total' => $total,
            'buckets' => $buckets,
            'bucketFilter' => $bucketFilter,
            'branchBreakdown' => $branchBreakdown,
            'lops' => $lops,
            'search' => $search,
            'technicians' => $this->activeTechnicians(),
            ...$this->locationOptions($regionFilter, $branchFilter),
            ...$this->scopeContext($user, $isSuperAdmin, $regionFilter, $branchFilter),
        ]);
    }

    /**
     * Teknisi aktif untuk modal assign (dipakai di tabel Program bila user berhak).
     */
    private function activeTechnicians()
    {
        return User::query()
            ->with('branch')
            ->whereHas('role', fn ($query) => $query->where('code', UserRole::TEKNISI->value))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Konteks scope untuk view: apakah user boleh memilih region/branch,
     * label lingkup yang sedang dilihat, dan peringatan bila akun belum
     * terhubung ke branch.
     *
     * @return array{canFilterLocation: bool, scopeLabel: string, scopeWarning: bool}
     */
    private function scopeContext(User $user, bool $isSuperAdmin, string $regionFilter, string $branchFilter): array
    {
        if ($isSuperAdmin) {
            return [
                'canFilterLocation' => true,
                'scopeLabel' => $branchFilter ?: ($regionFilter ?: 'Semua region & branch'),
                'scopeWarning' => false,
            ];
        }

        $branchName = $user->branch?->name;

        return [
            'canFilterLocation' => false,
            'scopeLabel' => $branchName ?? 'Branch belum diatur',
            'scopeWarning' => $branchName === null,
        ];
    }

    /**
     * Baca & sanitasi filter region/branch dari request.
     *
     * @return array{0: string, 1: string} [region, branch]
     */
    private function resolveLocationFilters(Request $request): array
    {
        $region = trim((string) $request->string('region'));
        $branch = trim((string) $request->string('branch'));

        if ($region !== '' && ! Branch::query()->where('region', $region)->exists()) {
            $region = '';
        }

        if ($branch !== '') {
            $branchQuery = Branch::query()->where('name', $branch);
            if ($region !== '') {
                $branchQuery->where('region', $region);
            }
            if (! $branchQuery->exists()) {
                $branch = '';
            }
        }

        return [$region, $branch];
    }

    /**
     * `qe_lops.branch` adalah string nama branch (bukan FK). Filter branch =
     * cocok nama; filter region = branch ada di daftar nama branch region itu.
     */
    private function applyLocationFilter(Builder $query, string $region, string $branch): void
    {
        if ($branch !== '') {
            $query->where('branch', $branch);

            return;
        }

        if ($region !== '') {
            $query->whereIn('branch', Branch::query()->where('region', $region)->select('name'));
        }
    }

    /**
     * @return array{regions: Collection, branches: Collection, regionFilter: string, branchFilter: string}
     */
    private function locationOptions(string $regionFilter, string $branchFilter): array
    {
        return [
            'regions' => Branch::query()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
            'branches' => Branch::query()->orderBy('region')->orderBy('name')->get(['id_branch', 'name', 'region']),
            'regionFilter' => $regionFilter,
            'branchFilter' => $branchFilter,
        ];
    }

    /**
     * @return array<string, int> status_lop => count
     */
    private function statusCounts(Builder $query): array
    {
        return $query
            ->toBase()
            ->selectRaw('status_lop, COUNT(*) as c')
            ->groupBy('status_lop')
            ->pluck('c', 'status_lop')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /**
     * @param  array<string, int>  $statusCounts
     * @return array<string, int> bucketKey => count
     */
    private function bucketCounts(array $statusCounts): array
    {
        $out = [];

        foreach (self::BUCKETS as $key => $def) {
            $out[$key] = array_sum(array_map(
                fn (string $status) => $statusCounts[$status] ?? 0,
                $def['statuses']
            ));
        }

        return $out;
    }

    /**
     * Query dasar pemetaan Program: seluruh LOP untuk 1 jenis Program (termasuk draft,
     * yang muncul di bucket "Belum Ditugaskan").
     */
    private function baseQuery(ProgramType $type): Builder
    {
        return QeLop::query()->where('program_type', $type->value);
    }

    /**
     * Scope data per akun. SUPER_ADMIN lintas-branch. Role lain (ADMIN /
     * MANAGER / APPROVER) dikunci ke branch akunnya (qe_lops.branch = nama
     * branch); tanpa branch => tidak ada data (aman, seperti dashboard admin).
     */
    private function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return $query;
        }

        $branchName = $user->branch?->name;

        if ($branchName === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch', $branchName);
    }
}
