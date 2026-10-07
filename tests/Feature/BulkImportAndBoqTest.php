<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\ProcessBulkLopImport;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeBoq;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\BoqImportService;
use App\Services\BoqService;
use App\Services\BulkLopImportService;
use App\Services\ImportBatchService;
use App\Services\LopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BulkImportAndBoqTest extends TestCase
{
    use RefreshDatabase;

    private function branchData(): array
    {
        $area = Area::updateOrCreate(['code' => '3'], ['name' => 'Area 3', 'is_active' => true]);
        $region = Region::updateOrCreate(['code' => 'JATIM'], [
            'code' => 'JATIM', 'name' => 'REGION JATIM',
            'area_id' => $area->id_area, 'is_active' => true,
        ]);
        $branch = Branch::updateOrCreate(['code' => 'SDA'], [
            'code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM',
            'region_id' => $region->id_region, 'is_active' => true,
        ]);
        ServiceArea::updateOrCreate(['workzone' => 'SDA'], [
            'workzone' => 'SDA', 'name' => 'SIDOARJO',
            'branch_id' => $branch->id_branch, 'region_id' => $region->id_region,
            'is_active' => true,
        ]);

        return [$area, $region, $branch];
    }

    public function test_bulk_lop_upload_is_queued_and_blank_name_is_generated(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, , $branch] = $this->branchData();
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);
        $file = UploadedFile::fake()->createWithContent(
            'bulk.csv',
            "incident,sto,branch,area,program_type,segment,budget_type,job_description,ihld_id,nama_lop,package_code\nINC900001,SDA,SIDOARJO,3,recovery,odp,,Penggantian box ODP,,,\n"
        );

        $response = $this->actingAs($admin)->post(route('bulk-import.lop.store'), ['file' => $file]);

        $batch = QeImportBatch::firstOrFail();
        $response->assertRedirect(route('imports.show', $batch));
        Queue::assertPushed(ProcessBulkLopImport::class, fn ($job) => $job->batchId === $batch->id_import_batch);

        app(BulkLopImportService::class)->process($batch, $admin);

        $lop = QeLop::where('incident', 'INC900001')->firstOrFail();
        $this->assertNotSame('', $lop->nama_lop);
        $this->assertStringContainsString('INC900001', $lop->nama_lop);
        $this->assertSame('completed', $batch->refresh()->status);
        $this->assertSame(1, $batch->success_rows);
        $this->assertDatabaseHas('qe_import_rows', ['status' => 'success', 'reference' => 'INC900001']);
    }

    public function test_boq_import_creates_master_data_and_assignment_prefills_material_reservation(): void
    {
        Storage::fake('local');
        [, , $branch] = $this->branchData();
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create(['branch_id' => $branch->id_branch]);
        $type = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material', 'is_active' => true]);
        $designator = Designator::create([
            'code' => 'M-ODP-01', 'item_name' => 'Box ODP', 'unit' => 'unit',
            'designator_type_id' => $type->id_designator_type,
        ]);
        $lop = QeLop::create([
            'incident' => 'INC900002', 'nama_lop' => 'LOP BOQ', 'program_type' => 'recovery',
            'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'segment' => ['odp'],
            'job_description' => 'Penggantian ODP', 'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);
        $package = Package::create(['code' => '5', 'name' => 'Paket 5']);
        $file = UploadedFile::fake()->createWithContent(
            'boq.csv',
            "PROJECT : LOP BOQ,,,,,,\n,, ,,,,,\nNO,DESIGNATOR,URAIAN PEKERJAAN,SATUAN,HARGA SATUAN (PAKET-5),,VOL\n,,,,MATERIAL,JASA,\n1,M-ODP-01,Box ODP,unit,125000,0,4\n"
        );
        $result = app(ImportBatchService::class)->create('boq', $file, $admin);
        $batch = $result['batch'];

        app(BoqImportService::class)->process($batch, $admin);

        $this->assertDatabaseHas('qe_boqs', [
            'qe_lop_id' => $lop->id_qe_lops, 'package_id' => $package->id_package,
            'item_count' => 1, 'grand_total' => 500000,
        ]);
        $this->assertDatabaseHas('qe_boq_items', [
            'designator_id' => $designator->id_designator, 'qty' => 4,
        ]);
        $this->assertSame('LOP BOQ', $batch->refresh()->metadata['project_name']);
        $this->assertSame('5', $batch->metadata['package_code']);

        app(LopService::class)->assign($lop->refresh(), $technician, $admin);

        $reservation = $lop->refresh()->materialReservation;
        $this->assertNotNull($reservation);
        $this->assertSame($technician->id_user, $reservation->technician_id);
        $this->assertDatabaseHas('qe_material_reservation_items', [
            'reservation_id' => $reservation->id_reservation,
            'designator_id' => $designator->id_designator,
            'qty' => 4,
        ]);

        $boq = QeBoq::where('qe_lop_id', $lop->id_qe_lops)->firstOrFail();
        $this->actingAs($admin)->get(route('data-boqs.index'))
            ->assertOk()
            ->assertSee('min="1" step="1"', false)
            ->assertSee('>4</td>', false)
            ->assertSee('Rp 125.000')
            ->assertSee('Rp 500.000')
            ->assertDontSee('4,000');

        $this->actingAs($admin)->put(route('data-boqs.update', $boq), [
            'package_id' => $package->id_package,
            'items' => [[
                'designator_id' => $designator->id_designator,
                'qty' => 4.5,
                'unit_price' => 125000,
            ]],
        ])->assertSessionHasErrors('items.0.qty');
    }

    public function test_boq_import_preserves_item_descriptions_longer_than_255_characters(): void
    {
        Storage::fake('local');
        [, , $branch] = $this->branchData();
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);

        DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material', 'is_active' => true]);
        DesignatorType::firstOrCreate(['code' => 'JASA'], ['name' => 'Jasa', 'is_active' => true]);
        Package::create(['code' => '5', 'name' => 'Paket 5']);
        $lop = QeLop::create([
            'incident' => 'INC-LONG-BOQ', 'nama_lop' => 'LOP LONG DESCRIPTION', 'program_type' => 'recovery',
            'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'segment' => ['odp'],
            'job_description' => 'Penggantian ODP', 'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);
        $longItemName = rtrim(str_repeat(
            'Pengadaan dan pemasangan perangkat jaringan fiber optik beserta aksesoris instalasi lengkap ',
            5
        ));
        $file = UploadedFile::fake()->createWithContent(
            'boq-long-description.csv',
            "PROJECT : LOP LONG DESCRIPTION,,,,,,\n,, ,,,,,\nNO,DESIGNATOR,URAIAN PEKERJAAN,SATUAN,HARGA SATUAN (PAKET-5),,VOL\n,,,,MATERIAL,JASA,\n1,M-LONG-DESCRIPTION,{$longItemName},unit,1000,0,2\n"
        );
        $batch = app(ImportBatchService::class)->create('boq', $file, $admin);

        app(BoqImportService::class)->process($batch, $admin);

        $this->assertGreaterThan(255, strlen($longItemName));
        $this->assertSame('completed', $batch->refresh()->status);
        $this->assertDatabaseHas('designators', [
            'code' => 'M-LONG-DESCRIPTION',
            'item_name' => $longItemName,
        ]);
        $this->assertSame(
            $longItemName,
            QeBoq::where('qe_lop_id', $lop->id_qe_lops)->firstOrFail()->items()->firstOrFail()->item_name
        );
    }

    public function test_active_boq_can_add_edit_and_remove_unused_items_while_preserving_manual_reservation_items(): void
    {
        [, , $branch] = $this->branchData();
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create(['branch_id' => $branch->id_branch]);
        $type = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material', 'is_active' => true]);
        $designatorA = Designator::create([
            'code' => 'M-ACTIVE-01', 'item_name' => 'Material Utama', 'unit' => 'unit',
            'designator_type_id' => $type->id_designator_type,
        ]);
        $designatorB = Designator::create([
            'code' => 'M-ACTIVE-02', 'item_name' => 'Material Tambahan BOQ', 'unit' => 'unit',
            'designator_type_id' => $type->id_designator_type,
        ]);
        $manualDesignator = Designator::create([
            'code' => 'M-MANUAL-01', 'item_name' => 'Material Manual Teknisi', 'unit' => 'unit',
            'designator_type_id' => $type->id_designator_type,
        ]);
        $lop = QeLop::create([
            'incident' => 'INC-ACTIVE-BOQ', 'nama_lop' => 'LOP Active BOQ', 'program_type' => 'recovery',
            'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'segment' => ['odp'],
            'job_description' => 'Pekerjaan aktif', 'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        $boq = app(BoqService::class)->save($lop, [[
            'designator_id' => $designatorA->id_designator,
            'qty' => 2,
            'unit_price' => 100000,
        ]], null, $admin);
        app(LopService::class)->assign($lop->refresh(), $technician, $admin);
        $lop->update(['status_lop' => 'progress']);

        $reservation = $lop->refresh()->materialReservation;
        $reservation->items()->create([
            'designator_id' => $manualDesignator->id_designator,
            'qty' => 3,
        ]);

        $this->actingAs($admin)->put(route('data-boqs.update', $boq), [
            'items' => [
                ['designator_id' => $designatorA->id_designator, 'qty' => 5, 'unit_price' => 100000],
                ['designator_id' => $designatorB->id_designator, 'qty' => 4, 'unit_price' => 25000],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('qe_material_reservation_items', [
            'reservation_id' => $reservation->id_reservation,
            'designator_id' => $designatorA->id_designator,
            'qty' => 5,
        ]);
        $this->assertDatabaseHas('qe_material_reservation_items', [
            'reservation_id' => $reservation->id_reservation,
            'designator_id' => $designatorB->id_designator,
            'qty' => 4,
        ]);
        $this->assertDatabaseHas('qe_material_reservation_items', [
            'reservation_id' => $reservation->id_reservation,
            'designator_id' => $manualDesignator->id_designator,
            'qty' => 3,
        ]);
        $this->assertSame(3, $reservation->items()->count());
        $this->assertSame('draft', $reservation->refresh()->status);

        $this->actingAs($admin)->get(route('data-boqs.index'))
            ->assertOk()
            ->assertSee('LOP sedang dikerjakan')
            ->assertSee('Tambah Item Designator')
            ->assertSee('Simpan perubahan BOQ?')
            ->assertSee('Hapus item designator?');
    }

    public function test_active_boq_does_not_remove_an_item_that_has_actual_usage(): void
    {
        [, , $branch] = $this->branchData();
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create(['branch_id' => $branch->id_branch]);
        $type = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material', 'is_active' => true]);
        $designatorA = Designator::create([
            'code' => 'M-USED-01', 'item_name' => 'Material Terpakai', 'unit' => 'unit',
            'designator_type_id' => $type->id_designator_type,
        ]);
        $designatorB = Designator::create([
            'code' => 'M-USED-02', 'item_name' => 'Material Pengganti', 'unit' => 'unit',
            'designator_type_id' => $type->id_designator_type,
        ]);
        $lop = QeLop::create([
            'incident' => 'INC-USED-BOQ', 'nama_lop' => 'LOP Used BOQ', 'program_type' => 'recovery',
            'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'segment' => ['odp'],
            'job_description' => 'Pekerjaan aktif', 'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);
        $boq = app(BoqService::class)->save($lop, [[
            'designator_id' => $designatorA->id_designator,
            'qty' => 2,
            'unit_price' => 100000,
        ]], null, $admin);
        app(LopService::class)->assign($lop->refresh(), $technician, $admin);
        $lop->update(['status_lop' => 'progress']);
        $reservationItem = $lop->refresh()->materialReservation->items()->firstOrFail();
        $reservationItem->update(['qty_actual' => 1]);

        $this->actingAs($admin)->put(route('data-boqs.update', $boq), [
            'items' => [[
                'designator_id' => $designatorB->id_designator,
                'qty' => 2,
                'unit_price' => 100000,
            ]],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseHas('qe_boq_items', [
            'qe_boq_id' => $boq->id_boq,
            'designator_id' => $designatorA->id_designator,
        ]);
        $this->assertDatabaseMissing('qe_boq_items', [
            'qe_boq_id' => $boq->id_boq,
            'designator_id' => $designatorB->id_designator,
        ]);
        $this->assertDatabaseHas('qe_material_reservation_items', [
            'id_reservation_item' => $reservationItem->id_reservation_item,
            'designator_id' => $designatorA->id_designator,
            'qty_actual' => 1,
        ]);
    }

    public function test_master_lop_and_data_boq_show_technician_reservation_when_plan_boq_is_missing(): void
    {
        [, , $branch] = $this->branchData();
        $serviceArea = ServiceArea::query()->where('branch_id', $branch->id_branch)->firstOrFail();
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create(['branch_id' => $branch->id_branch]);
        $type = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material', 'is_active' => true]);
        $designator = Designator::create([
            'code' => 'M-RESERVATION-BOQ',
            'item_name' => 'Material dari Reservasi Teknisi',
            'unit' => 'unit',
            'designator_type_id' => $type->id_designator_type,
        ]);
        $package = Package::create(['code' => '10-AKTUAL', 'name' => 'Paket Aktual Teknisi']);
        DesignatorPackagePrice::create([
            'designator_id' => $designator->id_designator,
            'package_id' => $package->id_package,
            'price' => 25_000,
        ]);
        $lop = QeLop::create([
            'incident' => 'INC-RESERVATION-BOQ',
            'nama_lop' => 'LOP Tanpa BOQ Plan',
            'program_type' => 'recovery',
            'sto' => $serviceArea->workzone,
            'branch' => $branch->name,
            'branch_id' => $branch->id_branch,
            'service_area_id' => $serviceArea->id_service_area,
            'status_lop' => 'completed',
            'created_by' => $admin->id_user,
        ]);
        $reservation = $lop->materialReservation()->create([
            'technician_id' => $technician->id_user,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $reservation->items()->create([
            'designator_id' => $designator->id_designator,
            'qty' => 5,
            'qty_actual' => 4,
        ]);

        $this->actingAs($admin)
            ->get(route('data-lops.index'))
            ->assertOk()
            ->assertSee('INC-RESERVATION-BOQ')
            ->assertSee('Reservasi Teknisi')
            ->assertSee('1 item');

        $this->actingAs($admin)
            ->get(route('data-boqs.index'))
            ->assertOk()
            ->assertSee('BOQ Aktual dari Reservasi Teknisi')
            ->assertSee('INC-RESERVATION-BOQ')
            ->assertSee('10-AKTUAL')
            ->assertSee('Rp 100.000')
            ->assertSee('Material dari Reservasi Teknisi');

        $this->assertDatabaseMissing('qe_boqs', ['qe_lop_id' => $lop->id_qe_lops]);
    }

    public function test_boq_parser_detects_project_and_supported_package_headers(): void
    {
        Storage::fake('local');

        foreach (['5', '10'] as $packageCode) {
            $projectRow = $packageCode === '10'
                ? "PROJECT : ,LOP DETEKSI {$packageCode},,,,,"
                : "PROJECT : LOP DETEKSI {$packageCode},,,,,,";
            $file = UploadedFile::fake()->createWithContent(
                "boq-paket-{$packageCode}.csv",
                "{$projectRow}\nNO,DESIGNATOR,URAIAN PEKERJAAN,SATUAN,HARGA SATUAN (PAKET-{$packageCode}),,VOL\n,,,,MATERIAL,JASA,\n1,M-ODP-01,Box ODP,unit,125000,0,2\n"
            );
            $path = $file->storeAs('imports', "boq-paket-{$packageCode}.csv", 'local');

            $parsed = app(BoqImportService::class)->parse(Storage::disk('local')->path($path));

            $this->assertSame("LOP DETEKSI {$packageCode}", $parsed['meta']['project']);
            $this->assertSame($packageCode, $parsed['meta']['package_code']);
            $this->assertSame('M-ODP-01', $parsed['rows'][0]['designator']);
            $this->assertSame(2, $parsed['rows'][0]['qty']);
        }
    }

    public function test_boq_parser_inherits_volume_from_parent_designator_row(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'PROJECT : LOP PARENT VOLUME');
        $sheet->fromArray([
            ['NO', 'DESIGNATOR', 'URAIAN PEKERJAAN', 'SATUAN', 'HARGA SATUAN (PAKET-10)', '', 'VOL'],
            ['', '', '', '', 'MATERIAL', 'JASA', ''],
            [1, 'SC-OF-SM-24', 'Closure FO', 'unit', 704700, 35100, 11],
            [2, 'M-SC-OF-SM-24', 'Closure FO Material', 'unit', 704700, 0, 0],
            [3, 'J-SC-OF-SM-24', 'Closure FO Jasa', 'unit', 0, 35100, 0],
        ], null, 'A3');

        $path = tempnam(sys_get_temp_dir(), 'boq-parent-volume').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            $parsed = app(BoqImportService::class)->parse($path);

            $this->assertCount(2, $parsed['rows']);
            $this->assertSame(['M-SC-OF-SM-24', 'J-SC-OF-SM-24'], array_column($parsed['rows'], 'designator'));
            $this->assertSame([11, 11], array_column($parsed['rows'], 'qty'));
        } finally {
            @unlink($path);
        }
    }

    public function test_boq_import_reads_actual_tif_template_and_only_uses_filled_volumes(): void
    {
        Storage::fake('local');
        [, , $branch] = $this->branchData();
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);
        DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material', 'is_active' => true]);
        DesignatorType::firstOrCreate(['code' => 'JASA'], ['name' => 'Jasa', 'is_active' => true]);
        $package = Package::create(['code' => '10', 'name' => 'Paket TIF-10']);
        $lop = QeLop::create([
            'incident' => 'INP3124092601',
            'nama_lop' => '3DMO_QEREC_INP3124092601_FEEDER',
            'program_type' => 'recovery',
            'sto' => 'SDA',
            'branch' => 'SIDOARJO',
            'area' => '3',
            'segment' => ['feeder'],
            'job_description' => 'QE Recovery Feeder',
            'status_lop' => 'draft',
            'created_by' => $admin->id_user,
        ]);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BoQ');
        $sheet->setCellValue('A1', 'DAFTAR HARGA SATUAN');
        $sheet->setCellValue('A3', 'PROJECT : 3DMO_QEREC_INP3124092601_FEEDER');
        $sheet->setCellValue('A4', 'STO : DMO');
        foreach (['NO', 'DESIGNATOR', 'URAIAN  PEKERJAAN', 'SATUAN', 'HARGA SATUAN (TIF-10)', '', 'VOL'] as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'6', $header);
        }
        $sheet->setCellValue('E7', 'MATERIAL');
        $sheet->setCellValue('F7', 'JASA');
        $sheet->fromArray([
            [1, 'M-BLANK', 'Material tanpa volume', 'meter', 100, 0, null],
            [2, 'J-ZERO', 'Jasa volume nol', 'meter', 0, 40, 0],
            [3, 'M-ACTUAL', 'Material terpakai', 'meter', 100, 0, 2],
            [4, 'J-ACTUAL', 'Jasa terpakai', 'meter', 0, 40, 2],
        ], null, 'A9');

        $path = tempnam(sys_get_temp_dir(), 'boq-tif').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile($path, 'boq-tif-10.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $result = app(ImportBatchService::class)->create('boq', $file, $admin);
        $batch = $result['batch'];

        app(BoqImportService::class)->process($batch, $admin);

        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(4, $batch->total_rows);
        $this->assertSame(2, $batch->success_rows);
        $this->assertSame('TIF-10', $batch->metadata['package_detected']);
        $this->assertSame(2, $batch->metadata['skipped_count']);
        $this->assertSame(1, $batch->metadata['material_count']);
        $this->assertSame(1, $batch->metadata['service_count']);
        $this->assertEquals(280, $batch->metadata['grand_total']);
        $this->assertDatabaseHas('qe_boqs', [
            'qe_lop_id' => $lop->id_qe_lops,
            'package_id' => $package->id_package,
            'item_count' => 2,
            'grand_total' => 280,
        ]);
        $this->assertDatabaseHas('designators', ['code' => 'M-ACTUAL', 'item_name' => 'Material terpakai']);
        $this->assertDatabaseHas('designators', ['code' => 'J-ACTUAL', 'item_name' => 'Jasa terpakai']);
        $this->assertDatabaseCount('qe_boq_items', 2);
        $this->assertSame(2, $batch->rows()->where('status', 'skipped')->count());

        $this->actingAs($admin)->get(route('imports.show', $batch))
            ->assertOk()
            ->assertSee('Hasil Import BOQ')
            ->assertSee('3DMO_QEREC_INP3124092601_FEEDER')
            ->assertSee('TIF-10')
            ->assertSee('Grand Total')
            ->assertSee('VOL')
            ->assertSee('M-ACTUAL')
            ->assertSee('J-ACTUAL')
            ->assertDontSee('M-BLANK')
            ->assertDontSee('J-ZERO');
    }

    public function test_admin_master_data_is_limited_to_own_branch_while_super_admin_sees_all(): void
    {
        [, $region, $sidoarjo] = $this->branchData();
        $surabaya = Branch::updateOrCreate(['code' => 'SBY'], [
            'code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM',
            'region_id' => $region->id_region, 'is_active' => true,
        ]);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $sidoarjo->id_branch]);
        $otherAdmin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $surabaya->id_branch]);
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        QeLop::create([
            'incident' => 'INC-SDA', 'nama_lop' => 'LOP Sidoarjo', 'program_type' => 'recovery',
            'branch' => 'SIDOARJO', 'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);
        QeLop::create([
            'incident' => 'INC-SBY', 'nama_lop' => 'LOP Surabaya', 'program_type' => 'recovery',
            'branch' => 'SURABAYA', 'status_lop' => 'draft', 'created_by' => $otherAdmin->id_user,
        ]);

        $this->actingAs($admin)->get(route('data-lops.index'))
            ->assertOk()->assertSee('INC-SDA')->assertDontSee('INC-SBY');
        $this->actingAs($superAdmin)->get(route('data-lops.index'))
            ->assertOk()->assertSee('INC-SDA')->assertSee('INC-SBY');
    }

    public function test_boq_import_duplicate_detection_and_replace_existing(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, , $branch] = $this->branchData();
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);
        $package = Package::create(['code' => '5', 'name' => 'Paket 5']);
        $lop = QeLop::create([
            'incident' => 'INC-DUP-TEST', 'nama_lop' => 'LOP Duplicate Test', 'program_type' => 'recovery',
            'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'segment' => ['odp'],
            'job_description' => 'Test', 'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        $fileContent = "PROJECT : LOP Duplicate Test,,,,,,\n,, ,,,,,\nNO,DESIGNATOR,URAIAN PEKERJAAN,SATUAN,HARGA SATUAN (PAKET-5),,VOL\n,,,,MATERIAL,JASA,\n1,M-ODP-01,Box ODP,unit,125000,0,4\n";

        // First upload - should create new batch
        $file1 = UploadedFile::fake()->createWithContent('boq.csv', $fileContent);
        $result1 = app(ImportBatchService::class)->create('boq', $file1, $admin, ['target' => 'plan']);
        $this->assertTrue($result1['isNew']);
        $batch = $result1['batch'];

        // Second upload with same content - should detect duplicate
        $file2 = UploadedFile::fake()->createWithContent('boq.csv', $fileContent);
        $response = $this->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('bulk-import.boq.store'), [
                'file' => $file2,
                'target' => 'plan',
                'replace_existing' => false,
            ]);

        $response->assertOk(); // Should return JSON with duplicate warning
        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'duplicate', 'result_url'])
            ->assertJson(['duplicate' => true]);

        $batch->refresh();
        $this->assertFalse($batch->metadata['replace_existing'] ?? false);
        $this->assertSame('plan', $batch->metadata['target']);

        // Replace existing - should reset batch and allow re-import
        $file3 = UploadedFile::fake()->createWithContent('boq.csv', $fileContent);
        $response = $this->actingAs($admin)->postJson(route('bulk-import.boq.store'), [
            'file' => $file3,
            'target' => 'plan',
            'replace_existing' => 'on',
        ]);

        $response->assertJson(['duplicate' => false]);
        $batch = QeImportBatch::findOrFail($batch->id_import_batch);
        $this->assertSame('queued', $batch->status);
        $this->assertTrue($batch->metadata['replace_existing']);
        $this->assertNull($batch->error_message);
        $this->assertNull($batch->completed_at);
    }
    public function test_boq_import_auto_creates_lop_from_project_name_with_sto_and_segment(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, , $branch] = $this->branchData();
        ServiceArea::updateOrCreate(['workzone' => 'JBR'], [
            'workzone' => 'JBR', 'name' => 'JEMBER',
            'branch_id' => $branch->id_branch, 'region_id' => $branch->region_id,
            'is_active' => true,
        ]);
        Package::updateOrCreate(['code' => '5'], ['name' => 'Paket 5', 'is_active' => true]);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);
        $type = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material', 'is_active' => true]);
        Designator::create([
            'code' => 'M-CONTOH', 'item_name' => 'Box ODP', 'unit' => 'unit',
            'designator_type_id' => $type->id_designator_type,
        ]);

        $fileContent = "PROJECT : 3JBR_QEREC_INC53492291_DISTRIBUSI_DESC,,,,,,\nSTO : JBR,,,,,,\nSEGMENT : distribusi,,,,,,\nNO,DESIGNATOR,URAIAN PEKERJAAN,SATUAN,HARGA SATUAN (PAKET-5),,VOL\n,,,,MATERIAL,JASA,\n1,M-CONTOH,Box ODP,unit,125000,0,4\n";

        $file = UploadedFile::fake()->createWithContent('boq.csv', $fileContent);
        $result = app(ImportBatchService::class)->create('boq', $file, $admin, ['target' => 'plan']);
        $batch = $result['batch'];

        app(BoqImportService::class)->process($batch, $admin);
        
        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $lop = QeLop::where('incident', 'INC53492291')->first();
        $this->assertNotNull($lop);
        $this->assertSame('draft', $lop->status_lop->value);
    }

    public function test_boq_import_auto_creates_lop_duplicate_incident_error(): void
    {
        Storage::fake('local');
        Queue::fake();
        [, , $branch] = $this->branchData();
        
        // Add JBR ServiceArea for the test
        $jbrServiceArea = ServiceArea::updateOrCreate(['workzone' => 'JBR'], [
            'workzone' => 'JBR', 'name' => 'JEMBER',
            'branch_id' => $branch->id_branch, 'region_id' => $branch->region_id,
            'is_active' => true,
        ]);
        
        // Use super admin to bypass branch scope check (existing LOP is in JEMBER)
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        
        // Add Package 5 for the test
        Package::updateOrCreate(['code' => '5'], ['name' => 'Paket 5', 'is_active' => true]);
        
        // Create an existing LOP with the SAME incident, but DIFFERENT nama_lop
        QeLop::create([
            'incident' => 'INC53492291', 'nama_lop' => 'NAMA LOP LAIN BERBEDA', 'program_type' => 'recovery',
            'sto' => 'JBR', 'branch' => 'JEMBER', 'area' => '3', 'segment' => ['distribusi'],
            'status_lop' => 'draft', 'created_by' => $superAdmin->id_user,
        ]);

        $fileContent = "PROJECT : 3JBR_QEREC_INC53492291_DISTRIBUSI_DESC,,,,,,\nSTO : JBR,,,,,,\nSEGMENT : distribusi,,,,,,\nNO,DESIGNATOR,URAIAN PEKERJAAN,SATUAN,HARGA SATUAN (PAKET-5),,VOL\n,,,,MATERIAL,JASA,\n1,M-CONTOH,Box ODP,unit,125000,0,4\n";

        $file = UploadedFile::fake()->createWithContent('boq.csv', $fileContent);
        $result = app(ImportBatchService::class)->create('boq', $file, $superAdmin, ['target' => 'plan']);
        $batch = $result['batch'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incident INC53492291 sudah digunakan oleh LOP lain');

        app(BoqImportService::class)->process($batch, $superAdmin);
    }
}
