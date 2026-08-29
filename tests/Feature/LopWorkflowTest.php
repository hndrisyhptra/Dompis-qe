<?php

namespace Tests\Feature;

use App\Enums\LopStatus;
use App\Enums\UserRole;
use App\Models\QeLop;
use App\Models\User;
use App\Services\LopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LopWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_lop_is_created_in_draft_status_and_history_recorded(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();

        $lop = app(LopService::class)->create([
            'kode_lop' => 'LOP-200',
            'nama_lop' => 'Recovery Jl. Diponegoro',
            'wbs_type' => 'recovery',
        ], $admin);

        $this->assertEquals(LopStatus::DRAFT, $lop->status_lop);
        $this->assertDatabaseHas('qe_lop_histories', [
            'qe_lop_id' => $lop->id_qe_lops,
            'status_after' => 'draft',
            'event_type' => 'created',
        ]);
    }

    public function test_assign_moves_draft_lop_to_assigned_and_does_not_touch_qe_lops_technician_column(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lop = app(LopService::class)->create([
            'kode_lop' => 'LOP-201',
            'nama_lop' => 'Preventive Jl. Kebon Jeruk',
            'wbs_type' => 'preventive',
        ], $admin);

        $assignment = app(LopService::class)->assign($lop, $teknisi, $admin);

        $lop->refresh();

        $this->assertEquals(LopStatus::ASSIGNED, $lop->status_lop);
        $this->assertEquals($teknisi->id_user, $assignment->technician_id);
        $this->assertEquals($teknisi->id_user, $lop->currentTechnician()->id_user);

        // technician_id sengaja tidak ada sebagai kolom di qe_lops.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('qe_lops', 'technician_id'));
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();

        $lop = app(LopService::class)->create([
            'kode_lop' => 'LOP-202',
            'nama_lop' => 'Recovery Jl. Asia Afrika',
            'wbs_type' => 'recovery',
        ], $admin);

        $this->expectException(\InvalidArgumentException::class);

        // draft -> completed melompati seluruh lifecycle, harus ditolak.
        app(LopService::class)->transitionStatus($lop, LopStatus::COMPLETED, $admin);
    }

    public function test_valid_full_lifecycle_transition_succeeds_and_each_step_is_logged(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();
        $approver = User::factory()->role(UserRole::APPROVER->value)->create();

        $service = app(LopService::class);

        $lop = $service->create([
            'kode_lop' => 'LOP-203',
            'nama_lop' => 'Relok Utilitas Jl. Cihampelas',
            'wbs_type' => 'relok_utilitas',
        ], $admin);

        $service->assign($lop, $teknisi, $admin);

        foreach ([
            LopStatus::PICKED_UP,
            LopStatus::SURVEY,
            LopStatus::PROGRESS,
            LopStatus::WAITING_APPROVAL,
        ] as $target) {
            $lop = $service->transitionStatus($lop, $target, $admin);
        }

        $lop = $service->transitionStatus($lop, LopStatus::COMPLETED, $approver);

        $this->assertEquals(LopStatus::COMPLETED, $lop->status_lop);

        // created + assigned + picked_up + survey + progress + waiting_approval + completed
        $this->assertEquals(7, $lop->histories()->count());
        $this->assertDatabaseHas('qe_lop_histories', [
            'qe_lop_id' => $lop->id_qe_lops,
            'status_before' => 'waiting_approval',
            'status_after' => 'completed',
        ]);
    }
}
