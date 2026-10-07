<?php

namespace App\Console\Commands;

use App\Models\QeImportBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillImportFileHashes extends Command
{
    protected $signature = 'app:backfill-import-file-hashes';

    protected $description = 'Isi file_hash untuk batch import lama dari file yang tersimpan di disk';

    public function handle(): int
    {
        $missing = QeImportBatch::query()
            ->whereNull('file_hash')
            ->whereNotNull('file_path')
            ->orderBy('id_import_batch')
            ->get();

        if ($missing->isEmpty()) {
            $this->info('Tidak ada batch yang perlu di-backfill.');

            return self::SUCCESS;
        }

        $filled = 0;
        $skipped = 0;

        foreach ($missing as $batch) {
            $disk = $batch->disk ?: 'local';
            $path = $batch->file_path;

            if (! Storage::disk($disk)->exists($path)) {
                $skipped++;

                continue;
            }

            $hash = hash_file('sha256', Storage::disk($disk)->path($path));

            // Kolom file_hash unik: lewati bila hash sudah dipakai batch lain.
            $conflict = QeImportBatch::query()
                ->where('file_hash', $hash)
                ->where('id_import_batch', '!=', $batch->id_import_batch)
                ->exists();

            if ($conflict) {
                $skipped++;

                continue;
            }

            $batch->update(['file_hash' => $hash]);
            $filled++;
        }

        $this->info("Backfill selesai. {$filled} batch diisi, {$skipped} dilewati (file hilang atau hash duplikat).");

        return self::SUCCESS;
    }
}
