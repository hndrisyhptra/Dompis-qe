<?php

namespace App\Console\Commands;

use App\Models\QeLop;
use App\Services\IncidentReportedDateService;
use Illuminate\Console\Command;

/**
 * Backfill dan penyegaran kolom qe_lops.reported_date dari ticket.reported_date.
 *
 * Dibutuhkan setelah migrasi kolom, dan bisa diulang kapan pun sumber tiket
 * baru pulih atau laporan LOP dicatat dalam keadaan database tiket tidak tersedia.
 */
class SyncReportedDate extends Command
{
    protected $signature = 'dompis:sync-reported-date
        {--all : Sinkronkan ulang seluruh LOP, bukan hanya yang reported_date-nya masih kosong}';

    protected $description = 'Menyalin reported_date tiket Dompis ke qe_lops.reported_date agar filter tanggal Master Data BOQ berjalan lokal';

    public function handle(IncidentReportedDateService $reportedDates): int
    {
        $query = QeLop::query()->select(['id_qe_lops', 'incident', 'reported_date']);

        if (! $this->option('all')) {
            $query->whereNull('reported_date');
        }

        $total = 0;
        $updated = 0;

        $query->chunkById(500, function ($lops) use ($reportedDates, &$total, &$updated): void {
            $dates = $reportedDates->datesFor($lops->pluck('incident')->all());

            foreach ($lops as $lop) {
                $total++;
                $date = $dates[mb_strtoupper(trim($lop->incident))] ?? null;

                // Tanggal yang tidak terbaca tetap null; jangan menulis ulang
                // nilai yang sudah sama agar aman dijalankan berulang.
                if ($date === null || $date === $lop->reported_date) {
                    continue;
                }

                $lop->timestamps = false;
                $lop->reported_date = $date;
                $lop->save();
                $updated++;
            }
        });

        $this->info("{$updated} dari {$total} LOP diperbarui.");

        return self::SUCCESS;
    }
}
