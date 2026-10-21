<?php

namespace Tests\Feature;

use App\Enums\ProgramType;
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
use App\Services\AissBoqExportService;
use App\Services\BoqService;
use App\Services\DataBoqExportService;
use App\Services\LopBoqProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BoqProgramCategoryRulesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $program): array
    {
        $owner = User::factory()->role('SUPER_ADMIN')->create();
        $area = Area::create(['code' => 'BC3', 'name' => 'BOQ Rules Area']);
        $region = Region::create(['code' => 'BCJ', 'name' => 'REGION JATIM', 'area_id' => $area->getKey()]);
        $branch = Branch::create(['code' => 'BCSBY', 'name' => 'BOQ Rules Surabaya', 'region' => $region->name, 'region_id' => $region->getKey()]);
        $sa = ServiceArea::create(['workzone' => 'BCSTO', 'name' => 'BOQ Rules STO', 'branch_id' => $branch->getKey(), 'region_id' => $region->getKey()]);
        $package = Package::create(['code' => 'Paket-5', 'name' => 'Paket BOQ Rule']);
        $designators = [];
        foreach (['M-BASE' => ['MATERIAL', 999], 'J-BASE' => ['JASA', 777], 'M-EXTRA' => ['MATERIAL', 20], 'J-EXTRA' => ['JASA', 7]] as $code => [$type, $price]) {
            $designators[$code] = Designator::create(['code' => $code, 'item_name' => $code.' description', 'unit' => 'pcs', 'designator_type_id' => DesignatorType::where('code', $type)->value('id_designator_type'), 'created_by' => $owner->getKey()]);
            DesignatorPackagePrice::create(['designator_id' => $designators[$code]->getKey(), 'package_id' => $package->getKey(), 'price' => $price]);
        }
        $lop = QeLop::create(['incident' => 'BOQ-RULE', 'nama_lop' => 'BOQ Category Rules', 'program_type' => $program, 'status_lop' => 'completed', 'branch_id' => $branch->getKey(), 'service_area_id' => $sa->getKey(), 'sto' => $sa->workzone, 'branch' => $branch->name, 'created_by' => $owner->getKey()]);
        $boq = QeBoq::create(['qe_lop_id' => $lop->getKey(), 'package_id' => $package->getKey(), 'source' => 'import', 'status' => 'ready', 'created_by' => $owner->getKey(), 'grand_total' => 9000, 'item_count' => 2]);
        foreach (['M-BASE' => ['MATERIAL', 10], 'J-BASE' => ['JASA', 30]] as $code => [$type, $price]) {
            $boq->items()->create(['designator_id' => $designators[$code]->getKey(), 'designator_code' => $code, 'item_name' => 'SNAPSHOT '.$code, 'unit' => 'pcs', 'type' => $type, 'qty' => 10, 'unit_price' => $price, 'total_price' => 10 * $price]);
        }
        $reservation = $lop->materialReservation()->create(['technician_id' => User::factory()->create()->getKey(), 'status' => 'submitted']);
        $reservation->items()->create(['designator_id' => $designators['M-BASE']->getKey(), 'qty' => 10, 'qty_actual' => 2]);
        $reservation->items()->create(['designator_id' => $designators['M-EXTRA']->getKey(), 'qty' => 4, 'qty_actual' => 1]);

        return compact('owner', 'area', 'region', 'branch', 'sa', 'package', 'designators', 'lop', 'boq', 'reservation');
    }

    public static function programs(): array
    {
        return array_map(fn (ProgramType $program) => [$program->value], ProgramType::cases());
    }

    #[DataProvider('programs')]
    public function test_all_views_exports_and_revenue_use_only_allowed_categories_and_snapshot_prices(string $program): void
    {
        $f = $this->fixture($program);
        $lop = $f['lop'];
        $utilitas = $program === 'relok_utilitas';
        $expectedActual = $utilitas ? 107.0 : 40.0;
        $expectedPlan = $program === 'recovery' ? 0.0 : ($utilitas ? 400.0 : 100.0);
        $projection = app(LopBoqProjectionService::class);
        $actual = $projection->report($lop, 'actual');
        $this->assertSame($expectedActual, $actual['grand']['total_actual']);
        $this->assertSame($utilitas ? ['J-BASE', 'M-BASE', 'M-EXTRA', 'J-EXTRA'] : ['M-BASE', 'M-EXTRA'], array_column($actual['lines'], 'designator_code'));
        $this->assertSame($expectedPlan, (float) ($projection->report($lop, 'plan')['grand']['total_plan'] ?? 0));
        $rows = iterator_to_array(app(DataBoqExportService::class)->rows($f['owner']));
        $this->assertCount($utilitas ? 4 : 2, $rows);
        $this->assertSame($expectedActual, (float) array_sum(array_column($rows, 10)));
        $book = app(AissBoqExportService::class)->workbook($lop);
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame(($utilitas ? 4 : 2) + 1, $sheet->getHighestRow());
            $materialRow = $utilitas ? 3 : 2;
            $this->assertSame('M-BASE', $sheet->getCell('B'.$materialRow)->getValue());
            $this->assertSame(10.0, $sheet->getCell('F'.$materialRow)->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
        $this->actingAs($f['owner'])->get(route('revenue.index'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['plan_value'] === $expectedPlan && $stats['actual_value'] === $expectedActual)
            ->assertViewHas('programRows', fn ($rows) => $rows->firstWhere('program', $program)['actual_value'] === $expectedActual);
        $this->get(route('data-boqs.index'))->assertOk()->assertViewHas('boqs', fn ($boqs) => $boqs->first()->program_items->count() === ($utilitas ? 2 : 1) && $boqs->first()->program_total === ($utilitas ? 400.0 : 100.0))
            ->assertSee('detailLaporanTab('.$lop->getKey().', '.($program === 'recovery' ? 'false' : 'true').')', false);
        $this->assertSame('9000.00', $f['boq']->fresh()->grand_total);
        $this->assertCount(2, $f['boq']->fresh()->items);
    }

    #[DataProvider('programs')]
    public function test_zero_actual_never_falls_back_to_plan_or_reserved_quantity(string $program): void
    {
        $f = $this->fixture($program);
        $f['reservation']->items()->update(['qty_actual' => 0]);
        $this->actingAs($f['owner'])->get(route('revenue.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === 0.0);
        $this->assertSame(0.0, app(LopBoqProjectionService::class)->report($f['lop'], 'actual')['grand']['total_actual']);
    }

    #[DataProvider('programs')]
    public function test_reservation_without_boq_uses_material_only_except_utilitas(string $program): void
    {
        $f = $this->fixture($program);
        $f['boq']->delete();
        $expected = $program === 'relok_utilitas' ? 3579.0 : 2018.0;
        $this->actingAs($f['owner'])->get(route('revenue.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === $expected && $stats['plan_value'] === 0.0);
        $this->assertSame($expected, app(LopBoqProjectionService::class)->report($f['lop']->fresh(), 'actual')['grand']['total_actual']);
    }

    public function test_revenue_category_rule_is_applied_in_each_admin_scope_and_excludes_foreign_location(): void
    {
        $f = $this->fixture('preventive');
        $outside = QeLop::create(['incident' => 'OUTSIDE', 'nama_lop' => 'Outside scope', 'program_type' => 'preventive', 'status_lop' => 'completed', 'branch' => 'Outside scope', 'created_by' => $f['owner']->getKey(), 'boq_snapshot' => ['material' => ['total' => 1000], 'grand_total' => 5000]]);
        foreach (['area', 'region', 'branch', 'service_area'] as $scope) {
            $admin = User::factory()->role('ADMIN')->create(['admin_scope_type' => $scope, 'area_id' => $f['area']->getKey(), 'region_id' => $f['region']->getKey(), 'branch_id' => $f['branch']->getKey(), 'service_area_id' => $f['sa']->getKey()]);
            if ($scope === 'service_area') {
                $admin->serviceAreas()->sync([$f['sa']->getKey()]);
            }
            $this->actingAs($admin)->get(route('revenue.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === 40.0 && $stats['plan_value'] === 100.0)
                ->assertViewHas('branchRows', fn ($rows) => $rows->sum('actual_value') === 40.0);
        }
        $this->actingAs($f['owner'])->get(route('revenue.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === 1040.0 && $stats['plan_value'] === 1100.0);
        $this->assertNotNull($outside->fresh());
    }

    public function test_unknown_legacy_combined_total_is_not_assumed_material_and_reports_warning(): void
    {
        $f = $this->fixture('recovery');
        $f['boq']->delete();
        $f['reservation']->delete();
        $f['lop']->update(['boq_snapshot' => ['grand_total' => 98765]]);
        $this->actingAs($f['owner'])->get(route('revenue.index'))->assertOk()->assertSee('tanpa rincian kategori')
            ->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === 0.0 && $stats['unclassified_boq_count'] === 1);
    }

    public function test_manual_save_rejects_service_for_material_only_program_but_import_skips_it(): void
    {
        $f = $this->fixture('preventive');
        $f['lop']->update(['status_lop' => 'draft']);
        $items = [['designator_id' => $f['designators']['M-BASE']->getKey(), 'qty' => 2, 'unit_price' => 10], ['designator_id' => $f['designators']['J-BASE']->getKey(), 'qty' => 2, 'unit_price' => 30]];
        try {
            app(BoqService::class)->save($f['lop'], $items, $f['package'], $f['owner']);
            $this->fail('Manual service input should be rejected.');
        } catch (ValidationException) {
            $this->assertCount(2, $f['boq']->fresh()->items);
        }
        $boq = app(BoqService::class)->save($f['lop'], $items, $f['package'], $f['owner'], 'import');
        $this->assertCount(1, $boq->items);
        $this->assertSame('20.00', $boq->grand_total);
    }
}
