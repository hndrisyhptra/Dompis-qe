<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom role sesuai daftar role di CLAUDE.md:
     * SUPER_ADMIN, ADMIN, TEKNISI, APPROVER, VIEWER.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'SUPER_ADMIN',
                'ADMIN',
                'TEKNISI',
                'APPROVER',
                'VIEWER',
            ])->default('VIEWER')->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
