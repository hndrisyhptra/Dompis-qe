<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\QeLop;
use App\Models\QeLopAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LopMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_qe_lop_tables_are_created_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('qe_lops'));
        $this->assertTrue(Schema::hasColumns('qe_lops', [
            'id_qe_lops', 'incident', 'nama_lop', 'wbs_type', 'sto', 'branch',
            'area', 'segment', 'budget_type', 'job_description', 'ihld_id',
            'package_id', 'status_lop', 'created_by', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasTable('lop_name_formats'));
        $this->assertTrue(Schema::hasColumns('lop_name_formats', [
            'id_lop_name_format', 'template', 'is_active', 'created_by', 'updated_by',
        ]));

        $this->assertTrue(Schema::hasTable('qe_lop_assignments'));
        $this->assertTrue(Schema::hasColumns('qe_lop_assignments', [
            'id_qe_lop_assignments', 'qe_lop_id', 'technician_id',
            'assigned_by', 'assigned_at', 'unassigned_at', 'status',
        ]));

        $this->assertTrue(Schema::hasTable('qe_lop_histories'));
        $this->assertTrue(Schema::hasColumns('qe_lop_histories', [
            'id_qe_lop_histories', 'qe_lop_id', 'user_id',
            'status_before', 'status_after', 'event_type', 'note', 'meta',
        ]));

        // Skema users hasil modul Authentication (menggantikan kolom
        // enum "role" dari modul LOP sebelumnya).
        $this->assertTrue(Schema::hasColumns('users', [
            'id_user', 'role_id', 'nik', 'username', 'phone',
            'branch_id', 'status', 'last_login_at',
        ]));
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertFalse(Schema::hasColumn('users', 'id'));
    }

    public function test_only_one_active_assignment_per_lop_is_allowed_at_db_level(): void
    {
        $creator = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisiA = User::factory()->role(UserRole::TEKNISI->value)->create();
        $teknisiB = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lop = QeLop::create([
            'incident' => 'LOP-001',
            'nama_lop' => 'Test LOP',
            'wbs_type' => 'recovery',
            'status_lop' => 'draft',
            'created_by' => $creator->id_user,
        ]);

        QeLopAssignment::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'technician_id' => $teknisiA->id_user,
            'assigned_by' => $creator->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        QeLopAssignment::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'technician_id' => $teknisiB->id_user,
            'assigned_by' => $creator->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);
    }
}
