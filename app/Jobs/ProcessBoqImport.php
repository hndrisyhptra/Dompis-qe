<?php

namespace App\Jobs;

use App\Models\QeImportBatch;
use App\Models\User;
use App\Services\BoqImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessBoqImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $batchId) {}

    public function handle(BoqImportService $service): void
    {
        $batch = QeImportBatch::findOrFail($this->batchId);
        $actor = User::findOrFail($batch->uploaded_by);
        $service->process($batch, $actor);
    }

    public function failed(?Throwable $exception): void
    {
        QeImportBatch::whereKey($this->batchId)->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception?->getMessage() ?? 'Job import BOQ gagal.', 0, 2000),
            'completed_at' => now(),
        ]);
    }
}
