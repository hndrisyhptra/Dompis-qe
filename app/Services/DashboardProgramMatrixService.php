<?php

namespace App\Services;

use App\Enums\AdminScopeType;
use App\Enums\ProgramType;
use App\Models\Branch;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Support\DashboardMatrixMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardProgramMatrixService
{
    /** Label kelompok untuk STO tanpa name dan untuk LOP yang STO-nya belum cocok. */
    private const UNNAMED_GROUP = 'STO TANPA NAMA';

    private const UNMAPPED_GROUP = 'STO BELUM TERDATA';

    public function __construct(private readonly LopVisibilityService $visibility) {}

    /** Query sudah memakai scope canonical dan filter dashboard dari caller. */
    public function build(Builder $query, User $user, array $filters): array
    {
        $scope = $user->admin_scope_type ?? ($user->branch_id ? AdminScopeType::BRANCH : null);
        $dimension = match ($scope) {
            AdminScopeType::AREA => 'region',
            AdminScopeType::REGION => 'branch',
            AdminScopeType::BRANCH => 'service_area',
            default => 'program',
        };
        $branches = in_array($dimension, ['region', 'branch'], true)
            ? Branch::query()->whereIn('id_branch', $this->visibility->accessibleBranchIds($user))->orderBy('name')->get()
            : collect();
        // Diurutkan per name lebih dulu agar STO dalam satu kelompok berdekatan.
        $serviceAreas = $dimension === 'service_area'
            ? ServiceArea::query()->whereIn('id_service_area', $this->visibility->accessibleServiceAreaIds($user))->orderBy('name')->orderBy('workzone')->get()
            : collect();
        $branchById = $branches->keyBy('id_branch');
        $branchByName = $branches->keyBy('name');
        $serviceAreaById = $serviceAreas->keyBy('id_service_area');
        $serviceAreaByCode = $serviceAreas->keyBy('workzone');

        $rows = match ($dimension) {
            'region' => Region::query()->where('area_id', $user->area_id)->orderBy('name')->get()
                ->map(fn (Region $region) => $this->row((string) $region->getKey(), $region->name, $region->code, ['region_id' => $region->getKey()])),
            'branch' => $branches->map(fn (Branch $branch) => $this->row((string) $branch->getKey(), $branch->name, $branch->code, ['branch_id' => $branch->getKey()])),
            'service_area' => $serviceAreas->map(fn (ServiceArea $serviceArea) => $this->row((string) $serviceArea->getKey(), $serviceArea->workzone, $serviceArea->name, ['service_area_id' => $serviceArea->getKey()], $this->serviceAreaGroup($serviceArea))),
            default => collect(ProgramType::cases())->when($filters['program'] !== '', fn ($programs) => $programs->filter(fn (ProgramType $program) => $program->value === $filters['program']))
                ->map(fn (ProgramType $program) => $this->row($program->value, $program->label(), 'Program dalam scope Service Area Anda', ['program' => $program->value])),
        };
        $rows = $rows->keyBy('key');
        $counts = [];
        $columns = ['qe_lops.program_type', 'qe_lops.status_lop'];
        $columns = array_merge($columns, match ($dimension) {
            'region', 'branch' => ['qe_lops.branch_id', 'qe_lops.branch'],
            'service_area' => ['qe_lops.service_area_id', 'qe_lops.sto'],
            default => [],
        });
        $aggregates = (clone $query)->select($columns)->selectRaw('COUNT(qe_lops.id_qe_lops) as total_count')->groupBy(...$columns)->get();

        foreach ($aggregates as $aggregate) {
            $branch = in_array($dimension, ['region', 'branch'], true)
                ? ($aggregate->branch_id !== null ? $branchById->get($aggregate->branch_id) : $branchByName->get($aggregate->branch))
                : null;
            $serviceArea = $dimension === 'service_area'
                ? ($aggregate->service_area_id !== null ? $serviceAreaById->get($aggregate->service_area_id) : $serviceAreaByCode->get($aggregate->sto))
                : null;
            $key = (string) match ($dimension) {
                'region' => $branch?->region_id,
                'branch' => $branch?->getKey(),
                'service_area' => $serviceArea?->getKey(),
                default => $aggregate->program_type->value,
            };
            if (! $rows->has($key)) {
                // Jangan menghilangkan LOP legacy tanpa STO dari total Branch.
                if ($dimension !== 'service_area') {
                    continue;
                }
                $key = 'unmapped';
                $rows->put($key, $this->row($key, 'Service Area belum terdata', 'Lengkapi STO pada data LOP', ['service_area_unmapped' => 1], self::UNMAPPED_GROUP));
            }
            $status = $aggregate->status_lop->value;
            $counts[$key][$status] = ($counts[$key][$status] ?? 0) + (int) $aggregate->total_count;
        }

        $rows = $rows->map(fn (array $row) => [...$row, 'summary' => DashboardMatrixMetrics::fromPipeline($counts[$row['key']] ?? [])])->values();

        return [
            'dimension' => $dimension,
            'heading' => match ($dimension) {
                'region' => 'Region', 'branch' => 'Branch', 'service_area' => 'Service Area / STO', default => 'Program'
            },
            'rows' => $rows->all(),
            'groups' => $this->groupServiceAreaRows($rows, $dimension),
            'summary' => DashboardMatrixMetrics::merge($rows->pluck('summary')->all()),
        ];
    }

    /**
     * Pengelompokan matrix STO berdasarkan kolom name service_areas, agar
     * ratusan STO bisa dilipat per wilayah tanpa kehilangan baris angka.
     */
    private function groupServiceAreaRows(Collection $rows, string $dimension): array
    {
        if ($dimension !== 'service_area') {
            return [];
        }

        return $rows->groupBy('group')
            ->map(fn (Collection $groupRows, string $name): array => [
                'name' => $name,
                'count' => $groupRows->count(),
                'summary' => DashboardMatrixMetrics::merge($groupRows->pluck('summary')->all()),
                'rows' => $groupRows->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function serviceAreaGroup(ServiceArea $serviceArea): string
    {
        $name = trim((string) $serviceArea->name);

        return $name !== '' ? $name : self::UNNAMED_GROUP;
    }

    /** Filter rincian adalah irisan tambahan; scope selalu diterapkan oleh caller. */
    public function applyLocationFilters(Builder $query, User $user, array $filters): void
    {
        if (! empty($filters['region_id'])) {
            $this->filterBranches($query, Branch::query()->where('region_id', $filters['region_id'])->pluck('id_branch')->all());
        }
        if (! empty($filters['branch_id'])) {
            $this->filterBranches($query, [(int) $filters['branch_id']]);
        }
        if (! empty($filters['service_area_id'])) {
            $serviceArea = ServiceArea::query()->find($filters['service_area_id']);
            if ($serviceArea === null) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->where(fn (Builder $q) => $q->where('qe_lops.service_area_id', $filters['service_area_id'])
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('qe_lops.service_area_id')->where('qe_lops.sto', $serviceArea->workzone)));
        }
        if (! empty($filters['service_area_unmapped'])) {
            $serviceAreas = ServiceArea::query()->whereIn('id_service_area', $this->visibility->accessibleServiceAreaIds($user))->get(['id_service_area', 'workzone']);
            $query->where(fn (Builder $q) => $q
                ->where(fn (Builder $fk) => $fk->whereNotNull('qe_lops.service_area_id')->whereNotIn('qe_lops.service_area_id', $serviceAreas->modelKeys()))
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('qe_lops.service_area_id')
                    ->where(fn (Builder $sto) => $sto->whereNull('qe_lops.sto')->orWhereNotIn('qe_lops.sto', $serviceAreas->pluck('workzone')))));
        }
    }

    private function filterBranches(Builder $query, array $ids): void
    {
        $names = Branch::query()->whereIn('id_branch', $ids)->pluck('name');
        $query->where(fn (Builder $q) => $q->whereIn('qe_lops.branch_id', $ids)
            ->orWhere(fn (Builder $legacy) => $legacy->whereNull('qe_lops.branch_id')->whereIn('qe_lops.branch', $names)));
    }

    private function row(string $key, string $label, ?string $caption, array $filters, ?string $group = null): array
    {
        return compact('key', 'label', 'caption', 'filters', 'group');
    }
}
