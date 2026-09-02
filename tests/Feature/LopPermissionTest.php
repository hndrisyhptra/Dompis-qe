<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LopPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_lop(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);

        $response = $this->actingAs($admin)->post(route('lop.store'), [
            'incident' => 'LOP-100',
            'nama_lop' => 'Recovery Jl. Merdeka',
            'wbs_type' => 'recovery',
            'sto' => 'SDA',
            'branch' => 'SIDOARJO',
            'area' => '3',
            'segment' => 'odp',
            'job_description' => 'Recovery Jl. Merdeka',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qe_lops', ['incident' => 'LOP-100']);
    }

    public function test_teknisi_cannot_create_lop(): void
    {
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $response = $this->actingAs($teknisi)->post(route('lop.store'), [
            'incident' => 'LOP-101',
            'nama_lop' => 'Recovery Jl. Sudirman',
            'wbs_type' => 'recovery',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('qe_lops', ['incident' => 'LOP-101']);
    }

    /**
     * Regression: AssignLopRequest sempat mengecek kolom lama users.id/role
     * (sebelum rename ke id_user/role_id) - lolos lewat LopService langsung
     * (LopWorkflowTest) tapi gagal 500 lewat HTTP karena tidak ada test yang
     * benar-benar submit assign lewat route sampai ditemukan manual.
     */
    public function test_admin_can_assign_technician_via_http(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lop = QeLop::create([
            'incident' => 'LOP-104',
            'nama_lop' => 'Recovery Jl. Braga',
            'wbs_type' => 'recovery',
            'status_lop' => 'draft',
            'created_by' => $admin->id_user,
        ]);

        $response = $this->actingAs($admin)->post(route('lop.assign', $lop), [
            'technician_id' => $teknisi->id_user,
        ]);

        $response->assertRedirect(route('lop.show', $lop));
        $this->assertDatabaseHas('qe_lop_assignments', [
            'qe_lop_id' => $lop->id_qe_lops,
            'technician_id' => $teknisi->id_user,
            'status' => 'active',
        ]);
    }

    public function test_manager_cannot_assign_technician(): void
    {
        // MANAGER hanya broad-visibility (read), bukan admin-level - tidak
        // boleh melakukan aksi assign teknisi (lihat QeLopPolicy::assign()).
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $manager = User::factory()->role(UserRole::MANAGER->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lop = QeLop::create([
            'incident' => 'LOP-102',
            'nama_lop' => 'Preventive Jl. Thamrin',
            'wbs_type' => 'preventive',
            'status_lop' => 'draft',
            'created_by' => $admin->id_user,
        ]);

        $response = $this->actingAs($manager)->post(route('lop.assign', $lop), [
            'technician_id' => $teknisi->id_user,
        ]);

        $response->assertForbidden();
    }

    public function test_teknisi_cannot_transition_status_of_lop_not_assigned_to_them(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lop = QeLop::create([
            'incident' => 'LOP-103',
            'nama_lop' => 'Relok Utilitas Jl. Gatot Subroto',
            'wbs_type' => 'relok_utilitas',
            'status_lop' => 'assigned',
            'created_by' => $admin->id_user,
        ]);

        $response = $this->actingAs($teknisi)->post(route('lop.transition', $lop), [
            'status' => 'picked_up',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_inbox_only_contains_lops_created_by_logged_in_admin_even_before_assignment(): void
    {
        $adminA = User::factory()->role(UserRole::ADMIN->value)->create();
        $adminB = User::factory()->role(UserRole::ADMIN->value)->create();
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create([
            'name' => 'Teknisi Modal Test',
        ]);

        QeLop::create([
            'incident' => 'LOP-ADMIN-A', 'nama_lop' => 'Project Admin A',
            'wbs_type' => 'recovery', 'status_lop' => 'draft',
            'created_by' => $adminA->id_user,
        ]);

        $lopAdminB = QeLop::create([
            'incident' => 'LOP-ADMIN-B', 'nama_lop' => 'Project Admin B',
            'wbs_type' => 'recovery', 'status_lop' => 'assigned',
            'created_by' => $adminB->id_user,
        ]);
        $lopAdminB->assignments()->create([
            'technician_id' => $technician->id_user,
            'assigned_by' => $adminA->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($adminA)
            ->get(route('lop.index'))
            ->assertOk()
            ->assertSee('LOP-ADMIN-A')
            ->assertDontSee('LOP-ADMIN-B')
            ->assertSee('Assign')
            ->assertSee('Teknisi Modal Test')
            ->assertSee('Input LOP Baru');

        $this->actingAs($superAdmin)
            ->get(route('lop.index'))
            ->assertRedirect(route('evidence-approval.index'));
    }

    public function test_assign_from_inbox_redirects_back_to_inbox(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = QeLop::create([
            'incident' => 'LOP-ASSIGN-INBOX', 'nama_lop' => 'Assign dari Inbox',
            'wbs_type' => 'recovery', 'status_lop' => 'draft',
            'created_by' => $admin->id_user,
        ]);

        $this->actingAs($admin)->post(route('lop.assign', $lop), [
            'technician_id' => $technician->id_user,
            'return_to' => 'index',
        ])->assertRedirect(route('lop.index'));

        $this->assertDatabaseHas('qe_lop_assignments', [
            'qe_lop_id' => $lop->id_qe_lops,
            'technician_id' => $technician->id_user,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('lop.index'))
            ->assertOk()
            ->assertSee('Detail LOP')
            ->assertSee('Reassign Teknisi')
            ->assertSee('Tracking Riwayat')
            ->assertSee('Review Evidence')
            ->assertSee('Hapus Assignment');
    }

    public function test_admin_can_remove_wrong_assignment_before_pickup(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $otherAdmin = User::factory()->role(UserRole::ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = QeLop::create([
            'incident' => 'LOP-UNASSIGN', 'nama_lop' => 'Salah Teknisi',
            'wbs_type' => 'recovery', 'status_lop' => 'assigned',
            'created_by' => $admin->id_user,
        ]);
        $assignment = $lop->assignments()->create([
            'technician_id' => $technician->id_user,
            'assigned_by' => $admin->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($otherAdmin)
            ->delete(route('lop.unassign', $lop))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('lop.unassign', $lop))
            ->assertRedirect(route('lop.index'));

        $this->assertDatabaseHas('qe_lop_assignments', [
            'id_qe_lop_assignments' => $assignment->id_qe_lop_assignments,
            'status' => 'replaced',
        ]);
        $this->assertDatabaseHas('qe_lops', [
            'id_qe_lops' => $lop->id_qe_lops,
            'status_lop' => 'draft',
        ]);
        $this->assertDatabaseHas('qe_lop_histories', [
            'qe_lop_id' => $lop->id_qe_lops,
            'status_before' => 'assigned',
            'status_after' => 'draft',
        ]);
    }
}
