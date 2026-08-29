<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menegakkan aturan "1 assignment aktif per LOP" di level database lewat
     * partial/filtered unique index. Sintaks berbeda antara MySQL 8+ (functional
     * key: kolom generated boolean) dan SQLite/Postgres (native partial index),
     * jadi kita cabang berdasarkan driver supaya tetap portable untuk testing
     * (SQLite in-memory) maupun production (MySQL).
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX qe_lop_assignments_one_active_per_lop
                 ON qe_lop_assignments (qe_lop_id)
                 WHERE status = \'active\''
            );

            return;
        }

        // MySQL 8.0.13+ mendukung generated column + unique index sebagai
        // pengganti partial index (MySQL tidak punya native partial index).
        DB::statement(
            'ALTER TABLE qe_lop_assignments
             ADD COLUMN active_lop_guard BIGINT UNSIGNED
             GENERATED ALWAYS AS (CASE WHEN status = \'active\' THEN qe_lop_id ELSE NULL END) STORED'
        );

        DB::statement(
            'ALTER TABLE qe_lop_assignments
             ADD UNIQUE INDEX qe_lop_assignments_one_active_per_lop (active_lop_guard)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS qe_lop_assignments_one_active_per_lop');

            return;
        }

        DB::statement(
            'ALTER TABLE qe_lop_assignments DROP INDEX qe_lop_assignments_one_active_per_lop'
        );
        DB::statement(
            'ALTER TABLE qe_lop_assignments DROP COLUMN active_lop_guard'
        );
    }
};
