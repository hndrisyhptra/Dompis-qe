<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ganti nama kolom PK branches.id -> id_branch, menyelaraskan dengan
     * konvensi custom-PK yang sudah dipakai users.id_user, qe_lops.id_qe_lops,
     * dst. Pola migration ini sama persis dengan
     * 2026_08_29_000003_rename_users_id_to_id_user.php - lihat migration itu
     * untuk penjelasan kenapa MySQL/MariaDB pakai CHANGE COLUMN (bukan
     * RENAME COLUMN) sementara SQLite/Postgres pakai RENAME COLUMN native.
     *
     * FK users.branch_id -> branches.id otomatis ikut menunjuk ke nama
     * kolom baru setelah rename - tidak perlu mengubah migration users.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('ALTER TABLE branches RENAME COLUMN id TO id_branch');

            return;
        }

        DB::statement('ALTER TABLE branches CHANGE id id_branch BIGINT UNSIGNED AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('ALTER TABLE branches RENAME COLUMN id_branch TO id');

            return;
        }

        DB::statement('ALTER TABLE branches CHANGE id_branch id BIGINT UNSIGNED AUTO_INCREMENT');
    }
};
