<?php

namespace App\Services;

use App\Enums\LopSegment;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\DesignatorPackagePrice;
use App\Models\Package;
use App\Models\QeMaterialReservationItem;
use App\Models\User;
use App\Support\LocationScope;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Sumber data tunggal untuk Laporan BOQ Actual & Sisa Material.
 *
 * Basisnya sama: item reservasi material yang reservasinya sudah `submitted`
 * (teknisi selesai rekap qty aktual di Step 5). Perbedaan kedua report cuma di
 * kolom yang ditonjolkan -> ditangani `columns()` / `flatten()`.
 *
 * Agregasi dilakukan di PHP (bukan GROUP BY SQL) supaya:
 * - aman lintas driver (SQLite test tidak punya GREATEST untuk max(0, qty-actual)),
 * - angka identik di 4 channel (layar per-LOP, rekap, CSV, XLSX).
 */
class MaterialReportService
{
    use LocationScope;

    private const STATUSES = ['waiting_approval', 'completed'];

    /**
     * @return array{
     *   q: ?string, program: ?string, segment: ?string, status: array<int,string>,
     *   package: ?int, date_from: ?string, date_to: ?string, view: string,
     *   region: string, branch: string
     * }
     */
    public function normalizeFilters(Request $request): array
    {
        $status = (array) $request->input('status', []);
        $status = array_values(array_intersect(
            array_map('strval', $status),
            self::STATUSES,
        ));

        $package = (int) $request->input('package');
        if ($package <= 0 || ! Package::query()->whereKey($package)->exists()) {
            $package = null;
        }

        $view = $request->string('view')->value();
        $view = in_array($view, ['per_lop', 'rekap'], true) ? $view : 'per_lop';

        return [
            'q' => $request->string('q')->trim()->value() ?: null,
            'program' => ProgramType::tryFrom((string) $request->string('program'))?->value,
            'segment' => LopSegment::tryFrom((string) $request->string('segment'))?->value,
            'status' => $status,
            'package' => $package,
            'date_from' => $this->parseDate($request->string('date_from')->value()),
            'date_to' => $this->parseDate($request->string('date_to')->value()),
            'view' => $view,
            'region' => '',
            'branch' => '',
        ];
    }

    /**
     * Semua baris item (satu row per reservation item) yang lolos filter + scope,
     * sudah difold ke DTO datar. Tanpa paginasi.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(array $filters, User $user): Collection
    {
        $priced = $filters['package'] !== null;
        $priceMap = $this->priceMap($filters['package']);

        return $this->baseQuery($filters, $user)->get()
            ->map(fn ($item) => $this->foldLine($item, $priceMap, $priced))
            ->values();
    }

    /**
     * Payload mode "Rincian per LOP".
     *
     * @return array<string, mixed>
     */
    public function perLop(array $filters, User $user, ?int $perPage = 20): array
    {
        $lines = $this->lines($filters, $user);
        $priced = $filters['package'] !== null;

        $grouped = $lines->groupBy('lop_id')->map(function (Collection $g) use ($priced) {
            $first = $g->first();

            return [
                'lop' => [
                    'id' => $first['lop_id'],
                    'name' => $first['lop_name'],
                    'incident' => $first['lop_incident'],
                    'branch' => $first['lop_branch'],
                    'program' => $first['lop_program'],
                    'segment' => $first['lop_segment'],
                    'status' => $first['lop_status'],
                    'status_raw' => $first['lop_status_raw'],
                ],
                'lines' => $g->values()->all(),
                'subtotal' => $this->sumRows($g, $priced),
            ];
        })->sortBy('lop.name')->values();

        $grand = $this->sumRows($lines, $priced) + [
            'lop_count' => $grouped->count(),
            'designator_count' => $lines->pluck('designator_id')->unique()->count(),
        ];

        return [
            'mode' => 'per_lop',
            'priced' => $priced,
            'package' => $priced ? Package::find($filters['package']) : null,
            'filters' => $filters,
            'groups' => $this->paginateOrAll($grouped, $perPage),
            'grand' => $grand,
        ];
    }

