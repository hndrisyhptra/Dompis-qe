<?php

namespace App\Policies;

use App\Enums\EvidenceStatus;
use App\Enums\UserRole;
use App\Models\QeEvidence;
use App\Models\User;

/**
 * Otorisasi review/upload evidence. Upload sendiri (create) diotorisasi
 * lewat QeLopPolicy::uploadEvidence() karena target-nya QeLop (belum ada
 * QeEvidence saat create) - policy ini untuk baris QeEvidence yang sudah ada.
 */
class EvidencePolicy
{
    /**
     * Beda dari QeLopPolicy::viewAny() (selalu true, scoping lewat query) -
     * viewAny di sini MEMANG gerbang akses halaman Approval Evidence, jadi
     * harus dicek permission-nya di sini, bukan cuma "semua yang login".
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('approve_evidence');
    }

    /**
     * Visibilitas sama seperti QeLopPolicy::view - kalau bisa lihat LOP-nya,
     * bisa lihat evidence-nya.
     */
    public function view(User $user, QeEvidence $evidence): bool
    {
        if ($user->hasRole(UserRole::ADMIN)) {
            return $this->isAssignedBy($user, $evidence);
        }

        if ($user->hasRole(UserRole::SUPER_ADMIN, ...UserRole::broadVisibility())) {
            return true;
        }

        return $evidence->lop->assignments()
            ->where('technician_id', $user->id_user)
            ->exists();
    }

    /**
     * Hapus evidence milik sendiri, selama masih pending (belum direview).
     */
    public function delete(User $user, QeEvidence $evidence): bool
    {
        if ($user->hasRole(...UserRole::adminLevel())) {
            return true;
        }

        return $evidence->uploaded_by === $user->id_user
            && $evidence->status === EvidenceStatus::PENDING;
    }

    public function approve(User $user, QeEvidence $evidence): bool
    {
        return $this->canReview($user, $evidence)
            && $evidence->status === EvidenceStatus::PENDING;
    }

    public function reject(User $user, QeEvidence $evidence): bool
    {
        return $this->canReview($user, $evidence)
            && $evidence->status === EvidenceStatus::PENDING;
    }

    public function resetReview(User $user, QeEvidence $evidence): bool
    {
        return $this->canReview($user, $evidence)
            && $evidence->status !== EvidenceStatus::PENDING;
    }

    public function replace(User $user, QeEvidence $evidence): bool
    {
        if (! $user->hasRole(UserRole::TEKNISI) || $evidence->status !== EvidenceStatus::REJECTED) {
            return false;
        }

        return $evidence->lop->assignments()
            ->where('technician_id', $user->id_user)
            ->where('status', 'active')
            ->exists();
    }

    private function canReview(User $user, QeEvidence $evidence): bool
    {
        if (! $user->hasPermission('approve_evidence')) {
            return false;
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return $this->isAssignedBy($user, $evidence);
        }

        return $user->hasRole(UserRole::SUPER_ADMIN, UserRole::APPROVER);
    }

    private function isAssignedBy(User $user, QeEvidence $evidence): bool
    {
        return $evidence->lop->activeAssignment()
            ->where('assigned_by', $user->id_user)
            ->exists();
    }
}
