<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CLAUDE.md (Database Authentication - users table) eksplisit: "email
     * nullable". Kolom email dari scaffold default Laravel masih NOT NULL -
     * baru ketahuan saat modul User Management dibangun (form create user
     * mengizinkan email kosong). Unique index tetap valid dengan banyak
     * NULL, sama seperti pola nik/username di migration sebelumnya.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
