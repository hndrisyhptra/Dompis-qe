<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureRole;
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

class DataBoqExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_contains_snapshot_items_and_actual_reservations_with_numeric_values(): void
    {
        $owner = $this->user(UserRole::SUPER_ADMIN);
        [$material, $service] = $this->designators($owner);
        $package = Package::create(['code' => 'Paket-5', 'name' => 'Paket 5']);
        foreach ([$material, $service] as $designator) {
            DesignatorPackagePrice::create(['designator_id' => $designator->getKey(), 'package_id' => $package->getKey(), 'price' => 9999]);
        }
        $plan = $this->lop($owner, '000123', ['nama_lop' => '=1+1', 'ihld_id' => '000987654321', 'program_type' => 'relok_utilitas', 'package_id' => $package->getKey()]);
        $this->boq($plan, $owner, $material, $service);
        $this->reserve($plan, $owner, $material, 10, 3);

        $recovery = $this->lop($owner, 'REC-IMPORT', ['package_id' => $package->getKey()]);
        $this->boq($recovery, $owner, $material, $service);

        $sheet = $this->download($owner);
        $this->assertSame(['ID IHLD', 'Incident', 'Nama LOP', 'Designator', 'Item', 'Kategori', 'Reservasi', 'Aktual', 'Harga', 'Total'], $sheet->rangeToArray('A1:J1')[0]);
        $this->assertSame('000987654321', $sheet->getCell('A2')->getValue());
        $this->assertSame('000987654321', $sheet->getCell('A3')->getValue());
        $this->assertNull($sheet->getCell('A4')->getValue());
        $this->assertNull($sheet->getCell('A5')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
        $this->assertSame('000123', $sheet->getCell('B2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
        $this->assertSame('=1+1', $sheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
        $rows = collect($sheet->rangeToArray('A2:J5', null, false, false));
        $materialRow = $rows->first(fn ($row) => $row[1] === '000123' && $row[3] === 'M-EXPORT');
        $serviceRow = $rows->first(fn ($row) => $row[1] === '000123' && $row[3] === 'J-EXPORT');
        $this->assertSame([10.0, 3.0, 100.0, 300.0], array_slice($materialRow, 6));
        $this->assertSame([10.0, 3.0, 40.0, 120.0], array_slice($serviceRow, 6));
        $unassignedRow = $rows->first(fn ($row) => $row[1] === 'REC-IMPORT' && $row[3] === 'M-EXPORT');
        $this->assertSame([5.0, null, 100.0, null], array_slice($unassignedRow, 6));
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('A1:J4', $sheet->getAutoFilter()->getRange());
        $this->assertDatabaseCount('qe_material_reservation_items', 1);
        $this->assertDatabaseHas('qe_boq_items', ['qe_boq_id' => $plan->boq->getKey(), 'designator_code' => 'M-EXPORT', 'qty' => 5, 'unit_price' => 100]);
    }

    public function test_reservation_export_uses_regional_package_includes_service_and_preserves_zero_and_missing_values(): void
    {
        $owner = $this->user(UserRole::SUPER_ADMIN);
        [$material, $service] = $this->designators($owner);
        $package = Package::create(['code' => 'Paket-10', 'name' => 'Paket 10']);
        DesignatorPackagePrice::create(['designator_id' => $material->getKey(), 'package_id' => $package->getKey(), 'price' => 25]);
        $region = Region::where('code', 'BALNUS')->firstOrFail();
        $branch = Branch::create(['code' => 'EXPORT-DPS', 'name' => 'EXPORT DPS', 'region_id' => $region->getKey(), 'region' => $region->name]);
        $lop = $this->lop($owner, 'RES-ONLY', ['program_type' => 'relok_utilitas', 'branch_id' => $branch->getKey(), 'branch' => $branch->name]);
        $this->reserve($lop, $owner, $material, 10, 0);
        $sheet = $this->download($owner);
        $this->assertSame([null, 'RES-ONLY', 'LOP RES-ONLY', 'M-EXPORT', 'Material export', 'MATERIAL', 10.0, 0.0, 25.0, 0.0], $sheet->rangeToArray('A2:J2', null, false, false)[0]);
        $this->assertSame([null, 'RES-ONLY', 'LOP RES-ONLY', 'J-EXPORT', 'Jasa export', 'JASA', 10.0, 0.0, null, null], $sheet->rangeToArray('A3:J3', null, false, false)[0]);
        $this->assertDatabaseCount('qe_boqs', 0);
        $this->assertDatabaseCount('qe_material_reservation_items', 1);
    }

    public function test_export_enforces_all_admin_scopes_and_legacy_locations(): void
    {
        $owner = $this->user(UserRole::SUPER_ADMIN);
        [$material] = $this->designators($owner);
        $area = Area::create(['code' => 'EXPORT-A', 'name' => 'Area Export']);
        $otherArea = Area::create(['code' => 'EXPORT-B', 'name' => 'Area Other']);
        $regions = [
            Region::create(['code' => 'EXPORT-R1', 'name' => 'Region 1', 'area_id' => $area->getKey()]),
            Region::create(['code' => 'EXPORT-R2', 'name' => 'Region 2', 'area_id' => $area->getKey()]),
            Region::create(['code' => 'EXPORT-R3', 'name' => 'Region 3', 'area_id' => $otherArea->getKey()]),
        ];
        $locations = [];
        foreach ([0, 0, 1, 2] as $index => $regionIndex) {
            $branch = Branch::create(['code' => 'EX-B'.$index, 'name' => 'Export Branch '.$index, 'region_id' => $regions[$regionIndex]->getKey(), 'region' => $regions[$regionIndex]->name]);
            $locations[] = ServiceArea::create(['workzone' => 'EX'.$index, 'name' => 'Export STO '.$index, 'branch_id' => $branch->getKey(), 'region_id' => $regions[$regionIndex]->getKey()]);
            $lop = $this->lop($owner, 'SCOPE-'.$index, ['branch_id' => $branch->getKey(), 'branch' => $branch->name, 'service_area_id' => $locations[$index]->getKey(), 'sto' => $locations[$index]->workzone]);
            $this->boq($lop, $owner, $material);
        }
        $legacy = $this->lop($owner, 'LEGACY', ['branch' => $locations[0]->branch->name, 'sto' => $locations[0]->workzone]);
        $this->reserve($legacy, $owner, $material, 1, null);
        $cases = [
            [['admin_scope_type' => 'area', 'area_id' => $area->getKey()], ['SCOPE-0', 'SCOPE-1', 'SCOPE-2', 'LEGACY']],
            [['admin_scope_type' => 'region', 'region_id' => $regions[0]->getKey()], ['SCOPE-0', 'SCOPE-1', 'LEGACY']],
            [['admin_scope_type' => 'branch', 'branch_id' => $locations[0]->branch_id], ['SCOPE-0', 'LEGACY']],
            [['branch_id' => $locations[0]->branch_id], ['SCOPE-0', 'LEGACY']],
            [['admin_scope_type' => 'service_area', 'service_area_id' => $locations[0]->getKey()], ['SCOPE-0', 'LEGACY']],
            [[], []],
        ];
        foreach ($cases as [$attributes, $expected]) {
            $admin = $this->user(UserRole::ADMIN, $attributes);
            $sheet = $this->download($admin, ['branch_id' => $locations[3]->branch_id, 'service_area_id' => $locations[3]->getKey(), 'q' => 'SCOPE-3']);
            $this->assertEqualsCanonicalizing($expected, $this->incidents($sheet));
        }
        $multi = $this->user(UserRole::ADMIN, ['admin_scope_type' => 'service_area']);
        $multi->serviceAreas()->attach([$locations[0]->getKey(), $locations[2]->getKey()]);
        $this->assertEqualsCanonicalizing(['SCOPE-0', 'SCOPE-2', 'LEGACY'], $this->incidents($this->download($multi)));
        $this->assertEqualsCanonicalizing(['SCOPE-0', 'SCOPE-1', 'SCOPE-2', 'SCOPE-3', 'LEGACY'], $this->incidents($this->download($owner)));
    }

    public function test_download_includes_all_pages_both_sources_and_excludes_archived_lops(): void
    {
        $owner = $this->user(UserRole::SUPER_ADMIN);
        [$material] = $this->designators($owner);
        for ($index = 0; $index < 57; $index++) {
            $plan = $this->lop($owner, 'PLAN-'.$index);
            $this->boq($plan, $owner, $material);
            $actual = $this->lop($owner, 'ACTUAL-'.$index);
            $this->reserve($actual, $owner, $material, 1, null);
        }
        $archived = $this->lop($owner, 'ARCHIVED');
        $this->boq($archived, $owner, $material);
        $archived->delete();
        $this->lop($owner, 'NO-BOQ');
        $deletedBoq = $this->lop($owner, 'DELETED-BOQ');
        $this->boq($deletedBoq, $owner, $material)->delete();
        $sheet = $this->download($owner, ['q' => 'PLAN-0', 'page' => 2, 'reservation_page' => 2]);
        $this->assertCount(114, $this->incidents($sheet));
        $this->assertNotContains('ARCHIVED', $this->incidents($sheet));
        $this->assertNotContains('NO-BOQ', $this->incidents($sheet));
        $this->assertNotContains('DELETED-BOQ', $this->incidents($sheet));
        $this->actingAs($owner)->get(route('data-boqs.index'))->assertOk()
            ->assertSee('Download Excel')->assertSee(route('data-boqs.export'), false)->assertSee('Import BOQ');
    }

    public function test_empty_export_still_has_headers_and_non_admin_roles_are_denied(): void
    {
        $this->get(route('data-boqs.export'))->assertRedirect(route('login'));
        foreach ([UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $this->actingAs($this->user($role))->get(route('data-boqs.export'))->assertForbidden();
        }
        $sheet = $this->download($this->user(UserRole::SUPER_ADMIN));
        $this->assertSame(1, $sheet->getHighestRow());
        $this->assertSame('ID IHLD', $sheet->getCell('A1')->getValue());
        $this->assertSame('Incident', $sheet->getCell('B1')->getValue());
        $this->assertSame('Total', $sheet->getCell('J1')->getValue());
    }

    public function test_backend_gate_denies_export_even_without_role_middleware(): void
    {
        $this->withoutMiddleware(EnsureRole::class);
        $this->actingAs($this->user(UserRole::MANAGER))->get(route('data-boqs.export'))->assertForbidden();
    }

    private function download(User $user, array $parameters = []): Worksheet
    {
        $response = $this->actingAs($user)->get(route('data-boqs.export', $parameters))->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        return IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    }

    private function incidents(Worksheet $sheet): array
    {
        if ($sheet->getHighestRow() === 1) {
            return [];
        }

        return array_values(array_unique(array_column($sheet->rangeToArray('B2:B'.$sheet->getHighestRow()), 0)));
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->role($role->value)->create($attributes);
    }

    private function designators(User $owner): array
    {
        $result = [];
        foreach (['MATERIAL' => ['M-EXPORT', 'Material export'], 'JASA' => ['J-EXPORT', 'Jasa export']] as $code => [$designatorCode, $name]) {
            $type = DesignatorType::firstOrCreate(['code' => $code], ['name' => $code]);
            $result[] = Designator::create(['code' => $designatorCode, 'item_name' => $name, 'unit' => 'meter', 'designator_type_id' => $type->getKey(), 'created_by' => $owner->getKey()]);
        }

        return $result;
    }

    private function lop(User $owner, string $incident, array $attributes = []): QeLop
    {
        return QeLop::create(array_merge(['incident' => $incident, 'nama_lop' => 'LOP '.$incident, 'program_type' => 'recovery', 'status_lop' => 'draft', 'branch' => 'UNMAPPED', 'created_by' => $owner->getKey()], $attributes));
    }

    private function boq(QeLop $lop, User $owner, Designator $material, ?Designator $service = null): QeBoq
    {
        $boq = QeBoq::create(['qe_lop_id' => $lop->getKey(), 'source' => 'import', 'status' => 'ready', 'item_count' => $service ? 2 : 1, 'grand_total' => $service ? 700 : 500, 'created_by' => $owner->getKey(), 'updated_by' => $owner->getKey()]);
        foreach (array_filter([$material, $service]) as $designator) {
            $price = $designator === $material ? 100 : 40;
            $boq->items()->create(['designator_id' => $designator->getKey(), 'designator_code' => $designator->code, 'item_name' => $designator->item_name, 'unit' => $designator->unit, 'type' => $designator->type->code, 'qty' => 5, 'unit_price' => $price, 'total_price' => 5 * $price]);
        }

        return $boq;
    }

    private function reserve(QeLop $lop, User $owner, Designator $material, int $qty, ?int $actual): void
    {
        $reservation = $lop->materialReservation()->create(['technician_id' => $owner->getKey(), 'status' => 'draft']);
        $reservation->items()->create(['designator_id' => $material->getKey(), 'qty' => $qty, 'qty_actual' => $actual]);
    }
}
