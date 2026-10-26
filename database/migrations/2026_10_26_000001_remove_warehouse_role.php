<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $roleId = DB::table('roles')->where('code', 'WAREHOUSE')->lockForUpdate()->value('id');
            if ($roleId && DB::table('users')->where('role_id', $roleId)->exists()) {
                throw new RuntimeException('Penghapusan role ditolak: pindahkan atau nonaktifkan dan lepaskan role akun Warehouse terlebih dahulu. Akun tidak dihapus otomatis.');
            }
            if ($roleId) {
                DB::table('role_permissions')->where('role_id', $roleId)->delete();
                DB::table('roles')->where('id', $roleId)->delete();
            }
            $permissions = DB::table('permissions')->whereIn('code', ['view_ba_bongkar', 'review_ba_bongkar'])->pluck('id');
            DB::table('role_permissions')->whereIn('permission_id', $permissions)->delete();
            DB::table('permissions')->whereIn('id', $permissions)->delete();
        });
    }

    public function down(): void
    {
        // Restores the former role/permission definitions, without changing any account or approval.
        (require __DIR__.'/2026_10_23_000002_add_warehouse_role.php')->up();
    }
};
