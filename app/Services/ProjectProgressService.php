<?php

namespace App\Services;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\LopStatus;
use App\Models\QeLop;
use Illuminate\Support\Collection;

class ProjectProgressService
{
    /**
     * @return array{
     *     percentage: int,
     *     completed_steps: int,
     *     total_steps: int,
     *     steps: array<int, bool>,
     *     review_key: string,
     *     review_label: string,
     *     review_variant: string,
     *     evidence_count: int,
     *     pending_count: int,
     *     approved_count: int,
     *     rejected_count: int
     * }
     */
    public function summary(QeLop $lop): array
    {
        $lop->loadMissing(['materialReservation.items', 'survey', 'evidences']);

        $items = $lop->materialReservation?->items ?? collect();
        $evidences = $lop->evidences;
        $reservedIds = $items->pluck('designator_id')->unique();
        $beforeIds = $evidences->where('category', EvidenceCategory::BEFORE)->pluck('designator_id')->unique();
        $afterIds = $evidences->where('category', EvidenceCategory::AFTER)->pluck('designator_id')->unique();

        $steps = [
            1 => $items->isNotEmpty(),
            2 => $items->isNotEmpty()
                && $lop->survey !== null
                && $evidences->where('category', EvidenceCategory::PRE)->isNotEmpty()
                && $evidences->where('category', EvidenceCategory::MATERIAL_ARRIVAL)->isNotEmpty()
                && $reservedIds->diff($beforeIds)->isEmpty(),
            3 => $evidences->where('category', EvidenceCategory::PROGRESS)->isNotEmpty(),
            4 => $items->isNotEmpty() && $reservedIds->diff($afterIds)->isEmpty(),
        ];

        $completedSteps = collect($steps)->filter()->count();
        $pendingCount = $evidences->where('status', EvidenceStatus::PENDING)->count();
        $approvedCount = $evidences->where('status', EvidenceStatus::APPROVED)->count();
        $rejectedCount = $evidences->where('status', EvidenceStatus::REJECTED)->count();
        $percentage = $completedSteps * 25;

        [$reviewKey, $reviewLabel, $reviewVariant] = $this->reviewState(
            $lop,
            $percentage,
            $evidences,
            $pendingCount,
            $approvedCount,
            $rejectedCount,
        );

        return [
            'percentage' => $percentage,
            'completed_steps' => $completedSteps,
            'total_steps' => 4,
            'steps' => $steps,
            'review_key' => $reviewKey,
            'review_label' => $reviewLabel,
            'review_variant' => $reviewVariant,
            'evidence_count' => $evidences->count(),
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
        ];
    }

    /** @param  Collection<int, mixed>  $evidences */
    private function reviewState(
        QeLop $lop,
        int $percentage,
        Collection $evidences,
        int $pendingCount,
        int $approvedCount,
        int $rejectedCount,
    ): array {
        if ($rejectedCount > 0) {
            return ['rejected', 'Reject', 'danger'];
        }

        if ($percentage === 100 && $evidences->isNotEmpty() && $approvedCount === $evidences->count()) {
            return ['approved', 'Approve', 'success'];
        }

        if ($lop->status_lop === LopStatus::WAITING_APPROVAL || ($percentage === 100 && $pendingCount > 0)) {
            return ['waiting_review', 'Waiting Review', 'warning'];
        }

        if ($lop->status_lop === LopStatus::COMPLETED) {
            return ['approved', 'Approve', 'success'];
        }

        return [$lop->status_lop->value, $lop->status_lop->label(), $lop->status_lop->badgeVariant()];
    }
}
