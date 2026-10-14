<?php

namespace Tests\Feature;

use App\Enums\LopStatus;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class AissBoqExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_works_without_a_template_file_in_the_project(): void
    {
        [$owner, $lop] = $this->fixture('recovery');
        $originalBasePath = $this->app->basePath();
        $this->app->setBasePath(sys_get_temp_dir().'/aiss-no-template-'.bin2hex(random_bytes(8)));

        try {
            $this->assertFileDoesNotExist(base_path('plans/Format AISS BOQ AWAL.xlsx'));
            $sheet = $this->download($owner, $lop, 'AISS-INC-AISS-LOP-AISS.xlsx');
            $this->assertSame('PELIMPAHAN', $sheet->getTitle());
            $this->assertSame('ID IHLD', $sheet->getCell('A1')->getValue());
            $this->assertSame('BRANCH', $sheet->getCell('L1')->getValue());
            $this->assertSame(2, $sheet->getHighestDataRow());
            $this->assertSame('0010513828', $sheet->getCell('A2')->getValue());
            $this->assertSame('M-AISS', $sheet->getCell('B2')->getValue());
        } finally {
            $this->app->setBasePath($originalBasePath);
        }
    }

    public function test_export_matches_template_headers_and_preserves_snapshot_prices_and_actual_quantities(): void
    {
        [$owner, $lop, $material, $service, $package] = $this->fixture('relok_utilitas');
        $boq = QeBoq::create(['qe_lop_id' => $lop->getKey(), 'package_id' => $package->getKey(), 'source' => 'import', 'status' => 'ready', 'created_by' => $owner->getKey(), 'updated_by' => $owner->getKey()]);
        $boq->items()->createMany([
            ['designator_id' => $material->getKey(), 'designator_code' => 'M-AISS', 'item_name' => 'Snapshot material', 'unit' => 'meter', 'type' => 'MATERIAL', 'qty' => 10, 'unit_price' => 100, 'total_price' => 1000],
            ['designator_id' => $service->getKey(), 'designator_code' => 'J-AISS', 'item_name' => 'Snapshot jasa', 'unit' => 'meter', 'type' => 'JASA', 'qty' => 10, 'unit_price' => 40, 'total_price' => 400],
        ]);
        $sheet = $this->download($owner, $lop, 'AISS-INC-AISS-LOP-AISS.xlsx');
        $this->assertSame('PELIMPAHAN', $sheet->getTitle());
        $this->assertSame([[
            'ID IHLD', 'DESIGNATOR', 'URAIAN PEKERJAAN', 'SATUAN', 'QTY', 'HARGA',
            'TOTAL', 'PAKET', 'STO', 'WITEL', 'NAMA_PROYEK', 'BRANCH',
        ]], $sheet->rangeToArray('A1:L1'));
        $this->assertSame('L', $sheet->getHighestDataColumn());
        $this->assertSame(3, $sheet->getHighestDataRow());
        $rows = collect($sheet->rangeToArray('A2:L3', null, false, false));
        $materialRow = $rows->first(fn ($row) => $row[1] === 'M-AISS');
        $serviceRow = $rows->first(fn ($row) => $row[1] === 'J-AISS');
        $this->assertEquals(['0010513828', 'M-AISS', 'Snapshot material', 'meter', 3, 100, 300, 5, 'ICX', 'AISS Branch', 'LOP AISS', 'AISS Branch'], $materialRow);
        $this->assertEquals(['0010513828', 'J-AISS', 'Snapshot jasa', 'meter', 3, 40, 120, 5, 'ICX', 'AISS Branch', 'LOP AISS', 'AISS Branch'], $serviceRow);
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        foreach (['E', 'F', 'G', 'H'] as $column) {
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell($column.'2')->getDataType());
        }
        foreach (['A' => 9, 'B' => 36.1796875, 'C' => 53.453125, 'D' => 20.453125, 'E' => 10.453125] as $column => $width) {
            $this->assertEquals($width, $sheet->getColumnDimension($column)->getWidth());
        }
        foreach (['A1', 'C2'] as $coordinate) {
            $font = $sheet->getStyle($coordinate)->getFont();
            $this->assertSame('Aptos Narrow', $font->getName());
            $this->assertEquals(11, $font->getSize());
            $this->assertFalse($font->getBold());
        }
        $this->assertSame('General', $sheet->getStyle('D2')->getNumberFormat()->getFormatCode());
        $this->assertDatabaseHas('qe_boq_items', ['qe_boq_id' => $boq->getKey(), 'designator_code' => 'M-AISS', 'unit_price' => 100, 'qty' => 10]);
        $this->assertDatabaseCount('qe_material_reservation_items', 1);
    }

    public function test_recovery_uses_regional_package_and_only_material_without_saving_a_boq(): void
    {
        [$owner, $lop] = $this->fixture('recovery');
        $sheet = $this->download($owner, $lop);
        $rows = $sheet->rangeToArray('A2:L2', null, false, false);
        $this->assertEquals(['M-AISS'], array_column($rows, 1));
        $this->assertEquals([3], array_column($rows, 4));
        $this->assertEquals([999], array_column($rows, 5));
        $this->assertEquals([2997], array_column($rows, 6));
        $this->assertEquals([5], array_column($rows, 7));
        $this->assertDatabaseCount('qe_boqs', 0);
        $this->assertDatabaseCount('qe_material_reservation_items', 1);
    }

    public function test_zero_null_legacy_location_and_formula_like_text_are_preserved(): void
    {
        [$owner, $lop, , $service, $package] = $this->fixture('recovery');
        $lop->update(['ihld_id' => null, 'nama_lop' => '=1+1', 'branch_id' => null, 'service_area_id' => null, 'sto' => 'LEGACY', 'branch' => 'AISS Branch']);
        $lop->materialReservation->items()->update(['qty_actual' => 0]);
        DesignatorPackagePrice::where('designator_id', $service->getKey())->where('package_id', $package->getKey())->delete();
        $sheet = $this->download($owner, $lop, 'AISS-INC-AISS-1-1.xlsx');
        $this->assertNull($sheet->getCell('A2')->getValue());
        $this->assertEquals(0, $sheet->getCell('E2')->getValue());
        $this->assertEquals(0, $sheet->getCell('G2')->getValue());
        $this->assertNull($sheet->getCell('F3')->getValue());
        $this->assertNull($sheet->getCell('G3')->getValue());
        $this->assertSame('LEGACY', $sheet->getCell('I2')->getValue());
        $this->assertSame('AISS Branch', $sheet->getCell('J2')->getValue());
        $this->assertSame('=1+1', $sheet->getCell('K2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('K2')->getDataType());
        $lop->materialReservation->items()->update(['qty_actual' => null]);
        $sheet = $this->download($owner, $lop->fresh());
        $this->assertNull($sheet->getCell('E2')->getValue());
        $this->assertNull($sheet->getCell('G2')->getValue());
    }

    public function test_download_and_button_only_allow_completed_active_lops_and_admin_roles(): void
    {
        [$owner, $lop] = $this->fixture('recovery');
        $this->actingAs($owner)->get(route('data-lops.index'))->assertOk()
            ->assertSee(route('data-lops.aiss-boq', $lop), false)->assertSee('Download BOQ AISS (.xlsx)');
        foreach (LopStatus::cases() as $status) {
            if ($status === LopStatus::COMPLETED) {
                continue;
            }
            $lop->update(['status_lop' => $status]);
            $this->get(route('data-lops.aiss-boq', $lop))->assertNotFound();
            $this->get(route('data-lops.index'))->assertOk()->assertDontSee(route('data-lops.aiss-boq', $lop), false);
        }
        $lop->update(['status_lop' => 'completed']);
        foreach ([UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $this->actingAs(User::factory()->role($role->value)->create())->get(route('data-lops.aiss-boq', $lop))->assertForbidden();
        }
        $lop->delete();
        $this->actingAs($owner)->get(route('data-lops.aiss-boq', $lop))->assertNotFound();
        auth()->logout();
        $this->get(route('data-lops.aiss-boq', $lop))->assertRedirect(route('login'));
    }

    public function test_export_respects_every_admin_scope_including_legacy_branch_and_multi_service_area(): void
    {
        [$owner, $lop] = $this->fixture('recovery');
        $branch = $lop->branchRef;
        $region = $branch->regionRef;
        foreach ([
            ['admin_scope_type' => 'area', 'area_id' => $region->area_id],
            ['admin_scope_type' => 'region', 'region_id' => $region->getKey()],
            ['admin_scope_type' => 'branch', 'branch_id' => $branch->getKey()],
            ['branch_id' => $branch->getKey()],
            ['admin_scope_type' => 'service_area', 'service_area_id' => $lop->service_area_id],
        ] as $attributes) {
            $admin = User::factory()->role(UserRole::ADMIN->value)->create($attributes);
            $this->download($admin, $lop);
        }
        $multi = User::factory()->role(UserRole::ADMIN->value)->create(['admin_scope_type' => 'service_area']);
        $multi->serviceAreas()->attach($lop->service_area_id);
        $this->download($multi, $lop);
        $lop->update(['branch_id' => null, 'service_area_id' => null]);
        $this->download($multi, $lop->fresh());
        $outside = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => null]);
        $this->actingAs($outside)->get(route('data-lops.aiss-boq', [$lop, 'branch_id' => $branch->getKey()]))->assertForbidden();
    }

    public function test_empty_completed_lop_exports_headers_without_template_sample_data(): void
    {
        [$owner, $lop] = $this->fixture('recovery');
        $lop->materialReservation->items()->delete();
        $sheet = $this->download($owner, $lop);
        $this->assertSame(1, $sheet->getHighestDataRow());
        $this->assertSame('ID IHLD', $sheet->getCell('A1')->getValue());
        $this->assertSame('BRANCH', $sheet->getCell('L1')->getValue());
        $this->assertNotContains('PU-AS', array_column($sheet->toArray(), 1));
    }

    private function download(User $user, QeLop $lop, ?string $filename = null): Worksheet
    {
        $response = $this->actingAs($user)->get(route('data-lops.aiss-boq', $lop))->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        if ($filename !== null) {
            $response->assertDownload($filename);
        }
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $path = tempnam(sys_get_temp_dir(), 'aiss-test-');
        file_put_contents($path, $response->streamedContent());
        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            unlink($path);
        }
    }

    private function fixture(string $program): array
    {
        $owner = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $area = Area::create(['code' => 'AISS-A', 'name' => 'AISS Area']);
        $region = Region::where('code', 'JATIM')->firstOrFail();
        $region->update(['area_id' => $area->getKey()]);
        $branch = Branch::create(['code' => 'AISS-B', 'name' => 'AISS Branch', 'region_id' => $region->getKey(), 'region' => $region->name]);
        $sto = ServiceArea::create(['workzone' => 'ICX', 'name' => 'AISS STO', 'branch_id' => $branch->getKey(), 'region_id' => $region->getKey()]);
        $package = Package::create(['code' => 'Paket-5', 'name' => 'Paket 5']);
        $items = [];
        foreach (['MATERIAL' => ['M-AISS', 999], 'JASA' => ['J-AISS', 250]] as $type => [$code, $price]) {
            $designatorType = DesignatorType::firstOrCreate(['code' => $type], ['name' => $type]);
            $item = Designator::create(['code' => $code, 'item_name' => 'Master '.$code, 'unit' => 'meter', 'designator_type_id' => $designatorType->getKey()]);
            DesignatorPackagePrice::create(['designator_id' => $item->getKey(), 'package_id' => $package->getKey(), 'price' => $price]);
            $items[] = $item;
        }
        $lop = QeLop::create(['incident' => 'INC-AISS', 'nama_lop' => 'LOP AISS', 'ihld_id' => '0010513828', 'program_type' => $program, 'status_lop' => 'completed', 'branch_id' => $branch->getKey(), 'service_area_id' => $sto->getKey(), 'branch' => $branch->name, 'sto' => $sto->workzone, 'created_by' => $owner->getKey()]);
        $reservation = $lop->materialReservation()->create(['technician_id' => $owner->getKey(), 'status' => 'submitted']);
        $reservation->items()->create(['designator_id' => $items[0]->getKey(), 'qty' => 10, 'qty_actual' => 3]);

        return [$owner, $lop, ...$items, $package];
    }
}
