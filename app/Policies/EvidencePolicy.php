<?php

namespace App\Policies;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\LopStatus;
use App\Enums\UserRole;
use App\Models\QeEvidence;
use App\Models\User;
use App\Services\LopVisibilityService;

/**
 * Otorisasi review/upload evidence. Upload sendiri (create) diotorisasi
 * lewat QeLopPolicy::uploadEvidence() karena target-nya QeLop (belum ada
 * QeEvidence saat create) - policy ini untuk baris QeEvidence yang sudah ada.
 */
class EvidencePolicy
{
    public function __construct(private readonly LopVisibilityService $visibility) {}

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
        if ($evidence->lop === null) {
            return false;
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return $this->visibility->canAccess($user, $evidence->lop);
        }

        if ($user->hasRole(UserRole::SUPER_ADMIN, ...UserRole::broadVisibility())) {
            return true;
        }

        return $evidence->lop->assignments()
            ->where('technician_id', $user->id_user)
            ->exists();
    }

    /**
     * Foto approved terkunci bagi teknisi sampai review direset admin.
     */
    public function delete(User $user, QeEvidence $evidence): bool
    {
        if ($evidence->lop === null) {
            return false;
        }

        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            return true;
        }
        if ($user->hasRole(UserRole::ADMIN)) {
            return $this->visibility->canAccess($user, $evidence->lop);
        }

        return $user->hasRole(UserRole::TEKNISI)
            && (int) $evidence->uploaded_by === (int) $user->id_user
            && $evidence->status !== EvidenceStatus::APPROVED
            && $evidence->category !== EvidenceCategory::REQUEST_LETTER
            && in_array($evidence->lop->status_lop, [LopStatus::PICKED_UP, LopStatus::SURVEY, LopStatus::PROGRESS, LopStatus::WAITING_APPROVAL, LopStatus::REJECTED], true)
            && $this->activelyAssigned($user, $evidence);
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
            && ($evidence->lop->status_lop !== LopStatus::COMPLETED || $user->hasRole(...UserRole::adminLevel()))
            && $evidence->status !== EvidenceStatus::PENDING;
    }

    public function replace(User $user, QeEvidence $evidence): bool
    {
        if ($evidence->lop === null || ! $user->hasRole(UserRole::TEKNISI) || $evidence->status !== EvidenceStatus::REJECTED) {
            return false;
        }

        return $this->activelyAssigned($user, $evidence);
    }

    private function activelyAssigned(User $user, QeEvidence $evidence): bool
    {
        if ($evidence->lop->relationLoaded('activeAssignment')) {
            return (int) $evidence->lop->activeAssignment?->technician_id === (int) $user->getKey();
        }

        return $evidence->lop->assignments()->where('technician_id', $user->getKey())->where('status', 'active')->exists();
    }

    private function canReview(User $user, QeEvidence $evidence): bool
    {
        if ($evidence->lop === null || $evidence->category === EvidenceCategory::REQUEST_LETTER) {
            return false;
        }

        if (! $user->hasPermission('approve_evidence')) {
            return false;
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return $this->visibility->canAccess($user, $evidence->lop);
        }

        return $user->hasRole(UserRole::SUPER_ADMIN, UserRole::APPROVER);
    }
}
