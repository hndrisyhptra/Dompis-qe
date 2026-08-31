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
            ->with(['activeAssignment.technician', 'materialReservation.items', 'survey', 'evidences'])
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

        return [
            'projects' => $projects,
            'activeCount' => (clone $query)->where('status_lop', '!=', LopStatus::COMPLETED->value)->count(),
            'approvalCount' => (clone $query)->where('status_lop', LopStatus::WAITING_APPROVAL->value)->count(),
            'completedCount' => (clone $query)->where('status_lop', LopStatus::COMPLETED->value)->count(),
            'attentionCount' => (clone $query)->where('status_lop', LopStatus::REJECTED->value)->count(),
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
