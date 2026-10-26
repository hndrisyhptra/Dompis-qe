<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('roles')->insertOrIgnore(['code' => 'WAREHOUSE', 'name' => 'Warehouse', 'created_at' => $now, 'updated_at' => $now]);
        $roleId = DB::table('roles')->where('code', 'WAREHOUSE')->value('id');
        foreach (['view_ba_bongkar' => 'Lihat BA Bongkar', 'review_ba_bongkar' => 'Review BA Bongkar'] as $code => $name) {
            DB::table('permissions')->insertOrIgnore(['code' => $code, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            foreach ([$roleId, DB::table('roles')->where('code', 'SUPER_ADMIN')->value('id')] as $grantedRoleId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $grantedRoleId, 'permission_id' => DB::table('permissions')->where('code', $code)->value('id'), 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('roles')->where('code', 'WAREHOUSE')->value('id');
        if (DB::table('users')->where('role_id', $id)->exists()) {
            throw new RuntimeException('Rollback ditolak: role Warehouse masih digunakan akun.');
        }
        DB::table('roles')->where('id', $id)->delete();
        DB::table('permissions')->whereIn('code', ['view_ba_bongkar', 'review_ba_bongkar'])->delete();
    }
};
