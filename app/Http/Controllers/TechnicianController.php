<?php

namespace App\Http\Controllers;

use App\Enums\LopStatus;
use App\Models\Designator;
use App\Models\QeLop;
use App\Services\LopBoqProjectionService;
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
        private readonly LopBoqProjectionService $boqProjection,
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

    public function project(Request $request, QeLop $qe_lop): View|RedirectResponse
    {
        $this->authorize('view', $qe_lop);
        $state = $this->workflowService->state($qe_lop);

        // Step terkunci: hanya boleh membuka step yang <= step pertama yang
        // belum lengkap (currentStep). Step yang sudah selesai tetap boleh
        // dibuka untuk review; step di depannya dikunci. Setelah submit/selesai
        // atau reject, kelima tahap terbuka untuk peninjauan tanpa form pekerjaan.
        $readOnly = in_array($qe_lop->status_lop, [LopStatus::WAITING_APPROVAL, LopStatus::COMPLETED, LopStatus::REJECTED], true);
        $maxStep = $readOnly ? 5 : $state['currentStep'];
        $rejectedStep = $state['rejectedSteps']->keys()->min();
        $requestedStep = max(1, min(5, $request->integer('step', $rejectedStep ?? $state['currentStep'])));

        if ($requestedStep > $maxStep) {
            return redirect()->route('technician.projects.show', [$qe_lop, 'step' => $maxStep]);
        }

        $showBoqReview = $state['materialUsageComplete'];
        $qe_lop->loadMissing(['branchRef', 'serviceArea']);

        return view('technician.project', [
            'lop' => $qe_lop,
            'state' => $state,
            'step' => $requestedStep,
            'maxStep' => $maxStep,
            'readOnly' => $readOnly,
            'showBoqReview' => $showBoqReview,
            'reservationPackage' => $this->workflowService->reservationPackage($qe_lop),
            'designators' => ! $readOnly && $requestedStep === 1 ? Designator::query()
                ->whereRelation('type', 'code', 'MATERIAL')
                ->orderBy('code')->get() : collect(),
        ]);
    }

    public function boqReview(Request $request, QeLop $qe_lop): View|RedirectResponse
    {
        $this->authorize('view', $qe_lop);
        $state = $this->workflowService->state($qe_lop);
        $returnStep = max(1, min(5, $request->integer('step', 5)));

        if (! $state['materialUsageComplete']) {
            return redirect()->route('technician.projects.show', [$qe_lop, 'step' => $returnStep])
                ->withErrors(['workflow' => 'Review BOQ tersedia setelah seluruh quantity actual disimpan pada Step After.']);
        }

        $qe_lop->loadMissing(['branchRef', 'serviceArea']);

        return view('technician.boq-review', [
            'lop' => $qe_lop,
            'returnStep' => $returnStep,
            'boqPlan' => $qe_lop->program_type->usesProjectStatus() ? $this->boqProjection->report($qe_lop, 'plan') : null,
            'boqActual' => $this->boqProjection->report($qe_lop, 'actual'),
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
