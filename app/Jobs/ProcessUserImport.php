<?php

namespace App\Jobs;

use App\Models\QeImportBatch;
use App\Models\User;
use App\Services\UserImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessUserImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public int $batchId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('user-import:'.$this->batchId))->dontRelease()->expireAfter(660)];
    }

    public function handle(UserImportService $service): void
    {
        $batch = QeImportBatch::find($this->batchId);
        if (! $batch || $batch->type !== 'users' || $batch->isFinished()) {
            return;
        }
        $actor = User::find($batch->uploaded_by);
        if (! $actor) {
            $service->fail($batch, 'Akun pengunggah tidak tersedia. Import user dibatalkan.');

            return;
        }
        $service->process($batch, $actor);
    }

    public function failed(?Throwable $exception): void
    {
        if ($batch = QeImportBatch::find($this->batchId)) {
            app(UserImportService::class)->fail($batch);
        }
    }
}
