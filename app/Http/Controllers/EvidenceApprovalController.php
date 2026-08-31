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
            $request->only(['q', 'status', 'step', 'region', 'branch', 'wbs', 'lop_status']),
        ));
    }

    public function reviewLop(Request $request, QeLop $qe_lop): View
    {
        $this->authorize('reviewEvidence', $qe_lop);

        return view('evidence-approval.lop-review', [
            ...$this->approvalService->reviewData($qe_lop),
            'currentStep' => max(1, min(4, $request->integer('step', 1))),
        ]);
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
}
