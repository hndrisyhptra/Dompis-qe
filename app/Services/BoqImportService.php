<?php

namespace App\Services;

use App\Enums\LopStatus;
use App\Enums\UserRole;
use App\Enums\ProgramType;
use App\Enums\ProjectStatus;
use App\Enums\LopBudgetType;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Enums\LopSegment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use RuntimeException;
use Throwable;

class BoqImportService
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly BoqService $boqService,
        private readonly BoqPlanService $boqPlanService,
        private readonly LopVisibilityService $visibility,
        private readonly ImportRowWriter $rowWriter,
        private readonly \App\Services\LopService $lopService,
        private readonly \App\Services\ManualIncidentService $incidentService,
    ) {}

    public function process(QeImportBatch $batch, User $actor, string $target = 'actual'): void
    {
        $replaceExisting = (bool) ($batch->metadata['replace_existing'] ?? false);
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

        $lop = $this->resolveOrCreateLop(
            $parsed['meta']['project'],
            $parsed['meta']['lop_meta'] ?? null,
            $actor,
            $rows
        );
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

        // Check if LOP already has BOQ Plan or Actual
        $target = $batch->metadata['target'] ?? 'actual';
        $hasExistingPlan = $lop->boqPlan()->exists();
        $hasExistingActual = $lop->boq()->exists();
        $targetHasExisting = $target === 'plan' ? $hasExistingPlan : $hasExistingActual;

        $batch->update([
            'metadata' => [
                ...($batch->metadata ?? []),
                'lop_has_existing_boq' => $hasExistingPlan || $hasExistingActual,
                'existing_boq_type' => $hasExistingPlan ? 'plan' : ($hasExistingActual ? 'actual' : null),
                'target_type' => $target,
                'target_has_existing_boq' => $targetHasExisting,
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

        DB::transaction(function () use ($normalizedRows, $actor, $lop, $package, $batch, $totals, $target, $replaceExisting) {
            // Hanya import yangmentation diblokir bila LOP sudah punya BOQ dengan
            // tipe yang sama. BOQ Plan dan BOQ Actual boleh hidup berdampingan,
            // sehingga import berlawanan tipe tidak perlu "Replace existing".
            $targetHasExisting = (bool) ($batch->metadata['target_has_existing_boq'] ?? false);

            if ($targetHasExisting && ! $replaceExisting) {
                $targetType = $target === 'plan' ? 'BOQ Plan' : 'BOQ Actual';
                throw new RuntimeException(
                    "LOP {$lop->incident} sudah memiliki {$targetType}. " .
                    "Centang 'Replace existing' untuk menimpa, atau batalkan import."
                );
            }

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

            $reference = [];
            if ($target === 'plan') {
                $plan = $this->boqPlanService->save($lop, $valid, $actor);
                $reference = ['plan_id' => $plan->id_plan];
            } else {
                $boq = $this->boqService->save($lop, $valid, $package, $actor, 'import');
                $reference = ['boq_id' => $boq->id_boq];
            }

            $resultRows = [];
            foreach ($normalizedRows as $row) {
                $isSkipped = $row['status'] === 'skipped';
                $resultRows[] = [
                    'import_batch_id' => $batch->id_import_batch,
                    'row_number' => $row['row_number'],
                    'status' => $isSkipped ? 'skipped' : 'success',
                    'reference' => $row['designator'],
                    'message' => $isSkipped
                        ? 'VOL kosong atau 0 — item tidak dipakai.'
                        : ($target === 'plan' ? 'Item BOQ Plan berhasil disimpan.' : 'Item BOQ Actual berhasil disimpan.'),
                    'payload' => $row,
                    'result' => $isSkipped ? null : $reference,
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
        $lopMeta = [];

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

                // Detect LOP Meta fields
                if (preg_match('/^(segment|program_type|sto|branch|area|ihld_id|package_id|job_description)\s*:\s*(.+)$/i', $rawValue, $match)) {
                    $key = strtolower(trim($match[1]));
                    $val = trim($match[2]);
                    if ($key === 'segment') {
                        $lopMeta[$key] = array_map('trim', explode(',', $val));
                    } else {
                        $lopMeta[$key] = $val;
                    }
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

        // Auto-compute LOP Meta
        if ($project !== '' && empty($lopMeta['program_type'])) {
             $lopMeta['program_type'] = $this->detectProgramType($project);
        }

        // Fallback to parseProjectName if LOP meta incomplete (missing STO, segment, or incident)
        if (! empty($project)) {
            $projectParsed = $this->parseProjectName($project);
            if ($projectParsed !== null) {
                // Merge project-parsed data (header values take precedence)
                foreach ($projectParsed as $key => $value) {
                    if (! isset($lopMeta[$key]) || $lopMeta[$key] === null) {
                        $lopMeta[$key] = $value;
                    }
                }
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

        // Template BOQ operasional menaruh VOL pada baris designator induk
        // (contoh SC-OF-SM-24), sementara harga material/jasa berada pada
        // baris M-SC-OF-SM-24 dan J-SC-OF-SM-24 dengan VOL 0. Simpan volume
        // induk agar dapat diwariskan ke kedua baris detail tersebut.
        $parentQuantities = [];
        for ($row = $dataStart; $row <= $highestRow; $row++) {
            $code = trim((string) $sheet->getCell($columns['designator'].$row)->getCalculatedValue());
            if ($code === '' || preg_match('/^[MJ]-/i', $code)) {
                continue;
            }

            $qty = $sheet->getCell($columns['qty'].$row)->getCalculatedValue();
            if (($this->normalizeNumber($qty) ?? 0) > 0) {
                $parentQuantities[mb_strtoupper($code)] = $qty;
            }
        }

        for ($row = $dataStart; $row <= $highestRow; $row++) {
            $code = trim((string) $sheet->getCell($columns['designator'].$row)->getCalculatedValue());
            $qty = $sheet->getCell($columns['qty'].$row)->getCalculatedValue();

            if ($code === '' || ! preg_match('/^[MJ]-/i', $code)) {
                continue;
            }

            $parentCode = mb_strtoupper(substr($code, 2));
            if (($this->normalizeNumber($qty) ?? 0) <= 0 && isset($parentQuantities[$parentCode])) {
                $qty = $parentQuantities[$parentCode];
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

        // Extract first item_name for default job_description if not provided
        if (empty($lopMeta['job_description'])) {
            $firstItem = collect($rows)->first(fn ($r) => ($this->normalizeNumber($r['qty']) ?? 0) > 0);
            $lopMeta['job_description'] = $firstItem['item_name'] ?? "Pekerjaan BOQ {$project}";
        }

        return [
            'meta' => [
                'project' => $project,
                'package_code' => $packageCode,
                'package_label' => $packageLabel,
                'lop_meta' => $lopMeta,
                'sto' => $lopMeta['sto'] ?? null,
            ],
            'rows' => $rows,
        ];
    }

    private function resolveLop(string $project, User $actor): ?QeLop
    {
        $incidentMatch = QeLop::query()->where('incident', $project)->first();
        if ($incidentMatch !== null) {
            $this->ensureLopAccess($incidentMatch, $actor);

            return $incidentMatch;
        }

        $matches = QeLop::query()->where('nama_lop', $project)->limit(2)->get();
        if ($matches->isEmpty()) {
            return null;
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

    /**
     * Calculate grand total from parsed BOQ rows.
     */
    private function calculateGrandTotalFromParsed(array $parsedRows): float
    {
        return collect($parsedRows)
            ->where(fn ($r) => ($this->normalizeNumber($r['qty']) ?? 0) > 0)
            ->sum(fn ($r) => ($this->normalizeNumber($r['qty']) ?? 0) * ($this->normalizeNumber($r['unit_price'], false, 0.0) ?? 0.0));
    }

    /**
     * Resolve or create LOP from parsed BOQ data.
     * If LOP exists (by incident or nama_lop), return it.
     * If not, create a new LOP (draft) for admin verification.
     */
    public function resolveOrCreateLop(
        string $project,
        ?array $lopMeta,
        User $actor,
        array $parsedRows = []
    ): QeLop {
        // 1. Try to find existing LOP first (by incident or nama_lop)
        $existing = $this->resolveLop($project, $actor);
        if ($existing !== null) {
            return $existing;
        }

        // 2. If we have meta data, try to create LOP as draft
        if ($lopMeta !== null) {
            return $this->createLopFromMeta($project, $lopMeta, $actor, $parsedRows);
        }

        // 3. No LOP found and no meta data to create one
        throw new RuntimeException(
            "LOP dengan nama PROJECT '{$project}' tidak ditemukan. " .
            "Silakan buat LOP terlebih dahulu via menu Input LOP, " .
            "atau tambahkan field LOP (SEGMENT, PROGRAM_TYPE, dll) di file Excel."
        );
    }

    /**
     * Create LOP from parsed BOQ metadata as draft.
     */
    private function createLopFromMeta(string $project, array $lopMeta, User $actor, array $parsedRows = []): QeLop
    {
        // Validate required fields
        $this->validateLopMeta($lopMeta);

        // Check if incident already exists (if provided)
        if (isset($lopMeta['incident'])) {
            $existingLop = QeLop::where('incident', $lopMeta['incident'])->first();
            if ($existingLop !== null) {
                throw new RuntimeException("Incident {$lopMeta['incident']} sudah digunakan oleh LOP lain. Verifikasi incident atau biarkan kosong untuk auto-generate.");
            }
        }

        // Resolve location from STO
        $location = $this->resolveLocationFromMeta($lopMeta, $actor);

        // Generate unique incident
        $incident = $lopMeta['incident'] ?? $this->generateUniqueIncident(
            $location['branch'],
            ProgramType::from($lopMeta['program_type'])
        );

        // Calculate budget type for relok_utilitas
        $budgetType = $lopMeta['budget_type'] ?? null;
        if ($lopMeta['program_type'] === ProgramType::RELOK_UTILITAS->value) {
            $grandTotal = $this->calculateGrandTotalFromParsed($parsedRows);
            $budgetType = $grandTotal > 25000000 ? LopBudgetType::CAPEX : LopBudgetType::OPEX;
        }

        // Prepare LOP data (as draft)
        $lopData = [
            'incident' => $incident,
            'nama_lop' => $project,
            'program_type' => $lopMeta['program_type'],
            'sto' => $location['sto'],
            'branch' => $location['branch']->name,
            'branch_id' => $location['branch']->id_branch,
            'service_area_id' => $location['serviceArea']->id_service_area,
            'area' => $location['area'],
            'segment' => $lopMeta['segment'],
            'budget_type' => $budgetType?->value,
            'job_description' => $lopMeta['job_description'],
            'ihld_id' => $lopMeta['ihld_id'] ?? null,
            'package_id' => $lopMeta['package_id'] ?? null,
            'status_lop' => LopStatus::DRAFT->value,
        ];

        return $this->lopService->create($lopData, $actor, $location['branch'], $location['serviceArea']);
    }

    /**
     * Validate required LOP metadata from BOQ file.
     */
    private function validateLopMeta(array $meta): void
    {
        $rules = [
            'program_type' => ['required', Rule::enum(ProgramType::class)],
            'segment' => ['required', 'array', 'min:1', 'max:5'],
            'segment.*' => [Rule::enum(\App\Enums\LopSegment::class)],
            'sto' => ['required', 'string'],
            'job_description' => ['required', 'string', 'max:2000'],
        ];

        $validator = Validator::make($meta, $rules);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            throw new RuntimeException('Data LOP tidak lengkap: ' . implode(', ', $errors));
        }
    }

    /**
     * Resolve location (branch, service area, area) from metadata.
     */
    private function resolveLocationFromMeta(array $meta, User $actor): array
    {
        $sto = $meta['sto'];
        $serviceArea = ServiceArea::with('branch.regionRef')
            ->where('workzone', $sto)
            ->first();

        if ($serviceArea === null || $serviceArea->branch === null) {
            throw new RuntimeException("STO {$sto} tidak ditemukan atau belum terhubung ke Branch.");
        }

        $branch = $serviceArea->branch;
        if (isset($meta['branch']) && mb_strtoupper($branch->name) !== $meta['branch']) {
            throw new RuntimeException("Branch {$branch->name} tidak sesuai dengan STO {$sto}.");
        }

        $area = $meta['area'] ?? ($branch->regionRef?->area?->code ?? '3');

        // Validate admin scope
        if (! $this->visibility->canUseLocation($actor, $branch->id_branch, $serviceArea->id_service_area)) {
            throw new RuntimeException('LOP berada di luar scope lokasi Admin yang login.');
        }

        return [
            'sto' => $sto,
            'branch' => $branch,
            'serviceArea' => $serviceArea,
            'area' => $area,
        ];
    }

    /**
     * Generate unique incident number with retry logic.
     */
    private function generateUniqueIncident(Branch $branch, \App\Enums\ProgramType $programType): string
    {
        for ($i = 0; $i < 3; $i++) {
            $incident = $this->incidentService->generate($branch, $programType);
            if (! QeLop::withTrashed()->where('incident', $incident)->exists()) {
                return $incident;
            }
        }
        throw new RuntimeException('Gagal generate incident unik setelah 3x percobaan.');
    }

    /**
     * Detect program type from project name.
     */
    private function detectProgramType(string $project): string
    {
        $lowerProject = mb_strtolower($project);

        if (mb_stripos($lowerProject, 'qerel') !== false || mb_stripos($lowerProject, 'relok') !== false) {
            return ProgramType::RELOK_UTILITAS->value;
        }
        if (mb_stripos($lowerProject, 'qerec') !== false) {
            return ProgramType::RECOVERY->value;
        }
        if (mb_stripos($lowerProject, 'qeprev') !== false || mb_stripos($lowerProject, 'prev') !== false) {
            return ProgramType::PREVENTIVE->value;
        }

                return ProgramType::RECOVERY->value; // default
    }

    /**
     * Parse LOP metadata from project name.
     * Format: {area}{STO}_{PROGRAM}_{INCIDENT}_{SEGMENT1_SEGMENT2_...}_{description}
     * Example: 3JBR_QEREC_INC53492291_DISTRIBUSI_FEEDER_JBR-FQ
     */
    private function parseProjectName(string $project): ?array
    {
        $segmentValues = collect(LopSegment::cases())->map(fn ($s) => mb_strtoupper($s->value))->all();

        $segmentsInProject = collect($segmentValues)->sortByDesc(fn ($s) => mb_strlen($s))->values();

        $segmentPattern = implode('|', array_map(fn ($s) => preg_quote($s, '/'), $segmentsInProject->toArray()));

        // Match: area(1 digit) + STO (3 letters) + _ + PROGRAM + _ + INC\d+ + _ + SEGMENTS (underscore-separated) + optional _ description
        $pattern = '/^(\d)([A-Z]{3})_([A-Z]+)_(INC\d+)_((?:'.$segmentPattern.')(?:_(?:'.$segmentPattern.'))*)(?:_(.+))?$/i';

        if (! preg_match($pattern, $project, $match)) {
            return null;
        }

        $areaCode = $match[1];
        $sto = $match[2];
        $programKeyword = $match[3];
        $incident = $match[4];
        $segmentStr = $match[5];
        $description = $match[6] ?? '';

        $segmentValuesFound = explode('_', mb_strtoupper($segmentStr));
        $segmentValuesFound = array_values(array_filter($segmentValuesFound, fn ($s) => in_array($s, $segmentValues, true)));

        if ($segmentValuesFound === []) {
            return null;
        }

        // Map program keyword to ProgramType value
        $programType = ProgramType::RECOVERY->value; // default
        $lowerKw = mb_strtolower($programKeyword);
        if (in_array($lowerKw, ['qerel', 'relok'], true)) {
            $programType = ProgramType::RELOK_UTILITAS->value;
        } elseif ($lowerKw === 'qerec') {
            $programType = ProgramType::RECOVERY->value;
        } elseif (in_array($lowerKw, ['qeprev', 'prev'], true)) {
            $programType = ProgramType::PREVENTIVE->value;
        }

        return [
            'area_code' => $areaCode,
            'sto' => $sto,
            'program_type' => $programType,
            'incident' => $incident,
            'segment' => array_map('mb_strtolower', $segmentValuesFound),
            'job_description' => str_replace('_', ' ', $description),
        ];
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
