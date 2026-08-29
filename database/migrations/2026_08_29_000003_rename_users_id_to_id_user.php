<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ganti nama kolom PK users.id -> id_user sesuai skema Authentication.
     * MySQL 8.0+ mendukung "RENAME COLUMN", tapi MariaDB (dipakai XAMPP,
     * termasuk versi Anda) TIDAK - MariaDB butuh sintaks lama
     * "CHANGE COLUMN <old> <new> <type_lengkap>". PDO melaporkan driver
     * name yang sama ('mysql') untuk MySQL asli maupun MariaDB, jadi kita
     * tidak bisa membedakan lewat getDriverName() - solusinya pakai
     * CHANGE COLUMN untuk kasus driver 'mysql' karena sintaks itu didukung
     * di MySQL & MariaDB sekaligus. SQLite (dipakai saat testing lewat
     * phpunit.xml) tetap pakai RENAME COLUMN native.
     *
     * Baik CHANGE COLUMN maupun RENAME COLUMN sama-sama membuat foreign key
     * di tabel lain (qe_lops.created_by, qe_lop_assignments.technician_id/
     * assigned_by, qe_lop_histories.user_id) otomatis ikut menunjuk ke nama
     * kolom baru - TIDAK perlu mengubah migration modul LOP yang sudah jalan.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('ALTER TABLE users RENAME COLUMN id TO id_user');

            return;
        }

        DB::statement('ALTER TABLE users CHANGE id id_user BIGINT UNSIGNED AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('ALTER TABLE users RENAME COLUMN id_user TO id');

            return;
        }

        DB::statement('ALTER TABLE users CHANGE id_user id BIGINT UNSIGNED AUTO_INCREMENT');
    }
};
