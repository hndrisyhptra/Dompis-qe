<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfill role_id dari kolom lama "role" (enum string, dibuat modul
     * LOP kemarin) lalu drop kolom lama. Nilai lama "VIEWER" TIDAK ada
     * padanannya di 5 role final (VIEWER dihapus) - baris dengan role
     * lama VIEWER akan dibiarkan role_id NULL, admin wajib assign ulang
     * manual lewat User Management setelah migration ini jalan.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $roleIds = DB::table('roles')->pluck('id', 'code');

        foreach (['SUPER_ADMIN', 'ADMIN', 'TEKNISI', 'MANAGER', 'APPROVER'] as $code) {
            if (! isset($roleIds[$code])) {
                continue;
            }

            DB::table('users')
                ->where('role', $code)
                ->update(['role_id' => $roleIds[$code]]);
        }

        Schema::table('users', function ($table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Tidak dibalik otomatis (lossy: role_id -> role enum string perlu
     * whitelist yang sama, dan VIEWER sudah tidak ada). Kalau perlu
     * rollback, tambahkan kolom role manual lalu backfill dari role_id.
     */
    public function down(): void
    {
        //
    }
};
