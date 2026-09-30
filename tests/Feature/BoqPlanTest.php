<?php

namespace Tests\Feature;

use App\Enums\LopStatus;
use App\Enums\UserRole;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\QeLop;
use App\Models\User;
use App\Services\BoqPlanService;
use App\Services\TechnicianWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoqPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_boq_plan_can_be_saved_and_converted_on_pickup(): void
    {
        $this->seed();

        $admin = User::factory()->create(['status' => 'active']);
        $admin->role_id = \App\Models\Role::where('code', UserRole::ADMIN->value)->value('id_role');
        $admin->save();

        $tech = User::factory()->create(['status' => 'active']);
        $tech->role_id = \App\Models\Role::where('code', UserRole::TEKNISI->value)->value('id_role');
        $tech->save();

        $lop = QeLop::create([
            'incident' => 'INC-123',
            'nama_lop' => 'LOP TEST',
            'program_type' => \App\Enums\ProgramType::RECOVERY,
            'status_lop' => LopStatus::ASSIGNED,
            'created_by' => $admin->id_user,
        ]);

        $lop->assignments()->create([
            'technician_id' => $tech->id_user,
            'assigned_by' => $admin->id_user,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $materialType = DesignatorType::where('code', 'MATERIAL')->first();
        $jasaType = DesignatorType::where('code', 'JASA')->first();

        $mat = Designator::create([
            'code' => 'M-TEST',
            'item_name' => 'Material Test',
            'unit' => 'pcs',
            'designator_type_id' => $materialType->id_designator_type,
            'created_by' => $admin->id_user,
        ]);

        $jasa = Designator::create([
            'code' => 'J-TEST',
            'item_name' => 'Jasa Test',
            'unit' => 'ls',
            'designator_type_id' => $jasaType->id_designator_type,
            'created_by' => $admin->id_user,
        ]);

        $planService = app(BoqPlanService::class);
        $plan = $planService->save($lop, [
            ['designator_id' => $mat->id_designator, 'qty' => 5, 'unit_price' => 10000],
            ['designator_id' => $jasa->id_designator, 'qty' => 1, 'unit_price' => 50000],
        ], $admin);

        $this->assertDatabaseHas('qe_boq_plans', [
            'id_plan' => $plan->id_plan,
            'qe_lop_id' => $lop->id_qe_lops,
        ]);

        $this->assertDatabaseHas('qe_boq_plan_items', [
            'qe_boq_plan_id' => $plan->id_plan,
            'designator_id' => $mat->id_designator,
            'qty' => 5,
        ]);

        $workflowService = app(TechnicianWorkflowService::class);
        $workflowService->pickup($lop, $tech);

        $this->assertDatabaseHas('qe_boqs', [
            'qe_lop_id' => $lop->id_qe_lops,
        ]);

        $this->assertDatabaseHas('qe_material_reservations', [
            'qe_lop_id' => $lop->id_qe_lops,
            'technician_id' => $tech->id_user,
        ]);

        $this->assertDatabaseHas('qe_material_reservation_items', [
            'designator_id' => $mat->id_designator,
            'qty' => 5,
        ]);
    }
}
