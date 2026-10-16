<?php

namespace App\Services;

use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class AissImportService
{
    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly LopVisibilityService $visibility,
        private readonly LopService $lops,
    ) {}

    /** Read raw values only. Formulas and columns unrelated to matching are never evaluated. */
    public function parse(string $path): array
    {
        try {
            $format = IOFactory::identify($path);
        } catch (Throwable) {
            throw new RuntimeException('File AISS tidak dapat dibaca sebagai Excel XLSX atau XLS.');
        }
        if (! in_array($format, ['Xlsx', 'Xls'], true)) {
            throw new RuntimeException('File AISS tidak dapat dibaca sebagai Excel XLSX atau XLS. Jangan hanya mengganti ekstensi file.');
        }
        $book = $this->reader->load($path);
        try {
            $selected = null;
            $headerRow = null;
            foreach ($book->getAllSheets() as $sheet) {
                for ($r = 1; $r <= min(20, $sheet->getHighestDataRow()); $r++) {
                    $header = fn (string $column) => $this->reader->normalizeHeader((string) $sheet->getCell($column.$r)->getValue());
                    if ($header('B') === 'ihld_id' && in_array($header('H'), ['nama_program', 'nama_lop'], true)
                        && $header('L') === 'status' && $header('W') === 'incident') {
                        $selected = $sheet;
                        $headerRow = $r;
                        break 2;
                    }
                }
            }
            if ($selected === null) {
                throw new RuntimeException('Header AISS tidak sesuai. Gunakan B: ID IHLD, H: NAMA PROGRAM, L: STATUS, W: INCIDENT.');
            }
            if ($selected->getHighestDataRow() - $headerRow > 10000) {
                throw new RuntimeException('Maksimal 10.000 baris setelah header. Pecah file AISS menjadi beberapa upload.');
            }
            $rows = [];
            for ($r = $headerRow + 1; $r <= $selected->getHighestDataRow(); $r++) {
                $data = [];
                $formula = false;
                foreach (['ihld_id' => 'B', 'nama_lop' => 'H', 'status_aiss' => 'L', 'incident' => 'W'] as $key => $column) {
                    $cell = $selected->getCell($column.$r);
                    $formula = $formula || $cell->getDataType() === DataType::TYPE_FORMULA;
                    $data[$key] = trim((string) $cell->getValue());
                }
                if (implode('', $data) !== '') {
                    $rows[] = ['row_number' => $r, 'data' => $data, 'formula' => $formula];
                }
            }
            if ($rows === []) {
                throw new RuntimeException('File AISS tidak memiliki baris data.');
            }

            return ['sheet' => $selected->getTitle(), 'rows' => $rows];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function process(QeImportBatch $batch): void
    {
        if ($batch->type !== 'aiss' || $batch->refresh()->status !== 'queued') {
            return;
        }
        $batch->update(['status' => 'processing', 'started_at' => now()]);
        try {
            $actor = User::find($batch->uploaded_by);
            if ($actor === null || ! Gate::forUser($actor)->allows('import-aiss')) {
                throw new RuntimeException('Akun pengunggah tidak aktif atau tidak lagi memiliki izin Import AISS.');
            }
            if (! Schema::hasColumn('qe_lops', 'status_aiss')) {
                throw new RuntimeException('Kolom Status AISS belum tersedia. Jalankan migration Status AISS terlebih dahulu.');
            }
            try {
                $parsed = $this->parse(Storage::disk($batch->disk)->path($batch->file_path));
            } catch (RuntimeException $exception) {
                $this->fail($batch, $exception->getMessage());

                return;
            }
            $batch->update(['total_rows' => count($parsed['rows']), 'metadata' => ['sheet' => $parsed['sheet'], 'processed_rows' => 0, 'skipped_rows' => 0]]);
            $indexes = ['ihld_id' => [], 'nama_lop' => [], 'incident' => []];
            $wanted = [];
            foreach (array_keys($indexes) as $key) {
                $wanted[$key] = array_fill_keys(array_filter(array_map(fn ($row) => $this->normalize($row['data'][$key]), $parsed['rows']), fn ($value) => $value !== ''), true);
            }
            // Keep only file keys and at most two candidates: a second match is already ambiguous.
            QeLop::withTrashed()->select(['id_qe_lops', 'ihld_id', 'nama_lop', 'incident'])->toBase()->chunkById(500, function (Collection $lops) use (&$indexes, $wanted): void {
                foreach ($lops as $lop) {
                    foreach (array_keys($indexes) as $key) {
                        $value = $this->normalize($lop->$key);
                        if (isset($wanted[$key][$value]) && count($indexes[$key][$value] ?? []) < 2) {
                            $indexes[$key][$value][] = (int) $lop->id_qe_lops;
                        }
                    }
                }
            }, 'id_qe_lops');
            $seen = [];
            foreach ($parsed['rows'] as $offset => $row) {
                if ($offset % 25 === 0) {
                    $actor->refresh();
                    if (! Gate::forUser($actor)->allows('import-aiss')) {
                        throw new RuntimeException('Izin pengunggah berubah saat import. Proses dihentikan.');
                    }
                }
                DB::transaction(function () use ($batch, $actor, $row, $indexes, &$seen): void {
                    $data = $row['data'];
                    $result = ['row_number' => $row['row_number'], 'reference' => mb_substr($data['ihld_id'] ?: ($data['incident'] ?: $data['nama_lop']), 0, 255), 'payload' => $data, 'status' => 'skipped'];
                    $matchId = null;
                    $stage = null;
                    $message = 'Tidak ada LOP yang cocok berdasarkan ID IHLD, Nama LOP, maupun Incident.';
                    foreach ($indexes as $key => $values) {
                        $ids = $values[$this->normalize($data[$key])] ?? [];
                        if ($ids === []) {
                            continue;
                        }
                        $stage = $key;
                        if (count($ids) === 1) {
                            $matchId = $ids[0];
                        } else {
                            $message = "Pencocokan {$key} ambigu (lebih dari satu LOP). Periksa data terlebih dahulu.";
                        }
                        break;
                    }
                    if ($row['formula']) {
                        $message = 'Kolom identitas/status berisi formula. Ubah menjadi nilai teks sebelum import.';
                    } elseif ($data['status_aiss'] === '' || mb_strlen($data['status_aiss']) > 255) {
                        $message = 'Status AISS kosong atau melebihi 255 karakter; nilai lama tidak diubah.';
                    } elseif ($matchId !== null) {
                        $lop = $this->visibility->apply(QeLop::query()->whereKey($matchId), $actor)->lockForUpdate()->first();
                        if ($lop === null || ! Gate::forUser($actor)->allows('update', $lop)) {
                            $message = 'LOP cocok tidak tersedia dalam akses Anda atau sudah diarsipkan.';
                        } elseif (isset($seen[$matchId])) {
                            $message = "LOP sudah diproses pada baris {$seen[$matchId]} file ini; baris berikutnya dilewati.";
                        } elseif ($this->normalize($lop->$stage) !== $this->normalize($data[$stage])) {
                            $message = 'Identitas LOP berubah saat import. Upload ulang setelah memeriksa data terbaru.';
                        } else {
                            $changed = $this->lops->updateAissStatus($lop, $data['status_aiss'], $actor, $batch->uuid, $stage);
                            $seen[$matchId] = $row['row_number'];
                            $result['status'] = 'success';
                            $result['result'] = ['lop_id' => $matchId, 'match_stage' => $stage, 'changed' => $changed];
                            $stageLabel = match ($stage) {
                                'ihld_id' => '1 (ID IHLD)', 'nama_lop' => '2 (Nama LOP)', default => '3 (Incident)'
                            };
                            $message = ($changed ? 'Status AISS diperbarui' : 'Status AISS sudah sesuai (tanpa perubahan)')." · cocok melalui tahap {$stageLabel} · {$lop->nama_lop}.";
                        }
                    }
                    $result['message'] = $message;
                    $batch->rows()->create($result);
                });
                if (($offset + 1) % 25 === 0 || $offset + 1 === count($parsed['rows'])) {
                    $this->progress($batch);
                }
            }
            $batch->refresh();
            $batch->update(['status' => $batch->success_rows === $batch->total_rows ? 'completed' : 'partial', 'completed_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($batch, 'Import AISS terhenti. Periksa izin, migration, dan hasil baris yang sudah tersimpan. Hubungi administrator jika berulang.');
        }
    }

    public function fail(QeImportBatch $batch, string $message): void
    {
        if ($batch->refresh()->isFinished()) {
            return;
        }
        $this->progress($batch);
        $batch->update(['status' => 'failed', 'error_message' => $message, 'completed_at' => now()]);
    }

    private function progress(QeImportBatch $batch): void
    {
        $success = $batch->rows()->where('status', 'success')->count();
        $skipped = $batch->rows()->where('status', 'skipped')->count();
        $failed = $batch->rows()->where('status', 'failed')->count();
        $batch->update(['success_rows' => $success, 'failed_rows' => $failed, 'metadata' => [...($batch->metadata ?? []), 'skipped_rows' => $skipped, 'processed_rows' => $success + $skipped + $failed]]);
    }

    private function normalize(?string $value): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $value ?? '')));
    }
}
