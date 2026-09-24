<?php

namespace App\Services;

use App\Models\QeImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImportBatchService
{
    public function create(string $type, UploadedFile $file, User $user, array $metadata = []): QeImportBatch
    {
        $uuid = (string) Str::uuid();
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $safeName = ($safeName !== '' ? $safeName : 'import').'.'.strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("imports/{$type}/{$uuid}", $safeName, 'local');

        return QeImportBatch::create([
            'uuid' => $uuid,
            'type' => $type,
            'status' => 'queued',
            'disk' => 'local',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'metadata' => $metadata,
            'uploaded_by' => $user->id_user,
        ]);
    }
}
