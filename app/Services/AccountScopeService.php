<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;

class AccountScopeService
{
    public function __construct(private readonly LopVisibilityService $visibility) {}

    public function label(User $user): string
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return $this->visibility->label($user);
        }

        return match (true) {
            $user->hasRole(UserRole::SUPER_ADMIN) => 'Seluruh wilayah operasional',
            $user->hasRole(UserRole::TEKNISI) => 'LOP penugasan Anda',
            $user->hasRole(UserRole::MANAGER) => 'Seluruh LOP · Monitoring',
            $user->hasRole(UserRole::APPROVER) => 'Seluruh LOP · Review sesuai izin',
            default => 'Scope belum diatur',
        };
    }
}
