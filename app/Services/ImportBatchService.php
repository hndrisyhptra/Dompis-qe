<?php

namespace App\Services;

use App\Models\QeImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportBatchService
{
    /**
     * @return array{batch: QeImportBatch, isNew: bool}
     */
    public function create(string $type, UploadedFile $file, User $user, array $metadata = []): array
    {
        // Compute file hash for deduplication
        $fileContent = file_get_contents($file->getRealPath());
        $fileHash = hash('sha256', $fileContent);

        // Check for existing batch with same file hash by same user
        $existingBatch = QeImportBatch::where('file_hash', $fileHash)
            ->where('uploaded_by', $user->id_user)
            ->where('type', $type)
            ->first();

        if ($existingBatch) {
            // Return existing batch for idempotent upload
            return ['batch' => $existingBatch, 'isNew' => false];
        }

        $uuid = (string) Str::uuid();
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $safeName = ($safeName !== '' ? $safeName : 'import').'.'.strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("imports/{$type}/{$uuid}", $safeName, 'local');

        $batch = QeImportBatch::create([
            'uuid' => $uuid,
            'type' => $type,
            'status' => 'queued',
            'disk' => 'local',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_hash' => $fileHash,
            'metadata' => $metadata,
            'uploaded_by' => $user->id_user,
        ]);

        return ['batch' => $batch, 'isNew' => true];
    }
}
