<?php

namespace App\Services;

use App\Enums\EvidenceStatus;
use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EvidenceApprovalService
{
    public function indexData(User $user, array $filters): array
    {
        $status = $filters['status'] ?? EvidenceStatus::PENDING->value;
        if ($status !== 'all' && ! EvidenceStatus::tryFrom($status)) {
            $status = EvidenceStatus::PENDING->value;
        }

        $step = $filters['step'] ?? '';
        $evidenceFilter = function ($query) use ($status, $step): void {
            if ($status !== 'all') {
                $query->where('status', $status);
            }

            if ($step) {
                $query->where('step', $step);
            }
        };

        $scoped = $this->scopedLops($user);
        $stats = $this->stats(clone $scoped);
        $query = clone $scoped;

        $query->with(['activeAssignment.technician', 'activeAssignment.assigner'])
            ->with(['evidences' => function ($query) use ($evidenceFilter): void {
                $evidenceFilter($query);
                $query->with(['designator', 'uploader', 'reviewer'])->latest();
            }])
            ->withCount([
                'evidences',
                'evidences as pending_evidences_count' => fn ($query) => $query->where('status', EvidenceStatus::PENDING),
                'evidences as approved_evidences_count' => fn ($query) => $query->where('status', EvidenceStatus::APPROVED),
                'evidences as rejected_evidences_count' => fn ($query) => $query->where('status', EvidenceStatus::REJECTED),
            ])
            ->whereHas('evidences', $evidenceFilter);

        $this->applyFilters($query, $user, $filters);

        $lops = $query->latest()->paginate(15)->withQueryString();
        foreach ($lops as $lop) {
            $lop->setAttribute('approval_summary', $this->summary($lop));
        }

        return [
            'lops' => $lops,
            'stats' => $stats,
            'statusFilter' => $status,
            'step' => $step,
            'search' => $filters['q'] ?? '',
            'regionFilter' => $filters['region'] ?? '',
            'branchFilter' => $filters['branch'] ?? '',
            'programFilter' => $filters['program'] ?? '',
            'lopStatusFilter' => $filters['lop_status'] ?? '',
            'isSuperAdmin' => $user->hasRole(UserRole::SUPER_ADMIN),
            'regions' => Branch::query()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
            'branches' => Branch::query()->orderBy('region')->orderBy('name')->get(),
        ];
    }

    public function reviewData(QeLop $lop): array
    {
        $lop->load([
            'activeAssignment.technician',
            'materialReservation.items.designator',
            'survey',
            'evidences.designator',
            'evidences.uploader',
            'evidences.reviewer',
        ]);

        return [
            'lop' => $lop,
            'approvalSummary' => $this->summary($lop),
        ];
    }

    public function summary(QeLop $lop): array
    {
        $total = isset($lop->evidences_count) ? (int) $lop->evidences_count : $lop->evidences->count();
        $pending = isset($lop->pending_evidences_count)
            ? (int) $lop->pending_evidences_count
            : $lop->evidences->where('status', EvidenceStatus::PENDING)->count();
        $approved = isset($lop->approved_evidences_count)
            ? (int) $lop->approved_evidences_count
            : $lop->evidences->where('status', EvidenceStatus::APPROVED)->count();
        $rejected = isset($lop->rejected_evidences_count)
            ? (int) $lop->rejected_evidences_count
            : $lop->evidences->where('status', EvidenceStatus::REJECTED)->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'approval_percentage' => $total > 0 ? (int) round(($approved / $total) * 100) : 0,
            'review_percentage' => $total > 0 ? (int) round((($approved + $rejected) / $total) * 100) : 0,
        ];
    }

    private function scopedLops(User $user): Builder
    {
        $query = QeLop::query();

        if ($user->hasRole(UserRole::ADMIN)) {
            $query->whereHas('activeAssignment', fn (Builder $assignment) => $assignment
                ->where('assigned_by', $user->id_user));
        }

        return $query;
    }

    private function stats(Builder $lopQuery): array
    {
        $lopIds = (clone $lopQuery)->select('qe_lops.id_qe_lops');
        $evidenceQuery = QeEvidence::query()->whereIn('qe_lop_id', $lopIds);

        return [
            'lop_pending' => (clone $lopQuery)->whereHas('evidences', fn (Builder $query) => $query->where('status', EvidenceStatus::PENDING))->count(),
            'evidence_pending' => (clone $evidenceQuery)->where('status', EvidenceStatus::PENDING)->count(),
            'lop_approved' => (clone $lopQuery)
                ->whereHas('evidences')
                ->whereDoesntHave('evidences', fn (Builder $query) => $query->where('status', '!=', EvidenceStatus::APPROVED))
                ->count(),
            'evidence_rejected' => (clone $evidenceQuery)->where('status', EvidenceStatus::REJECTED)->count(),
        ];
    }

    private function applyFilters(Builder $query, User $user, array $filters): void
    {
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('incident', 'like', "%{$search}%")
                    ->orWhere('nama_lop', 'like', "%{$search}%")
                    ->orWhere('sto', 'like', "%{$search}%")
                    ->orWhere('branch', 'like', "%{$search}%")
                    ->orWhereHas('activeAssignment.technician', fn (Builder $technician) => $technician->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('evidences.designator', fn (Builder $designator) => $designator->where('code', 'like', "%{$search}%"));
            });
        }

        if ($program = ProgramType::tryFrom((string) ($filters['program'] ?? ''))) {
            $query->where('program_type', $program);
        }

        if ($lopStatus = LopStatus::tryFrom((string) ($filters['lop_status'] ?? ''))) {
            $query->where('status_lop', $lopStatus);
        }

        if (! $user->hasRole(UserRole::SUPER_ADMIN)) {
            return;
        }

        if ($branch = trim((string) ($filters['branch'] ?? ''))) {
            $query->where('branch', $branch);
        } elseif ($region = trim((string) ($filters['region'] ?? ''))) {
            $query->whereIn('branch', Branch::query()->where('region', $region)->select('name'));
        }
    }
}
