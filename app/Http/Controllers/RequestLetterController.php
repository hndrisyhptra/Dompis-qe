<?php

namespace App\Http\Controllers;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceStep;
use App\Enums\EvidenceType;
use App\Http\Requests\StoreRequestLetterRequest;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Services\EvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RequestLetterController extends Controller
{
    public function __construct(private readonly EvidenceService $evidenceService) {}

    public function store(StoreRequestLetterRequest $request, QeLop $qe_lop): RedirectResponse
    {
        abort_unless($qe_lop->program_type->usesProjectStatus(), 404);

        $files = $request->file('files', []);
        $this->evidenceService->uploadMany($qe_lop, [
            'step' => EvidenceStep::SURVEY,
            'type' => EvidenceType::DOCUMENT,
            'category' => EvidenceCategory::REQUEST_LETTER,
            'note' => 'Dokumen Surat Permintaan',
        ], $files, $request->user());

        return back()->with('status', count($files).' file Surat Permintaan berhasil diupload.');
    }

    public function destroy(Request $request, QeLop $qe_lop, QeEvidence $evidence): RedirectResponse
    {
        abort_unless(
            $evidence->qe_lop_id === $qe_lop->id_qe_lops
            && $evidence->category === EvidenceCategory::REQUEST_LETTER,
            404
        );
        $this->authorize('delete', $evidence);
        $this->evidenceService->delete($evidence);

        return back()->with('status', 'Surat Permintaan berhasil dihapus.');
    }
}
