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
    ) {}

    public function process(QeImportBatch $batch, User $actor): void
    {
        $batch->update(['status' => 'processing', 'started_at' => now(), 'error_message' => null]);

        $table = $this->reader->table(
            Storage::disk($batch->disk)->path($batch->file_path),
            ['sto', 'program_type', 'segment', 'job_description']
        );

        $batch->update(['total_rows' => count($table['rows'])]);

        if ($table['rows'] === []) {
            throw new \RuntimeException('File tidak memiliki baris data LOP untuk diproses.');
        }

        $rows = array_map(fn (array $row): array => [
            'row_number' => $row['row_number'],
            'payload' => $this->normalizeRow($row['data']),
        ], $table['rows']);

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
        $usedIncidents = QeLop::withTrashed()
            ->whereIn('incident', collect($rows)->pluck('payload.incident')->filter()->unique())
            ->pluck('incident')
            ->mapWithKeys(fn (string $incident): array => [mb_strtoupper($incident) => true])
            ->all();

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
                );
                $usedIncidents[mb_strtoupper($lop->incident)] = true;
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
     * @param  array<string, bool>  $usedIncidents
     */
    private function createLop(
        array $data,
        User $actor,
        Collection $serviceAreas,
        Collection $packages,
        Collection $allowedBranchIds,
        Collection $allowedServiceAreaIds,
        array $usedIncidents,
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
        $segments = collect(preg_split('/[|,;]+/', $data['segment']) ?: [])
            ->map(fn ($segment) => strtolower(trim($segment)))
            ->filter()->unique()->values()->all();

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
            'segment' => ['required', 'array', 'min:1', 'max:3'],
            'segment.*' => [Rule::enum(LopSegment::class)],
            'budget_type' => ['nullable', 'required_if:program_type,relok_utilitas', Rule::enum(LopBudgetType::class)],
            'job_description' => ['required', 'string', 'max:2000'],
            'ihld_id' => ['nullable', 'string', 'max:100'],
            'nama_lop' => ['nullable', 'string', 'max:255'],
            'package_code' => ['nullable', 'string'],
        ]);
        $validator->validate();

        $incident = $data['incident'];
        if ($incident === '') {
            $incident = $this->manualIncident->generate($branch, ProgramType::from($data['program_type']));
        }

        if (isset($usedIncidents[mb_strtoupper($incident)])) {
            throw new \InvalidArgumentException("Incident {$incident} sudah digunakan.");
        }

        $package = $data['package_code'] !== '' ? $packages->get($data['package_code']) : null;
        if ($data['package_code'] !== '' && $package === null) {
            throw new \InvalidArgumentException("Paket {$data['package_code']} belum terdaftar.");
        }

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
    }

    private function normalizeRow(array $row): array
    {
        $program = strtolower(trim((string) ($row['program_type'] ?? '')));
        $program = match ($program) {
            'qe recovery', 'qerec', 'recovery' => 'recovery',
            'qe preventive', 'qeprev', 'preventive' => 'preventive',
            'qe relok utilitas', 'qerelok', 'relok', 'relok utilitas', 'relok_utilitas' => 'relok_utilitas',
            default => $program,
        };

        return [
            'incident' => strtoupper(trim((string) ($row['incident'] ?? ''))),
            'sto' => strtoupper(trim((string) ($row['sto'] ?? ''))),
            'branch' => strtoupper(trim((string) ($row['branch'] ?? ''))),
            'area' => trim((string) ($row['area'] ?? '')),
            'program_type' => $program,
            'segment' => strtolower(trim((string) ($row['segment'] ?? ''))),
            'budget_type' => strtoupper(trim((string) ($row['budget_type'] ?? ''))),
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
