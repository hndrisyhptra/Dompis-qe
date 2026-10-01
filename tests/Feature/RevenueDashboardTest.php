<?php

namespace Tests\Feature;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
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
        $this->lopWithBoq($owner, $locations['sda'], 'REV-SDA', 'completed', 100_000, 'recovery', ['odp']);
        $this->lopWithBoq($owner, $locations['sby'], 'REV-SBY', 'waiting_approval', 200_000, 'preventive', ['feeder']);
        $this->lopWithBoq($owner, $locations['dps'], 'REV-DPS', 'completed', 400_000, 'relok_utilitas', ['odp']);

        $this->actingAs($owner)
            ->get(route('revenue.index'))
            ->assertOk()
            ->assertSee('Revenue Overview')
            ->assertSee('Revenue per Program')
            ->assertSee('QE Recovery')
            ->assertSee('QE Preventive')
            ->assertSee('QE Relok Utilitas')
            ->assertDontSee('Plan dan Realisasi per Program')
            ->assertDontSee('Total Akumulasi')
            ->assertSee('Nilai Plan dan Realisasi per Branch')
            ->assertSee('Matrix Per Branch')
            ->assertSee('Breakdown Program')
            ->assertSee('Trend Nilai Plan Berdasarkan Segmen')
            ->assertDontSee('Pipeline Nilai')
            ->assertDontSee('LOP Bernilai Terbesar')
            ->assertViewHas('stats', fn (array $stats) => $stats['plan_value'] === 600_000.0
                && $stats['actual_value'] === 500_000.0
                && $stats['planned_actual_value'] === 400_000.0
                && $stats['recovery_actual_value'] === 100_000.0
                && $stats['gap_value'] === 200_000.0
                && $stats['realization_percentage'] === 67)
            ->assertViewHas('programRows', fn ($rows) => $rows->count() === 3
                && $rows->firstWhere('program', 'recovery')['plan_value'] === 0.0
                && $rows->firstWhere('program', 'recovery')['actual_value'] === 100_000.0
                && $rows->firstWhere('program', 'preventive')['plan_value'] === 200_000.0
                && $rows->firstWhere('program', 'relok_utilitas')['actual_value'] === 400_000.0)
            ->assertViewHas('branchRows', fn ($rows) => $rows->count() === 3
                && $rows->firstWhere('branch', 'SIDOARJO REV')['actual_value'] === 100_000.0
                && $rows->firstWhere('branch', 'SIDOARJO REV')['plan_value'] === 0.0
                && $rows->firstWhere('branch', 'SIDOARJO REV')['programs']->count() === 3
                && $rows->firstWhere('branch', 'SURABAYA REV')['actual_value'] === 0.0)
            ->assertViewHas('segmentRows', fn ($rows) => $rows->first()['label'] === 'ODP'
                && $rows->first()['plan_value'] === 400_000.0
                && $rows->firstWhere('label', 'Feeder')['plan_value'] === 200_000.0);
    }

    public function test_completed_legacy_lop_uses_snapshot_value_when_master_boq_is_missing(): void
    {
        [$owner, $locations] = $this->locations();
        QeLop::create([
            'incident' => 'REV-LEGACY-COMPLETE',
            'nama_lop' => 'Legacy Completed Revenue',
            'program_type' => 'relok_utilitas',
            'sto' => $locations['sda']->workzone,
            'branch' => $locations['sda']->branch->name,
            'branch_id' => $locations['sda']->branch_id,
            'service_area_id' => $locations['sda']->id_service_area,
            'segment' => ['odp'],
            'boq_snapshot' => ['grand_total' => 125_000],
            'status_lop' => 'completed',
            'created_by' => $owner->id_user,
        ]);

        $this->actingAs($owner)
            ->get(route('revenue.index'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['plan_value'] === 125_000.0
                && $stats['actual_value'] === 125_000.0
                && $stats['planned_actual_value'] === 125_000.0
                && $stats['gap_value'] === 0.0
                && $stats['realization_percentage'] === 100);
    }

    public function test_matrix_includes_accessible_branch_without_lop_and_exposes_program_breakdown(): void
    {
        [$owner, $locations] = $this->locations();
        $emptyBranch = Branch::create([
            'code' => 'RVEMPTY',
            'name' => 'EMPTY REVENUE BRANCH',
            'region' => $locations['jatim']->name,
            'region_id' => $locations['jatim']->id_region,
            'is_active' => true,
        ]);
        $this->lopWithBoq($owner, $locations['sda'], 'REV-MATRIX-SDA', 'completed', 150_000, 'preventive');

        $this->actingAs($owner)
            ->get(route('revenue.index'))
            ->assertOk()
            ->assertSee($emptyBranch->name)
            ->assertViewHas('branchRows', function ($rows) use ($emptyBranch): bool {
                $empty = $rows->firstWhere('branch_id', $emptyBranch->id_branch);
                $sidoarjo = $rows->firstWhere('branch', 'SIDOARJO REV');

                return $rows->count() === 4
                    && $empty['total_lops'] === 0
                    && $empty['plan_value'] === 0.0
                    && $empty['actual_value'] === 0.0
                    && $empty['programs']->count() === 3
                    && $sidoarjo['programs']->firstWhere('program', 'preventive')['plan_value'] === 150_000.0
                    && $sidoarjo['programs']->firstWhere('program', 'preventive')['actual_value'] === 150_000.0;
            });
    }

    public function test_completed_lop_without_plan_boq_uses_technician_actual_reservation_for_revenue(): void
    {
        [$owner, $locations] = $this->locations();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $materialType = DesignatorType::firstOrCreate(
            ['code' => 'MATERIAL'],
            ['name' => 'Material', 'is_active' => true],
        );
        $designator = Designator::create([
            'code' => 'M-REV-FALLBACK',
            'item_name' => 'Material Revenue Fallback',
            'unit' => 'meter',
            'designator_type_id' => $materialType->id_designator_type,
            'created_by' => $owner->id_user,
        ]);
        $olderPackage = Package::create(['code' => 'Paket-5', 'name' => 'Paket 5 Revenue']);
        $referencePackage = Package::create(['code' => 'Paket-10', 'name' => 'Paket 10 Revenue']);
        DesignatorPackagePrice::create([
            'designator_id' => $designator->id_designator,
            'package_id' => $olderPackage->id_package,
            'price' => 1_000,
        ]);
        DesignatorPackagePrice::create([
            'designator_id' => $designator->id_designator,
            'package_id' => $referencePackage->id_package,
            'price' => 1_500,
        ]);
        $lop = QeLop::create([
            'incident' => 'REV-RESERVATION-COMPLETE',
            'nama_lop' => 'Completed from Technician Reservation',
            'program_type' => 'recovery',
            'sto' => $locations['sda']->workzone,
            'branch' => $locations['sda']->branch->name,
            'branch_id' => $locations['sda']->branch_id,
            'service_area_id' => $locations['sda']->id_service_area,
            'status_lop' => 'completed',
            'created_by' => $owner->id_user,
        ]);
        $reservation = $lop->materialReservation()->create([
            'technician_id' => $technician->id_user,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $reservation->items()->create([
            'designator_id' => $designator->id_designator,
            'qty' => 10,
            'qty_actual' => 8,
        ]);
        $balnusLop = QeLop::create([
            'incident' => 'REV-RESERVATION-BALNUS',
            'nama_lop' => 'Completed Reservation Balnus',
            'program_type' => 'recovery',
            'sto' => $locations['dps']->workzone,
            'branch' => $locations['dps']->branch->name,
            'branch_id' => $locations['dps']->branch_id,
            'service_area_id' => $locations['dps']->id_service_area,
            'status_lop' => 'completed',
            'created_by' => $owner->id_user,
        ]);
        $balnusReservation = $balnusLop->materialReservation()->create([
            'technician_id' => $technician->id_user,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $balnusReservation->items()->create([
            'designator_id' => $designator->id_designator,
            'qty' => 3,
            'qty_actual' => 2,
        ]);

        $this->actingAs($owner)
            ->get(route('revenue.index'))
            ->assertOk()
            ->assertSee('JATIM/JATENG DIY memakai Paket 5')
            ->assertViewHas('stats', fn (array $stats) => $stats['plan_value'] === 0.0
                && $stats['actual_value'] === 11_000.0
                && $stats['recovery_actual_value'] === 11_000.0)
            ->assertViewHas('programRows', fn ($rows) => $rows->firstWhere('program', 'recovery')['actual_value'] === 11_000.0)
            ->assertViewHas('branchRows', fn ($rows) => $rows->firstWhere('branch', 'SIDOARJO REV')['actual_value'] === 8_000.0
                && $rows->firstWhere('branch', 'DENPASAR REV')['actual_value'] === 3_000.0);
    }

    public function test_area_region_branch_and_service_area_admin_revenue_scopes_are_enforced(): void
    {
        [$owner, $locations] = $this->locations();
        $this->lopWithBoq($owner, $locations['sda'], 'SCOPE-REV-SDA', 'completed', 100_000, 'recovery');
        $this->lopWithBoq($owner, $locations['sby'], 'SCOPE-REV-SBY', 'draft', 200_000, 'preventive');
        $this->lopWithBoq($owner, $locations['dps'], 'SCOPE-REV-DPS', 'completed', 400_000, 'relok_utilitas');

        $areaAdmin = User::factory()->role(UserRole::ADMIN->value)->create([
            'admin_scope_type' => AdminScopeType::AREA->value,
            'area_id' => $locations['area']->id_area,
        ]);
        $branchAdmin = User::factory()->role(UserRole::ADMIN->value)->create([
            'admin_scope_type' => AdminScopeType::BRANCH->value,
            'branch_id' => $locations['sda']->branch_id,
        ]);
        $regionAdmin = User::factory()->role(UserRole::ADMIN->value)->create([
            'admin_scope_type' => AdminScopeType::REGION->value,
            'region_id' => $locations['jatim']->id_region,
        ]);
        $serviceAdmin = User::factory()->role(UserRole::ADMIN->value)->create([
            'admin_scope_type' => AdminScopeType::SERVICE_AREA->value,
            'service_area_id' => $locations['sby']->id_service_area,
        ]);
        $serviceAdmin->serviceAreas()->sync([$locations['sby']->id_service_area]);

        $this->actingAs($areaAdmin)
            ->get(route('revenue.index'))
            ->assertViewHas('stats', fn (array $stats) => $stats['plan_value'] === 200_000.0
                && $stats['actual_value'] === 100_000.0)
            ->assertViewHas('branchRows', fn ($rows) => $rows->pluck('branch')->all() === ['SIDOARJO REV', 'SURABAYA REV']);

        $this->actingAs($regionAdmin)
            ->get(route('revenue.index'))
            ->assertViewHas('stats', fn (array $stats) => $stats['plan_value'] === 200_000.0
                && $stats['actual_value'] === 100_000.0);

        $this->actingAs($branchAdmin)
            ->get(route('revenue.index'))
            ->assertViewHas('stats', fn (array $stats) => $stats['plan_value'] === 0.0
                && $stats['actual_value'] === 100_000.0);

        $this->actingAs($serviceAdmin)
            ->get(route('revenue.index'))
            ->assertViewHas('stats', fn (array $stats) => $stats['plan_value'] === 200_000.0
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

        return [$owner, compact('area', 'jatim', 'sda', 'sby', 'dps')];
    }

    private function lopWithBoq(
        User $owner,
        ServiceArea $serviceArea,
        string $incident,
        string $status,
        float $value,
        string $program = 'recovery',
        array $segments = ['odp'],
    ): QeLop {
        $lop = QeLop::create([
            'incident' => $incident,
            'nama_lop' => "Project {$incident}",
            'program_type' => $program,
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
