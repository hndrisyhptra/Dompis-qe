<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeBoq;
use App\Models\QeLop;
use App\Models\User;
use App\Services\LopBoqProjectionService;
use App\Services\LopBoqValueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LopBoqProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_actual_automatically_includes_paired_service_without_adding_it_to_reservation(): void
    {
        $owner = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        [$material, $service] = $this->pairedDesignators($owner, 'REC');
        $package = Package::create(['code' => 'Paket-5', 'name' => 'Paket 5']);
        DesignatorPackagePrice::create(['designator_id' => $material->id_designator, 'package_id' => $package->id_package, 'price' => 100]);
        DesignatorPackagePrice::create(['designator_id' => $service->id_designator, 'package_id' => $package->id_package, 'price' => 50]);

        $lop = $this->lop($owner, 'recovery', $package);
        $reservation = $lop->materialReservation()->create([
            'technician_id' => $technician->id_user,
            'status' => 'draft',
        ]);
        $reservation->items()->create([
            'designator_id' => $material->id_designator,
            'qty' => 10,
            'qty_actual' => 7,
        ]);

        $actual = app(LopBoqProjectionService::class)->report($lop->fresh(), 'actual');
        $sisa = app(LopBoqProjectionService::class)->report($lop->fresh(), 'sisa');
        $summary = app(LopBoqValueService::class)->summarize($lop->fresh());

        $this->assertFalse($actual['has_plan']);
        $this->assertSame(['M-REC', 'J-REC'], collect($actual['lines'])->pluck('designator_code')->all());
        $this->assertSame('auto_service', collect($actual['lines'])->firstWhere('type', 'JASA')['source']);
        $this->assertSame(1050.0, $actual['grand']['total_actual']);
        $this->assertCount(1, $sisa['lines']);
        $this->assertSame(3.0, $sisa['lines'][0]['sisa']);
        $this->assertCount(1, $reservation->fresh()->items);
        $this->assertSame(350.0, $summary['service_total']);
        $this->assertSame(700.0, $summary['material_total']);
    }

    public function test_preventive_keeps_imported_plan_separate_from_actual_and_material_remainder(): void
    {
        $owner = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        [$material, $service] = $this->pairedDesignators($owner, 'PREV');
        $package = Package::create(['code' => 'Paket-5', 'name' => 'Paket 5']);
        $lop = $this->lop($owner, 'preventive', $package);
        $boq = QeBoq::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'package_id' => $package->id_package,
            'source' => 'import',
            'status' => 'ready',
            'item_count' => 2,
            'grand_total' => 700,
            'created_by' => $owner->id_user,
            'updated_by' => $owner->id_user,
        ]);
        $boq->items()->createMany([
            ['designator_id' => $material->id_designator, 'designator_code' => 'M-PREV', 'item_name' => 'Material PREV', 'unit' => 'meter', 'type' => 'MATERIAL', 'qty' => 5, 'unit_price' => 100, 'total_price' => 500],
            ['designator_id' => $service->id_designator, 'designator_code' => 'J-PREV', 'item_name' => 'Jasa PREV', 'unit' => 'meter', 'type' => 'JASA', 'qty' => 5, 'unit_price' => 40, 'total_price' => 200],
        ]);
        $reservation = $lop->materialReservation()->create([
            'technician_id' => $technician->id_user,
            'status' => 'draft',
        ]);
        $reservation->items()->create([
            'designator_id' => $material->id_designator,
            'qty' => 5,
            'qty_actual' => 3,
        ]);

        $projection = app(LopBoqProjectionService::class);
        $plan = $projection->report($lop->fresh(), 'plan');
        $actual = $projection->report($lop->fresh(), 'actual');
        $sisa = $projection->report($lop->fresh(), 'sisa');

        $this->assertTrue($plan['has_plan']);
        $this->assertSame(700.0, $plan['grand']['total_plan']);
        $this->assertSame(420.0, $actual['grand']['total_actual']);
        $this->assertSame(3.0, collect($actual['lines'])->firstWhere('designator_code', 'J-PREV')['qty_actual']);
        $this->assertCount(1, $sisa['lines']);
        $this->assertSame('M-PREV', $sisa['lines'][0]['designator_code']);
        $this->assertSame(2.0, $sisa['lines'][0]['sisa']);
        $this->assertSame(200.0, $sisa['grand']['nilai_sisa']);

        $this->actingAs($owner)
            ->getJson(route('reports.lop.boq-plan', $lop))
            ->assertOk()
            ->assertJsonPath('has_plan', true)
            ->assertJsonPath('grand.total_plan', 700);
        $this->actingAs($owner)
            ->get(route('reports.lop.boq-plan', $lop))
            ->assertOk()
            ->assertSee('BOQ Plan')
            ->assertSee('J-PREV');
        $this->actingAs($owner)
            ->getJson(route('reports.lop.boq-actual', $lop))
            ->assertOk()
            ->assertJsonPath('grand.total_actual', 420)
            ->assertJsonFragment(['designator_code' => 'J-PREV', 'qty_actual' => 3]);
    }

    /** @return array{Designator, Designator} */
    private function pairedDesignators(User $owner, string $suffix): array
    {
        $materialType = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material']);
        $serviceType = DesignatorType::firstOrCreate(['code' => 'JASA'], ['name' => 'Jasa']);

        return [
            Designator::create(['code' => "M-{$suffix}", 'item_name' => "Material {$suffix}", 'unit' => 'meter', 'designator_type_id' => $materialType->id_designator_type, 'created_by' => $owner->id_user]),
            Designator::create(['code' => "J-{$suffix}", 'item_name' => "Jasa {$suffix}", 'unit' => 'meter', 'designator_type_id' => $serviceType->id_designator_type, 'created_by' => $owner->id_user]),
        ];
    }

    private function lop(User $owner, string $program, Package $package): QeLop
    {
        return QeLop::create([
            'incident' => 'INC-'.mb_strtoupper($program),
            'nama_lop' => 'LOP '.mb_strtoupper($program),
            'program_type' => $program,
            'branch' => 'UNMAPPED',
            'package_id' => $package->id_package,
            'status_lop' => 'progress',
            'created_by' => $owner->id_user,
        ]);
    }
}
