<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Sumber canonical tanggal laporan incident: kolom varchar `ticket.reported_date`
 * pada koneksi read-only mysql_dompis. Bukan `reported_date_dt` (nilainya bisa
 * berbeda waktu), bukan `created_at`, dan bukan tanggal pembuatan LOP.
 *
 * Nilai di lapangan campuran: ISO (YYYY-MM-DD, sering disertai waktu) dan
 * hari-dulu DD/MM/YYYY. Tanggal kosong atau tidak terbaca tetap tampil bila
 * tanpa filter, tetapi tidak pernah masuk rentang yang dipilih.
 *
 * Ekspresi ini dipakai bersama oleh monitoring tiket dan filter halaman yang
 * berbasis qe_lops agar parse-nya tidak terpecah jadi dua versi.
 */
class TicketReportedDate
{
    /** Ekspresi SQL yang menghasilkan DATE dari reported_date campuran. */
    public static function expression(string $driver): string
    {
        $value = 'TRIM(reported_date)';
        if ($driver === 'sqlite') {
            $rest = "SUBSTR({$value}, INSTR({$value}, '/') + 1)";
            $day = "SUBSTR({$value}, 1, INSTR({$value}, '/') - 1)";
            $month = "SUBSTR({$rest}, 1, INSTR({$rest}, '/') - 1)";
            $year = "SUBSTR({$rest}, INSTR({$rest}, '/') + 1, 4)";
            $slashDate = "DATE(PRINTF('%04d-%02d-%02d', {$year}, {$month}, {$day}))";
        } else {
            $slashDate = "DATE(STR_TO_DATE({$value}, '%d/%m/%Y'))";
        }

        return "CASE WHEN INSTR({$value}, '/') > 0 THEN {$slashDate} ELSE DATE({$value}) END";
    }

    /**
     * Terapkan rentang inclusive; batas yang kosong dibiarkan terbuka.
     *
     * Mengembalikan ekspresi tanggal yang dipakai untuk pemanggil yang juga
     * perlu membacanya (misalnya saat menampilkan kolom reported_day).
     */
    public static function applyRange(Builder $query, string $driver, string $from, string $to): string
    {
        $reportedDate = self::expression($driver);

        if ($from !== '') {
            $query->whereRaw("{$reportedDate} >= ?", [$from]);
        }

        if ($to !== '') {
            $query->whereRaw("{$reportedDate} <= ?", [$to]);
        }

        return $reportedDate;
    }
}
