<?php

namespace App\Services;

use App\Enums\LopSegment;
use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RevenueService
{
    public function __construct(private readonly LopVisibilityService $visibility) {}

    public function dashboard(User $user, array $filters): array
    {
        $user->loadMissing(['branch', 'area', 'region', 'serviceAreas']);

        $isSuperAdmin = $user->hasRole(UserRole::SUPER_ADMIN);
        $branches = $this->accessibleBranches($user);
        $serviceAreas = $this->accessibleServiceAreas($user);
        $filterState = $this->normalizeFilters($filters, $branches, $serviceAreas);
        $query = $this->scopedLops($user);
        $this->applyFilters($query, $filterState, $branches, $serviceAreas);

        $branchRows = $this->branchRows(clone $query);
        $proposalValue = (float) $branchRows->sum('proposal_value');
        $actualValue = (float) $branchRows->sum('actual_value');
        $gapValue = max(0, $proposalValue - $actualValue);

        $stats = [
            'proposal_value' => $proposalValue,
            'actual_value' => $actualValue,
            'gap_value' => $gapValue,
            'realization_percentage' => $proposalValue > 0
                ? (int) round(($actualValue / $proposalValue) * 100)
                : 0,
        ];

        $maxBranchValue = max(1, (float) $branchRows->max('proposal_value'));
        $branchRows = $branchRows->map(function (array $row) use ($maxBranchValue): array {
            return [
                ...$row,
                'proposal_bar_percentage' => (int) round(($row['proposal_value'] / $maxBranchValue) * 100),
                'actual_bar_percentage' => (int) round(($row['actual_value'] / $maxBranchValue) * 100),
            ];
        });

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'scopeLabel' => $isSuperAdmin ? 'Seluruh wilayah operasional' : $this->visibility->label($user),
            'scopeWarning' => ! $isSuperAdmin && $this->visibility->accessibleServiceAreaIds($user)->isEmpty(),
            'stats' => $stats,
            'branchRows' => $branchRows,
            'segmentRows' => $this->segmentRows(clone $query),
            'filters' => $filterState,
            'branches' => $branches,
            'serviceAreas' => $serviceAreas,
            'regions' => $branches->pluck('region')->filter()->unique()->sort()->values(),
        ];
    }

    private function scopedLops(User $user): Builder
    {
        $query = QeLop::query();

        if ($user->hasRole(UserRole::ADMIN)) {
            $this->visibility->apply($query, $user);
        }

        return $query;
    }

    private function accessibleBranches(User $user): Collection
    {
        $query = Branch::query()->orderBy('region')->orderBy('name');
        if ($user->hasRole(UserRole::ADMIN)) {
            $query->whereIn('id_branch', $this->visibility->accessibleBranchIds($user));
        }

        return $query->get(['id_branch', 'name', 'region']);
    }

    private function accessibleServiceAreas(User $user): Collection
    {
        $query = ServiceArea::query()->with('branch:id_branch,name,region')->orderBy('workzone');
        if ($user->hasRole(UserRole::ADMIN)) {
            $query->whereIn('id_service_area', $this->visibility->accessibleServiceAreaIds($user));
        }

        return $query->get(['id_service_area', 'workzone', 'name', 'branch_id']);
    }

    private function normalizeFilters(array $filters, Collection $branches, Collection $serviceAreas): array
    {
        $region = trim((string) ($filters['region'] ?? ''));
        if ($region !== '' && ! $branches->contains('region', $region)) {
            $region = '';
        }

        $branchId = (int) ($filters['branch'] ?? 0);
        if ($branchId <= 0 || ! $branches->contains('id_branch', $branchId)) {
            $branchId = null;
        }

        $serviceAreaId = (int) ($filters['service_area'] ?? 0);
        $serviceArea = $serviceAreas->firstWhere('id_service_area', $serviceAreaId);
        if ($serviceArea === null || ($branchId !== null && (int) $serviceArea->branch_id !== $branchId)) {
            $serviceAreaId = null;
        }

        return [
            'region' => $region,
            'branch' => $branchId,
            'service_area' => $serviceAreaId,
            'program' => ProgramType::tryFrom((string) ($filters['program'] ?? ''))?->value ?? '',
            'status' => LopStatus::tryFrom((string) ($filters['status'] ?? ''))?->value ?? '',
        ];
    }

    private function applyFilters(Builder $query, array $filters, Collection $branches, Collection $serviceAreas): void
    {
        if ($filters['region'] !== '') {
            $this->whereBranches($query, $branches->where('region', $filters['region']));
        }

        if ($filters['branch'] !== null) {
            $this->whereBranches($query, $branches->where('id_branch', $filters['branch']));
        }

        if ($filters['service_area'] !== null) {
            $serviceArea = $serviceAreas->firstWhere('id_service_area', $filters['service_area']);
            $query->where(function (Builder $scope) use ($serviceArea): void {
                $scope->where('qe_lops.service_area_id', $serviceArea->id_service_area)
                    ->orWhere(function (Builder $legacy) use ($serviceArea): void {
                        $legacy->whereNull('qe_lops.service_area_id')
                            ->where('qe_lops.sto', $serviceArea->workzone);
                    });
            });
        }

        if ($filters['program'] !== '') {
            $query->where('qe_lops.program_type', $filters['program']);
        }

        if ($filters['status'] !== '') {
            $query->where('qe_lops.status_lop', $filters['status']);
        }
    }

    private function whereBranches(Builder $query, Collection $branches): void
    {
        $ids = $branches->pluck('id_branch');
        $names = $branches->pluck('name');

        $query->where(function (Builder $scope) use ($ids, $names): void {
            $scope->whereIn('qe_lops.branch_id', $ids)
                ->orWhere(function (Builder $legacy) use ($names): void {
                    $legacy->whereNull('qe_lops.branch_id')->whereIn('qe_lops.branch', $names);
                });
        });
    }

    private function branchRows(Builder $query): Collection
    {
        $rows = $this->withBoq($query)
            ->select(['qe_lops.branch_id', 'qe_lops.branch'])
            ->selectRaw('COUNT(qe_lops.id_qe_lops) as total_lops')
            ->selectRaw('SUM(COALESCE(revenue_boqs.grand_total, 0)) as proposal_value')
            ->selectRaw('SUM(CASE WHEN qe_lops.status_lop = ? THEN COALESCE(revenue_boqs.grand_total, 0) ELSE 0 END) as actual_value', [LopStatus::COMPLETED->value])
            ->groupBy('qe_lops.branch_id', 'qe_lops.branch')
            ->get();

        $branchNames = Branch::query()->whereIn('id_branch', $rows->pluck('branch_id')->filter())->pluck('name', 'id_branch');

        return $rows->map(function ($row) use ($branchNames): array {
            $proposal = (float) $row->proposal_value;
            $actual = (float) $row->actual_value;

            return [
                'branch' => $branchNames[$row->branch_id] ?? (trim((string) $row->branch) ?: 'Branch belum terdata'),
                'total_lops' => (int) $row->total_lops,
                'proposal_value' => $proposal,
                'actual_value' => $actual,
                'gap_value' => max(0, $proposal - $actual),
                'realization_percentage' => $proposal > 0 ? (int) round(($actual / $proposal) * 100) : 0,
            ];
        })->sortByDesc('proposal_value')->values();
    }

    private function segmentRows(Builder $query): Collection
    {
        $segments = [];
        $lops = $query
            ->join('qe_boqs as segment_boqs', function ($join): void {
                $join->on('segment_boqs.qe_lop_id', '=', 'qe_lops.id_qe_lops')
                    ->whereNull('segment_boqs.deleted_at');
            })
            ->select(['qe_lops.id_qe_lops', 'qe_lops.segment'])
            ->selectRaw('segment_boqs.grand_total as proposal_value')
            ->get();

        foreach ($lops as $lop) {
            $lopSegments = $lop->segments() ?: ['__unmapped'];
            foreach ($lopSegments as $segment) {
                $segments[$segment]['lop_count'] = ($segments[$segment]['lop_count'] ?? 0) + 1;
                $segments[$segment]['proposal_value'] = ($segments[$segment]['proposal_value'] ?? 0) + (float) $lop->proposal_value;
            }
        }

        $rows = collect($segments)->map(function (array $row, string $segment): array {
            return [
                'segment' => $segment,
                'label' => $segment === '__unmapped'
                    ? 'Belum terdata'
                    : (LopSegment::tryFrom($segment)?->label() ?? mb_strtoupper($segment)),
                'lop_count' => $row['lop_count'],
                'proposal_value' => (float) $row['proposal_value'],
            ];
        })->sortByDesc('proposal_value')->values();

        $maxValue = max(1, (float) $rows->max('proposal_value'));

        return $rows->map(fn (array $row): array => [
            ...$row,
            'bar_percentage' => (int) round(($row['proposal_value'] / $maxValue) * 100),
        ]);
    }

    private function withBoq(Builder $query): Builder
    {
        return $query->leftJoin('qe_boqs as revenue_boqs', function ($join): void {
            $join->on('revenue_boqs.qe_lop_id', '=', 'qe_lops.id_qe_lops')
                ->whereNull('revenue_boqs.deleted_at');
        });
    }
}
