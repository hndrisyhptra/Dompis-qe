<?php

namespace App\Services;

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
            $user = User::create([
                'role_id' => $data['role_id'] ?? null,
                'nik' => $data['nik'] ?? null,
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'status' => $data['status'] ?? 'active',
                'password' => $data['password'],
            ]);

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
            $changes = [];

            foreach (['role_id', 'branch_id', 'status'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] != $target->{$field}) {
                    $changes[$field] = ['from' => $target->{$field}, 'to' => $data[$field]];
                }
            }

            $payload = [
                'role_id' => $data['role_id'] ?? $target->role_id,
                'nik' => $data['nik'] ?? $target->nik,
                'name' => $data['name'] ?? $target->name,
                'username' => $data['username'] ?? $target->username,
                'email' => $data['email'] ?? $target->email,
                'phone' => $data['phone'] ?? $target->phone,
                'branch_id' => $data['branch_id'] ?? $target->branch_id,
                'status' => $data['status'] ?? $target->status,
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $target->update($payload);

            $eventType = match (true) {
                isset($changes['role_id']) => 'role_changed',
                isset($changes['branch_id']) => 'branch_changed',
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
