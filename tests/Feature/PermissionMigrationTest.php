<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasColumns('permissions', ['id', 'code', 'name', 'created_at', 'updated_at']));
    }

    public function test_role_permissions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('role_permissions'));
        $this->assertTrue(Schema::hasColumns('role_permissions', ['id', 'role_id', 'permission_id', 'created_at', 'updated_at']));
    }

    public function test_permissions_table_is_seeded_with_expected_codes(): void
    {
        $expected = [
            'manage_users', 'manage_roles', 'manage_master_data', 'system_setting',
            'create_lop', 'assign_lop', 'approve_evidence',
            'view_assigned_lop', 'pickup_lop', 'upload_evidence', 'survey',
            'view_dashboard', 'monitoring_progress', 'reporting',
            'review_evidence',
        ];

        $this->assertSame(count($expected), DB::table('permissions')->count());

        foreach ($expected as $code) {
            $this->assertDatabaseHas('permissions', ['code' => $code]);
        }
    }

    public function test_role_permissions_is_seeded_per_role(): void
    {
        $countFor = fn (string $roleCode) => DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->where('roles.code', $roleCode)
            ->count();

        $this->assertSame(3, $countFor('ADMIN'));
        $this->assertSame(4, $countFor('TEKNISI'));
        $this->assertSame(3, $countFor('MANAGER'));
        $this->assertSame(2, $countFor('APPROVER'));
        $this->assertSame(15, $countFor('SUPER_ADMIN'));
    }

    public function test_super_admin_role_permissions_covers_every_permission_code(): void
    {
        // Guard-rail: kalau nanti ada permission baru ditambah tapi lupa di-
        // grant ke SUPER_ADMIN, test ini gagal loud (bukan silent regression).
        $superAdminCount = DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->where('roles.code', 'SUPER_ADMIN')
            ->count();

        $this->assertSame(DB::table('permissions')->count(), $superAdminCount);
    }

    public function test_role_permissions_unique_constraint_prevents_duplicate_mapping(): void
    {
        $roleId = DB::table('roles')->where('code', 'ADMIN')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'create_lop')->value('id');

        $this->expectException(QueryException::class);

        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
