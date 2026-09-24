<?php

namespace App\Services;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Region;
use App\Models\Role;
use App\Models\ServiceArea;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya tempat yang boleh mengubah data akun user (role, branch,
 * status, hapus) dan menulis user_histories. Controller TIDAK BOLEH
 * memanggil User::update()/delete() langsung untuk field-field ini - semua
 * wajib lewat service ini supaya audit trail selalu konsisten, meniru pola
 * LopService.
 */
class UserService
{
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor) {
            [$scopePayload, $serviceAreaIds] = $this->normalizeScope($data);

            $user = User::create(array_merge([
                'role_id' => $data['role_id'] ?? null,
                'nik' => $data['nik'] ?? null,
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'status' => $data['status'] ?? 'active',
                'password' => $data['password'],
            ], $scopePayload));

            $user->serviceAreas()->sync($serviceAreaIds);

            $this->recordHistory($user, $actor, 'created', null, 'User dibuat');

            return $user;
        });
    }

    /**
     * Update data user. Password hanya diubah kalau diisi (blank = tidak
     * berubah). Guard: actor tidak boleh mengganti role_id miliknya sendiri
     * - mencegah SUPER_ADMIN mengunci diri sendiri dari User Management.
     */
    public function update(User $target, array $data, User $actor): User
    {
        if (
            $actor->id_user === $target->id_user
            && array_key_exists('role_id', $data)
            && (int) $data['role_id'] !== (int) $target->role_id
        ) {
            throw new InvalidArgumentException(
                'Anda tidak dapat mengubah role akun Anda sendiri.'
            );
        }

        return DB::transaction(function () use ($target, $data, $actor) {
            [$scopePayload, $serviceAreaIds] = $this->normalizeScope($data, $target);
            $changes = [];

            foreach (['role_id', 'status'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] != $target->{$field}) {
                    $changes[$field] = ['from' => $target->{$field}, 'to' => $data[$field]];
                }
            }

            foreach ($scopePayload as $field => $value) {
                $current = $target->{$field};
                $current = $current instanceof AdminScopeType ? $current->value : $current;
                if ($current != $value) {
                    $changes[$field] = ['from' => $current, 'to' => $value];
                }
            }

            $oldServiceAreaIds = $target->serviceAreas()->pluck('service_areas.id_service_area')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $newServiceAreaIds = collect($serviceAreaIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($oldServiceAreaIds !== $newServiceAreaIds) {
                $changes['service_area_ids'] = ['from' => $oldServiceAreaIds, 'to' => $newServiceAreaIds];
            }

            $payload = array_merge([
                'role_id' => $data['role_id'] ?? $target->role_id,
                'nik' => $data['nik'] ?? $target->nik,
                'name' => $data['name'] ?? $target->name,
                'username' => $data['username'] ?? $target->username,
                'email' => $data['email'] ?? $target->email,
                'phone' => $data['phone'] ?? $target->phone,
                'status' => $data['status'] ?? $target->status,
            ], $scopePayload);

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $target->update($payload);
            $target->serviceAreas()->sync($serviceAreaIds);

            $eventType = match (true) {
                isset($changes['role_id']) => 'role_changed',
                isset($changes['admin_scope_type']) || isset($changes['branch_id']) || isset($changes['service_area_ids']) => 'scope_changed',
                isset($changes['status']) => 'status_changed',
                default => 'updated',
            };

            $this->recordHistory($target, $actor, $eventType, $changes ?: null, 'Data user diperbarui');

            return $target->refresh();
        });
    }

    public function deactivate(User $target, User $actor): User
    {
        return $this->setStatus($target, 'inactive', $actor);
    }

    public function activate(User $target, User $actor): User
    {
        return $this->setStatus($target, 'active', $actor);
    }

    private function setStatus(User $target, string $status, User $actor): User
    {
        return DB::transaction(function () use ($target, $status, $actor) {
            $from = $target->status;
            $target->update(['status' => $status]);

            $this->recordHistory(
                $target,
                $actor,
                'status_changed',
                ['status' => ['from' => $from, 'to' => $status]],
                $status === 'active' ? 'User diaktifkan' : 'User dinonaktifkan'
            );

            return $target->refresh();
        });
    }

    public function delete(User $target, User $actor): void
    {
        DB::transaction(function () use ($target, $actor) {
            $this->recordHistory($target, $actor, 'deleted', null, 'User dihapus (soft delete)');
            $target->delete();
        });
    }

    public function restore(User $target, User $actor): User
    {
        return DB::transaction(function () use ($target, $actor) {
            $target->restore();

            $this->recordHistory($target, $actor, 'restored', null, 'User dipulihkan');

            return $target->refresh();
        });
    }

    /**
     * Menyimpan scope dalam bentuk konsisten. FK induk ikut diturunkan dari
     * master lokasi agar tidak dapat terjadi kombinasi Area/Region/Branch
     * yang saling bertentangan.
     *
     * @return array{0: array<string, mixed>, 1: array<int, int>}
     */
    private function normalizeScope(array $data, ?User $current = null): array
    {
        $roleId = $data['role_id'] ?? $current?->role_id;
        $role = $roleId ? Role::query()->find($roleId) : null;

        if ($role?->code !== UserRole::ADMIN->value) {
            $branchId = $data['branch_id'] ?? $current?->branch_id;
            $branch = $branchId ? Branch::query()->with('regionRef')->find($branchId) : null;

            return [[
                'admin_scope_type' => null,
                'area_id' => $branch?->regionRef?->area_id,
                'region_id' => $branch?->region_id,
                'branch_id' => $branch?->id_branch,
                'service_area_id' => null,
            ], []];
        }

        $scope = AdminScopeType::tryFrom((string) ($data['admin_scope_type'] ?? $current?->admin_scope_type?->value));

        return match ($scope) {
            AdminScopeType::AREA => $this->areaScope((int) ($data['area_id'] ?? $current?->area_id)),
            AdminScopeType::REGION => $this->regionScope((int) ($data['region_id'] ?? $current?->region_id)),
            AdminScopeType::BRANCH => $this->branchScope((int) ($data['branch_id'] ?? $current?->branch_id)),
            AdminScopeType::SERVICE_AREA => $this->serviceAreaScope(
                $data['service_area_ids'] ?? $current?->serviceAreas()->pluck('service_areas.id_service_area')->all() ?? []
            ),
            default => [[
                'admin_scope_type' => null,
                'area_id' => null,
                'region_id' => null,
                'branch_id' => null,
                'service_area_id' => null,
            ], []],
        };
    }

    private function areaScope(int $areaId): array
    {
        $area = Area::query()->findOrFail($areaId);

        return [[
            'admin_scope_type' => AdminScopeType::AREA->value,
            'area_id' => $area->id_area,
            'region_id' => null,
            'branch_id' => null,
            'service_area_id' => null,
        ], []];
    }

    private function regionScope(int $regionId): array
    {
        $region = Region::query()->findOrFail($regionId);

        return [[
            'admin_scope_type' => AdminScopeType::REGION->value,
            'area_id' => $region->area_id,
            'region_id' => $region->id_region,
            'branch_id' => null,
            'service_area_id' => null,
        ], []];
    }

    private function branchScope(int $branchId): array
    {
        $branch = Branch::query()->with('regionRef')->findOrFail($branchId);

        return [[
            'admin_scope_type' => AdminScopeType::BRANCH->value,
            'area_id' => $branch->regionRef?->area_id,
            'region_id' => $branch->region_id,
            'branch_id' => $branch->id_branch,
            'service_area_id' => null,
        ], []];
    }

    private function serviceAreaScope(array $ids): array
    {
        $ids = collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $serviceAreas = ServiceArea::query()
            ->with('branch.regionRef')
            ->whereIn('id_service_area', $ids)
            ->get();

        if ($serviceAreas->count() !== $ids->count()) {
            throw new InvalidArgumentException('Terdapat Service Area yang tidak valid.');
        }

        $primary = $serviceAreas->first();
        $branchIds = $serviceAreas->pluck('branch_id')->filter()->unique();
        $regionIds = $serviceAreas->pluck('region_id')->filter()->unique();
        $areaIds = $serviceAreas->map(fn (ServiceArea $item) => $item->branch?->regionRef?->area_id)->filter()->unique();

        return [[
            'admin_scope_type' => AdminScopeType::SERVICE_AREA->value,
            'area_id' => $areaIds->count() === 1 ? $areaIds->first() : null,
            'region_id' => $regionIds->count() === 1 ? $regionIds->first() : null,
            'branch_id' => $branchIds->count() === 1 ? $branchIds->first() : null,
            'service_area_id' => $primary?->id_service_area,
        ], $ids->all()];
    }

    private function recordHistory(
        User $target,
        User $actor,
        string $eventType,
        ?array $changes = null,
        ?string $note = null
    ): UserHistory {
        return UserHistory::create([
            'target_user_id' => $target->id_user,
            'actor_id' => $actor->id_user,
            'event_type' => $eventType,
            'changes' => $changes,
            'note' => $note,
        ]);
    }
}