    /**
     * Payload mode "Rekap per Designator".
     *
     * @return array<string, mixed>
     */
    public function rekap(array $filters, User $user, ?int $perPage = 50): array
    {
        $lines = $this->lines($filters, $user);
        $priced = $filters['package'] !== null;

        $rows = $lines->groupBy('designator_id')->map(function (Collection $g) use ($priced) {
            $first = $g->first();

            return [
                'designator_id' => $first['designator_id'],
                'designator_code' => $first['designator_code'],
                'designator_name' => $first['designator_name'],
                'unit' => $first['unit'],
                'qty' => (float) $g->sum('qty'),
                'qty_actual' => (float) $g->sum(fn ($r) => $r['qty_actual'] ?? 0),
                'sisa' => (float) $g->sum(fn ($r) => $r['sisa'] ?? 0),
                'price' => $first['price'],
                'total_actual' => $priced ? (float) $g->sum(fn ($r) => $r['total_actual'] ?? 0) : null,
                'nilai_sisa' => $priced ? (float) $g->sum(fn ($r) => $r['nilai_sisa'] ?? 0) : null,
                'price_missing' => (bool) $first['price_missing'],
                'lop_count' => $g->pluck('lop_id')->unique()->count(),
            ];
        })->sortBy('designator_code')->values();

        $grand = $this->sumRows($lines, $priced) + [
            'designator_count' => $rows->count(),
            'lop_count' => $lines->pluck('lop_id')->unique()->count(),
        ];

        return [
            'mode' => 'rekap',
            'priced' => $priced,
            'package' => $priced ? Package::find($filters['package']) : null,
            'filters' => $filters,
            'rows' => $this->paginateOrAll($rows, $perPage),
            'grand' => $grand,
        ];
    }

    /**
     * Kolom (key => label) terurut untuk kombinasi report + mode + priced.
     *
     * @return array<string, string>
     */
    public function columns(string $report, string $mode, bool $priced): array
    {
        if ($mode === 'rekap') {
            $cols = [
                'designator_code' => 'Designator',
                'designator_name' => 'Uraian',
                'unit' => 'Satuan',
                'qty' => 'Σ Qty Rencana',
                'qty_actual' => 'Σ Qty Actual',
                'sisa' => 'Σ Sisa',
            ];
            if ($priced) {
                $cols['price'] = 'Harga KHS';
                $cols[$report === 'boq' ? 'total_actual' : 'nilai_sisa'] = $report === 'boq' ? 'Σ Total Actual' : 'Σ Nilai Sisa';
            }
            $cols['lop_count'] = 'Jml LOP';

            return $cols;
        }

        // per_lop
        $cols = [
            'lop' => 'LOP',
            'incident' => 'Incident',
            'branch' => 'Branch',
            'designator_code' => 'Designator',
            'designator_name' => 'Uraian',
            'unit' => 'Satuan',
            'qty' => 'Qty Rencana',
            'qty_actual' => 'Qty Actual',
        ];
        if ($report === 'sisa') {
            $cols['sisa'] = 'Sisa';
        }
        if ($priced) {
            $cols['price'] = 'Harga KHS';
            $cols[$report === 'boq' ? 'total_actual' : 'nilai_sisa'] = $report === 'boq' ? 'Total Actual' : 'Nilai Sisa';
            $cols['keterangan'] = 'Keterangan';
        }

        return $cols;
    }

