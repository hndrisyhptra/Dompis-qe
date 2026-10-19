<?php

namespace App\Jobs;

use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\User;
use App\Services\BulkLopImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ProcessBulkLopImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public int $batchId) {}

    public function handle(BulkLopImportService $service): void
    {
        $batch = QeImportBatch::findOrFail($this->batchId);
        if ($batch->isFinished()) {
            return;
        }
        $lock = Cache::lock("bulk_lop_import_{$this->batchId}", 660);
        if (! $lock->get()) {
            return;
        }
        try {
            $actor = User::find($batch->uploaded_by);
            if (! $actor || ! $actor->isActive() || ! Gate::forUser($actor)->allows('create', QeLop::class)) {
                throw new \InvalidArgumentException('Akun pengunggah tidak aktif atau tidak lagi memiliki izin membuat LOP. Hubungi Superadmin.');
            }
            $service->process($batch, $actor);
        } catch (Throwable $exception) {
            $service->fail($batch, $exception);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($batch = QeImportBatch::find($this->batchId)) {
            app(BulkLopImportService::class)->fail($batch, $exception);
        }
    }
}
