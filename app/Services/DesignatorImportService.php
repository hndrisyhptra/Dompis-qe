<?php

namespace App\Services;

use App\Enums\DesignatorType;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Import CSV untuk modul Master Designator (Designator & KHS). Ditarik jadi
 * Service (bukan langsung di controller) karena logikanya genuinely
 * kompleks: parsing, validasi per baris, all-or-nothing transaction -
 * berbeda dari CRUD sederhana lain di modul ini yang cukup ditangani
 * langsung di controller.
 *
 * Kontrak: validasi SEMUA baris dulu - kalau ada satu saja yang salah,
 * TIDAK ADA yang disimpan (rollback penuh), errors dikembalikan lengkap
 * supaya user bisa perbaiki file sekali jalan. Upsert by kode (natural
 * key) supaya re-upload file yang sama idempoten, dan me-revive baris yang
 * sebelumnya soft-deleted kalau kodenya muncul lagi di file.
 */
class DesignatorImportService
{
    /**
     * Kolom CSV: code,item_name,unit,type
     */
    public function importDesignators(UploadedFile $file, User $actor): array
    {
        [$rows, $parseErrors] = $this->readCsv($file, ['code', 'item_name', 'unit', 'type']);

        $errors = $parseErrors;
        $valid = [];

        foreach ($rows as $lineNumber => $row) {
            $rowErrors = [];

            if ($row['code'] === '') {
                $rowErrors[] = 'code wajib diisi';
            }
            if ($row['item_name'] === '') {
                $rowErrors[] = 'item_name wajib diisi';
            }
            if ($row['unit'] === '') {
                $rowErrors[] = 'unit wajib diisi';
            }
            if (DesignatorType::tryFrom($row['type']) === null) {
                $rowErrors[] = "type '{$row['type']}' tidak valid (harus material atau jasa)";
            }

            if ($rowErrors) {
                $errors[] = "Baris {$lineNumber}: ".implode(', ', $rowErrors);

                continue;
            }

            $valid[] = $row;
        }

        if ($errors) {
            return ['imported' => 0, 'errors' => $errors];
        }

        $now = now();

        DB::transaction(function () use ($valid, $actor, $now) {
            $values = array_map(fn ($row) => [
                'code' => $row['code'],
                'item_name' => $row['item_name'],
                'unit' => $row['unit'],
                'type' => $row['type'],
                'created_by' => $actor->id_user,
                'updated_by' => $actor->id_user,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $valid);

            Designator::withTrashed()->upsert(
                $values,
                ['code'],
                ['item_name', 'unit', 'type', 'updated_by', 'deleted_at', 'updated_at']
            );
        });

        return ['imported' => count($valid), 'errors' => []];
    }

    /**
     * Kolom CSV: designator_code,package_code,price
     */
    public function importPrices(UploadedFile $file, User $actor): array
    {
        [$rows, $parseErrors] = $this->readCsv($file, ['designator_code', 'package_code', 'price']);

        $errors = $parseErrors;
        $valid = [];

        $designatorIds = Designator::pluck('id_designator', 'code');
        $packageIds = Package::pluck('id_package', 'code');

        foreach ($rows as $lineNumber => $row) {
            $rowErrors = [];

            $designatorId = $designatorIds[$row['designator_code']] ?? null;
            $packageId = $packageIds[$row['package_code']] ?? null;

            if (! $designatorId) {
                $rowErrors[] = "designator_code '{$row['designator_code']}' tidak ditemukan";
            }
            if (! $packageId) {
                $rowErrors[] = "package_code '{$row['package_code']}' tidak ditemukan";
            }
            if (! is_numeric($row['price']) || (float) $row['price'] < 0) {
                $rowErrors[] = "price '{$row['price']}' tidak valid";
            }

            if ($rowErrors) {
                $errors[] = "Baris {$lineNumber}: ".implode(', ', $rowErrors);

                continue;
            }

            $valid[] = [
                'designator_id' => $designatorId,
                'package_id' => $packageId,
                'price' => (float) $row['price'],
            ];
        }

        if ($errors) {
            return ['imported' => 0, 'errors' => $errors];
        }

        $now = now();

        DB::transaction(function () use ($valid, $actor, $now) {
            $values = array_map(fn ($row) => [
                'designator_id' => $row['designator_id'],
                'package_id' => $row['package_id'],
                'price' => $row['price'],
                'created_by' => $actor->id_user,
                'updated_by' => $actor->id_user,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $valid);

            DesignatorPackagePrice::withTrashed()->upsert(
                $values,
                ['designator_id', 'package_id'],
                ['price', 'updated_by', 'deleted_at', 'updated_at']
            );
        });

        return ['imported' => count($valid), 'errors' => []];
    }

    /**
     * Baca CSV, kembalikan [baris_ternormalisasi(1-indexed sesuai nomor
     * baris file), error_parsing]. Baris pertama dianggap header dan
     * dicocokkan terhadap $expectedColumns (urutan bebas, dicocokkan by
     * nama kolom).
     */
    private function readCsv(UploadedFile $file, array $expectedColumns): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return [[], ['File tidak bisa dibaca.']];
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [[], ['File CSV kosong.']];
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $missing = array_diff($expectedColumns, $header);

        if ($missing) {
            fclose($handle);

            return [[], ['Header CSV tidak lengkap, kolom hilang: '.implode(', ', $missing)]];
        }

        $rows = [];
        $lineNumber = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($line === [null] || $line === false) {
                continue;
            }

            $assoc = @array_combine($header, $line);

            if ($assoc === false) {
                continue;
            }

            $row = [];
            foreach ($expectedColumns as $column) {
                $row[$column] = trim((string) ($assoc[$column] ?? ''));
            }

            $rows[$lineNumber] = $row;
        }

        fclose($handle);

        return [$rows, []];
    }
}
