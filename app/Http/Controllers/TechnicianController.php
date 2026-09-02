<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
=======
use App\Enums\DesignatorType;
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
use App\Enums\LopStatus;
use App\Models\Designator;
use App\Models\QeLop;
use App\Services\TechnicianDashboardService;
use App\Services\TechnicianWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TechnicianController extends Controller
{
    public function __construct(
        private readonly TechnicianDashboardService $dashboardService,
        private readonly TechnicianWorkflowService $workflowService,
    ) {}

    public function dashboard(Request $request): View
    {
        return view('technician.dashboard', $this->dashboardService->dashboard($request->user()));
    }

    public function inbox(Request $request): View
    {
        $tab = $request->string('tab')->value() === 'complete' ? 'complete' : 'active';
        $query = $this->dashboardService->projectsQuery($request->user());

        if ($tab === 'complete') {
            $query->where('status_lop', LopStatus::COMPLETED->value);
        } else {
            $query->where('status_lop', '!=', LopStatus::COMPLETED->value);
        }

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($builder) => $builder
                ->where('incident', 'like', "%{$search}%")
                ->orWhere('nama_lop', 'like', "%{$search}%")
                ->orWhere('sto', 'like', "%{$search}%"));
        }

        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status_lop', $status);
        }

        $projects = $query->latest()->paginate(12)->withQueryString();
        $this->dashboardService->attachProgress($projects->getCollection());

        return view('technician.inbox', [
            'projects' => $projects,
            'tab' => $tab,
            'search' => $search,
            'statusFilter' => $status,
        ]);
    }

    public function project(Request $request, QeLop $qe_lop): View
    {
        $this->authorize('view', $qe_lop);
        $state = $this->workflowService->state($qe_lop);
        $requestedStep = max(1, min(4, $request->integer('step', $state['currentStep'])));

        return view('technician.project', [
            'lop' => $qe_lop,
            'state' => $state,
            'step' => $requestedStep,
            'designators' => Designator::query()
<<<<<<< HEAD
                ->whereRelation('type', 'code', 'MATERIAL')
=======
                ->where('type', DesignatorType::MATERIAL->value)
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
                ->orderBy('code')->get(),
        ]);
    }

    public function notifications(Request $request): View
    {
        return view('technician.notifications', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
        ]);
    }

    public function readNotification(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        if ($lopId = $item->data['lop_id'] ?? null) {
            return redirect()->route('technician.projects.show', $lopId);
        }

        return back();
    }

    public function readAllNotifications(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function profile(Request $request): View
    {
        return view('technician.profile', ['user' => $request->user()->load(['role', 'branch'])]);
    }
}
