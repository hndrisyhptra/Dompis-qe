<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplaceEvidenceRequest;
use App\Http\Requests\StoreMaterialReservationRequest;
use App\Http\Requests\StoreSurveyLocationRequest;
use App\Http\Requests\StoreTechnicianEvidenceRequest;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Services\EvidenceService;
use App\Services\TechnicianWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TechnicianWorkflowController extends Controller
{
    public function __construct(
        private readonly TechnicianWorkflowService $workflowService,
        private readonly EvidenceService $evidenceService,
    ) {}

    public function pickup(Request $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('transitionStatus', $qe_lop);
        $this->workflowService->pickup($qe_lop, $request->user());

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => 1])
            ->with('status', 'Project berhasil di-pickup. Mulai reservasi material.');
    }

    public function resume(Request $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('transitionStatus', $qe_lop);
        $this->workflowService->resumeRejected($qe_lop, $request->user());

        return redirect()->route('technician.projects.show', $qe_lop)
            ->with('status', 'Mode perbaikan evidence dimulai.');
    }

    public function materials(StoreMaterialReservationRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->workflowService->saveMaterials($qe_lop, $request->user(), $request->validated('items'));

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => 2])
            ->with('status', 'Reservasi material tersimpan.');
    }

    public function location(StoreSurveyLocationRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->workflowService->saveLocation($qe_lop, $request->user(), $request->validated());

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => 2])
            ->with('status', 'Lokasi survey tersimpan.');
    }

    public function evidence(StoreTechnicianEvidenceRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->workflowService->uploadEvidence(
            $qe_lop,
            $request->user(),
            $request->safe()->except('files'),
            $request->file('files')
        );

        $step = match ($request->validated('category')) {
            'pre', 'material_arrival', 'before' => 2,
            'progress' => 3,
            'after' => 4,
        };

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => $step])
            ->with('status', count($request->file('files')).' file evidence berhasil diupload.');
    }

    public function replaceEvidence(
        ReplaceEvidenceRequest $request,
        QeLop $qe_lop,
        QeEvidence $evidence
    ): RedirectResponse {
        abort_unless($evidence->qe_lop_id === $qe_lop->id_qe_lops, 404);

        $this->evidenceService->replace($evidence, $request->file('file'), $request->user());

        $step = match ($evidence->category?->value) {
            'pre', 'material_arrival', 'before' => 2,
            'progress' => 3,
            'after' => 4,
            default => 2,
        };

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => $step])
            ->with('status', 'Evidence berhasil diganti dan dikirim ulang untuk review.');
    }

    public function completeSurvey(Request $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('transitionStatus', $qe_lop);
        $this->workflowService->completeSurvey($qe_lop, $request->user());

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => 3])
            ->with('status', 'Survey selesai. Lanjutkan evidence progress.');
    }

    public function submitApproval(Request $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('transitionStatus', $qe_lop);
        $this->workflowService->submitApproval($qe_lop, $request->user());

        return redirect()->route('technician.projects.show', $qe_lop)
            ->with('status', 'Project berhasil diajukan untuk approval.');
    }
}
