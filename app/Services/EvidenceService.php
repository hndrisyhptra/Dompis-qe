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

    /**
     * Disk penyimpanan evidence (default `public`, bisa dipindah ke s3 lewat
     * config('evidence.disk') tanpa mengubah service ini).
     */
    private function disk(): string
    {
        return config('evidence.disk', 'public');
    }

    /**
     * Tulis SATU file evidence (+ thumbnail opsional dari browser) dan buat
     * satu baris qe_evidences. Tidak membuka transaksi sendiri - caller yang
     * mengaturnya (lihat uploadMany). $writtenPaths (by-ref) diisi path file
     * yang sudah ditulis, untuk cleanup bila transaksi caller gagal.
     */
    public function storeOne(
        QeLop $lop,
        array $data,
        UploadedFile $file,
        User $actor,
        ?UploadedFile $thumb = null,
        ?array &$writtenPaths = null,
    ): QeEvidence {
        $dir = "evidences/{$lop->id_qe_lops}/{$data['step']}";
        // Filename aman: UUID + ekstensi asli, bukan nama file mentah dari user.
        $uuid = (string) Str::uuid();

        $path = $file->storeAs($dir, $uuid.'.'.$file->getClientOriginalExtension(), $this->disk());
        if (is_array($writtenPaths)) {
            $writtenPaths[] = $path;
        }

        $thumbPath = null;
        if ($thumb) {
            $thumbPath = $thumb->storeAs($dir, $uuid.'_thumb.'.$thumb->getClientOriginalExtension(), $this->disk());
            if (is_array($writtenPaths)) {
                $writtenPaths[] = $thumbPath;
            }
        }

        return QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'designator_id' => $data['designator_id'] ?? null,
            'uploaded_by' => $actor->id_user,
            'step' => $data['step'],
            'type' => $data['type'],
            'category' => $data['category'] ?? null,
            'file_path' => $path,
            'thumb_path' => $thumbPath,
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

    public function upload(QeLop $lop, array $data, UploadedFile $file, User $actor, ?UploadedFile $thumb = null): QeEvidence
    {
        return DB::transaction(fn () => $this->storeOne($lop, $data, $file, $actor, $thumb));
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
        $writtenPaths = [];

        try {
            return DB::transaction(function () use ($lop, $data, $files, $actor, &$writtenPaths) {
                $evidences = [];

                foreach ($files as $file) {
                    $evidences[] = $this->storeOne($lop, $data, $file, $actor, null, $writtenPaths);
                }

                return $evidences;
            });
        } catch (Throwable $exception) {
            Storage::disk($this->disk())->delete($writtenPaths);
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

    public function replace(QeEvidence $evidence, UploadedFile $file, User $actor, ?UploadedFile $thumb = null): QeEvidence
    {
        $dir = "evidences/{$evidence->qe_lop_id}/{$evidence->step->value}";
        $uuid = (string) Str::uuid();

        $newPath = $file->storeAs($dir, $uuid.'.'.$file->getClientOriginalExtension(), $this->disk());
        $newThumbPath = $thumb
            ? $thumb->storeAs($dir, $uuid.'_thumb.'.$thumb->getClientOriginalExtension(), $this->disk())
            : null;

        $oldPaths = array_filter([$evidence->file_path, $evidence->thumb_path]);

        try {
            $evidence = DB::transaction(function () use ($evidence, $file, $actor, $newPath, $newThumbPath) {
                $metadata = $evidence->metadata ?? [];
                $evidence->update([
                    'file_path' => $newPath,
                    'thumb_path' => $newThumbPath,
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
            Storage::disk($this->disk())->delete(array_filter([$newPath, $newThumbPath]));
            throw $exception;
        }

        Storage::disk($this->disk())->delete($oldPaths);

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

    public function resetReview(QeEvidence $evidence, User $actor): QeEvidence
    {
        $previousStatus = $evidence->status;

        $evidence = DB::transaction(function () use ($evidence, $actor, $previousStatus) {
            $metadata = $evidence->metadata ?? [];
            $reviewResets = $metadata['review_resets'] ?? [];
            $reviewResets[] = [
                'previous_status' => $previousStatus->value,
                'previous_note' => $evidence->review_note,
                'previous_reviewer_id' => $evidence->reviewed_by,
                'previous_reviewed_at' => $evidence->reviewed_at?->toIso8601String(),
                'reset_by' => $actor->id_user,
                'reset_at' => now()->toIso8601String(),
            ];

            $evidence->update([
                'status' => EvidenceStatus::PENDING,
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'metadata' => [...$metadata, 'review_resets' => $reviewResets],
            ]);

            $lop = $evidence->lop()->first();
            if (
                $previousStatus === EvidenceStatus::REJECTED
                && $lop?->status_lop === LopStatus::REJECTED
                && ! $lop->evidences()
                    ->where('id_evidence', '!=', $evidence->id_evidence)
                    ->where('status', EvidenceStatus::REJECTED)
                    ->exists()
            ) {
                $this->lopService->transitionStatus(
                    $lop,
                    LopStatus::WAITING_APPROVAL,
                    $actor,
                    'Keputusan reject evidence direset untuk pemeriksaan ulang'
                );
            }

            return $evidence->refresh();
        });

        $evidence->uploader?->notify(new TechnicianActivityNotification(
            'Evidence diperiksa ulang',
            "Keputusan review {$evidence->category?->label()} pada {$evidence->lop->incident} dikembalikan ke pending.",
            $evidence->qe_lop_id,
            'info'
        ));

        return $evidence;
    }

    public function delete(QeEvidence $evidence): void
    {
        DB::transaction(function () use ($evidence) {
            Storage::disk($this->disk())->delete(array_filter([$evidence->file_path, $evidence->thumb_path]));
            $evidence->delete();
        });
    }
}
