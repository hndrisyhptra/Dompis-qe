<?php

namespace App\Services;

use App\Enums\ProgramType;
use App\Models\Branch;
use App\Models\QeLop;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Membuat nomor tiket manual ketika incident tidak ditemukan di DB tiket
 * eksternal saat Input LOP Baru.
 *
 * Format: INP{id_branch}{no_program}{DDMMYY}{urut2digit}
 *   - id_branch : PK branches milik user yang login (bukan dari form)
 *   - no_program    : 1=recovery, 2=preventive, 3=relok_utilitas (ProgramType::order())
 *   - DDMMYY    : tanggal pembuatan
 *   - urut      : nomor urut, di-reset ke 01 tiap hari per kombinasi branch+program
 *
 * Contoh: INP3102092601 (branch id 3 / Surabaya, recovery, 02 Sep 2026, urut 01).
 */
class ManualIncidentService
{
    public function generate(Branch $branch, ProgramType $program, ?CarbonInterface $date = null): string
    {
        $date ??= Carbon::now();

        $prefix = sprintf('INP%d%d%s', $branch->id_branch, $program->order(), $date->format('dmy'));

        $next = $this->lastSequence($prefix) + 1;

        return $prefix.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Urut terakhir yang sudah dipakai untuk prefix ini. Menghitung juga LOP
     * yang sudah di-soft-delete karena unique index qe_lops.incident tetap
     * mencakup baris tersebut.
     */
    private function lastSequence(string $prefix): int
    {
        $prefixLength = strlen($prefix);

        return QeLop::withTrashed()
            ->where('incident', 'like', $prefix.'%')
            ->pluck('incident')
            ->map(fn (string $incident) => (int) substr($incident, $prefixLength))
            ->max() ?? 0;
    }
}
