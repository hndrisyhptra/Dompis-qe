<?php

namespace App\Services;

<<<<<<< HEAD
use App\Models\Designator;
use App\Models\DesignatorCategory;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
=======
use App\Enums\DesignatorType;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
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
<<<<<<< HEAD
     * Kolom CSV: code,item_name,unit,type[,category]
     * - type    : kode ATAU nama Tipe Designator (mis. MATERIAL / Material). Wajib.
     * - category: kode ATAU nama Kategori Designator. Opsional.
     */
    public function importDesignators(UploadedFile $file, User $actor): array
    {
        [$rows, $parseErrors] = $this->readCsv($file, ['code', 'item_name', 'unit', 'type'], ['category']);
=======
     * Kolom CSV: code,item_name,unit,type
     */
    public function importDesignators(UploadedFile $file, User $actor): array
    {
        [$rows, $parseErrors] = $this->readCsv($file, ['code', 'item_name', 'unit', 'type']);
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c

        $errors = $parseErrors;
        $valid = [];

<<<<<<< HEAD
        $typeIds = $this->lookupIndex(DesignatorType::query()->pluck('id_designator_type', 'code'), DesignatorType::query()->pluck('id_designator_type', 'name'));
        $categoryIds = $this->lookupIndex(DesignatorCategory::query()->pluck('id_designator_category', 'code'), DesignatorCategory::query()->pluck('id_designator_category', 'name'));

=======
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
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
<<<<<<< HEAD

            $typeId = $typeIds[mb_strtoupper($row['type'])] ?? null;
            if ($typeId === null) {
                $rowErrors[] = "type '{$row['type']}' tidak ditemukan di Master Tipe Designator";
            }

            $categoryId = null;
            if (($row['category'] ?? '') !== '') {
                $categoryId = $categoryIds[mb_strtoupper($row['category'])] ?? null;
                if ($categoryId === null) {
                    $rowErrors[] = "category '{$row['category']}' tidak ditemukan di Master Kategori Designator";
                }
=======
            if (DesignatorType::tryFrom($row['type']) === null) {
                $rowErrors[] = "type '{$row['type']}' tidak valid (harus material atau jasa)";
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
            }

            if ($rowErrors) {
                $errors[] = "Baris {$lineNumber}: ".implode(', ', $rowErrors);

                continue;
            }

<<<<<<< HEAD
            $valid[] = [
                'code' => $row['code'],
                'item_name' => $row['item_name'],
                'unit' => $row['unit'],
                'designator_type_id' => $typeId,
                'designator_category_id' => $categoryId,
            ];
=======
            $valid[] = $row;
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
        }

        if ($errors) {
            return ['imported' => 0, 'errors' => $errors];
        }

        $now = now();

        DB::transaction(function () use ($valid, $actor, $now) {
            $values = array_map(fn ($row) => [
<<<<<<< HEAD
                ...$row,
=======
                'code' => $row['code'],
                'item_name' => $row['item_name'],
                'unit' => $row['unit'],
                'type' => $row['type'],
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
                'created_by' => $actor->id_user,
                'updated_by' => $actor->id_user,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $valid);

            Designator::withTrashed()->upsert(
                $values,
                ['code'],
<<<<<<< HEAD
                ['item_name', 'unit', 'designator_type_id', 'designator_category_id', 'updated_by', 'deleted_at', 'updated_at']
=======
                ['item_name', 'unit', 'type', 'updated_by', 'deleted_at', 'updated_at']
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
            );
        });

        return ['imported' => count($valid), 'errors' => []];
    }

    /**
<<<<<<< HEAD
     * Gabung index by-code dan by-name jadi satu peta UPPERCASE => id.
     */
    private function lookupIndex($byCode, $byName): array
    {
        $index = [];
        foreach ($byName as $name => $id) {
            $index[mb_strtoupper((string) $name)] = $id;
        }
        foreach ($byCode as $code => $id) {
            $index[mb_strtoupper((string) $code)] = $id;
        }

        return $index;
    }

    /**
=======
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
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
<<<<<<< HEAD
     * nama kolom). $optionalColumns disertakan di tiap baris bila header-nya
     * ada (kalau tidak ada, diisi string kosong) tanpa bikin import gagal.
     */
    private function readCsv(UploadedFile $file, array $expectedColumns, array $optionalColumns = []): array
=======
     * nama kolom).
     */
    private function readCsv(UploadedFile $file, array $expectedColumns): array
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
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
<<<<<<< HEAD
            foreach ([...$expectedColumns, ...$optionalColumns] as $column) {
=======
            foreach ($expectedColumns as $column) {
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
                $row[$column] = trim((string) ($assoc[$column] ?? ''));
            }

            $rows[$lineNumber] = $row;
        }

        fclose($handle);

        return [$rows, []];
    }
}
