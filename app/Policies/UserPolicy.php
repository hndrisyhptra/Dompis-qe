<?php

namespace App\Policies;

use App\Models\User;

/**
 * Satu-satunya sumber kebenaran otorisasi untuk modul User Management.
 * Berbeda dari QeLopPolicy (role-based) - policy ini konsumen pertama
 * sistem permission granular (hasPermission()), sesuai keputusan saat
 * infra permissions/role_permissions dibangun.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('manage_users');
    }

    /**
     * Tidak boleh menghapus akun sendiri - lockout risk.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->hasPermission('manage_users')
            && $user->id_user !== $model->id_user;
    }

    /**
     * Tidak boleh menonaktifkan akun sendiri - lockout risk.
     */
    public function deactivate(User $user, User $model): bool
    {
        return $user->hasPermission('manage_users')
            && $user->id_user !== $model->id_user;
    }

    public function activate(User $user, User $model): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasPermission('manage_users');
    }
}
