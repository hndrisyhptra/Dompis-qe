<?php

namespace App\Services;

use App\Support\TicketReportedDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menyalin tanggal laporan incident dari sumber tiket Dompis ke qe_lops.
 *
 * Sumber canonical tetap `ticket.reported_date` pada koneksi read-only
 * mysql_dompis, tetapi nilainya disimpan sebagai DATE pada
 * `qe_lops.reported_date` sehingga filter dan sort halaman berbasis LOP tidak
 * perlu query lintas koneksi di tiap request.
 */
class IncidentReportedDateService
{
    /**
     * Tanggal laporan per incident, ternormalisasi Y-m-d atau null bila tidak terbaca.
     *
     * @param  array<int, string>  $incidents
     * @return array<string, string|null> kunci UPPER(TRIM(incident))
     */
    public function datesFor(array $incidents): array
    {
        $incidents = collect($incidents)
            ->map(fn (string $incident): string => mb_strtoupper(trim($incident)))
            ->filter()
            ->unique()
            ->values();

        if ($incidents->isEmpty()) {
            return [];
        }

        $dates = [];

        try {
            $source = DB::connection('mysql_dompis');

            foreach ($incidents->chunk(500) as $chunk) {
                // Kolom incident berindex (tidak UPPER/TRIM agar index tetap
                // terpakai); kolasi ci pada sumber tiket sudah menutup beda
                // kapital, dan sisi LOP sudah dinormalisasi aplikasi.
                $rows = $source->table('ticket')
                    ->whereIn('incident', $chunk->all())
                    ->get(['incident', 'reported_date']);

                foreach ($rows as $row) {
                    $key = mb_strtoupper(trim($row->incident));
                    $dates[$key] ??= TicketReportedDate::normalize($row->reported_date);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $dates;
    }

    /**
     * Terapkan rentang tanggal laporan pada query yang memakai qe_lops.reported_date.
     *
     * Aturannya sama di daftar BOQ, list reservasi, dan export sehingga tidak
     * ada dua versi: batas kosong dibiarkan terbuka, dan baris dengan tanggal
     * null tetap terlihat tanpa filter tetapi tidak pernah masuk rentang terpilih.
     */
    public function applyRange(Builder $query, string $from, string $to): void
    {
        if ($from !== '') {
            $query->where('reported_date', '>=', $from);
        }

        if ($to !== '') {
            $query->where('reported_date', '<=', $to);
        }
    }
}
