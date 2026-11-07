<?php

namespace App\Services;

use App\Enums\LopStatus;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TechnicianDashboardService
{
    public function __construct(private readonly ProjectProgressService $progressService) {}

    public function projectsQuery(User $technician): Builder
    {
        return QeLop::query()
            ->with(['activeAssignment.technician', 'materialReservation.items.designator', 'boq.items.designator', 'survey', 'evidences'])
            ->whereHas('assignments', fn (Builder $query) => $query
                ->where('technician_id', $technician->id_user));
    }

    public function dashboard(User $technician): array
    {
        $query = $this->projectsQuery($technician);
        $active = (clone $query)->where('status_lop', '!=', LopStatus::COMPLETED->value);

        $projects = $active->orderByRaw("CASE status_lop WHEN 'rejected' THEN 0 WHEN 'assigned' THEN 1 WHEN 'picked_up' THEN 2 WHEN 'survey' THEN 3 WHEN 'progress' THEN 4 ELSE 5 END")
            ->latest()->limit(5)->get();
        $this->attachProgress($projects);

        $counts = (clone $query)
            ->selectRaw('SUM(CASE WHEN status_lop != ? THEN 1 ELSE 0 END) AS active_count', [LopStatus::COMPLETED->value])
            ->selectRaw('SUM(CASE WHEN status_lop = ? THEN 1 ELSE 0 END) AS approval_count', [LopStatus::WAITING_APPROVAL->value])
            ->selectRaw('SUM(CASE WHEN status_lop = ? THEN 1 ELSE 0 END) AS completed_count', [LopStatus::COMPLETED->value])
            ->selectRaw('SUM(CASE WHEN status_lop = ? THEN 1 ELSE 0 END) AS attention_count', [LopStatus::REJECTED->value])
            ->first();

        return [
            'projects' => $projects,
            'activeCount' => (int) $counts->active_count,
            'approvalCount' => (int) $counts->approval_count,
            'completedCount' => (int) $counts->completed_count,
            'attentionCount' => (int) $counts->attention_count,
            'unreadCount' => $technician->unreadNotifications()->count(),
        ];
    }

    public function attachProgress(iterable $projects): void
    {
        foreach ($projects as $project) {
            $project->setAttribute('progress_summary', $this->progressService->summary($project));
        }
    }
}
