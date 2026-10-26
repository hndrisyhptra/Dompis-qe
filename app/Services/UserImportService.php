<?php

namespace App\Services;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Exceptions\SpreadsheetReadException;
use App\Http\Requests\StoreUserRequest;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeImportBatch;
use App\Models\Region;
use App\Models\Role;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class UserImportService
{
    public const MAX_ROWS = 1000;

    public const HEADERS = ['name', 'username', 'nik', 'email', 'phone', 'password', 'role', 'status', 'admin_scope_type', 'area', 'region', 'branch', 'service_areas'];

    public function __construct(private readonly SpreadsheetReader $reader, private readonly UserService $users) {}

    public function enqueue(UploadedFile $file, User $actor): QeImportBatch
    {
        abort_unless($actor->isActive() && $actor->can('create', User::class), 403);
        $uuid = (string) Str::uuid();
        $path = "imports/users/{$uuid}/source.enc";
        try {
            // File mengandung password awal. Simpan terenkripsi, bukan sebagai upload publik.
            if (! Storage::disk('local')->put($path, Crypt::encryptString($file->getContent()), 'private')) {
                throw new RuntimeException('File import user tidak dapat disimpan.');
            }

            return QeImportBatch::create([
                'uuid' => $uuid, 'type' => 'users', 'status' => 'queued', 'disk' => 'local',
                'file_path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'metadata' => ['format' => strtolower($file->getClientOriginalExtension())], 'uploaded_by' => $actor->id_user,
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function process(QeImportBatch $batch, User $actor): void
    {
        if ($batch->type !== 'users' || $batch->refresh()->status !== 'queued') {
            return;
        }
        $temporaryPath = $this->temporaryPath($batch);
        $stage = 'authorization';
        try {
            if (! $actor->isActive() || ! $actor->can('create', User::class)) {
                throw new InvalidArgumentException('Pengunggah tidak lagi memiliki akses untuk membuat user.');
            }
            $batch->update(['status' => 'processing', 'started_at' => now()]);
            $stage = 'open_file';
            $disk = Storage::disk($batch->disk);
            if (! $disk->put($temporaryPath, Crypt::decryptString($disk->get($batch->file_path)), 'private')) {
                throw new RuntimeException('File import user tidak dapat dibaca.');
            }
            $stage = 'read_file';
            $table = $this->reader->table($disk->path($temporaryPath), ['name', 'username', 'password', 'role'], sheetName: 'Users', maxRows: self::MAX_ROWS);
            $total = count($table['rows']);
            if ($total === 0 || $total > self::MAX_ROWS) {
                throw new InvalidArgumentException('File harus berisi 1–'.self::MAX_ROWS.' user. Pecah file yang lebih besar menjadi beberapa upload.');
            }
            $batch->update(['total_rows' => $total]);
            $stage = 'load_master';
            $catalog = $this->catalog();
            $stage = 'create_users';
            $success = $failed = 0;
            foreach ($table['rows'] as $index => $row) {
                $raw = array_intersect_key($row['data'], array_flip(self::HEADERS));
                $safe = array_intersect_key($raw, array_flip(['name', 'username', 'role', 'status', 'admin_scope_type', 'area', 'region', 'branch', 'service_areas']));
                $reference = mb_substr(trim((string) ($raw['username'] ?? '')), 0, 100);
                try {
                    $data = $this->userData($raw, $catalog);
                    DB::transaction(function () use ($data, $actor, $batch, $row, $safe, $reference) {
                        $user = $this->users->create($data, $actor);
                        $batch->rows()->create([
                            'row_number' => $row['row_number'], 'status' => 'success', 'reference' => $reference,
                            'message' => 'User berhasil dibuat.', 'payload' => $safe, 'result' => ['user_id' => $user->id_user],
                        ]);
                    });
                    $success++;
                } catch (Throwable $e) {
                    $message = $e instanceof ValidationException ? implode(' ', $e->validator->errors()->all())
                        : ($e instanceof InvalidArgumentException ? $e->getMessage() : 'User tidak dapat disimpan. Periksa duplikasi akun atau hubungi administrator.');
                    $batch->rows()->create([
                        'row_number' => $row['row_number'], 'status' => 'failed', 'reference' => $reference,
                        'message' => mb_substr($message, 0, 2000), 'payload' => $safe,
                    ]);
                    $failed++;
                }
                if (($index + 1) % 25 === 0 || $index + 1 === $total) {
                    $batch->update(['success_rows' => $success, 'failed_rows' => $failed,
                        'metadata' => [...$batch->metadata, 'processed_rows' => $index + 1]]);
                }
            }
            $batch->update(['status' => $failed === 0 ? 'completed' : ($success > 0 ? 'partial' : 'failed'), 'completed_at' => now()]);
        } catch (Throwable $e) {
            [$code, $message] = $this->failureDetails($e, $stage);
            $batch->update(['metadata' => [...($batch->metadata ?? []), 'error_code' => $code, 'error_stage' => $stage]]);
            $this->fail($batch, $message);
        } finally {
            $this->cleanup($batch);
        }
    }

    /** Jangan meneruskan pesan exception SQL/library yang dapat berisi password atau nilai sel. */
    private function failureDetails(Throwable $exception, string $stage): array
    {
        if ($exception instanceof \Error && str_contains($exception->getMessage(), 'Unknown named parameter')
            && (str_contains($exception->getMessage(), '$sheetName') || str_contains($exception->getMessage(), '$maxRows'))) {
            return ['worker_outdated', 'Proses worker antrean masih memakai kode lama. Restart worker lokal/server lalu upload ulang file. Format template Anda belum tentu bermasalah.'];
        }
        if ($exception instanceof SpreadsheetReadException) {
            return [$exception->reason, $exception->getMessage()];
        }
        if ($exception instanceof DecryptException) {
            return ['file_decryption_failed', 'File upload tidak dapat didekripsi oleh worker. Pastikan aplikasi dan worker memakai konfigurasi yang sama, restart worker, lalu upload ulang.'];
        }
        if ($exception instanceof InvalidArgumentException) {
            return ['invalid_file_or_access', $exception->getMessage()];
        }

        return match ($stage) {
            'open_file' => ['storage_failed', 'File upload tidak dapat dibuka. Periksa izin storage aplikasi dan worker, lalu upload ulang.'],
            'read_file' => ['read_failed', 'Pembaca file user mengalami kendala. Restart worker untuk memuat kode terbaru. Jika tetap gagal, hubungi administrator; bukan otomatis kesalahan header atau role.'],
            'load_master' => ['master_failed', 'Master role/lokasi tidak dapat dibaca. Periksa koneksi database dan struktur master pada worker.'],
            default => ['processing_failed', 'Proses user terhenti. Periksa hasil baris yang sudah tercatat sebelum mengunggah ulang.'],
        };
    }

    public function fail(QeImportBatch $batch, string $message = 'Proses import user berhenti. Periksa hasil baris dan worker sebelum mengunggah ulang.'): void
    {
        if ($batch->type !== 'users' || $batch->isFinished()) {
            return;
        }
        $batch->update(['status' => 'failed', 'error_message' => $message, 'completed_at' => now(),
            'success_rows' => $batch->rows()->where('status', 'success')->count(),
            'failed_rows' => $batch->rows()->where('status', 'failed')->count()]);
        $this->cleanup($batch);
    }

    public function cleanup(QeImportBatch $batch): void
    {
        if ($batch->type === 'users') {
            Storage::disk($batch->disk)->delete([$batch->file_path, $this->temporaryPath($batch)]);
        }
    }

    private function temporaryPath(QeImportBatch $batch): string
    {
        $format = in_array(data_get($batch->metadata, 'format'), ['xlsx', 'xls', 'csv'], true) ? $batch->metadata['format'] : 'csv';

        return $batch->file_path.'.working.'.$format;
    }

    private function catalog(): array
    {
        return [
            'role' => $this->index(Role::whereIn('code', array_column(UserRole::cases(), 'value'))->get(), ['code', 'name']),
            'area' => $this->index(Area::where('is_active', true)->get(), ['code', 'name']),
            'region' => $this->index(Region::where('is_active', true)->get(), ['code', 'name']),
            'branch' => $this->index(Branch::with('regionRef')->where('is_active', true)->get(), ['code', 'name']),
            'service_areas' => $this->index(ServiceArea::with('branch.regionRef')->where('is_active', true)->get(), ['workzone']),
        ];
    }

    private function index(Collection $models, array $fields): array
    {
        $index = [];
        foreach ($models as $model) {
            foreach ($fields as $field) {
                $key = $this->key((string) $model->{$field});
                if ($key !== '') {
                    $index[$key][$model->getKey()] = $model;
                }
            }
        }

        return $index;
    }

    private function key(string $value): string
    {
        return mb_strtoupper(trim((string) preg_replace('/[\s_-]+/u', ' ', trim($value))));
    }

    private function resolve(string $field, string $value, array $catalog): mixed
    {
        if ($value === '') {
            return null;
        }
        $matches = $catalog[$field][$this->key($value)] ?? [];
        if (count($matches) !== 1) {
            throw new InvalidArgumentException("{$field} tidak ditemukan, tidak aktif, atau ambigu. Gunakan kode pada master lokasi/role.");
        }

        return reset($matches);
    }

    private function userData(array $raw, array $catalog): array
    {
        $text = fn (string $field): string => trim((string) ($raw[$field] ?? ''));
        $role = $this->resolve('role', $text('role'), $catalog);
        if ($role === null) {
            throw new InvalidArgumentException('role wajib diisi.');
        }
        $scopeValue = strtolower(str_replace(' ', '_', $this->key($text('admin_scope_type'))));
        $scopeValue = preg_replace('/^admin_/', '', $scopeValue);
        $scope = AdminScopeType::tryFrom($scopeValue);
        $area = $this->resolve('area', $text('area'), $catalog);
        $region = $this->resolve('region', $text('region'), $catalog);
        $branch = $this->resolve('branch', $text('branch'), $catalog);
        $serviceAreas = [];
        foreach (array_filter(array_map('trim', preg_split('/[|;,]+/', $text('service_areas')))) as $code) {
            $serviceArea = $this->resolve('service_areas', $code, $catalog);
            $serviceAreas[$serviceArea->getKey()] = $serviceArea;
        }

        if (($region && $area && $region->area_id !== $area->getKey())
            || ($branch && $region && $branch->region_id !== $region->getKey())
            || ($branch && $area && $branch->regionRef?->area_id !== $area->getKey())) {
            throw new InvalidArgumentException('Area, Region, dan Branch yang diisi tidak berada pada hierarki lokasi yang sama.');
        }
        foreach ($serviceAreas as $serviceArea) {
            if (($branch && $serviceArea->branch_id !== $branch->getKey())
                || ($region && $serviceArea->region_id !== $region->getKey())
                || ($area && $serviceArea->branch?->regionRef?->area_id !== $area->getKey())) {
                throw new InvalidArgumentException('Service Area tidak sesuai dengan Area/Region/Branch yang diisi.');
            }
        }

        if ($role->code === UserRole::ADMIN->value) {
            if ($scope === null) {
                throw new InvalidArgumentException('Admin wajib memilih admin_scope_type: area, region, branch, atau service_area.');
            }
            if (($scope === AdminScopeType::AREA && ($branch || $region || $serviceAreas))
                || ($scope === AdminScopeType::REGION && ($branch || $serviceAreas))
                || ($scope === AdminScopeType::BRANCH && $serviceAreas)) {
                throw new InvalidArgumentException('Kosongkan kolom lokasi yang lebih rendah daripada level scope Admin.');
            }
        } elseif ($text('admin_scope_type') !== '' || $area || $region || $serviceAreas) {
            throw new InvalidArgumentException('Role selain Admin hanya memakai Branch opsional. Kosongkan scope, area, region, dan service_areas.');
        }

        $status = match (strtolower($text('status'))) {
            '', 'active', 'aktif' => 'active', 'inactive', 'nonaktif' => 'inactive', default => $text('status'),
        };
        $data = [
            'name' => $text('name'), 'username' => $text('username'), 'nik' => $text('nik') ?: null,
            'email' => $text('email') ?: null, 'phone' => $text('phone') ?: null,
            'password' => (string) ($raw['password'] ?? ''), 'password_confirmation' => (string) ($raw['password'] ?? ''),
            'role_id' => $role->getKey(), 'status' => $status, 'admin_scope_type' => $scope?->value,
            'area_id' => $area?->getKey(), 'region_id' => $region?->getKey(), 'branch_id' => $branch?->getKey(),
            'service_area_ids' => array_keys($serviceAreas),
        ];
        // Pakai kontrak validasi user manual, termasuk unique pada akun soft-deleted.
        $request = new StoreUserRequest;
        $request->replace($data);
        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);
        $validator->validate();

        return $data;
    }
}
