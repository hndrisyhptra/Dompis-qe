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
use App\Support\ReservedDesignatorRules;
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
            'unclassified_boq_count' => (int) $programRows->sum('unclassified_boq_count'),
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
            ->selectRaw('SUM(CASE WHEN '.$this->unclassifiedBoqSql().' THEN 1 ELSE 0 END) as unclassified_boq_count')
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
            'unclassified_boq_count' => (int) ($row?->unclassified_boq_count ?? 0),
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
        $usage = DB::table('qe_material_reservation_items as usage_items')
            ->join('qe_material_reservations as usage_reservations', 'usage_reservations.id_reservation', '=', 'usage_items.reservation_id')
            ->leftJoin('designators as usage_designators', 'usage_designators.id_designator', '=', 'usage_items.designator_id')
            ->leftJoin('designator_types as usage_types', 'usage_types.id_designator_type', '=', 'usage_designators.designator_type_id')
            ->whereNull('usage_reservations.deleted_at')
            ->whereRaw("(COALESCE(UPPER(usage_types.code), 'MATERIAL') = 'MATERIAL' OR ".ReservedDesignatorRules::specialSql('usage_designators.code').')')
            ->select(['usage_reservations.qe_lop_id', 'usage_items.designator_id', 'usage_items.qty_actual', 'usage_designators.code']);
        $eligible = $this->eligibleTypeSql('item_lops.program_type', 'value_boq_items.type', 'value_boq_items.designator_code');
        $baseMatch = $this->baseCodeSql('value_usage.code').' = '.$this->baseCodeSql('value_boq_items.designator_code');
        $itemTotals = DB::table('qe_boq_items as value_boq_items')
            ->join('qe_boqs as item_boqs', 'item_boqs.id_boq', '=', 'value_boq_items.qe_boq_id')
            ->join('qe_lops as item_lops', 'item_lops.id_qe_lops', '=', 'item_boqs.qe_lop_id')
            ->leftJoinSub($usage, 'value_usage', fn ($join) => $join->on('value_usage.qe_lop_id', '=', 'item_lops.id_qe_lops')->whereRaw($baseMatch))
            ->select('value_boq_items.qe_boq_id')
            ->selectRaw('COUNT(value_boq_items.id_boq_item) as raw_item_count')
            ->selectRaw("SUM(CASE WHEN {$eligible} THEN value_boq_items.total_price ELSE 0 END) as item_total")
            ->selectRaw("SUM(CASE WHEN {$eligible} THEN COALESCE(value_usage.qty_actual, value_boq_items.qty) * value_boq_items.unit_price ELSE 0 END) as actual_total")
            ->whereNull('value_boq_items.deleted_at')->whereNull('item_boqs.deleted_at')->whereNull('item_lops.deleted_at')
            ->groupBy('value_boq_items.qe_boq_id');
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
        $packageSql = "CASE WHEN value_boqs.id_boq IS NOT NULL THEN COALESCE(value_boqs.package_id, value_lops.package_id) ELSE {$regionalPackageSql} END";
        $servicePair = $this->baseCodeSql('value_services.code').' = '.$this->baseCodeSql('value_designators.code');
        $coveredBase = $this->baseCodeSql('covered_items.designator_code').' = '.$this->baseCodeSql('value_designators.code');
        $servicePrograms = $this->serviceProgramsSql();
        $reservationTotals = DB::table('qe_material_reservations as value_reservations')
            ->join('qe_material_reservation_items as value_items', 'value_items.reservation_id', '=', 'value_reservations.id_reservation')
            ->join('qe_lops as value_lops', 'value_lops.id_qe_lops', '=', 'value_reservations.qe_lop_id')
            ->leftJoin('qe_boqs as value_boqs', fn ($join) => $join->on('value_boqs.qe_lop_id', '=', 'value_lops.id_qe_lops')->whereNull('value_boqs.deleted_at'))
            ->leftJoin('designators as value_designators', 'value_designators.id_designator', '=', 'value_items.designator_id')
            ->leftJoin('designator_types as value_types', 'value_types.id_designator_type', '=', 'value_designators.designator_type_id')
            ->leftJoin('designators as value_services', fn ($join) => $join->whereRaw("value_lops.program_type IN ({$servicePrograms})")
                ->whereRaw('NOT ('.ReservedDesignatorRules::specialSql('value_services.code').')')
                ->whereRaw("SUBSTR(UPPER(TRIM(value_designators.code)), 1, 2) = 'M-' AND SUBSTR(UPPER(TRIM(value_services.code)), 1, 2) = 'J-'")
                ->whereRaw($servicePair)->whereNull('value_services.deleted_at'))
            ->leftJoin('branches as value_branches', function ($join): void {
                $join->on('value_branches.id_branch', '=', 'value_lops.branch_id')
                    ->orOn(function ($legacy): void {
                        $legacy->whereNull('value_lops.branch_id')
                            ->on('value_branches.name', '=', 'value_lops.branch');
                    });
            })
            ->leftJoin('designator_package_prices as value_prices', function ($join) use ($packageSql): void {
                $join->on('value_prices.designator_id', '=', 'value_items.designator_id')
                    ->whereRaw("value_prices.package_id = {$packageSql}")
                    ->whereNull('value_prices.deleted_at');
            })
            ->leftJoin('designator_package_prices as service_prices', fn ($join) => $join->on('service_prices.designator_id', '=', 'value_services.id_designator')
                ->whereRaw("service_prices.package_id = {$packageSql}")->whereNull('service_prices.deleted_at'))
            ->select('value_reservations.qe_lop_id')
            ->selectRaw('COUNT(value_items.id_reservation_item) as extra_count')
            ->selectRaw('SUM(COALESCE(value_items.qty_actual, value_items.qty) * (COALESCE(value_prices.price, 0) + COALESCE(service_prices.price, 0))) as actual_total')
            ->whereRaw("(COALESCE(UPPER(value_types.code), 'MATERIAL') = 'MATERIAL' OR ".ReservedDesignatorRules::specialSql('value_designators.code').')')
            ->whereNotExists(fn ($covered) => $covered->selectRaw('1')->from('qe_boq_items as covered_items')
                ->whereColumn('covered_items.qe_boq_id', 'value_boqs.id_boq')->whereNull('covered_items.deleted_at')
                ->whereRaw($this->eligibleTypeSql('value_lops.program_type', 'covered_items.type', 'covered_items.designator_code'))->whereRaw($coveredBase))
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
     * Nilai Plan sesuai kategori program. Snapshot legacy tanpa rincian harus
     * mempunyai total material eksplisit, kecuali Utilitas yang boleh keduanya.
     */
    private function boqValueSql(): string
    {
        $material = $this->snapshotSql(['material.total', 'totals.material_total', 'material_total']);
        $grand = $this->snapshotSql(['grand_total', 'totals.grand_total']);
        $services = $this->serviceProgramsSql();

        // Total lama tanpa kategori tidak dapat dianggap material secara otomatis.
        return "CASE WHEN revenue_item_totals.raw_item_count IS NOT NULL THEN COALESCE(revenue_item_totals.item_total, 0)
            WHEN qe_lops.program_type IN ({$services}) THEN COALESCE(NULLIF(revenue_boqs.grand_total, 0), {$grand}, 0)
            ELSE COALESCE({$material}, 0) END";
    }

    private function actualValueSql(): string
    {
        return "CASE WHEN revenue_item_totals.raw_item_count IS NOT NULL
            THEN COALESCE(revenue_item_totals.actual_total, 0) + COALESCE(revenue_reservations.actual_total, 0)
            WHEN revenue_reservations.extra_count IS NOT NULL THEN COALESCE(revenue_reservations.actual_total, 0)
            ELSE {$this->boqValueSql()} END";
    }

    private function serviceProgramsSql(): string
    {
        return collect(ProgramType::cases())->filter(fn (ProgramType $program) => $program->includesBoqServices())->map(fn (ProgramType $program) => "'{$program->value}'")->implode(',');
    }

    private function eligibleTypeSql(string $program, string $type, string $code): string
    {
        return "(UPPER(TRIM({$type})) = 'MATERIAL' OR ({$program} IN ({$this->serviceProgramsSql()}) AND UPPER(TRIM({$type})) = 'JASA') OR ".ReservedDesignatorRules::specialSql($code).')';
    }

    private function baseCodeSql(string $column): string
    {
        return 'CASE WHEN '.ReservedDesignatorRules::specialSql($column)." THEN UPPER(TRIM({$column})) WHEN SUBSTR(UPPER(TRIM({$column})), 1, 2) IN ('M-', 'J-') THEN SUBSTR(UPPER(TRIM({$column})), 3) ELSE UPPER(TRIM({$column})) END";
    }

    private function snapshotSql(array $paths): string
    {
        $expressions = array_map(fn (string $path) => DB::connection()->getDriverName() === 'mysql'
            ? "CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(qe_lops.boq_snapshot, '$.{$path}')), 'null') AS DECIMAL(18,2))"
            : "CAST(json_extract(qe_lops.boq_snapshot, '$.{$path}') AS REAL)", $paths);

        return 'COALESCE('.implode(', ', $expressions).')';
    }

    private function unclassifiedBoqSql(): string
    {
        $material = $this->snapshotSql(['material.total', 'totals.material_total', 'material_total']);
        $grand = $this->snapshotSql(['grand_total', 'totals.grand_total']);

        return "qe_lops.program_type NOT IN ({$this->serviceProgramsSql()}) AND revenue_item_totals.raw_item_count IS NULL
            AND {$material} IS NULL AND COALESCE(revenue_boqs.grand_total, {$grand}, 0) > 0";
    }
}
