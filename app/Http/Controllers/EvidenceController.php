<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEvidenceRequest;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Services\EvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload/hapus evidence, selalu dalam konteks satu LOP (lihat lop.show).
 */
class EvidenceController extends Controller
{
    public function __construct(private readonly EvidenceService $evidenceService) {}

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

    /**
     * Layani file lewat Laravel agar URL evidence tetap valid ketika aplikasi
     * dipasang di subfolder XAMPP dan tidak bergantung pada symlink /storage.
     */
    public function file(QeEvidence $evidence): StreamedResponse
    {
        return $this->stream($evidence, false);
    }

    public function thumbnail(QeEvidence $evidence): StreamedResponse
    {
        return $this->stream($evidence, true);
    }

    private function stream(QeEvidence $evidence, bool $thumbnail): StreamedResponse
    {
        $this->authorize('view', $evidence);

        $disk = Storage::disk(config('evidence.disk', 'public'));
        $path = $thumbnail && $evidence->thumb_path && $disk->exists($evidence->thumb_path)
            ? $evidence->thumb_path
            : $evidence->file_path;

        abort_unless($path && $disk->exists($path), 404, 'File evidence tidak ditemukan.');

        $originalName = data_get($evidence->metadata, 'original_name', basename($path));
        $fileName = $thumbnail ? 'thumb-'.pathinfo($originalName, PATHINFO_FILENAME).'.webp' : $originalName;
        $mime = $thumbnail && $path === $evidence->thumb_path
            ? 'image/webp'
            : data_get($evidence->metadata, 'mime');

        return $disk->response($path, $fileName, array_filter([
            'Content-Type' => $mime,
            // URL memiliki versi berbasis file_path, sehingga browser aman
            // menyimpan thumbnail/foto lama dan otomatis mengambil versi baru
            // ketika evidence diganti.
            'Cache-Control' => 'private, max-age=2592000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]), 'inline');
    }
}
