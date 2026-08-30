<?php

namespace App\Http\Controllers;

use App\Enums\EvidenceStatus;
use App\Http\Requests\RejectEvidenceRequest;
use App\Models\QeEvidence;
use App\Services\EvidenceService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EvidenceApprovalController extends Controller
{
    public function __construct(private readonly EvidenceService $evidenceService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QeEvidence::class);

        $query = QeEvidence::query()
            ->with(['lop', 'designator', 'uploader'])
            ->where('status', EvidenceStatus::PENDING);

        if ($step = $request->string('step')->trim()->value()) {
            $query->where('step', $step);
        }

        return view('evidence-approval.index', [
            'evidences' => $query->latest()->paginate(20)->withQueryString(),
            'step' => $step,
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

        return redirect()
            ->route('evidence-approval.index')
            ->with('status', 'Evidence disetujui.');
    }

    public function reject(RejectEvidenceRequest $request, QeEvidence $evidence): RedirectResponse
    {
        $this->evidenceService->reject($evidence, $request->user(), $request->validated('review_note'));

        return redirect()
            ->route('evidence-approval.index')
            ->with('status', 'Evidence ditolak.');
    }
}
