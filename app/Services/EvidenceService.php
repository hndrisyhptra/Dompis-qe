<?php

namespace App\Services;

use App\Enums\EvidenceStatus;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Satu-satunya tempat yang boleh menulis file evidence ke disk & baris
 * qe_evidences - mirip LopService, tapi untuk evidence (upload, approve,
 * reject, hapus).
 */
class EvidenceService
{
    public function upload(QeLop $lop, array $data, UploadedFile $file, User $actor): QeEvidence
    {
        return DB::transaction(function () use ($lop, $data, $file, $actor) {
            // Filename aman: UUID + ekstensi asli, bukan nama file mentah dari user.
            $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs(
                "evidences/{$lop->id_qe_lops}/{$data['step']}",
                $filename,
                'public'
            );

            return QeEvidence::create([
                'qe_lop_id' => $lop->id_qe_lops,
                'designator_id' => $data['designator_id'] ?? null,
                'uploaded_by' => $actor->id_user,
                'step' => $data['step'],
                'type' => $data['type'],
                'file_path' => $path,
                'metadata' => [
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ],
                'note' => $data['note'] ?? null,
                'status' => EvidenceStatus::PENDING,
            ]);
        });
    }

    public function approve(QeEvidence $evidence, User $actor): QeEvidence
    {
        $evidence->update([
            'status' => EvidenceStatus::APPROVED,
            'review_note' => null,
            'reviewed_by' => $actor->id_user,
            'reviewed_at' => now(),
        ]);

        return $evidence->refresh();
    }

    public function reject(QeEvidence $evidence, User $actor, string $reason): QeEvidence
    {
        $evidence->update([
            'status' => EvidenceStatus::REJECTED,
            'review_note' => $reason,
            'reviewed_by' => $actor->id_user,
            'reviewed_at' => now(),
        ]);

        return $evidence->refresh();
    }

    public function delete(QeEvidence $evidence): void
    {
        DB::transaction(function () use ($evidence) {
            Storage::disk('public')->delete($evidence->file_path);
            $evidence->delete();
        });
    }
}
