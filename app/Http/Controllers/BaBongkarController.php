<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBaBongkarRequest;
use App\Models\QeLop;
use App\Services\BaBongkarService;
use App\Support\BaBongkarRules;
use App\Support\BaSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BaBongkarController extends Controller
{
    public function review(Request $request, QeLop $qe_lop, BaBongkarService $service)
    {
        $this->authorize('reviewBaBongkar', $qe_lop);
        $data = $request->validate([
            'revision' => ['required', 'uuid'],
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ]);
        $service->review($qe_lop, $request->user(), $data['revision'], $data['decision'] === 'approve', $data['review_note'] ?? null);

        return redirect()->route('evidence-approval.lop.review', [$qe_lop, 'step' => 6])->with('status', $data['decision'] === 'approve' ? 'BA Bongkar disetujui Admin.' : 'BA Bongkar dikembalikan ke teknisi.');
    }

    public function store(SaveBaBongkarRequest $request, QeLop $qe_lop, BaBongkarService $service)
    {
        $service->save($qe_lop, $request->user(), $request->validated(), $request->file('photos', []), BaSignature::read($request, true));

        return redirect()->route('technician.projects.show', [$qe_lop, 'step' => 6])->with('status', $request->boolean('required') ? 'BA Bongkar tersimpan. Periksa PDF sebelum mengajukan approval.' : 'Pilihan Tidak tersimpan. Pekerjaan dapat diajukan jika seluruh checklist lengkap.');
    }

    public function download(QeLop $qe_lop): StreamedResponse
    {
        $this->authorize('view', $qe_lop);

        $document = BaBongkarRules::document($qe_lop->loadMissing('evidences'));
        abort_if($document === null, 404, 'LOP ini tidak memiliki BA Bongkar.');

        $disk = Storage::disk($document->storageDisk());
        abort_unless($disk->exists($document->file_path), 404, 'File BA Bongkar tidak ditemukan.');

        $filename = data_get($document->metadata, 'original_name')
            ?: 'BA Bongkar-'.preg_replace('/[^\pL\pN _.-]/u', '-', (string) $qe_lop->incident).'.pdf';

        return $disk->download($document->file_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
