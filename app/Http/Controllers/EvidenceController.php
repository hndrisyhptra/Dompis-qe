<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEvidenceRequest;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Services\EvidenceService;
use Illuminate\Http\RedirectResponse;

/**
 * Upload/hapus evidence, selalu dalam konteks satu LOP (lihat lop.show).
 */
class EvidenceController extends Controller
{
    public function __construct(private readonly EvidenceService $evidenceService)
    {
    }

    public function store(StoreEvidenceRequest $request, QeLop $qe_lop): RedirectResponse
    {
        $this->evidenceService->upload(
            $qe_lop,
            $request->validated(),
            $request->file('file'),
            $request->user()
        );

        return redirect()
            ->route('lop.show', $qe_lop)
            ->with('status', 'Evidence berhasil diupload.');
    }

    public function destroy(QeLop $qe_lop, QeEvidence $evidence): RedirectResponse
    {
        $this->authorize('delete', $evidence);

        $this->evidenceService->delete($evidence);

        return redirect()
            ->route('lop.show', $qe_lop)
            ->with('status', 'Evidence berhasil dihapus.');
    }
}
