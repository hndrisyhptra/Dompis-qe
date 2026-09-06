<?php

namespace App\Http\Controllers;

use App\Enums\EvidenceStatus;
use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Http\Requests\StoreLopRequest;
use App\Http\Requests\TransitionLopStatusRequest;
use App\Http\Requests\UpdateLopRequest;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use App\Services\DatekParserService;
use App\Services\EvidenceApprovalService;
use App\Services\LopNamingService;
use App\Services\LopService;
use App\Services\ManualIncidentService;
use App\Services\ProjectProgressService;
use App\Services\TicketLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LopController extends Controller
{
    public function __construct(
        private readonly LopService $lopService,
        private readonly LopNamingService $namingService,
        private readonly ProjectProgressService $progressService,
        private readonly EvidenceApprovalService $approvalService,
        private readonly TicketLookupService $ticketLookup,
        private readonly ManualIncidentService $manualIncident,
        private readonly DatekParserService $datekParser,
    ) {}

    /**
     * Inbox - Active LOP (semua status selain completed).
     */
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasRole(UserRole::TEKNISI)) {
            return redirect()->route('technician.inbox');
        }

        if ($request->user()->hasRole(UserRole::SUPER_ADMIN)) {
            return redirect()->route('evidence-approval.index');
        }

        $this->authorize('viewAny', QeLop::class);

        $query = QeLop::query()
            ->with([
                'creator', 'activeAssignment.technician', 'assignments.technician',
                'assignments.assigner', 'histories.user', 'materialReservation.items',
                'survey', 'evidences',
            ])
            ->where('status_lop', '!=', LopStatus::COMPLETED->value);

        $query = $this->scopeForUser($query, $request);

        $statsQuery = clone $query;

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder
                ->where('incident', 'like', "%{$search}%")
                ->orWhere('nama_lop', 'like', "%{$search}%")
                ->orWhere('sto', 'like', "%{$search}%")
                ->orWhere('branch', 'like', "%{$search}%"));
        }

        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status_lop', $status);
        }

        if ($program = $request->string('program')->trim()->value()) {
            $query->where('program_type', $program);
        }

        $lops = $query->latest()->paginate(20)->withQueryString();
        foreach ($lops->getCollection() as $lop) {
            $lop->setAttribute('progress_summary', $this->progressService->summary($lop));
            $lop->setAttribute('approval_summary', $this->approvalService->summary($lop));
        }

        return view('lop.index', [
            'lops' => $lops,
            'technicians' => $this->activeTechnicians(),
            'search' => $search,
            'statusFilter' => $status,
            'programFilter' => $program,
            'stats' => [
                'active' => (clone $statsQuery)->count(),
                'waiting' => (clone $statsQuery)->where('status_lop', LopStatus::WAITING_APPROVAL->value)->count(),
                'rejected' => (clone $statsQuery)->where('status_lop', LopStatus::REJECTED->value)->count(),
                'review' => (clone $statsQuery)->whereHas('evidences', fn ($query) => $query->where('status', EvidenceStatus::PENDING))->count(),
            ],
        ]);
    }

    /**
     * Inbox - History (LOP yang sudah completed).
     */
    public function history(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasRole(UserRole::TEKNISI)) {
            return redirect()->route('technician.inbox', ['tab' => 'complete']);
        }

        if ($request->user()->hasRole(UserRole::SUPER_ADMIN)) {
            return redirect()->route('evidence-approval.index');
        }

        $this->authorize('viewAny', QeLop::class);

        $query = QeLop::query()
            ->with(['creator', 'activeAssignment.technician'])
            ->where('status_lop', LopStatus::COMPLETED->value);

        $query = $this->scopeForUser($query, $request);

        return view('lop.history', [
            'lops' => $query->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QeLop::class);

        return view('lop.create', [
            'branches' => Branch::query()->orderBy('region')->orderBy('name')->get(),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Lookup data tiket dari DB operasional eksternal untuk auto-fill form
     * Input LOP Baru (STO, Branch, Segmen). Dipanggil via fetch dari Alpine.
     * Sekaligus mengecek apakah nomor incident sudah dipakai LOP lain di
     * `dompis_qe` (termasuk yang sudah di-soft-delete, karena aturan unik
     * pada qe_lops.incident juga mencakupnya).
     */
    public function ticketLookup(Request $request): JsonResponse
    {
        $this->authorize('create', QeLop::class);

        $data = $request->validate([
            'incident' => ['required', 'string', 'max:100'],
        ]);

        $incident = mb_strtoupper(trim($data['incident']));
        $result = $this->ticketLookup->lookup($incident);

        $existing = QeLop::withTrashed()->where('incident', $incident)->first();

        if ($existing !== null) {
            $result['existing_lop'] = [
                'nama_lop' => $existing->nama_lop,
                'status' => $existing->status_lop?->label(),
                'trashed' => $existing->trashed(),
            ];
        }

        return response()->json($result);
    }

    /**
     * Buat nomor tiket manual (INP...) saat incident tidak ada di DB tiket.
     * Branch mengikuti branch user yang login; Program dipilih di form.
     */
    public function manualIncident(Request $request): JsonResponse
    {
        $this->authorize('create', QeLop::class);

        $data = $request->validate([
            'program_type' => ['required', Rule::enum(ProgramType::class)],
        ]);

        $branch = $request->user()->branch;

        if ($branch === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Akun Anda belum terhubung ke branch. Hubungi admin untuk mengisi branch sebelum membuat nomor tiket manual.',
            ], 422);
        }

        $incident = $this->manualIncident->generate($branch, ProgramType::from($data['program_type']));

        return response()->json([
            'ok' => true,
            'incident' => $incident,
            'branch' => $branch->name,
        ]);
    }

    /**
     * Ekstraksi "datek terdampak" (ODC/ODP/GPON/kabel/dll) dari teks ringkasan
     * tiket. Dipakai tombol "Parse ulang" di form saat admin mengubah ringkasan.
     */
    public function parseDatek(Request $request): JsonResponse
    {
        $this->authorize('create', QeLop::class);

        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json($this->datekParser->parse($data['summary'] ?? null));
    }

    public function store(StoreLopRequest $request): RedirectResponse
    {
        $lop = $this->lopService->create($request->validated(), $request->user());

        return redirect()
            ->route('lop.index')
            ->with('status', 'LOP berhasil dibuat.');
    }

    public function show(QeLop $qe_lop): View|RedirectResponse
    {
        if (request()->user()->hasRole(UserRole::TEKNISI)) {
            return redirect()->route('technician.projects.show', $qe_lop);
        }

        $this->authorize('view', $qe_lop);

        if (request()->user()->hasRole(UserRole::SUPER_ADMIN)) {
            return redirect()->route('evidence-approval.index');
        }

        return redirect()->route('lop.index');
    }

    public function edit(QeLop $qe_lop): View
    {
        $this->authorize('update', $qe_lop);

        return view('lop.edit', [
            'lop' => $qe_lop,
            'branches' => Branch::query()->orderBy('region')->orderBy('name')->get(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateLopRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->lopService->update($qe_lop, $request->validated());

        return redirect()
            ->route('lop.index')
            ->with('status', 'LOP berhasil diperbarui.');
    }

    /**
     * Transisi status LOP (survey, progress, waiting_approval, completed,
     * rejected, dst) - divalidasi lewat LopService::transitionStatus()
     * terhadap whitelist LopStatus::transitions().
     */
    public function transitionStatus(TransitionLopStatusRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $target = LopStatus::from($request->validated('status'));

        $this->lopService->transitionStatus(
            $qe_lop,
            $target,
            $request->user(),
            $request->validated('note')
        );

        return redirect()
            ->route('lop.index')
            ->with('status', "Status LOP diubah ke {$target->label()}.");
    }

    /**
     * Scoping data per-role untuk seluruh daftar LOP.
     */
    private function scopeForUser($query, Request $request)
    {
        $user = $request->user();

        if ($user->hasRole(UserRole::TEKNISI)) {
            $query->whereHas('assignments', function ($q) use ($user) {
                $q->where('technician_id', $user->id_user);
            });
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            $query->where('created_by', $user->id_user);
        }

        return $query;
    }

    private function formOptions(): array
    {
        return [
            'segments' => LopSegment::cases(),
            'budgetTypes' => LopBudgetType::cases(),
            'programTypes' => ProgramType::cases(),
            'nameTemplate' => $this->namingService->activeTemplate(),
            'programCodes' => collect(ProgramType::cases())->mapWithKeys(
                fn (ProgramType $type) => [$type->value => $type->code()]
            ),
        ];
    }

    private function activeTechnicians()
    {
        return User::query()
            ->with('branch')
            ->whereHas('role', fn ($query) => $query->where('code', UserRole::TEKNISI->value))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
}
