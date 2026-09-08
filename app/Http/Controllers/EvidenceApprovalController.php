<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectEvidenceRequest;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Services\EvidenceApprovalService;
use App\Services\EvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EvidenceApprovalController extends Controller
{
    public function __construct(
        private readonly EvidenceService $evidenceService,
        private readonly EvidenceApprovalService $approvalService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QeEvidence::class);

        return view('evidence-approval.index', $this->approvalService->indexData(
            $request->user(),
            $request->only(['q', 'status', 'step', 'region', 'branch', 'program', 'lop_status']),
        ));
    }

    public function reviewLop(Request $request, QeLop $qe_lop): View
    {
        $this->authorize('reviewEvidence', $qe_lop);

        $data = $this->approvalService->reviewData($qe_lop);

        // Tanpa ?step= eksplisit, buka langsung di step yang evidence-nya
        // masih perlu direview (mis. setelah teknisi mengunggah perbaikan).
        $step = $request->has('step')
            ? max(1, min(5, $request->integer('step')))
            : $data['suggestedStep'];

        return view('evidence-approval.lop-review', [...$data, 'currentStep' => $step]);
    }

    public function show(QeEvidence $evidence): View
    {
        $this->authorize('view', $evidence);

        $evidence->load(['lop', 'designator', 'uploader', 'reviewer']);

        return view('evidence-approval.show', ['evidence' => $evidence]);
    }

    public function approve(QeEvidence $evidence): RedirectResponse
    {
        $this->authorize('approve', $evidence);

        $this->evidenceService->approve($evidence, request()->user());

        return back()->with('status', 'Evidence disetujui.');
    }

    public function reject(RejectEvidenceRequest $request, QeEvidence $evidence): RedirectResponse
    {
        $this->evidenceService->reject($evidence, $request->user(), $request->validated('review_note'));

        return back()->with('status', 'Evidence ditolak.');
    }

    public function resetReview(Request $request, QeEvidence $evidence): RedirectResponse
    {
        $this->authorize('resetReview', $evidence);

        $this->evidenceService->resetReview($evidence, $request->user());

        return back()->with('status', 'Keputusan review direset ke Pending.');
    }

    public function completeReview(Request $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('reviewEvidence', $qe_lop);

        $this->approvalService->completeReview($qe_lop, $request->user());

        return redirect()
            ->route('evidence-approval.index')
            ->with('status', "Review {$qe_lop->incident} selesai — LOP ditandai Completed.");
    }
}
