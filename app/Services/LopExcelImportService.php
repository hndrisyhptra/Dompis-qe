<?php

namespace App\Services;

use App\Enums\ProgramType;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeLop;
use App\Models\QeMaterialReservation;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Import LOP via Excel (header-based, bukan fixed index).
 *
 * Format file (lihat plans/3DPR_QERELOK_SAU_TAMBAHAN SJUT SANUR.xlsx):
 * - R1-R4 meta: PROJECT : <nama_lop>, STO : <workzone>
 * - R6 header: NO | DESIGNATOR | URAIAN PEKERJAAN | SATUAN |
 *   HARGA SATUAN (PAKET-{n}) | VOL | TOTAL HARGA | KETERANGAN
 * - R7 sub-header: MATERIAL | JASA (di bawah HARGA SATUAN dan TOTAL)
 * - R9+ baris data: DESIGNATOR prefix M (material, harga kolom E)
 *   atau J (jasa, harga kolom F); VOL (G) kosong/0 = tidak dipakai.
 * - H=E*G, I=F*G, J=SUM(H:I) — dihitung ulang, tidak dibaca mentah.
 *
 * Kontrak seperti DesignatorImportService: validasi SEMUA baris dulu,
 * kalau ada satu error maka TIDAK ADA yang disimpan (all-or-nothing),
 * errors dikembalikan per baris "Baris N: ...".
 */
class LopExcelImportService
{
    public function __construct(
        private readonly LopService $lopService,
        private readonly ManualIncidentService $manualIncident,
        private readonly BoqService $boqService,
        private readonly LopVisibilityService $visibility,
    ) {}

