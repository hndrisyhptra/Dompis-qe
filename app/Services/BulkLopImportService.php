<?php

namespace App\Services;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\Package;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BulkLopImportService
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly LopService $lopService,
        private readonly ManualIncidentService $manualIncident,
        private readonly LopVisibilityService $visibility,
        private readonly ImportRowWriter $rowWriter,
        private readonly TicketLookupService $tickets,
        private readonly IncidentTicketValidationService $incidentValidation,
    ) {}

    public function process(QeImportBatch $batch, User $actor): void
    {
        $batch->update(['status' => 'processing', 'started_at' => now(), 'error_message' => null]);

        $table = $this->reader->table(
            Storage::disk($batch->disk)->path($batch->file_path),
            ['sto', 'program_type', 'segment', 'job_description'],
            sheetName: 'LOP',
        );

        $batch->update(['total_rows' => count($table['rows'])]);

        if ($table['rows'] === []) {
            throw new \RuntimeException('File tidak memiliki baris data LOP untuk diproses.');
        }

        $rows = array_map(fn (array $row): array => [
            'row_number' => $row['row_number'],
            'payload' => $this->normalizeRow($row['data']),
        ], $table['rows']);

        $ticketTypes = $this->tickets->lookupTypes(collect($rows)->filter(function (array $row): bool {
            $program = ProgramType::tryFrom($row['payload']['program_type']);

            return $program !== null && $this->incidentValidation->requiresLookup($row['payload']['incident'], $program);
        })->pluck('payload.incident')->all());

        $serviceAreas = ServiceArea::query()
            ->with('branch.regionRef.area')
            ->where('is_active', true)
            ->whereIn('workzone', collect($rows)->pluck('payload.sto')->filter()->unique())
            ->get()
            ->keyBy(fn (ServiceArea $serviceArea): string => mb_strtoupper($serviceArea->workzone));
        $packages = Package::query()
            ->whereIn('code', collect($rows)->pluck('payload.package_code')->filter()->unique())
            ->get()
            ->keyBy(fn (Package $package): string => mb_strtoupper($package->code));
        $incidentQuery = QeLop::withTrashed()
            ->whereIn('incident', collect($rows)->pluck('payload.incident')->filter()->unique());
        $visibleIds = $actor->hasRole(UserRole::ADMIN)
            ? array_fill_keys($this->visibility->apply(clone $incidentQuery, $actor)->pluck('id_qe_lops')->all(), true)
            : null;
        $usedIncidents = $incidentQuery->get(['id_qe_lops', 'incident', 'nama_lop', 'deleted_at'])
            ->mapWithKeys(fn (QeLop $lop): array => [mb_strtoupper($lop->incident) => [
                'name' => $visibleIds === null || isset($visibleIds[$lop->id_qe_lops]) ? $lop->nama_lop : null,
                'deleted' => $lop->trashed(),
            ]])->all();

        // Scope lokasi dihitung satu kali. Sebelumnya pengecekan ini menjalankan
        // beberapa query lagi untuk setiap baris file.
        $allowedBranchIds = $actor->hasRole(UserRole::ADMIN)
            ? $this->visibility->accessibleBranchIds($actor)
            : collect();
        $allowedServiceAreaIds = $actor->hasRole(UserRole::ADMIN)
            ? $this->visibility->accessibleServiceAreaIds($actor)
            : collect();
        $successRows = 0;
        $failedRows = 0;
        $resultRows = [];
        $totalRows = count($rows);

        foreach ($rows as $index => $row) {
            $payload = $row['payload'];

            try {
                $lop = $this->createLop(
                    $payload,
                    $actor,
                    $serviceAreas,
                    $packages,
                    $allowedBranchIds,
                    $allowedServiceAreaIds,
                    $usedIncidents,
                    $ticketTypes,
                );
                $usedIncidents[mb_strtoupper($lop->incident)] = ['name' => $lop->nama_lop, 'deleted' => false, 'row_number' => $row['row_number']];
                $resultRows[] = [
                    'import_batch_id' => $batch->id_import_batch,
                    'row_number' => $row['row_number'],
                    'status' => 'success',
                    'reference' => $lop->incident,
                    'message' => 'LOP berhasil dibuat.',
                    'payload' => $this->safePayload($payload),
                    'result' => ['lop_id' => $lop->id_qe_lops, 'nama_lop' => $lop->nama_lop],
                ];
                $successRows++;
            } catch (Throwable $e) {
                $resultRows[] = [
                    'import_batch_id' => $batch->id_import_batch,
                    'row_number' => $row['row_number'],
                    'status' => 'failed',
                    'reference' => $payload['incident'] ?: null,
                    'message' => $this->message($e),
                    'payload' => $this->safePayload($payload),
                ];
                $failedRows++;
            }

            $processedRows = $index + 1;
            if (count($resultRows) >= 25 || $processedRows === $totalRows) {
                $this->rowWriter->insert($batch, $resultRows);
                $resultRows = [];
                $batch->update([
                    'success_rows' => $successRows,
                    'failed_rows' => $failedRows,
                    'metadata' => [
                        ...($batch->metadata ?? []),
                        'processed_rows' => $processedRows,
                    ],
                ]);
            }
        }

        $batch->update([
            'status' => $failedRows === 0
                ? 'completed'
                : ($successRows > 0 ? 'partial' : 'failed'),
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  Collection<string, ServiceArea>  $serviceAreas
     * @param  Collection<string, Package>  $packages
     * @param  Collection<int, int|string>  $allowedBranchIds
     * @param  Collection<int, int|string>  $allowedServiceAreaIds
     * @param  array<string, array>  $usedIncidents
     */
    private function createLop(
        array $data,
        User $actor,
        Collection $serviceAreas,
        Collection $packages,
        Collection $allowedBranchIds,
        Collection $allowedServiceAreaIds,
        array $usedIncidents,
        array $ticketTypes,
    ): QeLop {
        $serviceArea = $serviceAreas->get($data['sto']);

        if ($serviceArea === null) {
            throw new \InvalidArgumentException("STO {$data['sto']} belum terdaftar di Master Service Area.");
        }

        $branch = $serviceArea->branch;

        if ($branch === null || ($data['branch'] !== '' && mb_strtoupper($branch->name) !== $data['branch'])) {
            throw new \InvalidArgumentException('Branch tidak sesuai dengan STO.');
        }

        if ($actor->hasRole(UserRole::ADMIN)
            && (! $allowedBranchIds->contains($branch->id_branch) || ! $allowedServiceAreaIds->contains($serviceArea->id_service_area))) {
            throw new \InvalidArgumentException('Lokasi LOP berada di luar scope admin.');
        }

        $area = $data['area'] !== '' ? $data['area'] : (string) ($branch->regionRef?->area?->code ?? '3');
        if ((string) $area !== (string) ($branch->regionRef?->area?->code ?? '3')) {
            throw new \InvalidArgumentException('Area tidak sesuai dengan Branch/STO.');
        }
        $segments = LopSegment::normalizeInput($data['segment']);

        $validator = Validator::make([
            ...$data,
            'area' => $area,
            'branch' => $branch->name,
            'segment' => $segments,
        ], [
            'incident' => ['nullable', 'string', 'max:100', 'regex:/^(INC|INP)[A-Z0-9-]+$/'],
            'sto' => ['required', 'string', 'max:20'],
            'branch' => ['required', 'string', 'max:100'],
            'area' => ['required', 'string', 'max:10'],
            'program_type' => ['required', Rule::enum(ProgramType::class)],
            'segment' => ['required', 'array', 'min:1', 'max:'.LopSegment::MAX_SELECTIONS],
            'segment.*' => [Rule::enum(LopSegment::class)],
            'budget_type' => ['required', Rule::enum(LopBudgetType::class)],
            'job_description' => ['required', 'string', 'max:2000'],
            'ihld_id' => ['nullable', 'string', 'max:100'],
            'nama_lop' => ['nullable', 'string', 'max:255'],
            'package_code' => ['nullable', 'string'],
        ], [
            'segment.max' => 'Maksimal 5 segmen untuk setiap program.',
            'segment.required' => 'Pilih minimal 1 segmen.',
            'segment.*.Illuminate\\Validation\\Rules\\Enum' => 'Segmen tidak dikenal. Gunakan referensi segmen pada template.',
            'program_type.Illuminate\\Validation\\Rules\\Enum' => 'Program tidak dikenal atau ambigu. Gunakan salah satu dari empat program pada template.',
        ]);
        $validator->validate();

        $incident = $data['incident'];
        if ($incident === '') {
            $incident = $this->manualIncident->generate($branch, ProgramType::from($data['program_type']));
        }

        if (isset($usedIncidents[mb_strtoupper($incident)])) {
            throw new \InvalidArgumentException($this->duplicateMessage($incident, $usedIncidents[mb_strtoupper($incident)]));
        }

        $validation = $this->incidentValidation->inspect(
            $incident, ProgramType::from($data['program_type']), $ticketTypes[$incident] ?? ['found' => false],
        );
        if (! $validation['valid']) {
            throw ValidationException::withMessages(['incident' => $validation['message']]);
        }

        $package = $data['package_code'] !== '' ? $packages->get($data['package_code']) : null;
        if ($data['package_code'] !== '' && $package === null) {
            throw new \InvalidArgumentException("Paket {$data['package_code']} belum terdaftar.");
        }

        try {
            return $this->lopService->create([
                'incident' => $incident,
                'nama_lop' => $data['nama_lop'],
                'program_type' => $data['program_type'],
                'sto' => $data['sto'],
                'branch' => $branch->name,
                'branch_id' => $branch->id_branch,
                'service_area_id' => $serviceArea->id_service_area,
                'area' => $area,
                'segment' => $segments,
                'budget_type' => $data['budget_type'] ?: null,
                'job_description' => $data['job_description'],
                'ihld_id' => $data['ihld_id'] ?: null,
                'package_id' => $package?->id_package,
            ], $actor, $branch, $serviceArea);
        } catch (UniqueConstraintViolationException $e) {
            // Batch lain dapat membuat INCIDENT setelah prefetch; jangan tampilkan SQL.
            $existing = QeLop::withTrashed()->where('incident', $incident)->first();
            if ($existing !== null) {
                $visible = $this->visibility->apply(QeLop::withTrashed()->whereKey($existing->getKey()), $actor)->exists();
                throw new \InvalidArgumentException($this->duplicateMessage($incident, [
                    'name' => $visible ? $existing->nama_lop : null,
                    'deleted' => $existing->trashed(),
                ]));
            }
            throw $e;
        }
    }

    private function duplicateMessage(string $incident, array $existing): string
    {
        $lop = $existing['name'] !== null ? 'LOP "'.$existing['name'].'"' : 'LOP di luar scope Anda';
        $note = isset($existing['row_number']) ? ' (dibuat pada baris '.$existing['row_number'].' file ini)' : '';
        if ($existing['deleted']) {
            $note .= ' (LOP sudah dihapus/arsip; INCIDENT tetap tidak boleh digunakan ulang)';
        }

        return "INCIDENT {$incident} sudah digunakan pada {$lop}{$note}.";
    }

    private function normalizeRow(array $row): array
    {
        $inputProgram = trim((string) ($row['program_type'] ?? $row['program'] ?? ''));
        $program = ProgramType::fromInput($inputProgram);

        return [
            'incident' => strtoupper(trim((string) ($row['incident'] ?? ''))),
            'sto' => strtoupper(trim((string) ($row['sto'] ?? ''))),
            'branch' => strtoupper(trim((string) ($row['branch'] ?? ''))),
            'area' => trim((string) ($row['area'] ?? '')),
            'program_type' => $program?->value ?? $inputProgram,
            'segment' => strtolower(trim((string) ($row['segment'] ?? ''))),
            'budget_type' => $program?->budgetType()->value ?? '',
            'job_description' => trim((string) ($row['job_description'] ?? '')),
            'ihld_id' => trim((string) ($row['ihld_id'] ?? '')),
            'nama_lop' => trim((string) ($row['nama_lop'] ?? '')),
            'package_code' => strtoupper(trim((string) ($row['package_code'] ?? ''))),
        ];
    }

    private function safePayload(array $payload): array
    {
        return collect($payload)->map(fn ($value) => is_string($value) ? mb_substr($value, 0, 500) : $value)->all();
    }

    private function message(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return collect($e->errors())->flatten()->implode(' ');
        }

        return mb_substr($e->getMessage(), 0, 1000);
    }
}
