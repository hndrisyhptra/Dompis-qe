<?php

namespace App\Services;

use App\Support\TicketReportedDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menyediakan daftar incident dari sumber tiket Dompis untuk suatu rentang
 * reported_date.
 *
 * Dipakai halaman yang tabel utamanya `qe_lops` (misalnya Data BOQ) karena
 * tanggal laporan tidak tersimpan di dompis_qe sehingga tidak bisa di-join
 * lintas koneksi. Hasilnya dipakai sebagai daftar pembanding di query lokal,
 * bukan sebagai sumber baris, sehingga tetap tunduk pada scope admin.
 */
class IncidentReportedDateService
{
    /**
     * Incident dengan reported_date berada dalam rentang inclusive.
     *
     * Mengembalikan null bila kedua batas kosong, supaya pemanggil tahu tidak
     * ada filter yang perlu diterapkan (berbeda dengan daftar kosong yang
     * berarti tidak ada tiket pada rentang tersebut).
     *
     * @return array<int, string>|null sudah dinormalisasi UPPER(TRIM()) agar
     *                                 sepadan dengan pemakaian incident di aplikasi
     *
     * @throws \Throwable bila sumber tiket tidak tersedia
     */
    public function incidentsBetween(string $from, string $to): ?array
    {
        if ($from === '' && $to === '') {
            return null;
        }

        $source = DB::connection('mysql_dompis');
        $query = $source->table('ticket')->whereRaw("TRIM(incident) <> ''");
        TicketReportedDate::applyRange($query, $source->getDriverName(), $from, $to);

        return $query->distinct()
            ->pluck('incident')
            ->map(fn (string $incident): string => mb_strtoupper(trim($incident)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Batasi query LOP pada daftar hasil incidentsBetween().
     *
     * null berarti tanpa filter sehingga query tidak disentuh. [] berarti
     * sumber tiket tidak punya satu pun incident pada rentang — atau sumbernya
     * sedang tidak tersedia — jadi query harus menghasilkan kosong, bukan
     * diabaikan dan menampilkan data tanpa filter.
     *
     * Normalisasi UPPER(TRIM()) dipakai pada kedua sisi agar sepadan dengan
     * pemakaian incident di halaman monitoring tiket.
     *
     * @param  array<int, string>|null  $incidents
     */
    public function applyTo(Builder $query, ?array $incidents): void
    {
        if ($incidents === null) {
            return;
        }

        if ($incidents === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn(DB::raw('UPPER(TRIM(qe_lops.incident))'), $incidents);
    }
}
