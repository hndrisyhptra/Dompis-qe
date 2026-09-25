<?php

namespace App\Services;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal pembatasan data LOP untuk role ADMIN.
 * SUPER_ADMIN / role monitoring tetap mengikuti policy modul masing-masing.
 */
class LopVisibilityService
{
    /** @var array<string, Collection> */
    private array $branchIdsCache = [];

    /** @var array<string, Collection> */
    private array $serviceAreaIdsCache = [];

    public function apply(Builder $query, User $user, string $table = 'qe_lops'): Builder
    {
        if (! $user->hasRole(UserRole::ADMIN)) {
            return $query;
        }

        $user->loadMissing(['serviceAreas', 'branch.regionRef']);
        $scope = $user->admin_scope_type
            ?? ($user->branch_id ? AdminScopeType::BRANCH : null);

        return match ($scope) {
            AdminScopeType::AREA => $user->area_id
                ? $this->applyBranches($query, $table, Branch::query()
                    ->whereHas('regionRef', fn (Builder $region) => $region->where('area_id', $user->area_id))
                    ->pluck('id_branch'))
                : $this->deny($query),
            AdminScopeType::REGION => $user->region_id
                ? $this->applyBranches($query, $table, Branch::query()
                    ->where('region_id', $user->region_id)
                    ->pluck('id_branch'))
                : $this->deny($query),
            AdminScopeType::BRANCH => $user->branch_id
                ? $this->applyBranches($query, $table, collect([$user->branch_id]))
                : $this->deny($query),
            AdminScopeType::SERVICE_AREA => $this->serviceAreaIds($user)->isNotEmpty()
                ? $this->applyServiceAreas($query, $table, $this->serviceAreaIds($user))
                : $this->deny($query),
            default => $this->deny($query),
        };
    }

    public function canAccess(User $user, QeLop $lop): bool
    {
        if (! $user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        return $this->apply(QeLop::query()->whereKey($lop->getKey()), $user)->exists();
    }

    public function canUseLocation(User $user, int $branchId, int $serviceAreaId): bool
    {
        if (! $user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        return $this->locationMatchesScope($user, $branchId, $serviceAreaId);
    }

    public function accessibleBranchIds(User $user): Collection
    {
        if (! $user->hasRole(UserRole::ADMIN)) {
            return Branch::query()->pluck('id_branch');
        }

        $cacheKey = $this->cacheKey($user);
        if (isset($this->branchIdsCache[$cacheKey])) {
            return $this->branchIdsCache[$cacheKey];
        }

        $user->loadMissing('serviceAreas');

        return $this->branchIdsCache[$cacheKey] = match ($user->admin_scope_type ?? ($user->branch_id ? AdminScopeType::BRANCH : null)) {
            AdminScopeType::AREA => Branch::query()
                ->whereHas('regionRef', fn (Builder $region) => $region->where('area_id', $user->area_id))
                ->pluck('id_branch'),
            AdminScopeType::REGION => Branch::query()->where('region_id', $user->region_id)->pluck('id_branch'),
            AdminScopeType::BRANCH => collect([$user->branch_id])->filter(),
            AdminScopeType::SERVICE_AREA => ServiceArea::query()
                ->whereIn('id_service_area', $this->serviceAreaIds($user))
                ->pluck('branch_id')->unique()->values(),
            default => collect(),
        };
    }

    public function accessibleServiceAreaIds(User $user): Collection
    {
        if (! $user->hasRole(UserRole::ADMIN)) {
            return ServiceArea::query()->pluck('id_service_area');
        }

        $cacheKey = $this->cacheKey($user);
        if (isset($this->serviceAreaIdsCache[$cacheKey])) {
            return $this->serviceAreaIdsCache[$cacheKey];
        }

        $scope = $user->admin_scope_type ?? ($user->branch_id ? AdminScopeType::BRANCH : null);
        if ($scope === AdminScopeType::SERVICE_AREA) {
            return $this->serviceAreaIdsCache[$cacheKey] = $this->serviceAreaIds($user);
        }

        return $this->serviceAreaIdsCache[$cacheKey] = ServiceArea::query()
            ->whereIn('branch_id', $this->accessibleBranchIds($user))
            ->pluck('id_service_area');
    }

    public function label(User $user): string
    {
        $user->loadMissing(['area', 'region', 'branch', 'serviceAreas']);

        return match ($user->admin_scope_type) {
            AdminScopeType::AREA => 'Area · '.($user->area?->name ?? 'belum diatur'),
            AdminScopeType::REGION => 'Region · '.($user->region?->name ?? 'belum diatur'),
            AdminScopeType::BRANCH => 'Branch · '.($user->branch?->name ?? 'belum diatur'),
            AdminScopeType::SERVICE_AREA => 'Service Area · '.$user->serviceAreas->pluck('workzone')->join(', '),
            default => $user->branch?->name ?? 'Scope belum diatur',
        };
    }

    private function locationMatchesScope(User $user, int $branchId, int $serviceAreaId): bool
    {
        return $this->accessibleBranchIds($user)->contains($branchId)
            && $this->accessibleServiceAreaIds($user)->contains($serviceAreaId);
    }

    private function serviceAreaIds(User $user): Collection
    {
        return $user->serviceAreas->pluck('id_service_area')
            ->push($user->service_area_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function applyBranches(Builder $query, string $table, Collection $branchIds): Builder
    {
        $branchIds = $branchIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $legacyNames = Branch::query()->whereIn('id_branch', $branchIds)->pluck('name');

        return $query->where(function (Builder $scope) use ($table, $branchIds, $legacyNames) {
            $scope->whereIn("{$table}.branch_id", $branchIds);
            if ($legacyNames->isNotEmpty()) {
                $scope->orWhere(function (Builder $legacy) use ($table, $legacyNames) {
                    $legacy->whereNull("{$table}.branch_id")
                        ->whereIn("{$table}.branch", $legacyNames);
                });
            }
        });
    }

    private function applyServiceAreas(Builder $query, string $table, Collection $serviceAreaIds): Builder
    {
        $serviceAreaIds = $serviceAreaIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $legacyWorkzones = ServiceArea::query()
            ->whereIn('id_service_area', $serviceAreaIds)
            ->pluck('workzone');

        return $query->where(function (Builder $scope) use ($table, $serviceAreaIds, $legacyWorkzones) {
            $scope->whereIn("{$table}.service_area_id", $serviceAreaIds);
            if ($legacyWorkzones->isNotEmpty()) {
                $scope->orWhere(function (Builder $legacy) use ($table, $legacyWorkzones) {
                    $legacy->whereNull("{$table}.service_area_id")
                        ->whereIn("{$table}.sto", $legacyWorkzones);
                });
            }
        });
    }

    private function deny(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }

    private function cacheKey(User $user): string
    {
        return (string) $user->getKey();
    }
}
