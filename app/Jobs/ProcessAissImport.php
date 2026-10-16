<?php

namespace App\Jobs;

use App\Models\QeImportBatch;
use App\Services\AissImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessAissImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public int $batchId) {}

    public function handle(AissImportService $service): void
    {
        $lock = Cache::lock("aiss_import_{$this->batchId}", 660);
        if (! $lock->get()) {
            return;
        }
        try {
            $service->process(QeImportBatch::findOrFail($this->batchId));
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($batch = QeImportBatch::find($this->batchId)) {
            app(AissImportService::class)->fail($batch, 'Proses import AISS terhenti. Periksa hasil baris yang sudah tersimpan sebelum upload ulang.');
        }
    }
}