    /**
     * Daftar baris datar (item + subtotal + grand total) untuk CSV & Export xlsx.
     * Angka tetap float mentah supaya rumus SUM di Excel jalan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function flatten(array $payload, string $report, string $mode): array
    {
        $priced = $payload['priced'];
        $out = [];

        if ($mode === 'rekap') {
            $rows = $this->asCollection($payload['rows']);
            foreach ($rows as $r) {
                $out[] = $this->rekapRow($r, $report, $priced);
            }
            $out[] = $this->rekapTotalRow($payload['grand'], $report, $priced);

            return $out;
        }

        $groups = $this->asCollection($payload['groups']);
        foreach ($groups as $group) {
            foreach ($group['lines'] as $line) {
                $out[] = $this->perLopRow($group, $line, $report, $priced);
            }
            $out[] = $this->perLopSubtotalRow($group, $report, $priced);
        }
        if ($groups->isNotEmpty()) {
            $out[] = $this->perLopGrandRow($payload['grand'], $report, $priced);
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // internals
    // ---------------------------------------------------------------------

    private function baseQuery(array $filters, User $user): Builder
    {
        $query = QeMaterialReservationItem::query()
            ->select('qe_material_reservation_items.*')
            ->join('qe_material_reservations as r', 'r.id_reservation', '=', 'qe_material_reservation_items.reservation_id')
            ->join('qe_lops as l', 'l.id_qe_lops', '=', 'r.qe_lop_id')
            ->whereNull('r.deleted_at')
            ->whereNull('l.deleted_at')
            ->where('r.status', 'submitted')
            ->when($filters['program'], fn ($q, $v) => $q->where('l.program_type', $v))
            ->when($filters['segment'], fn ($q, $v) => $q->where('l.segment', $v))
            ->when($filters['status'], fn ($q, $v) => $q->whereIn('l.status_lop', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('l.nama_lop', 'like', "%{$v}%")
                ->orWhere('l.incident', 'like', "%{$v}%")
                ->orWhere('l.sto', 'like', "%{$v}%")))
            ->when($filters['date_from'], fn ($q, $v) => $q->whereDate('r.submitted_at', '>=', $v))
            ->when($filters['date_to'], fn ($q, $v) => $q->whereDate('r.submitted_at', '<=', $v))
            ->with(['designator' => fn ($q) => $q->withTrashed(), 'reservation.lop']);

        $this->scopeForUser($query, $user, 'l.branch', [UserRole::SUPER_ADMIN, UserRole::MANAGER]);
        $this->applyLocationFilter($query, $filters['region'], $filters['branch'], 'l.branch');

        return $query;
    }

    /** @return array<int, float> designator_id => price */
    private function priceMap(?int $packageId): array
    {
        if ($packageId === null) {
            return [];
        }

        return DesignatorPackagePrice::query()
            ->where('package_id', $packageId)
            ->pluck('price', 'designator_id')
            ->map(fn ($p) => (float) $p)
            ->all();
    }

