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
use Illuminate\Support\Facades\DB;

class RevenueService
{
    public function __construct(
        private readonly LopVisibilityService $visibility,
        private readonly LopBoqValueService $boqValues,
        private readonly RegionalPackageResolver $regionalPackages,
    ) {}

    public function dashboard(User $user, array $filters): array
    {
        $user->loadMissing(['branch', 'area', 'region', 'serviceAreas']);

        $isSuperAdmin = $user->hasRole(UserRole::SUPER_ADMIN);
        $branches = $this->accessibleBranches($user);
        $serviceAreas = $this->accessibleServiceAreas($user);
        $filterState = $this->normalizeFilters($filters, $branches, $serviceAreas);
        $query = $this->scopedLops($user);
        $this->applyFilters($query, $filterState, $branches, $serviceAreas);

        $programRows = $this->programRows(clone $query);
        $planValue = (float) $programRows->sum('plan_value');
        $actualValue = (float) $programRows->sum('actual_value');
        $plannedActualValue = (float) $programRows->sum('planned_actual_value');
        $recoveryRow = $programRows->firstWhere('program', ProgramType::RECOVERY->value);
        $gapValue = (float) max(0, $planValue - $plannedActualValue);

        $stats = [
            'plan_value' => $planValue,
            'actual_value' => $actualValue,
            'planned_actual_value' => $plannedActualValue,
            'recovery_actual_value' => (float) ($recoveryRow['actual_value'] ?? 0),
            'gap_value' => $gapValue,
            'realization_percentage' => $planValue > 0
                ? (int) round(($plannedActualValue / $planValue) * 100)
                : null,
        ];

        $branchRows = $this->branchRows(
            clone $query,
            $this->matrixBranches($branches, $serviceAreas, $filterState),
        );
        $maxBranchValue = max(1, (float) $branchRows
            ->max(fn (array $row) => max($row['plan_value'], $row['actual_value'])));
        $branchRows = $branchRows->map(function (array $row) use ($maxBranchValue): array {
            return [
                ...$row,
                'plan_bar_percentage' => (int) round(($row['plan_value'] / $maxBranchValue) * 100),
                'actual_bar_percentage' => (int) round(($row['actual_value'] / $maxBranchValue) * 100),
            ];
        });

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'scopeLabel' => $isSuperAdmin ? 'Seluruh wilayah operasional' : $this->visibility->label($user),
            'scopeWarning' => ! $isSuperAdmin && $this->visibility->accessibleServiceAreaIds($user)->isEmpty(),
            'stats' => $stats,
            'programRows' => $programRows,
            'branchRows' => $branchRows,
            'segmentRows' => $this->segmentRows(clone $query),
            'filters' => $filterState,
            'branches' => $branches,
            'serviceAreas' => $serviceAreas,
            'regions' => $branches->pluck('region')->filter()->unique()->sort()->values(),
            'referencePackageLabel' => $this->boqValues->referencePackage()?->code,
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

    private function matrixBranches(Collection $branches, Collection $serviceAreas, array $filters): Collection
    {
        $matrixBranches = $branches;

        if ($filters['region'] !== '') {
            $matrixBranches = $matrixBranches->where('region', $filters['region']);
        }

        if ($filters['branch'] !== null) {
            $matrixBranches = $matrixBranches->where('id_branch', $filters['branch']);
        }

        if ($filters['service_area'] !== null) {
            $serviceArea = $serviceAreas->firstWhere('id_service_area', $filters['service_area']);
            $matrixBranches = $matrixBranches->where('id_branch', $serviceArea?->branch_id);
        }

        return $matrixBranches->values();
    }

    private function branchRows(Builder $query, Collection $branches): Collection
    {
        $boqValue = $this->boqValueSql();
        $actualValue = $this->actualValueSql();
        $rows = $this->withBoq($query)
            ->select(['qe_lops.branch_id', 'qe_lops.branch', 'qe_lops.program_type'])
            ->selectRaw('COUNT(qe_lops.id_qe_lops) as total_lops')
            ->selectRaw("SUM(CASE WHEN qe_lops.program_type <> ? THEN {$boqValue} ELSE 0 END) as plan_value", [ProgramType::RECOVERY->value])
            ->selectRaw("SUM(CASE WHEN qe_lops.status_lop = ? THEN {$actualValue} ELSE 0 END) as actual_value", [LopStatus::COMPLETED->value])
            ->selectRaw("SUM(CASE WHEN qe_lops.status_lop = ? AND qe_lops.program_type <> ? THEN {$actualValue} ELSE 0 END) as planned_actual_value", [LopStatus::COMPLETED->value, ProgramType::RECOVERY->value])
            ->groupBy('qe_lops.branch_id', 'qe_lops.branch', 'qe_lops.program_type')
            ->get();

        return $branches->map(function (Branch $branch) use ($rows): array {
            $branchRows = $rows->filter(fn ($row): bool => (int) $row->branch_id === (int) $branch->id_branch
                || ($row->branch_id === null && mb_strtolower(trim((string) $row->branch)) === mb_strtolower($branch->name)));
            $rowsByProgram = $branchRows->keyBy(fn ($row): string => $row->program_type instanceof ProgramType
                ? $row->program_type->value
                : (string) $row->program_type);
            $programs = collect(ProgramType::cases())
                ->map(fn (ProgramType $program): array => $this->programRevenueRow($program, $rowsByProgram->get($program->value)))
                ->values();
            $plan = (float) $programs->sum('plan_value');
            $actual = (float) $programs->sum('actual_value');
            $plannedActual = (float) $programs->sum('planned_actual_value');

            return [
                'branch_id' => $branch->id_branch,
                'branch' => $branch->name,
                'region' => $branch->region,
                'total_lops' => (int) $programs->sum('total_lops'),
                'plan_value' => $plan,
                'actual_value' => $actual,
                'planned_actual_value' => $plannedActual,
                'gap_value' => max(0, $plan - $plannedActual),
                'realization_percentage' => $plan > 0 ? (int) round(($plannedActual / $plan) * 100) : null,
                'programs' => $programs,
            ];
        })->values();
    }

    private function programRows(Builder $query): Collection
    {
        $boqValue = $this->boqValueSql();
        $actualValue = $this->actualValueSql();
        $rows = $this->withBoq($query)
            ->select('qe_lops.program_type')
            ->selectRaw('COUNT(qe_lops.id_qe_lops) as total_lops')
            ->selectRaw("SUM(CASE WHEN qe_lops.program_type <> ? THEN {$boqValue} ELSE 0 END) as plan_value", [ProgramType::RECOVERY->value])
            ->selectRaw("SUM(CASE WHEN qe_lops.status_lop = ? THEN {$actualValue} ELSE 0 END) as actual_value", [LopStatus::COMPLETED->value])
            ->selectRaw("SUM(CASE WHEN qe_lops.status_lop = ? AND qe_lops.program_type <> ? THEN {$actualValue} ELSE 0 END) as planned_actual_value", [LopStatus::COMPLETED->value, ProgramType::RECOVERY->value])
            ->groupBy('qe_lops.program_type')
            ->get();

        $rowsByProgram = $rows->keyBy(fn ($row) => $row->program_type instanceof ProgramType
            ? $row->program_type->value
            : (string) $row->program_type);

        return collect(ProgramType::cases())
            ->map(fn (ProgramType $program): array => $this->programRevenueRow($program, $rowsByProgram->get($program->value)))
            ->values();
    }

    private function programRevenueRow(ProgramType $program, mixed $row): array
    {
        $plan = (float) ($row?->plan_value ?? 0);
        $actual = (float) ($row?->actual_value ?? 0);
        $plannedActual = (float) ($row?->planned_actual_value ?? 0);
        $chartMax = max(1, $plan, $actual);

        return [
            'program' => $program->value,
            'label' => $program->label(),
            'total_lops' => (int) ($row?->total_lops ?? 0),
            'plan_value' => $plan,
            'actual_value' => $actual,
            'planned_actual_value' => $plannedActual,
            'gap_value' => max(0, $plan - $plannedActual),
            'realization_percentage' => $plan > 0 ? (int) round(($plannedActual / $plan) * 100) : null,
            'has_plan' => $program !== ProgramType::RECOVERY,
            'plan_bar_percentage' => (int) round(($plan / $chartMax) * 100),
            'actual_bar_percentage' => (int) round(($actual / $chartMax) * 100),
        ];
    }

    private function segmentRows(Builder $query): Collection
    {
        $segments = [];
        $boqValue = $this->boqValueSql();
        $lops = $this->withBoq($query)
            ->where('qe_lops.program_type', '!=', ProgramType::RECOVERY->value)
            ->select(['qe_lops.id_qe_lops', 'qe_lops.segment'])
            ->selectRaw("{$boqValue} as plan_value")
            ->get();

        foreach ($lops as $lop) {
            if ((float) $lop->plan_value <= 0) {
                continue;
            }

            $lopSegments = $lop->segments() ?: ['__unmapped'];
            foreach ($lopSegments as $segment) {
                $segments[$segment]['lop_count'] = ($segments[$segment]['lop_count'] ?? 0) + 1;
                $segments[$segment]['plan_value'] = ($segments[$segment]['plan_value'] ?? 0) + (float) $lop->plan_value;
            }
        }

        $rows = collect($segments)->map(function (array $row, string $segment): array {
            return [
                'segment' => $segment,
                'label' => $segment === '__unmapped'
                    ? 'Belum terdata'
                    : (LopSegment::tryFrom($segment)?->label() ?? mb_strtoupper($segment)),
                'lop_count' => $row['lop_count'],
                'plan_value' => (float) $row['plan_value'],
            ];
        })->sortByDesc('plan_value')->values();

        $maxValue = max(1, (float) $rows->max('plan_value'));

        return $rows->map(fn (array $row): array => [
            ...$row,
            'bar_percentage' => (int) round(($row['plan_value'] / $maxValue) * 100),
        ]);
    }

    private function withBoq(Builder $query): Builder
    {
        $itemTotals = DB::table('qe_boq_items')
            ->select('qe_boq_id')
            ->selectRaw('SUM(total_price) as item_total')
            ->whereNull('deleted_at')
            ->groupBy('qe_boq_id');
        $referencePackageId = $this->boqValues->referencePackageId() ?? 0;
        $package5Id = $this->regionalPackages->package5()?->id_package ?? 0;
        $package10Id = $this->regionalPackages->package10()?->id_package ?? 0;
        $regionalPackageSql = "CASE
            WHEN UPPER(REPLACE(COALESCE(value_branches.region, ''), ' ', '')) LIKE '%JATIM%'
                OR UPPER(REPLACE(COALESCE(value_branches.region, ''), ' ', '')) LIKE '%JATENGDIY%'
                THEN COALESCE(NULLIF({$package5Id}, 0), value_lops.package_id, NULLIF({$referencePackageId}, 0))
            WHEN UPPER(REPLACE(COALESCE(value_branches.region, ''), ' ', '')) LIKE '%BALNUS%'
                THEN COALESCE(NULLIF({$package10Id}, 0), value_lops.package_id, NULLIF({$referencePackageId}, 0))
            ELSE COALESCE(value_lops.package_id, NULLIF({$referencePackageId}, 0))
        END";
        $reservationTotals = DB::table('qe_material_reservations as value_reservations')
            ->join('qe_material_reservation_items as value_items', 'value_items.reservation_id', '=', 'value_reservations.id_reservation')
            ->join('qe_lops as value_lops', 'value_lops.id_qe_lops', '=', 'value_reservations.qe_lop_id')
            ->leftJoin('branches as value_branches', function ($join): void {
                $join->on('value_branches.id_branch', '=', 'value_lops.branch_id')
                    ->orOn(function ($legacy): void {
                        $legacy->whereNull('value_lops.branch_id')
                            ->on('value_branches.name', '=', 'value_lops.branch');
                    });
            })
            ->leftJoin('designator_package_prices as value_prices', function ($join) use ($regionalPackageSql): void {
                $join->on('value_prices.designator_id', '=', 'value_items.designator_id')
                    ->whereRaw("value_prices.package_id = {$regionalPackageSql}")
                    ->whereNull('value_prices.deleted_at');
            })
            ->select('value_reservations.qe_lop_id')
            ->selectRaw('SUM(COALESCE(value_items.qty_actual, value_items.qty) * COALESCE(value_prices.price, 0)) as actual_total')
            ->whereNull('value_reservations.deleted_at')
            ->whereNull('value_lops.deleted_at')
            ->groupBy('value_reservations.qe_lop_id');

        return $query->leftJoin('qe_boqs as revenue_boqs', function ($join): void {
            $join->on('revenue_boqs.qe_lop_id', '=', 'qe_lops.id_qe_lops')
                ->whereNull('revenue_boqs.deleted_at');
        })->leftJoinSub($itemTotals, 'revenue_item_totals', function ($join): void {
            $join->on('revenue_item_totals.qe_boq_id', '=', 'revenue_boqs.id_boq');
        })->leftJoinSub($reservationTotals, 'revenue_reservations', function ($join): void {
            $join->on('revenue_reservations.qe_lop_id', '=', 'qe_lops.id_qe_lops');
        });
    }

    /**
     * Nilai BOQ utama, dengan fallback untuk data lama yang grand_total-nya
     * belum tersinkron tetapi masih memiliki item atau snapshot import.
     */
    private function boqValueSql(): string
    {
        $snapshotValue = DB::connection()->getDriverName() === 'mysql'
            ? "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(qe_lops.boq_snapshot, '$.grand_total')) AS DECIMAL(18,2)), CAST(JSON_UNQUOTE(JSON_EXTRACT(qe_lops.boq_snapshot, '$.totals.grand_total')) AS DECIMAL(18,2)))"
            : "COALESCE(CAST(json_extract(qe_lops.boq_snapshot, '$.grand_total') AS REAL), CAST(json_extract(qe_lops.boq_snapshot, '$.totals.grand_total') AS REAL))";

        return "COALESCE(NULLIF(revenue_boqs.grand_total, 0), NULLIF(revenue_item_totals.item_total, 0), {$snapshotValue}, 0)";
    }

    private function actualValueSql(): string
    {
        return "COALESCE(NULLIF({$this->boqValueSql()}, 0), NULLIF(revenue_reservations.actual_total, 0), 0)";
    }
}
