<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use RuntimeException;
use Throwable;

class BoqImportService
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly BoqService $boqService,
        private readonly LopVisibilityService $visibility,
        private readonly ImportRowWriter $rowWriter,
    ) {}

    public function process(QeImportBatch $batch, User $actor): void
    {
        $batch->update(['status' => 'processing', 'started_at' => now(), 'error_message' => null]);

        $parsed = $this->parse(Storage::disk($batch->disk)->path($batch->file_path));
        $rows = $parsed['rows'];

        // Simpan hasil pembacaan file lebih awal agar tetap tampil pada halaman
        // hasil ketika pencocokan LOP atau Paket KHS gagal.
        $batch->update([
            'total_rows' => count($rows),
            'metadata' => [
                ...($batch->metadata ?? []),
                'project_name' => $parsed['meta']['project'],
                'package_detected' => $parsed['meta']['package_label'],
                'package_source_code' => $parsed['meta']['package_code'],
            ],
        ]);

        $lop = $this->resolveLop($parsed['meta']['project'], $actor);
        $package = $this->resolvePackage($parsed['meta']['package_code']);

        $batch->update([
            'metadata' => [
                ...($batch->metadata ?? []),
                'qe_lop_id' => $lop->id_qe_lops,
                'lop_incident' => $lop->incident,
                'lop_name' => $lop->nama_lop,
                'package_id' => $package->id_package,
                'package_code' => $package->code,
            ],
        ]);

        $usedCodes = collect($rows)
            ->filter(fn (array $row) => ($this->normalizeNumber($row['qty']) ?? 0) > 0)
            ->pluck('designator')
            ->values();
        $designators = Designator::withTrashed()
            ->whereIn('code', $usedCodes)
            ->get()
            ->keyBy('code');
        $seen = [];
        $errors = [];
        $normalizedRows = [];
        $totals = [
            'material_count' => 0,
            'material_total' => 0.0,
            'service_count' => 0,
            'service_total' => 0.0,
            'used_count' => 0,
            'skipped_count' => 0,
        ];

        $rowCount = count($rows);
        foreach ($rows as $index => $row) {
            $messages = [];
            $code = $row['designator'];
            $qty = $this->normalizeNumber($row['qty']);
            $unitPrice = $this->normalizeNumber($row['unit_price'], false, 0.0);
            $rawQty = trim((string) ($row['qty'] ?? ''));
            $status = 'ready';

            if ($rawQty === '' || ($qty !== null && $qty <= 0)) {
                $status = 'skipped';
                $totals['skipped_count']++;
            } else {
                if ($qty === null) {
                    $messages[] = 'VOL harus berupa angka.';
                } elseif (floor($qty) !== $qty) {
                    $messages[] = 'VOL harus berupa angka bulat tanpa desimal.';
                }
                if ($unitPrice === null || $unitPrice < 0) {
                    $messages[] = 'Harga satuan tidak valid.';
                }
                if (isset($seen[$code])) {
                    $messages[] = "Designator {$code} duplikat dengan baris {$seen[$code]}.";
                }
                if (! $designators->has($code) && ($row['item_name'] === '' || $row['unit'] === '')) {
                    $messages[] = 'Uraian pekerjaan dan satuan wajib diisi untuk designator baru.';
                }
                $seen[$code] ??= $row['row_number'];
            }

            if ($messages !== []) {
                $errors[$row['row_number']] = implode(' ', $messages);
                $status = 'error';
            }

            $total = $status === 'ready' ? round((float) $qty * (float) $unitPrice, 2) : 0.0;
            if ($status === 'ready') {
                $totals['used_count']++;
                if ($row['type'] === 'MATERIAL') {
                    $totals['material_count']++;
                    $totals['material_total'] += $total;
                } else {
                    $totals['service_count']++;
                    $totals['service_total'] += $total;
                }
            }

            $normalizedRows[] = [
                ...$row,
                'qty' => $status === 'skipped' ? null : $qty,
                'unit_price' => $unitPrice ?? 0.0,
                'total_price' => $total,
                'status' => $status,
                'exists' => $designators->has($code) && ! $designators->get($code)->trashed(),
                'message' => $errors[$row['row_number']] ?? null,
            ];

            $processedRows = $index + 1;
            if ($processedRows % 25 === 0 || $processedRows === $rowCount) {
                $batch->update([
                    'metadata' => [
                        ...($batch->metadata ?? []),
                        'processed_rows' => $processedRows,
                    ],
                ]);
            }
        }

        $totals['grand_total'] = $totals['material_total'] + $totals['service_total'];
        $batch->update([
            'metadata' => [
                ...($batch->metadata ?? []),
                ...$totals,
            ],
        ]);

        if ($totals['used_count'] === 0 && $errors === []) {
            throw new RuntimeException('Tidak ada designator dengan VOL lebih dari 0 pada file BOQ.');
        }

        if ($errors !== []) {
            $resultRows = [];
            foreach ($normalizedRows as $row) {
                $isSkipped = $row['status'] === 'skipped';
                $resultRows[] = [
                    'import_batch_id' => $batch->id_import_batch,
                    'row_number' => $row['row_number'],
                    'status' => $isSkipped ? 'skipped' : 'failed',
                    'reference' => $row['designator'],
                    'message' => $isSkipped
                        ? 'VOL kosong atau 0 — item tidak dipakai.'
                        : ($errors[$row['row_number']] ?? 'Import dibatalkan karena terdapat kesalahan pada baris lain.'),
                    'payload' => $row,
                ];
            }
            $this->rowWriter->insert($batch, $resultRows);

            $batch->update([
                'failed_rows' => collect($normalizedRows)->whereIn('status', ['ready', 'error'])->count(),
                'status' => 'failed',
                'error_message' => 'BOQ tidak disimpan karena validasi file gagal.',
                'completed_at' => now(),
            ]);

            return;
        }

        DB::transaction(function () use ($normalizedRows, $actor, $lop, $package, $batch, $totals) {
            $this->createMissingDesignators($normalizedRows, $actor);
            $designators = Designator::query()
                ->whereIn('code', collect($normalizedRows)->where('status', 'ready')->pluck('designator'))
                ->get()
                ->keyBy('code');
            $valid = [];

            foreach ($normalizedRows as $row) {
                if ($row['status'] !== 'ready') {
                    continue;
                }

                $designator = $designators->get($row['designator']);
                if ($designator === null) {
                    throw new RuntimeException("Designator {$row['designator']} gagal dibuat atau ditemukan.");
                }

                $valid[] = [
                    'designator_id' => $designator->id_designator,
                    'qty' => $row['qty'],
                    'unit_price' => $row['unit_price'],
                ];
            }

            $boq = $this->boqService->save($lop, $valid, $package, $actor, 'import');

            $resultRows = [];
            foreach ($normalizedRows as $row) {
                $isSkipped = $row['status'] === 'skipped';
                $resultRows[] = [
                    'import_batch_id' => $batch->id_import_batch,
                    'row_number' => $row['row_number'],
                    'status' => $isSkipped ? 'skipped' : 'success',
                    'reference' => $row['designator'],
                    'message' => $isSkipped ? 'VOL kosong atau 0 — item tidak dipakai.' : 'Item BOQ berhasil disimpan.',
                    'payload' => $row,
                    'result' => $isSkipped ? null : ['boq_id' => $boq->id_boq],
                ];
            }
            $this->rowWriter->insert($batch, $resultRows);

            $batch->update([
                'success_rows' => $totals['used_count'],
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * @return array{
     *     meta: array{project:string,package_code:?string,package_label:?string},
     *     rows: array<int, array{row_number:int,designator:string,item_name:string,unit:string,type:string,qty:mixed,unit_price:mixed}>
     * }
     */
    public function parse(string $path): array
    {
        try {
            $spreadsheet = $this->reader->load($path);
        } catch (Throwable $e) {
            throw new RuntimeException('File BOQ tidak dapat dibaca.', previous: $e);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumnIndex = min(30, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        $headerRow = null;
        $columns = [];
        $project = '';
        $packageCode = null;
        $packageLabel = null;

        for ($row = 1; $row <= min(20, $highestRow); $row++) {
            $candidate = [];
            for ($index = 1; $index <= $highestColumnIndex; $index++) {
                $column = Coordinate::stringFromColumnIndex($index);
                $rawValue = trim((string) $sheet->getCell("{$column}{$row}")->getCalculatedValue());
                $key = $this->reader->normalizeHeader($rawValue);
                if ($key !== '') {
                    $candidate[$key] = $column;
                }

                if ($project === '' && preg_match('/^(?:project|nama\s*lop)\s*:\s*(.+)$/i', $rawValue, $match)) {
                    $project = trim($match[1]);
                }

                if ($project === '' && preg_match('/^(?:project|nama\s*lop)\s*:?$/i', $rawValue)) {
                    for ($projectIndex = $index + 1; $projectIndex <= $highestColumnIndex; $projectIndex++) {
                        $projectColumn = Coordinate::stringFromColumnIndex($projectIndex);
                        $projectCandidate = trim((string) $sheet->getCell("{$projectColumn}{$row}")->getCalculatedValue());
                        if ($projectCandidate !== '' && $projectCandidate !== ':') {
                            $project = $projectCandidate;

                            break;
                        }
                    }
                }

                if ($packageCode === null && preg_match('/(paket|tif)[\s:_-]*(\d+)\b/i', $rawValue, $match)) {
                    $packageCode = $match[2];
                    $packageLabel = mb_strtoupper($match[1]).'-'.$match[2];
                }
            }

            if (isset($candidate['designator'], $candidate['qty'])) {
                $headerRow = $row;
                $columns = $candidate;
                break;
            }
        }

        if ($headerRow === null) {
            $spreadsheet->disconnectWorksheets();
            throw new RuntimeException('Header DESIGNATOR dan QTY/VOL tidak ditemukan.');
        }

        if ($project === '') {
            $spreadsheet->disconnectWorksheets();
            throw new RuntimeException('Nama PROJECT/Nama LOP tidak ditemukan pada file BOQ.');
        }

        if ($packageCode === null) {
            $spreadsheet->disconnectWorksheets();
            throw new RuntimeException('Informasi TIF/Paket tidak ditemukan pada header HARGA SATUAN file BOQ.');
        }

        $flatPriceColumn = $columns['unit_price'] ?? null;
        $materialPriceColumn = null;
        $servicePriceColumn = null;
        $dataStart = $headerRow + 1;

        if ($flatPriceColumn === null) {
            $dataStart = $headerRow + 2;
            for ($index = 1; $index <= $highestColumnIndex; $index++) {
                $column = Coordinate::stringFromColumnIndex($index);
                $sub = strtolower(trim((string) $sheet->getCell($column.($headerRow + 1))->getValue()));
                if ($sub === 'material' && $materialPriceColumn === null) {
                    $materialPriceColumn = $column;
                }
                if ($sub === 'jasa' && $servicePriceColumn === null) {
                    $servicePriceColumn = $column;
                }
            }
            $materialPriceColumn ??= 'E';
            $servicePriceColumn ??= 'F';
        }

        $rows = [];
        $itemNameColumn = $columns['job_description'] ?? 'C';
        $unitColumn = $columns['satuan'] ?? $columns['unit'] ?? 'D';
        for ($row = $dataStart; $row <= $highestRow; $row++) {
            $code = trim((string) $sheet->getCell($columns['designator'].$row)->getCalculatedValue());
            $qty = $sheet->getCell($columns['qty'].$row)->getCalculatedValue();

            if ($code === '' || ! preg_match('/^[MJ]-/i', $code)) {
                continue;
            }

            $price = $flatPriceColumn !== null
                ? $sheet->getCell($flatPriceColumn.$row)->getCalculatedValue()
                : $sheet->getCell((str_starts_with($code, 'J-') ? $servicePriceColumn : $materialPriceColumn).$row)->getCalculatedValue();

            $rows[] = [
                'row_number' => $row,
                'designator' => $code,
                'item_name' => trim((string) $sheet->getCell($itemNameColumn.$row)->getCalculatedValue()),
                'unit' => trim((string) $sheet->getCell($unitColumn.$row)->getCalculatedValue()),
                'type' => str_starts_with(mb_strtoupper($code), 'J-') ? 'JASA' : 'MATERIAL',
                'qty' => $qty,
                'unit_price' => $price,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        if ($rows === []) {
            throw new RuntimeException('Tidak ada item BOQ dengan qty lebih dari 0.');
        }

        return [
            'meta' => [
                'project' => $project,
                'package_code' => $packageCode,
                'package_label' => $packageLabel,
            ],
            'rows' => $rows,
        ];
    }

    private function resolveLop(string $project, User $actor): QeLop
    {
        $incidentMatch = QeLop::query()->where('incident', $project)->first();
        if ($incidentMatch !== null) {
            $this->ensureLopAccess($incidentMatch, $actor);

            return $incidentMatch;
        }

        $matches = QeLop::query()->where('nama_lop', $project)->limit(2)->get();
        if ($matches->isEmpty()) {
            throw new RuntimeException("LOP dengan nama PROJECT '{$project}' tidak ditemukan.");
        }
        if ($matches->count() > 1) {
            throw new RuntimeException("Nama PROJECT '{$project}' digunakan lebih dari satu LOP. Gunakan Nama LOP yang unik.");
        }

        $lop = $matches->first();
        $this->ensureLopAccess($lop, $actor);

        return $lop;
    }

    private function ensureLopAccess(QeLop $lop, User $actor): void
    {
        if (! $actor->hasRole(UserRole::ADMIN)) {
            return;
        }

        if (! $this->visibility->canAccess($actor, $lop)) {
            throw new RuntimeException('LOP hasil deteksi file berada di luar scope lokasi Admin yang login.');
        }
    }

    private function resolvePackage(?string $packageCode): Package
    {
        if ($packageCode === null) {
            throw new RuntimeException('Paket BOQ tidak berhasil dideteksi.');
        }

        $package = Package::query()
            ->whereIn('code', [
                $packageCode,
                "PAKET-{$packageCode}",
                "PAKET {$packageCode}",
                "TIF-{$packageCode}",
                "TIF {$packageCode}",
            ])
            ->first()
            ?? Package::query()->where(function ($query) use ($packageCode) {
                $query->where('name', 'like', "%Paket {$packageCode}%")
                    ->orWhere('name', 'like', "%TIF-{$packageCode}%")
                    ->orWhere('name', 'like', "%TIF {$packageCode}%");
            })->first();

        if ($package === null) {
            throw new RuntimeException("TIF/PAKET-{$packageCode} terdeteksi, tetapi belum tersedia pada Master Paket KHS.");
        }

        return $package;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function createMissingDesignators(array $rows, User $actor): void
    {
        $typeIds = DesignatorType::query()
            ->whereIn('code', ['MATERIAL', 'JASA'])
            ->pluck('id_designator_type', 'code');

        if (! $typeIds->has('MATERIAL') || ! $typeIds->has('JASA')) {
            throw new RuntimeException('Master Jenis Designator MATERIAL dan JASA belum lengkap.');
        }

        $now = now();
        $newDesignators = collect($rows)
            ->where('status', 'ready')
            ->where('exists', false)
            ->unique('designator')
            ->map(fn (array $row) => [
                'code' => $row['designator'],
                'item_name' => $row['item_name'],
                'unit' => $row['unit'],
                'designator_type_id' => $typeIds[$row['type']],
                'designator_category_id' => null,
                'created_by' => $actor->id_user,
                'updated_by' => $actor->id_user,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($newDesignators === []) {
            return;
        }

        Designator::withTrashed()->upsert(
            $newDesignators,
            ['code'],
            ['item_name', 'unit', 'designator_type_id', 'updated_by', 'deleted_at', 'updated_at']
        );
    }

    private function normalizeNumber(mixed $raw, bool $emptyAsNull = true, ?float $emptyDefault = null): ?float
    {
        if ($raw === null || trim((string) $raw) === '') {
            return $emptyAsNull ? null : $emptyDefault;
        }

        $value = str_replace(["\u{00A0}", ' ', 'Rp', 'rp', 'RP'], '', trim((string) $raw));
        if (! preg_match('/^[\d.,-]+$/', $value)) {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1 || preg_match('/\.\d{3}$/', $value)) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
