<?php

namespace App\Services;

use App\Enums\LopSegment;
use App\Models\Branch;
use App\Models\TicketSegmentMap;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menarik data tiket dari DB operasional eksternal (koneksi read-only
 * `mysql_dompis`, tabel `ticket`) untuk auto-fill form Input LOP Baru.
 *
 * Hanya membaca. Tidak pernah menulis ke koneksi eksternal.
 */
class TicketLookupService
{
    /**
     * @return array{
     *     found: bool,
     *     error?: bool,
     *     message?: string,
     *     sto?: ?string,
     *     branch?: ?string,
     *     segment?: ?string,
     *     jenis_tiket_2?: ?string,
     *     warnings?: array<int, string>,
     * }
     */
    public function lookup(string $incident): array
    {
        $incident = mb_strtoupper(trim($incident));

        if ($incident === '') {
            return ['found' => false];
        }

        try {
            $ticket = DB::connection('mysql_dompis')
                ->table('ticket')
                ->select('incident', 'workzone', 'jenis_tiket_2')
                ->where('incident', $incident)
                ->first();
        } catch (Throwable $e) {
            report($e);

            return [
                'found' => false,
                'error' => true,
                'message' => 'Tidak dapat terhubung ke database tiket. Isi field secara manual.',
            ];
        }

        if ($ticket === null) {
            return ['found' => false];
        }

        $warnings = [];

        $workzone = $ticket->workzone !== null ? trim((string) $ticket->workzone) : '';
        $branch = $this->resolveBranch($workzone);

        if ($branch === null) {
            $warnings[] = $workzone === ''
                ? 'Workzone tiket kosong sehingga Branch tidak dapat ditentukan otomatis. Pilih Branch manual.'
                : sprintf('Branch tidak dapat ditentukan otomatis dari workzone "%s". Pilih Branch manual.', $workzone);
        }

        $jenisTiket2 = $ticket->jenis_tiket_2 !== null ? trim((string) $ticket->jenis_tiket_2) : '';
        $segment = $this->resolveSegment($jenisTiket2);

        if ($segment === null && $jenisTiket2 !== '') {
            $warnings[] = sprintf(
                'Segmen tidak dapat ditentukan otomatis dari jenis tiket "%s". Pilih Segmen manual atau tambahkan pemetaannya di Master Data.',
                $jenisTiket2,
            );
        }

        return [
            'found' => true,
            'sto' => $workzone !== '' ? mb_strtoupper($workzone) : null,
            'branch' => $branch,
            'segment' => $segment,
            'jenis_tiket_2' => $jenisTiket2 !== '' ? $jenisTiket2 : null,
            'warnings' => $warnings,
        ];
    }

    /**
     * workzone -> service_area.nama_sa -> area.branch_id -> branch.nama_branch,
     * lalu dipastikan namanya ada di master branches lokal.
     */
    private function resolveBranch(string $workzone): ?string
    {
        if ($workzone === '') {
            return null;
        }

        try {
            $namaBranch = DB::connection('mysql_dompis')
                ->table('service_area as sa')
                ->join('area as a', 'a.id_area', '=', 'sa.area_id')
                ->join('branch as b', 'b.id_branch', '=', 'a.branch_id')
                ->where('sa.nama_sa', $workzone)
                ->orderBy('b.id_branch')
                ->limit(1)
                ->value('b.nama_branch');
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($namaBranch === null || $namaBranch === '') {
            return null;
        }

        $namaBranch = trim((string) $namaBranch);

        return Branch::query()->where('name', $namaBranch)->exists()
            ? $namaBranch
            : null;
    }

    private function resolveSegment(string $jenisTiket2): ?string
    {
        if ($jenisTiket2 === '') {
            return null;
        }

        $segment = TicketSegmentMap::query()
            ->where('source_value', mb_strtoupper($jenisTiket2))
            ->value('segment');

        if ($segment instanceof LopSegment) {
            return $segment->value;
        }

        return $segment !== null && $segment !== '' ? (string) $segment : null;
    }
}