    /**
     * @param  array<int, float>  $priceMap
     * @return array<string, mixed>
     */
    private function foldLine(QeMaterialReservationItem $item, array $priceMap, bool $priced): array
    {
        $lop = $item->reservation->lop;
        $qty = (float) $item->qty;
        $qtyActual = $item->qty_actual === null ? null : (float) $item->qty_actual;
        $sisa = $item->sisa();
        $price = $priceMap[$item->designator_id] ?? null;

        return [
            'lop_id' => $lop->id_qe_lops,
            'lop_name' => $lop->nama_lop,
            'lop_incident' => $lop->incident,
            'lop_branch' => $lop->branch,
            'lop_program' => $lop->program_type?->label(),
            'lop_segment' => $lop->segment?->label(),
            'lop_status' => $lop->status_lop?->label(),
            'lop_status_raw' => $lop->status_lop?->value,
            'designator_id' => $item->designator_id,
            'designator_code' => $item->designator?->code ?? '—',
            'designator_name' => $item->designator?->item_name,
            'unit' => $item->designator?->unit,
            'qty' => $qty,
            'qty_actual' => $qtyActual,
            'sisa' => $sisa,
            'price' => $price,
            'total_actual' => ($qtyActual !== null && $price !== null) ? $qtyActual * $price : null,
            'nilai_sisa' => ($sisa !== null && $price !== null) ? $sisa * $price : null,
            'price_missing' => $priced && $price === null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function sumRows(Collection $rows, bool $priced): array
    {
        return [
            'qty' => (float) $rows->sum('qty'),
            'qty_actual' => (float) $rows->sum(fn ($r) => $r['qty_actual'] ?? 0),
            'sisa' => (float) $rows->sum(fn ($r) => $r['sisa'] ?? 0),
            'total_actual' => $priced ? (float) $rows->sum(fn ($r) => $r['total_actual'] ?? 0) : null,
            'nilai_sisa' => $priced ? (float) $rows->sum(fn ($r) => $r['nilai_sisa'] ?? 0) : null,
            'price_missing_count' => $rows->where('price_missing', true)->pluck('designator_id')->unique()->count(),
        ];
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @return LengthAwarePaginator<T>|array<int, T>
     */
    private function paginateOrAll(Collection $items, ?int $perPage)
    {
        if ($perPage === null) {
            return $items->all();
        }

        $page = Paginator::resolveCurrentPage('page');

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page'],
        );

        return $paginator->withQueryString();
    }

    /** @return Collection<int, mixed> */
    private function asCollection($groupsOrRows): Collection
    {
        if ($groupsOrRows instanceof LengthAwarePaginator) {
            return collect($groupsOrRows->items());
        }

        return collect($groupsOrRows);
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    // ---- flat row builders (shared by CSV + xlsx) ----

    /** @return array<string, mixed> */
    private function rekapRow(array $r, string $report, bool $priced): array
    {
        $row = [
            'designator_code' => $r['designator_code'],
            'designator_name' => $r['designator_name'],
            'unit' => $r['unit'],
            'qty' => $r['qty'],
            'qty_actual' => $r['qty_actual'],
            'sisa' => $r['sisa'],
            'lop_count' => $r['lop_count'],
        ];

        if ($priced) {
            $row['price'] = $r['price_missing'] ? '' : $r['price'];
            $key = $report === 'boq' ? 'total_actual' : 'nilai_sisa';
            $row[$key] = $r['price_missing'] ? '' : $r[$key];
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function rekapTotalRow(array $grand, string $report, bool $priced): array
    {
        $row = [
            'designator_code' => 'TOTAL',
            'designator_name' => '',
            'unit' => '',
            'qty' => $grand['qty'],
            'qty_actual' => $grand['qty_actual'],
            'sisa' => $grand['sisa'],
            'lop_count' => $grand['lop_count'],
        ];

        if ($priced) {
            $row['price'] = '';
            $row[$report === 'boq' ? 'total_actual' : 'nilai_sisa'] = $report === 'boq' ? $grand['total_actual'] : $grand['nilai_sisa'];
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function perLopRow(array $group, array $line, string $report, bool $priced): array
    {
        $row = [
            'lop' => $group['lop']['name'],
            'incident' => $group['lop']['incident'],
            'branch' => $group['lop']['branch'],
            'designator_code' => $line['designator_code'],
            'designator_name' => $line['designator_name'],
            'unit' => $line['unit'],
            'qty' => $line['qty'],
            'qty_actual' => $line['qty_actual'],
        ];

        if ($report === 'sisa') {
            $row['sisa'] = $line['sisa'];
        }

        if ($priced) {
            $row['price'] = $line['price_missing'] ? '' : $line['price'];
            $key = $report === 'boq' ? 'total_actual' : 'nilai_sisa';
            $row[$key] = $line['price_missing'] ? '' : $line[$key];
            $row['keterangan'] = $line['price_missing'] ? 'harga belum diset' : '';
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function perLopSubtotalRow(array $group, string $report, bool $priced): array
    {
        $sub = $group['subtotal'];

        $row = [
            'lop' => 'Subtotal — '.$group['lop']['name'],
            'incident' => '',
            'branch' => '',
            'designator_code' => '',
            'designator_name' => '',
            'unit' => '',
            'qty' => $sub['qty'],
            'qty_actual' => $sub['qty_actual'],
        ];

        if ($report === 'sisa') {
            $row['sisa'] = $sub['sisa'];
        }

        if ($priced) {
            $row['price'] = '';
            $row[$report === 'boq' ? 'total_actual' : 'nilai_sisa'] = $report === 'boq' ? $sub['total_actual'] : $sub['nilai_sisa'];
            $row['keterangan'] = $sub['price_missing_count'] > 0 ? $sub['price_missing_count'].' designator belum ada harga' : '';
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function perLopGrandRow(array $grand, string $report, bool $priced): array
    {
        $row = [
            'lop' => 'TOTAL KESELURUHAN',
            'incident' => '',
            'branch' => '',
            'designator_code' => '',
            'designator_name' => '',
            'unit' => '',
            'qty' => $grand['qty'],
            'qty_actual' => $grand['qty_actual'],
        ];

        if ($report === 'sisa') {
            $row['sisa'] = $grand['sisa'];
        }

        if ($priced) {
            $row['price'] = '';
            $row[$report === 'boq' ? 'total_actual' : 'nilai_sisa'] = $report === 'boq' ? $grand['total_actual'] : $grand['nilai_sisa'];
            $row['keterangan'] = '';
        }

        return $row;
    }
}
