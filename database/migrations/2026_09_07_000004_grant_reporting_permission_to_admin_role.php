<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Beri permission `reporting` ke role ADMIN supaya admin operasional bisa
     * membuka menu Laporan (BOQ Actual / Sisa Material) - terscope ke branch-nya.
     * MANAGER & SUPER_ADMIN sudah punya lewat seeder role_permissions awal.
     * Idempoten.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'ADMIN')->value('id');
        $permId = DB::table('permissions')->where('code', 'reporting')->value('id');

        if ($roleId && $permId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'ADMIN')->value('id');
        $permId = DB::table('permissions')->where('code', 'reporting')->value('id');

        if ($roleId && $permId) {
            DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permId)
                ->delete();
        }
    }
};
