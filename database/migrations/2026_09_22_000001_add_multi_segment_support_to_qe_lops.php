<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah segment dari VARCHAR(30) menjadi JSON/TEXT agar bisa simpan array.
        // SQLite (untuk testing) tidak support JSON type, jadi pakai TEXT dengan cast array di Model.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Drop index lama yang hanya untuk single string
            try {
                Schema::table('qe_lops', function (Blueprint $table) {
                    $table->dropIndex('qe_lops_segment_idx');
                });
            } catch (\Throwable $e) {
                // index mungkin sudah tidak ada
            }

            // Backfill dulu sementara masih VARCHAR — bungkus "odp" -> '["odp"]' agar ALTER ke JSON valid
            $rows = DB::table('qe_lops')->select('id_qe_lops', 'segment')->get();
            foreach ($rows as $row) {
                $val = $row->segment;
                if ($val === null || $val === '') {
                    continue;
                }
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    continue;
                }
                if (str_contains($val, ',')) {
                    $parts = array_values(array_filter(array_map(fn ($v) => trim($v), explode(',', $val))));
                    $new = json_encode($parts);
                } else {
                    $new = json_encode([trim($val)]);
                }
                DB::table('qe_lops')->where('id_qe_lops', $row->id_qe_lops)->update(['segment' => $new]);
            }

            DB::statement('ALTER TABLE qe_lops MODIFY COLUMN segment JSON NULL');
        } else {
            // SQLite / pgsql fallback: ubah ke TEXT
            Schema::table('qe_lops', function (Blueprint $table) {
                $table->text('segment')->nullable()->change();
            });

            // Backfill untuk non-mysql
            $rows = DB::table('qe_lops')->select('id_qe_lops', 'segment')->get();
            foreach ($rows as $row) {
                $val = $row->segment;
                if ($val === null || $val === '') {
                    continue;
                }
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    continue;
                }
                if (str_contains($val, ',')) {
                    $parts = array_values(array_filter(array_map(fn ($v) => trim($v), explode(',', $val))));
                    $new = json_encode($parts);
                } else {
                    $new = json_encode([trim($val)]);
                }
                DB::table('qe_lops')->where('id_qe_lops', $row->id_qe_lops)->update(['segment' => $new]);
            }
        }
    }

    public function down(): void
    {
        // Kembalikan ke string tunggal (ambil elemen pertama array)
        $rows = DB::table('qe_lops')->select('id_qe_lops', 'segment')->get();
        foreach ($rows as $row) {
            $val = $row->segment;
            if ($val === null || $val === '') {
                continue;
            }
            $decoded = json_decode($val, true);
            if (is_array($decoded)) {
                $first = $decoded[0] ?? null;
                DB::table('qe_lops')->where('id_qe_lops', $row->id_qe_lops)->update(['segment' => $first]);
            }
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE qe_lops MODIFY COLUMN segment VARCHAR(30) NULL');
            Schema::table('qe_lops', function (Blueprint $table) {
                $table->index('segment', 'qe_lops_segment_idx');
            });
        } else {
            Schema::table('qe_lops', function (Blueprint $table) {
                $table->string('segment', 30)->nullable()->change();
            });
        }
    }
};
