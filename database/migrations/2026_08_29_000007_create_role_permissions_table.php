<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pivot role <-> permission. Seed langsung di migration, sama seperti
     * roles & permissions (master data, bukan transaksional).
     *
     * SUPER_ADMIN sengaja diberi SEMUA kode permission (union), bukan cuma
     * 4 kode contoh di CLAUDE.md - SUPER_ADMIN sudah diperlakukan sebagai
     * superset ADMIN di UserRole::adminLevel() (modul LOP), jadi kalau di
     * tabel ini SUPER_ADMIN tidak punya create_lop itu bug, bukan desain.
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        $now = now();
        $allPermissionCodes = DB::table('permissions')->pluck('code')->all();

        $map = [
            'ADMIN' => ['create_lop', 'assign_lop', 'approve_evidence'],
            'TEKNISI' => ['view_assigned_lop', 'pickup_lop', 'upload_evidence', 'survey'],
            'MANAGER' => ['view_dashboard', 'monitoring_progress', 'reporting'],
            'APPROVER' => ['review_evidence', 'approve_evidence'],
            'SUPER_ADMIN' => $allPermissionCodes,
        ];

        $rows = [];

        foreach ($map as $roleCode => $permissionCodes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');

            foreach (array_unique($permissionCodes) as $permissionCode) {
                $permissionId = DB::table('permissions')->where('code', $permissionCode)->value('id');

                $rows[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('role_permissions')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
