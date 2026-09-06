<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplaceEvidenceRequest;
use App\Http\Requests\StoreMaterialReservationRequest;
use App\Http\Requests\StoreMaterialUsageRequest;
use App\Http\Requests\StoreSurveyLocationRequest;
use App\Http\Requests\StoreTechnicianEvidenceFileRequest;
use App\Http\Requests\StoreTechnicianEvidenceRequest;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Services\EvidenceService;
use App\Services\TechnicianWorkflowService;
use Illuminate\Http\JsonResponse;
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

    public function materialUsage(StoreMaterialUsageRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->workflowService->saveMaterialUsage($qe_lop, $request->user(), $request->validated('usage'));

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => 5])
            ->with('status', 'Rekap material terpakai tersimpan.');
    }

    public function location(StoreSurveyLocationRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->workflowService->saveLocation($qe_lop, $request->user(), $request->validated());

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => 3])
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
            'material_arrival' => 2,
            'pre', 'before' => 3,
            'progress' => 4,
            'after' => 5,
        };

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => $step])
            ->with('status', count($request->file('files')).' file evidence berhasil diupload.');
    }

    /**
     * Upload evidence SATU file (jalur async dengan progress + retry).
     * Dipanggil via XHR dari window.evidenceUploader; mengembalikan JSON.
     */
    public function evidenceFile(StoreTechnicianEvidenceFileRequest $request, QeLop $qe_lop): JsonResponse
    {
        $evidence = $this->workflowService->uploadEvidenceFile(
            $qe_lop,
            $request->user(),
            $request->safe()->except(['file', 'thumb']),
            $request->file('file'),
            $request->file('thumb'),
        );

        return response()->json([
            'id' => $evidence->id_evidence,
            'url' => $evidence->url(),
            'thumb_url' => $evidence->thumbUrl(),
            'name' => $evidence->metadata['original_name'] ?? null,
            'size' => $evidence->metadata['size'] ?? null,
            'status' => $evidence->status->value,
            'status_label' => $evidence->status->label(),
            'category' => $evidence->category?->value,
            'designator_id' => $evidence->designator_id,
            'created_at' => $evidence->created_at?->format('d M Y H:i'),
        ], 201);
    }

    public function replaceEvidence(
        ReplaceEvidenceRequest $request,
        QeLop $qe_lop,
        QeEvidence $evidence
    ): RedirectResponse {
        abort_unless($evidence->qe_lop_id === $qe_lop->id_qe_lops, 404);

        $this->evidenceService->replace($evidence, $request->file('file'), $request->user(), $request->file('thumb'));

        $step = match ($evidence->category?->value) {
            'material_arrival' => 2,
            'pre', 'before' => 3,
            'progress' => 4,
            'after' => 5,
            default => 3,
        };

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => $step])
            ->with('status', 'Evidence berhasil diganti dan dikirim ulang untuk review.');
    }

    public function completeSurvey(Request $request, QeLop $qe_lop): RedirectResponse
    {
        $this->authorize('transitionStatus', $qe_lop);
        $this->workflowService->completeSurvey($qe_lop, $request->user());

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => 4])
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