    /**
     * Baca file mentah: meta + baris designator (belum divalidasi bisnis).
     *
     * @return array{meta: array{project: string, sto: string, package_detected: ?string}, rows: array<int, array{line: int, designator: string, uraian: string, satuan: string, harga_material: mixed, harga_jasa: mixed, vol: mixed}>, parseErrors: array<int, string>}
     */
    public function parse(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (Throwable) {
            return ['meta' => ['project' => '', 'sto' => '', 'package_detected' => null], 'rows' => [], 'parseErrors' => ['File tidak bisa dibaca sebagai Excel.']];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        // --- Meta R1-R10: cari "PROJECT : ..." dan "STO : ..." ---
        $project = '';
        $sto = '';
        for ($r = 1; $r <= min(10, $highestRow); $r++) {
            $cell = trim((string) $sheet->getCell("A{$r}")->getValue());
            if (preg_match('/^project\s*:(.*)$/i', $cell, $m)) {
                $project = trim($m[1]);
            }
            if (preg_match('/^sto\s*:(.*)$/i', $cell, $m)) {
                $sto = mb_strtoupper(trim($m[1]));
            }
        }

        // --- Header: cari baris yang memuat "DESIGNATOR" ---
        $headerRow = null;
        $packageDetected = null;
        for ($r = 1; $r <= min(15, $highestRow); $r++) {
            $found = false;
            foreach (range('A', 'L') as $c) {
                $v = strtolower(trim((string) $sheet->getCell("{$c}{$r}")->getValue()));
                if ($v === 'designator') {
                    $found = true;
                }
                if (preg_match('/paket-?\s*(\w+)/i', (string) $sheet->getCell("{$c}{$r}")->getValue(), $pm)) {
                    $packageDetected = mb_strtoupper(trim($pm[1]));
                }
            }
            if ($found) {
                $headerRow = $r;

                break;
            }
        }

        if ($headerRow === null) {
            return ['meta' => ['project' => $project, 'sto' => $sto, 'package_detected' => $packageDetected], 'rows' => [], 'parseErrors' => ['Header DESIGNATOR tidak ditemukan.']];
        }

        // --- Petakan kolom by header (case-insensitive) ---
        $colMap = [];
        foreach (range('A', 'L') as $c) {
            $h = strtolower(trim((string) $sheet->getCell("{$c}{$headerRow}")->getValue()));
            $colMap[$h] = $c;
        }

        $colNo = $colMap['no'] ?? 'A';
        $colDes = $colMap['designator'] ?? 'B';
        $colUraian = null;
        $colSatuan = null;
        $colVol = null;
        foreach ($colMap as $h => $c) {
            if (str_contains($h, 'uraian')) {
                $colUraian = $c;
            }
            if (str_contains($h, 'satuan')) {
                $colSatuan = $c;
            }
            if ($h === 'vol' || str_contains($h, 'volume')) {
                $colVol = $c;
            }
        }

        // Kolom harga MATERIAL/JASA dari sub-header (baris header+1):
        // E = MATERIAL, F = JASA pada template standar.
        $colHargaM = null;
        $colHargaJ = null;
        foreach (range('A', 'L') as $c) {
            $sub = strtolower(trim((string) $sheet->getCell("{$c}".($headerRow + 1))->getValue()));
            if ($sub === 'material' && $colHargaM === null) {
                // MATERIAL pertama = harga material; yang kedua (di TOTAL) diabaikan
                $colHargaM = $c;
            }
            if ($sub === 'jasa' && $colHargaJ === null) {
                $colHargaJ = $c;
            }
        }
        // Fallback template standar bila sub-header tidak terbaca
        $colHargaM ??= 'E';
        $colHargaJ ??= 'F';
        $colUraian ??= 'C';
        $colSatuan ??= 'D';
        $colVol ??= 'G';

        // --- Baris data: DESIGNATOR diawali M-/J- ---
        $rows = [];
        for ($r = $headerRow + 2; $r <= $highestRow; $r++) {
            $designator = trim((string) $sheet->getCell("{$colDes}{$r}")->getCalculatedValue());

            if ($designator === '' || ! preg_match('/^[MJ]-/i', $designator)) {
                continue;
            }

            $rows[] = [
                'line' => $r,
                'no' => trim((string) $sheet->getCell("{$colNo}{$r}")->getCalculatedValue()),
                'designator' => $designator,
                'uraian' => trim((string) $sheet->getCell("{$colUraian}{$r}")->getCalculatedValue()),
                'satuan' => trim((string) $sheet->getCell("{$colSatuan}{$r}")->getCalculatedValue()),
                'harga_material' => $sheet->getCell("{$colHargaM}{$r}")->getCalculatedValue(),
                'harga_jasa' => $sheet->getCell("{$colHargaJ}{$r}")->getCalculatedValue(),
                'vol' => $sheet->getCell("{$colVol}{$r}")->getCalculatedValue(),
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return [
            'meta' => ['project' => $project, 'sto' => $sto, 'package_detected' => $packageDetected],
            'rows' => $rows,
            'parseErrors' => $rows === [] ? ['Tidak ada baris designator (M-/J-) yang terbaca.'] : [],
        ];
    }

    /**
     * Klasifikasi baris + default LOP untuk preview (tanpa simpan DB).
     *
     * @param  array  $overrides  program_type, segment, budget_type, package_code, area, branch, sto, incident, nama_lop, job_description
     * @return array{lop: array, package: array{detected: ?string, exists: bool, options: array<int, string>}, rows: array, errors: array<int, string>, warnings: array<int, string>, totals: array{material_count: int, material_total: float, jasa_count: int, jasa_total: float, grand_total: float, used_count: int, skipped_count: int}}
     */
    public function preview(array $parsed, array $overrides = []): array
    {
        $errors = $parsed['parseErrors'];
        $warnings = [];

        $project = trim((string) ($overrides['nama_lop'] ?? $parsed['meta']['project'] ?? ''));
        $sto = mb_strtoupper(trim((string) ($overrides['sto'] ?? $parsed['meta']['sto'] ?? '')));

        if ($project === '') {
            $errors[] = 'PROJECT (nama LOP) kosong di file.';
        }

        // Branch via service_areas.workzone
        $branchName = trim((string) ($overrides['branch'] ?? ''));
        $serviceArea = $sto !== '' ? ServiceArea::where('workzone', $sto)->with('branch')->first() : null;
        if ($branchName === '' && $serviceArea?->branch) {
            $branchName = $serviceArea->branch->name;
        }
        if ($sto !== '' && ! $serviceArea) {
            $warnings[] = "STO {$sto} belum terdaftar di Master Service Area — pilih Branch manual.";
        }

        // Area: angka depan nama LOP, fallback 3
        $area = trim((string) ($overrides['area'] ?? ''));
        if ($area === '' && preg_match('/^(\d+)/', $project, $m)) {
            $area = $m[1];
        }
        if ($area === '') {
            $area = '3';
            $warnings[] = 'Area tidak terdeteksi dari nama LOP — dipakai Area 3.';
        }

        // Program default: RELOK bila nama mengandung RELOK
        $program = $overrides['program_type'] ?? (stripos($project, 'RELOK') !== false ? ProgramType::RELOK_UTILITAS->value : ProgramType::RECOVERY->value);

        // Paket: deteksi header vs existing
        $detected = $parsed['meta']['package_detected'];
        $packageCodes = Package::query()->orderBy('code')->pluck('code')->all();
        $chosen = $overrides['package_code'] ?? ($detected && in_array($detected, $packageCodes, true) ? $detected : ($packageCodes[0] ?? null));
        $packageExists = $detected !== null && in_array($detected, $packageCodes, true);
        if ($detected !== null && ! $packageExists) {
            $warnings[] = "Paket di Excel: {$detected} — belum ada di database (tersedia: ".($packageCodes ? implode(', ', $packageCodes) : 'belum ada').'). Harga dipakai dari kolom Excel sebagai snapshot.';
        }

        $designatorIndex = Designator::query()->pluck('id_designator', 'code')->all();

        $rows = [];
        $materialTotal = 0.0;
        $jasaTotal = 0.0;
        $materialCount = 0;
        $jasaCount = 0;
        $usedCount = 0;
        $skippedCount = 0;

        foreach ($parsed['rows'] as $row) {
            $rowErrors = [];
            $code = trim($row['designator']);
            $prefix = mb_strtoupper(substr($code, 0, 1));
            $type = $prefix === 'M' ? 'MATERIAL' : 'JASA';

            $priceRaw = $prefix === 'M' ? $row['harga_material'] : $row['harga_jasa'];
            $price = $this->normalizeNumber($priceRaw);
            $vol = $this->normalizeNumber($row['vol'], true);

            if ($price === null) {
                $rowErrors[] = "harga '{$row['harga_material']}/{$row['harga_jasa']}' tidak valid";
            }

            $status = 'ok';
            if ($vol === null || $vol <= 0) {
                // VOL kosong/0 = tidak dipakai (bukan error)
                $status = 'skipped';
                $skippedCount++;
            }

            $exists = isset($designatorIndex[$code]);
            if (! $exists && ($row['uraian'] === '' || $row['satuan'] === '')) {
                $rowErrors[] = 'uraian/satuan kosong — dibutuhkan untuk membuat designator baru';
            }

            if ($rowErrors) {
                $errors[] = "Baris {$row['line']}: ".implode(', ', $rowErrors);
                $status = 'error';
            }

            $total = ($status === 'ok' && $price !== null) ? $vol * $price : 0.0;

            if ($status === 'ok') {
                $usedCount++;
                if ($type === 'MATERIAL') {
                    $materialCount++;
                    $materialTotal += $total;
                } else {
                    $jasaCount++;
                    $jasaTotal += $total;
                }
            }

            $rows[] = [
                'line' => $row['line'],
                'no' => $row['no'],
                'designator' => $code,
                'type' => $type,
                'uraian' => $row['uraian'],
                'satuan' => $row['satuan'],
                'harga' => $price ?? 0.0,
                'vol' => $status === 'skipped' ? null : $vol,
                'total' => $total,
                'status' => $status,
                'exists' => $exists,
                'messages' => $rowErrors,
            ];
        }

        if ($usedCount === 0 && empty($errors)) {
            $errors[] = 'Tidak ada designator dengan VOL terisi — isi minimal 1 VOL.';
        }

        return [
            'lop' => [
                'nama_lop' => $project,
                'project' => $project,
                'sto' => $sto,
                'branch' => $branchName,
                'area' => $area,
                'program_type' => $program,
                'incident' => mb_strtoupper(trim((string) ($overrides['incident'] ?? ''))),
                'job_description' => trim((string) ($overrides['job_description'] ?? $project)),
            ],
            'package' => ['detected' => $detected, 'exists' => $packageExists, 'options' => $packageCodes, 'chosen' => $chosen],
            'rows' => $rows,
            'errors' => array_values($errors),
            'warnings' => array_values($warnings),
            'totals' => [
                'material_count' => $materialCount,
                'material_total' => $materialTotal,
                'jasa_count' => $jasaCount,
                'jasa_total' => $jasaTotal,
                'grand_total' => $materialTotal + $jasaTotal,
                'used_count' => $usedCount,
                'skipped_count' => $skippedCount,
            ],
        ];
    }

    /**
     * Simpan hasil preview yang sudah divalidasi. ALL-OR-NOTHING.
     *
     * @param  array  $payload  lop + rows + package dari preview/store (sudah final dari user)
     */
    public function import(array $payload, User $actor): QeLop
    {
        return DB::transaction(function () use ($payload, $actor) {
            $lopData = $payload['lop'];
            $rows = $payload['rows'];
            $packageCode = $payload['package']['chosen'] ?? null;
            $serviceArea = ServiceArea::query()->with('branch')->where('workzone', $lopData['sto'])->firstOrFail();
            $branchModel = $serviceArea->branch;
            if ($branchModel === null || ! $this->visibility->canUseLocation($actor, $branchModel->id_branch, $serviceArea->id_service_area)) {
                throw new \InvalidArgumentException('Lokasi LOP berada di luar scope admin.');
            }

            // 1. Auto-create designator yang belum ada (upsert by code)
            $typeIds = [
                'MATERIAL' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
                'JASA' => DesignatorType::where('code', 'JASA')->value('id_designator_type'),
            ];
            $now = now();
            $newDesignators = [];
            foreach ($rows as $row) {
                if ($row['status'] !== 'ok' || $row['exists']) {
                    continue;
                }
                $newDesignators[$row['designator']] = [
                    'code' => $row['designator'],
                    'item_name' => $row['uraian'] !== '' ? $row['uraian'] : $row['designator'],
                    'unit' => $row['satuan'] !== '' ? $row['satuan'] : 'unit',
                    'designator_type_id' => $typeIds[$row['type']] ?? null,
                    'designator_category_id' => null,
                    'created_by' => $actor->id_user,
                    'updated_by' => $actor->id_user,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($newDesignators) {
                Designator::withTrashed()->upsert(
                    array_values($newDesignators),
                    ['code'],
                    ['item_name', 'unit', 'designator_type_id', 'updated_by', 'deleted_at', 'updated_at']
                );
            }

            $designatorIds = Designator::query()->pluck('id_designator', 'code')->all();

            // 2. Paket
            $package = $packageCode ? Package::where('code', $packageCode)->first() : null;

            // 3. Incident: generate INP bila kosong
            $incident = mb_strtoupper(trim((string) ($lopData['incident'] ?? '')));
            if ($incident === '') {
                $incident = $this->manualIncident->generate($branchModel, ProgramType::from($lopData['program_type']));
            }

            // 4. LOP via LopService (reuse penamaan + histori + normalisasi segmen)
            $lop = $this->lopService->create([
                'incident' => $incident,
                'nama_lop' => $lopData['nama_lop'],
                'program_type' => $lopData['program_type'],
                'sto' => $lopData['sto'],
                'branch' => $lopData['branch'],
                'branch_id' => $branchModel->id_branch,
                'service_area_id' => $serviceArea->id_service_area,
                'area' => $lopData['area'],
                'segment' => $lopData['segment'] ?? [],
                'budget_type' => $lopData['budget_type'] ?? null,
                'job_description' => $lopData['job_description'],
                'ticket_summary' => null,
                'datek' => null,
                'ihld_id' => null,
                'package_id' => $package?->id_package,
            ], $actor);

            // 5. Reservasi draft — MATERIAL saja (guard seperti teknisi)
            $materialItems = [];
            foreach ($rows as $row) {
                if ($row['status'] !== 'ok' || $row['type'] !== 'MATERIAL') {
                    continue;
                }
                $id = $designatorIds[$row['designator']] ?? null;
                if ($id === null) {
                    continue;
                }
                $materialItems[] = ['designator_id' => $id, 'qty' => $row['vol']];
            }

            if ($materialItems) {
                $reservation = QeMaterialReservation::updateOrCreate(
                    ['qe_lop_id' => $lop->id_qe_lops],
                    ['technician_id' => null, 'status' => 'draft']
                );
                $reservation->items()->delete();
                $reservation->items()->createMany($materialItems);
            }

            // 6. Snapshot BOQ M+J (harga dari Excel)
            $snapshotRows = array_map(fn ($row) => [
                'designator' => $row['designator'],
                'type' => $row['type'],
                'uraian' => $row['uraian'],
                'satuan' => $row['satuan'],
                'harga' => $row['harga'],
                'vol' => $row['vol'],
                'total' => $row['total'],
            ], array_values(array_filter($rows, fn ($row) => $row['status'] === 'ok')));

            $lop->update([
                'boq_snapshot' => [
                    'package_detected' => $payload['package']['detected'] ?? null,
                    'package_used' => $package?->code,
                    'imported_at' => now()->toDateTimeString(),
                    'imported_by' => $actor->id_user,
                    'material' => [
                        'count' => $payload['totals']['material_count'] ?? 0,
                        'total' => $payload['totals']['material_total'] ?? 0,
                    ],
                    'jasa' => [
                        'count' => $payload['totals']['jasa_count'] ?? 0,
                        'total' => $payload['totals']['jasa_total'] ?? 0,
                    ],
                    'grand_total' => $payload['totals']['grand_total'] ?? 0,
                    'rows' => $snapshotRows,
                ],
            ]);

            // Sinkronkan format import lama ke tabel BOQ terstruktur supaya
            // langsung tersedia di menu Data BOQ dan assignment berikutnya.
            $boqItems = collect($rows)
                ->where('status', 'ok')
                ->map(fn ($row) => [
                    'designator_id' => $designatorIds[$row['designator']],
                    'qty' => $row['vol'],
                    'unit_price' => $row['harga'],
                ])
                ->values()
                ->all();

            if ($boqItems !== []) {
                $this->boqService->save($lop, $boqItems, $package, $actor, 'legacy_import');

                $syncedSnapshot = $lop->refresh()->boq_snapshot;
                $syncedSnapshot['package_detected'] = $payload['package']['detected'] ?? null;
                $lop->update(['boq_snapshot' => $syncedSnapshot]);
            }

            return $lop->refresh();
        });
    }

    /**
     * Normalisasi angka Indonesia (1.468.800 / 1.468.800,50) maupun plain.
     * $allowEmpty: '' / null => null (untuk VOL = skip, bukan error).
     */
    private function normalizeNumber(mixed $raw, bool $allowEmpty = false): ?float
    {
        if ($raw === null) {
            return $allowEmpty ? null : 0.0;
        }

        $s = trim((string) $raw);

        if ($s === '') {
            return $allowEmpty ? null : 0.0;
        }

        // Bersihkan spasi / NBSP / Rp
        $s = str_replace(["\u{00A0}", ' ', 'Rp', 'rp', 'RP'], '', $s);

        if (! preg_match('/^[\d.,-]+$/', $s)) {
            return null;
        }

        // Format Indonesia: titik = ribuan, koma = desimal
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            // Tanpa koma: titik dianggap ribuan bila >1 titik atau 3 digit di belakang
            if (substr_count($s, '.') > 1 || preg_match('/\.\d{3}$/', $s)) {
                $s = str_replace('.', '', $s);
            }
        }

        return is_numeric($s) ? (float) $s : null;
    }

    public function areas(): array
    {
        return Area::query()->where('is_active', true)->orderBy('code')->get(['code', 'name'])->all();
    }
}
