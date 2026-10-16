<?php

namespace App\Services;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStatus;
use App\Enums\LopStatus;
use App\Models\QeLop;
use App\Support\MaterialArrivalRules;
use App\Support\ReservedDesignatorRules;
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

        $items = ReservedDesignatorRules::workflowItems($lop);
        $evidences = $lop->evidences;
        $reviewableEvidences = $evidences->reject(
            fn ($evidence) => $evidence->category === EvidenceCategory::REQUEST_LETTER
        );
        $reservedIds = $items->pluck('designator_id')->unique();
        $progressIds = $evidences->where('category', EvidenceCategory::PROGRESS)->pluck('designator_id')->unique();
        $afterIds = $evidences->where('category', EvidenceCategory::AFTER)->pluck('designator_id')->unique();

        $steps = [
            1 => $items->isNotEmpty() && $items->every(fn ($item) => $item->exists),
            2 => $items->isNotEmpty()
                && MaterialArrivalRules::complete($lop, $evidences),
            // Pra wajib lokasi dan foto kondisi awal; capture Insera opsional.
            3 => $items->isNotEmpty()
                && $lop->survey !== null
                && $evidences->where('category', EvidenceCategory::PRE)->isNotEmpty(),
            4 => $items->isNotEmpty() && $reservedIds->diff($progressIds)->isEmpty(),
            5 => $items->isNotEmpty()
                && $reservedIds->diff($afterIds)->isEmpty()
                && $items->every(fn ($item) => $item->exists && ReservedDesignatorRules::actualComplete($item)),
        ];

        $totalSteps = count($steps);
        $completedSteps = collect($steps)->filter()->count();
        $pendingCount = $reviewableEvidences->where('status', EvidenceStatus::PENDING)->count();
        $approvedCount = $reviewableEvidences->where('status', EvidenceStatus::APPROVED)->count();
        $rejectedCount = $reviewableEvidences->where('status', EvidenceStatus::REJECTED)->count();
        $percentage = (int) round($completedSteps / $totalSteps * 100);

        [$reviewKey, $reviewLabel, $reviewVariant] = $this->reviewState(
            $lop,
            $percentage,
            $reviewableEvidences,
            $pendingCount,
            $approvedCount,
            $rejectedCount,
        );

        return [
            'percentage' => $percentage,
            'completed_steps' => $completedSteps,
            'total_steps' => $totalSteps,
            'steps' => $steps,
            'review_key' => $reviewKey,
            'review_label' => $reviewLabel,
            'review_variant' => $reviewVariant,
            'evidence_count' => $reviewableEvidences->count(),
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
