<?php

namespace Tests\Feature;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeBoq;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_is_only_available_to_admin_and_super_admin(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();

        $this->actingAs($admin)->get(route('revenue.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('revenue.index'))->assertOk();
        $this->actingAs($technician)->get(route('revenue.index'))->assertForbidden();
    }

    public function test_revenue_uses_boq_value_and_completed_status_for_realization(): void
    {
        [$owner, $locations] = $this->locations();
        $this->lopWithBoq($owner, $locations['sda'], 'REV-SDA', 'completed', 100_000, ['odp']);
        $this->lopWithBoq($owner, $locations['sby'], 'REV-SBY', 'waiting_approval', 200_000, ['feeder']);
        $this->lopWithBoq($owner, $locations['dps'], 'REV-DPS', 'completed', 400_000, ['odp']);

        $this->actingAs($owner)
            ->get(route('revenue.index'))
            ->assertOk()
            ->assertSee('Financial Overview')
            ->assertSee('Nilai Usulan dan Realisasi per Branch')
            ->assertSee('Matrix Per Branch')
            ->assertSee('Trend Nilai Usulan Berdasarkan Segmen')
            ->assertDontSee('Pipeline Nilai')
            ->assertDontSee('LOP Bernilai Terbesar')
            ->assertViewHas('stats', fn (array $stats) => $stats['proposal_value'] === 700_000.0
                && $stats['actual_value'] === 500_000.0
                && $stats['gap_value'] === 200_000.0)
            ->assertViewHas('branchRows', fn ($rows) => $rows->count() === 3
                && $rows->firstWhere('branch', 'SIDOARJO REV')['actual_value'] === 100_000.0
                && $rows->firstWhere('branch', 'SURABAYA REV')['actual_value'] === 0.0)
            ->assertViewHas('segmentRows', fn ($rows) => $rows->first()['label'] === 'ODP'
                && $rows->first()['proposal_value'] === 500_000.0
                && $rows->firstWhere('label', 'Feeder')['proposal_value'] === 200_000.0);
    }

    public function test_area_branch_and_service_area_admin_revenue_scopes_are_enforced(): void
    {
        [$owner, $locations] = $this->locations();
        $this->lopWithBoq($owner, $locations['sda'], 'SCOPE-REV-SDA', 'completed', 100_000);
        $this->lopWithBoq($owner, $locations['sby'], 'SCOPE-REV-SBY', 'draft', 200_000);
        $this->lopWithBoq($owner, $locations['dps'], 'SCOPE-REV-DPS', 'completed', 400_000);

        $areaAdmin = User::factory()->role(UserRole::ADMIN->value)->create([
            'admin_scope_type' => AdminScopeType::AREA->value,
            'area_id' => $locations['area']->id_area,
        ]);
        $branchAdmin = User::factory()->role(UserRole::ADMIN->value)->create([
            'admin_scope_type' => AdminScopeType::BRANCH->value,
            'branch_id' => $locations['sda']->branch_id,
        ]);
        $serviceAdmin = User::factory()->role(UserRole::ADMIN->value)->create([
            'admin_scope_type' => AdminScopeType::SERVICE_AREA->value,
            'service_area_id' => $locations['sby']->id_service_area,
        ]);
        $serviceAdmin->serviceAreas()->sync([$locations['sby']->id_service_area]);

        $this->actingAs($areaAdmin)
            ->get(route('revenue.index'))
            ->assertViewHas('stats', fn (array $stats) => $stats['proposal_value'] === 300_000.0
                && $stats['actual_value'] === 100_000.0)
            ->assertViewHas('branchRows', fn ($rows) => $rows->pluck('branch')->all() === ['SURABAYA REV', 'SIDOARJO REV']);

        $this->actingAs($branchAdmin)
            ->get(route('revenue.index'))
            ->assertViewHas('stats', fn (array $stats) => $stats['proposal_value'] === 100_000.0
                && $stats['actual_value'] === 100_000.0);

        $this->actingAs($serviceAdmin)
            ->get(route('revenue.index'))
            ->assertViewHas('stats', fn (array $stats) => $stats['proposal_value'] === 200_000.0
                && $stats['actual_value'] === 0.0)
            ->assertViewHas('branchRows', fn ($rows) => $rows->pluck('branch')->all() === ['SURABAYA REV']);
    }

    private function locations(): array
    {
        $area = Area::create(['code' => 'RV3', 'name' => 'Area Revenue', 'is_active' => true]);
        $jatim = Region::query()->where('code', 'JATIM')->firstOrFail();
        $balnus = Region::query()->where('code', 'BALNUS')->firstOrFail();
        $jatim->update(['area_id' => $area->id_area]);

        $sdaBranch = Branch::create(['code' => 'RVSDA', 'name' => 'SIDOARJO REV', 'region' => $jatim->name, 'region_id' => $jatim->id_region, 'is_active' => true]);
        $sbyBranch = Branch::create(['code' => 'RVSBY', 'name' => 'SURABAYA REV', 'region' => $jatim->name, 'region_id' => $jatim->id_region, 'is_active' => true]);
        $dpsBranch = Branch::create(['code' => 'RVDPS', 'name' => 'DENPASAR REV', 'region' => $balnus->name, 'region_id' => $balnus->id_region, 'is_active' => true]);

        $sda = ServiceArea::create(['workzone' => 'RVSDA', 'name' => 'Sidoarjo Revenue', 'branch_id' => $sdaBranch->id_branch, 'region_id' => $jatim->id_region, 'is_active' => true]);
        $sby = ServiceArea::create(['workzone' => 'RVSBY', 'name' => 'Surabaya Revenue', 'branch_id' => $sbyBranch->id_branch, 'region_id' => $jatim->id_region, 'is_active' => true]);
        $dps = ServiceArea::create(['workzone' => 'RVDPS', 'name' => 'Denpasar Revenue', 'branch_id' => $dpsBranch->id_branch, 'region_id' => $balnus->id_region, 'is_active' => true]);
        $owner = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        return [$owner, compact('area', 'sda', 'sby', 'dps')];
    }

    private function lopWithBoq(
        User $owner,
        ServiceArea $serviceArea,
        string $incident,
        string $status,
        float $value,
        array $segments = ['odp'],
    ): QeLop {
        $lop = QeLop::create([
            'incident' => $incident,
            'nama_lop' => "Project {$incident}",
            'program_type' => 'recovery',
            'sto' => $serviceArea->workzone,
            'branch' => $serviceArea->branch->name,
            'branch_id' => $serviceArea->branch_id,
            'service_area_id' => $serviceArea->id_service_area,
            'segment' => $segments,
            'status_lop' => $status,
            'created_by' => $owner->id_user,
        ]);

        QeBoq::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'source' => 'manual',
            'status' => 'ready',
            'item_count' => 1,
            'grand_total' => $value,
            'created_by' => $owner->id_user,
            'updated_by' => $owner->id_user,
        ]);

        return $lop;
    }
}
