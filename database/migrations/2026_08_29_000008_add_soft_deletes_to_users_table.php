<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * users adalah "data penting" (CLAUDE.md Database Rules) - hapus akun
     * lewat modul User Management jangan hard delete, pakai soft delete
     * (sama seperti qe_lops). "Nonaktifkan sementara" tetap lewat kolom
     * status (active/inactive), soft delete untuk "hapus" sungguhan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
