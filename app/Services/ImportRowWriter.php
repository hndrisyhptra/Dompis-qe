<?php

namespace App\Services;

use App\Models\QeImportBatch;
use Illuminate\Support\Facades\DB;

class ImportRowWriter
{
    /**
     * Menulis hasil import secara berkelompok untuk menghindari satu query
     * INSERT untuk setiap baris spreadsheet.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insert(QeImportBatch $batch, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();
        $records = array_map(fn (array $row): array => [
            'import_batch_id' => $batch->id_import_batch,
            'row_number' => $row['row_number'],
            'status' => $row['status'],
            'reference' => $row['reference'] ?? null,
            'message' => $row['message'] ?? null,
            'payload' => $this->json($row['payload'] ?? null),
            'result' => $this->json($row['result'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        foreach (array_chunk($records, 250) as $chunk) {
            DB::table('qe_import_rows')->insert($chunk);
        }
    }

    private function json(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? null : $json;
    }
}
