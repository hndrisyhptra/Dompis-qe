<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QeLop;
use App\Models\User;

/**
 * Satu-satunya sumber kebenaran otorisasi untuk modul LOP. Controller TIDAK
 * BOLEH melakukan pengecekan role inline (`if ($user->role === 'ADMIN')`) -
 * semua keputusan izin lewat policy ini supaya konsisten & mudah diaudit.
 */
class QeLopPolicy
{
    public function viewAny(User $user): bool
    {
        // Semua role login boleh melihat daftar (Inbox Active LOP / History),
        // scoping data per-role (mis. teknisi hanya lihat LOP miliknya)
        // dilakukan di query controller, bukan di sini.
        return true;
    }

    public function view(User $user, QeLop $lop): bool
    {
        if ($user->hasRole(...[...UserRole::adminLevel(), ...UserRole::broadVisibility()])) {
            return true;
        }

        // Teknisi hanya boleh lihat LOP yang sedang/pernah ditugaskan padanya.
        return $lop->assignments()->where('technician_id', $user->id_user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(...UserRole::adminLevel());
    }

    public function update(User $user, QeLop $lop): bool
    {
        return $user->hasRole(...UserRole::adminLevel());
    }

    public function delete(User $user, QeLop $lop): bool
    {
        return $user->hasRole(UserRole::SUPER_ADMIN);
    }

    public function assign(User $user, QeLop $lop): bool
    {
        return $user->hasRole(...UserRole::adminLevel());
    }

    public function transitionStatus(User $user, QeLop $lop): bool
    {
        if ($user->hasRole(...UserRole::adminLevel())) {
            return true;
        }

        // Approver hanya boleh transisi dari waiting_approval (approve/reject).
        if ($user->hasRole(UserRole::APPROVER)) {
            return $lop->status_lop->value === 'waiting_approval';
        }

        // Teknisi hanya boleh transisi LOP yang sedang ditugaskan padanya,
        // dan hanya untuk status pekerjaan lapangan (bukan approval/completed).
        if ($user->hasRole(UserRole::TEKNISI)) {
            $isAssignedToMe = $lop->assignments()
                ->where('technician_id', $user->id_user)
                ->where('status', 'active')
                ->exists();

            return $isAssignedToMe && in_array($lop->status_lop->value, [
                'assigned', 'picked_up', 'survey', 'progress',
            ], true);
        }

        return false;
    }
}
