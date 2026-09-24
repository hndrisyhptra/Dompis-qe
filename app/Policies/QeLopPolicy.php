<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QeLop;
use App\Models\User;
use App\Services\LopVisibilityService;

/**
 * Satu-satunya sumber kebenaran otorisasi untuk modul LOP. Controller TIDAK
 * BOLEH melakukan pengecekan role inline (`if ($user->role === 'ADMIN')`) -
 * semua keputusan izin lewat policy ini supaya konsisten & mudah diaudit.
 */
class QeLopPolicy
{
    public function __construct(private readonly LopVisibilityService $visibility) {}

    public function viewAny(User $user): bool
    {
        // Semua role login boleh melihat daftar (Inbox Active LOP / History),
        // scoping data per-role (mis. teknisi hanya lihat LOP miliknya)
        // dilakukan di query controller, bukan di sini.
        return true;
    }

    public function view(User $user, QeLop $lop): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN, ...UserRole::broadVisibility())) {
            return true;
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return $this->visibility->canAccess($user, $lop);
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
        return $user->hasRole(UserRole::SUPER_ADMIN)
            || ($user->hasRole(UserRole::ADMIN) && $this->visibility->canAccess($user, $lop));
    }

    public function delete(User $user, QeLop $lop): bool
    {
        $allowedRole = $user->hasRole(UserRole::SUPER_ADMIN)
            || ($user->hasRole(UserRole::ADMIN) && $this->visibility->canAccess($user, $lop));

        return $allowedRole
            && $lop->status_lop->value === 'draft'
            && ! $lop->assignments()->exists()
            && ! $lop->evidences()->exists();
    }

    public function assign(User $user, QeLop $lop): bool
    {
        // SUPER_ADMIN: assign LOP apa pun (konsisten dgn create/update/delete).
        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return true;
        }

        // ADMIN: hanya LOP di dalam scope lokasi akun.
        if (! $user->hasRole(UserRole::ADMIN)) {
            return false;
        }

        return $this->visibility->canAccess($user, $lop);
    }

    public function unassign(User $user, QeLop $lop): bool
    {
        return $this->assign($user, $lop)
            && $lop->status_lop->value === 'assigned'
            && $lop->activeAssignment()->exists();
    }

    public function reviewEvidence(User $user, QeLop $lop): bool
    {
        if (! $user->hasPermission('approve_evidence')) {
            return false;
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return $lop->activeAssignment()
                ->where('assigned_by', $user->id_user)
                ->exists();
        }

        return $user->hasRole(UserRole::SUPER_ADMIN, UserRole::APPROVER);
    }

    /**
     * Upload evidence untuk LOP ini. Aturan sama seperti transitionStatus:
     * teknisi cuma boleh untuk LOP yang di-assign ke dia & masih berjalan
     * (bukan completed/rejected).
     */
    public function uploadEvidence(User $user, QeLop $lop): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return true;
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return $this->visibility->canAccess($user, $lop);
        }

        if ($user->hasRole(UserRole::TEKNISI)) {
            $isAssignedToMe = $lop->assignments()
                ->where('technician_id', $user->id_user)
                ->where('status', 'active')
                ->exists();

            return $isAssignedToMe && $lop->status_lop->value !== 'completed';
        }

        return false;
    }

    public function transitionStatus(User $user, QeLop $lop): bool
    {
        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return true;
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return $this->visibility->canAccess($user, $lop);
        }

        // Approver hanya boleh transisi dari waiting_approval (approve/reject).
        if ($user->hasRole(UserRole::APPROVER)) {
            return $lop->status_lop->value === 'waiting_approval';
        }

        // Teknisi hanya boleh transisi LOP yang sedang ditugaskan padanya, dan
        // hanya untuk status pekerjaan lapangan (bukan approval/completed).
        // 'rejected' termasuk: teknisi menekan "Perbaikan Evidence" untuk
        // mengembalikan LOP ke 'progress' (transisi sah di LopStatus::transitions()).
        if ($user->hasRole(UserRole::TEKNISI)) {
            $isAssignedToMe = $lop->assignments()
                ->where('technician_id', $user->id_user)
                ->where('status', 'active')
                ->exists();

            return $isAssignedToMe && in_array($lop->status_lop->value, [
                'assigned', 'picked_up', 'survey', 'progress', 'rejected',
            ], true);
        }

        return false;
    }

}
