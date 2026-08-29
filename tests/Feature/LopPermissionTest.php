<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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

        $response = $this->actingAs($admin)->post(route('lop.store'), [
            'kode_lop' => 'LOP-100',
            'nama_lop' => 'Recovery Jl. Merdeka',
            'wbs_type' => 'recovery',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qe_lops', ['kode_lop' => 'LOP-100']);
    }

    public function test_teknisi_cannot_create_lop(): void
    {
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $response = $this->actingAs($teknisi)->post(route('lop.store'), [
            'kode_lop' => 'LOP-101',
            'nama_lop' => 'Recovery Jl. Sudirman',
            'wbs_type' => 'recovery',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('qe_lops', ['kode_lop' => 'LOP-101']);
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
            'kode_lop' => 'LOP-104',
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
            'kode_lop' => 'LOP-102',
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
            'kode_lop' => 'LOP-103',
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
}
