<?php

namespace App\Services;

use App\Enums\LopBudgetType;
use App\Enums\LopSegment;
use App\Enums\ProgramType;
use App\Models\Branch;
use App\Models\Package;
use App\Models\QeImportBatch;
use App\Models\QeImportRow;
use App\Models\QeLop;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        foreach ($table['rows'] as $row) {
            $payload = $this->normalizeRow($row['data']);

            try {
                $lop = DB::transaction(fn () => $this->createLop($payload, $actor));
                QeImportRow::create([
                    'import_batch_id' => $batch->id_import_batch,
                    'row_number' => $row['row_number'],
                    'status' => 'success',
                    'reference' => $lop->incident,
                    'message' => 'LOP berhasil dibuat.',
                    'payload' => $this->safePayload($payload),
                    'result' => ['lop_id' => $lop->id_qe_lops, 'nama_lop' => $lop->nama_lop],
                ]);
                $batch->increment('success_rows');
            } catch (Throwable $e) {
                QeImportRow::create([
                    'import_batch_id' => $batch->id_import_batch,
                    'row_number' => $row['row_number'],
                    'status' => 'failed',
                    'reference' => $payload['incident'] ?: null,
                    'message' => $this->message($e),
                    'payload' => $this->safePayload($payload),
                ]);
                $batch->increment('failed_rows');
            }
        }

        $batch->refresh();
        $batch->update([
            'status' => $batch->failed_rows === 0
                ? 'completed'
                : ($batch->success_rows > 0 ? 'partial' : 'failed'),
            'completed_at' => now(),
        ]);
    }

    private function createLop(array $data, User $actor): QeLop
    {
        $serviceArea = ServiceArea::query()
            ->with('branch.regionRef.area')
            ->where('workzone', $data['sto'])
            ->where('is_active', true)
            ->first();

        if ($serviceArea === null) {
            throw new \InvalidArgumentException("STO {$data['sto']} belum terdaftar di Master Service Area.");
        }

        $branch = $data['branch'] !== ''
            ? Branch::query()->where('name', $data['branch'])->where('is_active', true)->first()
            : $serviceArea->branch;

        if ($branch === null || $branch->id_branch !== $serviceArea->branch_id) {
            throw new \InvalidArgumentException('Branch tidak sesuai dengan STO.');
        }

        if (! $this->visibility->canUseLocation($actor, $branch->id_branch, $serviceArea->id_service_area)) {
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
            'incident' => ['nullable', 'string', 'max:100', 'regex:/^(INC|INP)[A-Z0-9-]+$/', Rule::unique('qe_lops', 'incident')],
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
            'package_code' => ['nullable', 'string', 'exists:packages,code'],
        ]);
        $validator->validate();

        $incident = $data['incident'];
        if ($incident === '') {
            $incident = $this->manualIncident->generate($branch, ProgramType::from($data['program_type']));
        }

        if (QeLop::withTrashed()->where('incident', $incident)->exists()) {
            throw new \InvalidArgumentException("Incident {$incident} sudah digunakan.");
        }

        $package = $data['package_code'] !== '' ? Package::where('code', $data['package_code'])->first() : null;

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
        ], $actor);
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
