<?php

namespace App\Services;

use App\Enums\EvidenceStatus;
use App\Enums\LopStatus;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\User;
use App\Notifications\TechnicianActivityNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Satu-satunya tempat yang boleh menulis file evidence ke disk & baris
 * qe_evidences - mirip LopService, tapi untuk evidence (upload, approve,
 * reject, hapus).
 */
class EvidenceService
{
    public function __construct(private readonly LopService $lopService) {}

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
                'category' => $data['category'] ?? null,
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

    /**
     * Simpan beberapa file dalam satu aksi. File yang sudah tersimpan akan
     * dibersihkan kembali jika salah satu write database/file gagal.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, QeEvidence>
     */
    public function uploadMany(QeLop $lop, array $data, array $files, User $actor): array
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($lop, $data, $files, $actor, &$storedPaths) {
                $evidences = [];

                foreach ($files as $file) {
                    $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
                    $path = $file->storeAs(
                        "evidences/{$lop->id_qe_lops}/{$data['step']}",
                        $filename,
                        'public'
                    );
                    $storedPaths[] = $path;

                    $evidences[] = QeEvidence::create([
                        'qe_lop_id' => $lop->id_qe_lops,
                        'designator_id' => $data['designator_id'] ?? null,
                        'uploaded_by' => $actor->id_user,
                        'step' => $data['step'],
                        'type' => $data['type'],
                        'category' => $data['category'],
                        'file_path' => $path,
                        'metadata' => [
                            'original_name' => $file->getClientOriginalName(),
                            'mime' => $file->getClientMimeType(),
                            'size' => $file->getSize(),
                            'latitude' => $data['latitude'] ?? null,
                            'longitude' => $data['longitude'] ?? null,
                        ],
                        'note' => $data['note'] ?? null,
                        'status' => EvidenceStatus::PENDING,
                    ]);
                }

                return $evidences;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }
    }

    public function approve(QeEvidence $evidence, User $actor): QeEvidence
    {
        $evidence->update([
            'status' => EvidenceStatus::APPROVED,
            'review_note' => null,
            'reviewed_by' => $actor->id_user,
            'reviewed_at' => now(),
        ]);

        $evidence = $evidence->refresh();

        $evidence->uploader?->notify(new TechnicianActivityNotification(
            'Evidence disetujui',
            "{$evidence->category?->label()} pada {$evidence->lop->incident} telah disetujui.",
            $evidence->qe_lop_id,
            'success'
        ));

        return $evidence;
    }

    public function replace(QeEvidence $evidence, UploadedFile $file, User $actor): QeEvidence
    {
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $newPath = $file->storeAs(
            "evidences/{$evidence->qe_lop_id}/{$evidence->step->value}",
            $filename,
            'public'
        );
        $oldPath = $evidence->file_path;

        try {
            $evidence = DB::transaction(function () use ($evidence, $file, $actor, $newPath) {
                $metadata = $evidence->metadata ?? [];
                $evidence->update([
                    'file_path' => $newPath,
                    'uploaded_by' => $actor->id_user,
                    'metadata' => [
                        ...$metadata,
                        'original_name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'replaced_at' => now()->toIso8601String(),
                    ],
                    'status' => EvidenceStatus::PENDING,
                    'review_note' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);

                return $evidence->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);
            throw $exception;
        }

        Storage::disk('public')->delete($oldPath);

        return $evidence;
    }

    public function reject(QeEvidence $evidence, User $actor, string $reason): QeEvidence
    {
        $evidence->update([
            'status' => EvidenceStatus::REJECTED,
            'review_note' => $reason,
            'reviewed_by' => $actor->id_user,
            'reviewed_at' => now(),
        ]);

        $evidence = $evidence->refresh();

        if ($evidence->lop->status_lop === LopStatus::WAITING_APPROVAL) {
            $this->lopService->transitionStatus(
                $evidence->lop,
                LopStatus::REJECTED,
                $actor,
                "Evidence ditolak: {$reason}"
            );
        }

        $evidence->uploader?->notify(new TechnicianActivityNotification(
            'Evidence perlu diperbaiki',
            "{$evidence->category?->label()} pada {$evidence->lop->incident} ditolak: {$reason}",
            $evidence->qe_lop_id,
            'danger'
        ));

        return $evidence;
    }

    public function delete(QeEvidence $evidence): void
    {
        DB::transaction(function () use ($evidence) {
            Storage::disk('public')->delete($evidence->file_path);
            $evidence->delete();
        });
    }
}
