<?php

namespace App\Services;

use App\Enums\EvidenceStatus;
use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MyApprovalService
{
    public function __construct(
        private readonly LopVisibilityService $visibility,
        private readonly EvidenceApprovalService $approval,
    ) {}

    public function indexData(User $user, array $filters): array
    {
        $tab = in_array($filters['tab'] ?? '', ['actionable', 'pending', 'ready', 'rejected', 'completed', 'empty', 'all'], true)
            ? $filters['tab'] : 'actionable';
        $search = mb_substr(trim((string) ($filters['q'] ?? '')), 0, 200);
        $program = ProgramType::tryFrom((string) ($filters['program'] ?? ''));
        $status = LopStatus::tryFrom((string) ($filters['lop_status'] ?? ''));
        $ids = $this->applyTab($this->countedLops($user), $tab)->select('id_qe_lops');
        $query = $this->detailedLops($user)->whereIn('qe_lops.id_qe_lops', $ids);
        if ($search !== '') {
            $query->where(fn (Builder $q) => $q->where('incident', 'like', "%{$search}%")
                ->orWhere('nama_lop', 'like', "%{$search}%")
                ->orWhere('sto', 'like', "%{$search}%")
                ->orWhere('branch', 'like', "%{$search}%")
                ->orWhereHas('serviceArea', fn (Builder $sa) => $sa->where('workzone', 'like', "%{$search}%"))
                ->orWhereHas('branchRef', fn (Builder $branch) => $branch->where('name', 'like', "%{$search}%"))
                ->orWhereHas('activeAssignment.technician', fn (Builder $tech) => $tech->where('name', 'like', "%{$search}%")));
        }
        if ($program !== null) {
            $query->where('program_type', $program);
        }
        if ($status !== null) {
            $query->where('status_lop', $status);
        }

        $lops = $query->orderByRaw('CASE WHEN pending_since IS NULL THEN 1 ELSE 0 END')
            ->orderBy('pending_since')->orderByDesc('last_evidence_at')->orderBy('qe_lops.id_qe_lops')
            ->paginate(15)->withQueryString();
        foreach ($lops as $lop) {
            $summary = $this->approval->summary($lop);
            $lop->setAttribute('approval_summary', $summary);
            $lop->setAttribute('approval_ready', $lop->status_lop === LopStatus::WAITING_APPROVAL
                && $summary['total'] > 0 && $summary['approved'] === $summary['total']);
            $lop->setAttribute('review_step', $lop->approval_ready || $lop->status_lop === LopStatus::COMPLETED ? 6 : null);
        }

        return [
            'lops' => $lops, 'stats' => $this->stats($user), 'tab' => $tab, 'search' => $search,
            'programFilter' => $program?->value ?? '', 'lopStatusFilter' => $status?->value ?? '',
            'programs' => ProgramType::cases(), 'statuses' => LopStatus::cases(),
        ];
    }

    public function pendingLopCount(User $user): int
    {
        return $this->assignedLops($user)
            ->where('status_lop', '!=', LopStatus::COMPLETED)
            ->whereHas('evidences', fn (Builder $q) => $this->approval->onlyReviewable($q)->where('status', EvidenceStatus::PENDING))
            ->count();
    }

    public function stats(User $user): array
    {
        $row = $this->countedLops($user)
            ->selectRaw('COUNT(*) as total_lops')
            ->selectRaw('COALESCE(SUM(CASE WHEN status_lop != ? AND pending_evidences_count > 0 THEN 1 ELSE 0 END), 0) as pending_lops', [LopStatus::COMPLETED->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status_lop != ? THEN pending_evidences_count ELSE 0 END), 0) as pending_evidences', [LopStatus::COMPLETED->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status_lop = ? AND evidences_count > 0 AND approved_evidences_count = evidences_count THEN 1 ELSE 0 END), 0) as ready_lops', [LopStatus::WAITING_APPROVAL->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status_lop != ? AND rejected_evidences_count > 0 THEN 1 ELSE 0 END), 0) as rejected_lops', [LopStatus::COMPLETED->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status_lop = ? THEN 1 ELSE 0 END), 0) as completed_lops', [LopStatus::COMPLETED->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN evidences_count = 0 THEN 1 ELSE 0 END), 0) as empty_lops')
            ->first();

        $stats = array_map(fn ($value): int => (int) $value, (array) $row);
        $stats['actionable_lops'] = $stats['pending_lops'] + $stats['ready_lops'];

        return $stats;
    }

    public function reminder(User $user): ?array
    {
        $stats = $this->stats($user);
        if ($stats['actionable_lops'] === 0) {
            return null;
        }

        $ids = $this->applyTab($this->countedLops($user), 'actionable')->select('id_qe_lops');
        $priority = $this->detailedLops($user)->whereIn('qe_lops.id_qe_lops', $ids)
            ->orderByRaw('CASE WHEN pending_since IS NULL THEN 1 ELSE 0 END')
            ->orderBy('pending_since')->orderBy('qe_lops.id_qe_lops')->first();

        return ['stats' => $stats, 'priority' => $priority];
    }

    private function assignedLops(User $user): Builder
    {
        Gate::forUser($user)->authorize('view-my-approvals');
        $query = QeLop::query()->whereHas('activeAssignment', fn (Builder $q) => $q->where('assigned_by', $user->getKey()));

        return $this->visibility->apply($query, $user);
    }

    private function countedLops(User $user): QueryBuilder
    {
        $query = $this->assignedLops($user)->select(['qe_lops.id_qe_lops', 'qe_lops.status_lop']);

        return DB::query()->fromSub($this->approval->withSummaryCounts($query), 'my_approvals');
    }

    private function detailedLops(User $user): Builder
    {
        return $this->approval->withSummaryCounts($this->assignedLops($user))
            ->with(['activeAssignment.technician', 'branchRef', 'serviceArea'])
            ->withMin(['evidences as pending_since' => fn ($q) => $this->approval->onlyReviewable($q)->where('status', EvidenceStatus::PENDING)], 'updated_at')
            ->withMax(['evidences as last_evidence_at' => fn ($q) => $this->approval->onlyReviewable($q)], 'updated_at');
    }

    private function applyTab(QueryBuilder $query, string $tab): QueryBuilder
    {
        return match ($tab) {
            'pending' => $query->where('status_lop', '!=', LopStatus::COMPLETED->value)->where('pending_evidences_count', '>', 0),
            'ready' => $query->where('status_lop', LopStatus::WAITING_APPROVAL->value)->where('evidences_count', '>', 0)->whereColumn('approved_evidences_count', 'evidences_count'),
            'rejected' => $query->where('status_lop', '!=', LopStatus::COMPLETED->value)->where('rejected_evidences_count', '>', 0),
            'completed' => $query->where('status_lop', LopStatus::COMPLETED->value),
            'empty' => $query->where('evidences_count', 0),
            'actionable' => $query->where(function (QueryBuilder $q): void {
                $q->where(fn (QueryBuilder $pending) => $this->applyTab($pending, 'pending'))
                    ->orWhere(fn (QueryBuilder $ready) => $this->applyTab($ready, 'ready'));
            }),
            default => $query,
        };
    }
}
