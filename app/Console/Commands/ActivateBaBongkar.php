<?php

namespace App\Console\Commands;

use App\Enums\LopStatus;
use App\Enums\UserRole;
use App\Models\QeLop;
use App\Models\User;
use App\Services\LopBoqProjectionService;
use App\Services\LopService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivateBaBongkar extends Command
{
    protected $signature = 'dompis:activate-ba-bongkar {--cutoff= : Batas created_at, ISO 8601 dengan timezone} {--actor= : ID Super Admin pelaksana} {--apply : Terapkan; tanpa opsi ini hanya audit read-only}';

    protected $description = 'Audit atau buka kembali LOP lama untuk melengkapi tahap 6 BA Bongkar';

    public function handle(LopService $service, LopBoqProjectionService $projection): int
    {
        if (! $this->option('cutoff') || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $this->option('cutoff'))) {
            $this->error('Isi --cutoff dengan ISO 8601 dan timezone, misalnya 2026-10-23T23:59:59+07:00.');

            return self::FAILURE;
        }
        try {
            $cutoff = CarbonImmutable::parse($this->option('cutoff'))->timezone(config('app.timezone'));
        } catch (\Throwable) {
            $this->error('Tanggal cutoff tidak valid.');

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $actor = $this->option('actor') ? User::find($this->option('actor')) : null;
        if ($apply && (! $actor?->isActive() || ! $actor->hasRole(UserRole::SUPER_ADMIN))) {
            $this->error('--apply memerlukan --actor berupa ID Super Admin aktif.');

            return self::FAILURE;
        }
        if ($apply && ! Schema::hasColumn('qe_lops', 'ba_bongkar_data')) {
            $this->error('Jalankan migration BA Bongkar terlebih dahulu.');

            return self::FAILURE;
        }
        $counts = [];
        $revenue = 0.0;
        $missingPrice = 0;
        $unassigned = [];
        $changed = 0;
        $query = QeLop::query()->where(fn ($q) => $q->where('created_at', '<=', $cutoff)->orWhereNull('created_at'))
            ->whereDoesntHave('histories', fn ($q) => $q->where('event_type', 'ba_bongkar_activated'))
            ->with(['activeAssignment', 'boq.items.designator.type', 'boq.package', 'materialReservation.items.designator.type', 'package']);
        $query->chunkById(100, function ($lops) use (&$counts, &$revenue, &$missingPrice, &$unassigned, &$changed, $apply, $actor, $service, $projection, $cutoff) {
            foreach ($lops as $lop) {
                $status = $lop->status_lop->value;
                $counts[$status] = ($counts[$status] ?? 0) + 1;
                if ($lop->status_lop === LopStatus::COMPLETED) {
                    $actual = $projection->report($lop, 'actual');
                    $revenue += (float) ($actual['grand']['total_actual'] ?? 0);
                    $missingPrice += collect($actual['lines'])->where('price_missing', true)->count();
                }
                if (! $lop->activeAssignment && in_array($lop->status_lop, [LopStatus::COMPLETED, LopStatus::WAITING_APPROVAL, LopStatus::REJECTED, LopStatus::PROGRESS], true)) {
                    $unassigned[] = $lop->getKey();
                }
                if (! $apply) {
                    continue;
                }
                DB::transaction(function () use ($lop, $actor, $service, $cutoff, &$changed) {
                    $locked = QeLop::query()->lockForUpdate()->find($lop->getKey());
                    if (! $locked || $locked->histories()->where('event_type', 'ba_bongkar_activated')->exists()) {
                        return;
                    }
                    $before = $locked->status_lop;
                    if (in_array($before, [LopStatus::WAITING_APPROVAL, LopStatus::COMPLETED, LopStatus::REJECTED], true)) {
                        $service->transitionStatus($locked, LopStatus::PROGRESS, $actor, 'Aktivasi tahap 6 BA Bongkar; evidence, review, BOQ dan rekap dipertahankan.');
                        $locked->materialReservation()->update(['status' => 'draft', 'submitted_at' => null]);
                    }
                    $service->recordBaBongkarEvent($locked, $actor, 'ba_bongkar_activated', ['status_before' => $before->value, 'status_after' => $locked->status_lop->value, 'cutoff' => $cutoff->toIso8601String()]);
                    $changed++;
                });
            }
        }, 'id_qe_lops');
        $this->info($apply ? 'Aktivasi BA Bongkar selesai.' : 'DRY RUN — tidak ada data yang diubah.');
        $this->table(['Status sebelumnya', 'Jumlah'], collect($counts)->map(fn ($count, $status) => [$status, $count])->values()->all());
        $this->line('Realisasi yang keluar sementara (harga tersedia): Rp '.number_format($revenue, 0, ',', '.'));
        $this->line('Item tanpa harga: '.$missingPrice);
        $this->line('LOP tanpa teknisi aktif yang perlu ditugaskan: '.(implode(', ', $unassigned) ?: 'tidak ada'));
        $this->line('LOP ditandai aktivasi: '.$changed);

        return self::SUCCESS;
    }
}
