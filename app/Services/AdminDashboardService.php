<?php

namespace App\Services;

use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminDashboardService
{
    public function __construct(
        private readonly DashboardMonitoringService $monitoring,
        private readonly LopVisibilityService $visibility,
    ) {}

    public function dashboard(User $user, array $filters): array
    {
        $user->loadMissing('branch');

        $isSuperAdmin = $user->hasRole(UserRole::SUPER_ADMIN);
        $filterState = $this->normalizeFilters($isSuperAdmin, $filters);
        $query = $this->scopedLops($user);

        if ($isSuperAdmin) {
            $this->applyFilters($query, $filterState);
        }

        $aggregate = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status_lop <> ? THEN 1 ELSE 0 END) as active', [LopStatus::COMPLETED->value])
            ->selectRaw('SUM(CASE WHEN status_lop = ? THEN 1 ELSE 0 END) as waiting_review', [LopStatus::WAITING_APPROVAL->value])
            ->selectRaw('SUM(CASE WHEN status_lop = ? THEN 1 ELSE 0 END) as completed', [LopStatus::COMPLETED->value])
            ->selectRaw('SUM(CASE WHEN status_lop = ? THEN 1 ELSE 0 END) as rejected', [LopStatus::REJECTED->value])
            ->selectRaw("SUM(CASE WHEN ihld_id IS NULL OR ihld_id = '' THEN 1 ELSE 0 END) as missing_ihld")
            ->first();
        $total = (int) ($aggregate?->total ?? 0);
        $missingIhld = (int) ($aggregate?->missing_ihld ?? 0);

        $stats = [
            'total' => $total,
            'active' => (int) ($aggregate?->active ?? 0),
            'waiting_review' => (int) ($aggregate?->waiting_review ?? 0),
            'completed' => (int) ($aggregate?->completed ?? 0),
            'rejected' => (int) ($aggregate?->rejected ?? 0),
            'missing_ihld' => $missingIhld,
            'ihld_completion_percentage' => $total > 0
                ? (int) round((($total - $missingIhld) / $total) * 100)
                : 0,
        ];

        $matrixRegions = $this->matrixRegions(clone $query, $user, $filterState);

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'scopeLabel' => $isSuperAdmin ? 'Seluruh wilayah operasional' : $this->visibility->label($user),
            'scopeWarning' => ! $isSuperAdmin && $this->visibility->accessibleBranchIds($user)->isEmpty(),
            'stats' => $stats,
            'matrixRegions' => $matrixRegions,
            'monitoring' => $this->monitoring->summary(clone $query, $user, $filterState, $filters['date'] ?? null),
            'dashboardTab' => in_array($filters['tab'] ?? '', ['program', 'status', 'summary'], true) ? $filters['tab'] : 'program',
            'pipelineStatuses' => collect(LopStatus::cases())->map(fn (LopStatus $status) => [
                'value' => $status->value,
                'label' => $status === LopStatus::WAITING_APPROVAL ? 'In Review' : $status->label(),
            ]),
            'filters' => $filterState,
            'regions' => Branch::query()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
            'branches' => Branch::query()->orderBy('region')->orderBy('name')->get(),
        ];
    }

    /**
     * Daftar ringkas untuk drill-down angka pada matrix Dashboard.
     * Scope pengguna selalu diterapkan kembali di server; parameter dari UI
     * hanya mempersempit hasil dan tidak dapat memperluas akses Admin.
     */
    public function matrixLops(User $user, array $filters): array
    {
        $query = $this->scopedLops($user);
        $normalized = [
            'region' => trim((string) ($filters['region'] ?? '')),
            'branch' => trim((string) ($filters['branch'] ?? '')),
            'program' => ProgramType::tryFrom((string) ($filters['program'] ?? ''))?->value ?? '',
            'status' => LopStatus::tryFrom((string) ($filters['status'] ?? ''))?->value ?? '',
            'metric' => in_array(($filters['metric'] ?? ''), ['assigned', 'in_review', 'complete'], true)
                ? (string) $filters['metric']
                : '',
        ];

        $this->applyFilters($query, $normalized);

        match ($normalized['metric']) {
            'assigned' => $query->where('status_lop', LopStatus::ASSIGNED),
            'in_review' => $query->where('status_lop', LopStatus::WAITING_APPROVAL),
            'complete' => $query->where('status_lop', LopStatus::COMPLETED),
            default => null,
        };

        $total = (clone $query)->count();
        $rows = $query
            ->with(['activeAssignment.technician', 'branchRef', 'serviceArea'])
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(function (QeLop $lop): array {
                $status = $lop->status_lop instanceof LopStatus
                    ? $lop->status_lop
                    : LopStatus::from((string) $lop->status_lop);
                $program = $lop->program_type instanceof ProgramType
                    ? $lop->program_type
                    : ProgramType::from((string) $lop->program_type);

                return [
                    'incident' => $lop->incident,
                    'name' => $lop->nama_lop,
                    'branch' => $lop->locationBranchName(),
                    'service_area' => $lop->locationServiceAreaName(),
                    'program' => $program->label(),
                    'status' => $status->label(),
                    'status_key' => $status->value,
                    'technician' => $lop->activeAssignment?->technician?->name ?? 'Belum ditugaskan',
                    'updated_at' => $lop->updated_at?->format('d M Y · H:i'),
                    'detail_url' => route('lop.show', $lop),
                ];
            })
            ->values();

        return ['total' => $total, 'data' => $rows];
    }

    /** Scope diterapkan sebelum filter drill-down monitoring dari browser. */
    public function monitoringLops(User $user, array $filters): array
    {
        $query = $this->scopedLops($user);
        $this->applyFilters($query, [
            'region' => (string) ($filters['region'] ?? ''),
            'branch' => (string) ($filters['branch'] ?? ''),
            'program' => ProgramType::tryFrom((string) ($filters['program'] ?? ''))?->value ?? '',
            'status' => LopStatus::tryFrom((string) ($filters['status'] ?? ''))?->value ?? '',
        ]);

        return $this->monitoring->branchLops($query, $filters);
    }

    public function monitoringActivities(User $user, int $lopId, array $filters): array
    {
        $query = $this->scopedLops($user)->whereKey($lopId);
        abort_unless((clone $query)->exists(), 404);

        return $this->monitoring->lopActivities($query, $filters);
    }

    private function scopedLops(User $user): Builder
    {
        $query = QeLop::query();

        if ($user->hasRole(UserRole::ADMIN)) {
            $this->visibility->apply($query, $user);
        }

        return $query;
    }

    private function matrixRegions(Builder $query, User $user, array $filters): array
    {
        $aggregates = (clone $query)
            ->leftJoin('branches as matrix_branches', 'matrix_branches.id_branch', '=', 'qe_lops.branch_id')
            ->select(['qe_lops.program_type', 'qe_lops.status_lop'])
            ->selectRaw("COALESCE(matrix_branches.name, NULLIF(qe_lops.branch, ''), 'BRANCH BELUM TERDATA') as matrix_branch")
            ->selectRaw('COUNT(qe_lops.id_qe_lops) as total_count')
            ->groupBy('matrix_branch', 'qe_lops.program_type', 'qe_lops.status_lop')
            ->get();

        $counts = [];
        foreach ($aggregates as $aggregate) {
            $branch = $aggregate->matrix_branch;
            $program = $aggregate->program_type instanceof ProgramType
                ? $aggregate->program_type->value
                : (string) $aggregate->program_type;
            $status = $aggregate->status_lop instanceof LopStatus
                ? $aggregate->status_lop->value
                : (string) $aggregate->status_lop;

            $counts[$branch][$program][$status] = [
                'total' => (int) $aggregate->total_count,
            ];
        }

        $branchQuery = Branch::query()->orderBy('region')->orderBy('name');
        if ($user->hasRole(UserRole::ADMIN)) {
            $branchQuery->whereIn('id_branch', $this->visibility->accessibleBranchIds($user));
        } else {
            if ($filters['region'] !== '') {
                $branchQuery->where('region', $filters['region']);
            }
            if ($filters['branch'] !== '') {
                $branchQuery->where('name', $filters['branch']);
            }
        }

        $branchRecords = $branchQuery->get()
            ->map(fn (Branch $branch) => ['name' => $branch->name, 'region' => $branch->region ?: 'REGION BELUM TERDATA']);

        if ($user->hasRole(UserRole::SUPER_ADMIN) && $filters['region'] === '' && $filters['branch'] === '') {
            $knownBranches = $branchRecords->pluck('name');
            foreach (array_keys($counts) as $branchName) {
                if (! $knownBranches->contains($branchName)) {
                    $branchRecords->push(['name' => $branchName, 'region' => 'REGION BELUM TERDATA']);
                }
            }
        }

        $programCases = $filters['program'] !== ''
            ? collect([ProgramType::from($filters['program'])])
            : collect(ProgramType::cases());

        return $branchRecords
            ->groupBy('region')
            ->map(function ($branches, string $region) use ($counts, $programCases): array {
                $branchRows = $branches->map(function (array $branch) use ($counts, $programCases): array {
                    $programRows = $programCases->map(function (ProgramType $program) use ($branch, $counts): array {
                        $pipeline = collect(LopStatus::cases())->mapWithKeys(function (LopStatus $status) use ($branch, $program, $counts): array {
                            return [$status->value => (int) ($counts[$branch['name']][$program->value][$status->value]['total'] ?? 0)];
                        })->all();

                        $total = array_sum($pipeline);
                        $complete = $pipeline[LopStatus::COMPLETED->value];

                        return [
                            'value' => $program->value,
                            'label' => $program->label(),
                            'total' => $total,
                            'assigned' => $pipeline[LopStatus::ASSIGNED->value],
                            'in_review' => $pipeline[LopStatus::WAITING_APPROVAL->value],
                            'complete' => $complete,
                            'percentage' => $total > 0 ? (int) round(($complete / $total) * 100) : 0,
                            'pipeline' => $pipeline,
                        ];
                    })->values()->all();

                    return [
                        'name' => $branch['name'],
                        'summary' => $this->summarizeMatrixRows($programRows),
                        'program' => $programRows,
                    ];
                })->values()->all();

                return [
                    'name' => $region,
                    'summary' => $this->summarizeMatrixRows(array_column($branchRows, 'summary')),
                    'branches' => $branchRows,
                ];
            })
            ->values()
            ->all();
    }

    private function summarizeMatrixRows(array $rows): array
    {
        $pipeline = collect(LopStatus::cases())->mapWithKeys(
            fn (LopStatus $status) => [$status->value => array_sum(array_column(array_column($rows, 'pipeline'), $status->value))]
        )->all();

        $total = array_sum(array_column($rows, 'total'));
        $complete = array_sum(array_column($rows, 'complete'));

        return [
            'total' => $total,
            'assigned' => array_sum(array_column($rows, 'assigned')),
            'in_review' => array_sum(array_column($rows, 'in_review')),
            'complete' => $complete,
            'percentage' => $total > 0 ? (int) round(($complete / $total) * 100) : 0,
            'pipeline' => $pipeline,
        ];
    }

    private function normalizeFilters(bool $isSuperAdmin, array $filters): array
    {
        if (! $isSuperAdmin) {
            return ['region' => '', 'branch' => '', 'program' => '', 'status' => ''];
        }

        $region = trim((string) ($filters['region'] ?? ''));
        if ($region !== '' && ! Branch::query()->where('region', $region)->exists()) {
            $region = '';
        }

        $branch = trim((string) ($filters['branch'] ?? ''));
        if ($branch !== '') {
            $branchQuery = Branch::query()->where('name', $branch);
            if ($region !== '') {
                $branchQuery->where('region', $region);
            }

            if (! $branchQuery->exists()) {
                $branch = '';
            }
        }

        return [
            'region' => $region,
            'branch' => $branch,
            'program' => ProgramType::tryFrom((string) ($filters['program'] ?? ''))?->value ?? '',
            'status' => LopStatus::tryFrom((string) ($filters['status'] ?? ''))?->value ?? '',
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['region'] !== '') {
            $query->where(fn (Builder $q) => $q
                ->whereIn('qe_lops.branch_id', Branch::query()->where('region', $filters['region'])->select('id_branch'))
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('qe_lops.branch_id')
                    ->whereIn('qe_lops.branch', Branch::query()->where('region', $filters['region'])->select('name'))));
        }

        if ($filters['branch'] !== '') {
            if ($filters['branch'] === 'BRANCH BELUM TERDATA') {
                $query->whereNull('qe_lops.branch_id')->where(fn (Builder $q) => $q->whereNull('qe_lops.branch')->orWhere('qe_lops.branch', ''));
            } else {
                $query->where(fn (Builder $q) => $q
                    ->whereIn('qe_lops.branch_id', Branch::query()->where('name', $filters['branch'])->select('id_branch'))
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('qe_lops.branch_id')->where('qe_lops.branch', $filters['branch'])));
            }
        }

        if ($filters['program'] !== '') {
            $query->where('qe_lops.program_type', $filters['program']);
        }

        if ($filters['status'] !== '') {
            $query->where('qe_lops.status_lop', $filters['status']);
        }
    }
}
