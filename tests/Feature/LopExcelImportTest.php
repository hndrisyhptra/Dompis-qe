<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\Package;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\LopExcelImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LopExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private function seedAreaBranchServiceArea(): array
    {
        $area = Area::updateOrCreate(['code' => '3'], ['name' => 'Area 3', 'is_active' => true]);
        $region = Region::updateOrCreate(['code' => 'BALNUS'], ['name' => 'REGION BALNUS', 'is_active' => true]);
        $region->update(['area_id' => $area->id_area]);
        $branch = Branch::updateOrCreate(['code' => 'DPS'], ['name' => 'DENPASAR', 'region' => 'REGION BALNUS', 'region_id' => $region->id_region, 'is_active' => true]);
        ServiceArea::updateOrCreate(['workzone' => 'SAU'], ['name' => 'SANUR', 'branch_id' => $branch->id_branch, 'region_id' => $region->id_region, 'is_active' => true]);

        return [$area, $region, $branch];
    }

    /**
     * Bangun file xlsx minimal mirip template BOQ.
     * $rows: [designator, uraian, satuan, hargaM, hargaJ, vol]
     */
    private function excelFile(array $rows, string $project = '3DPR_QERELOK_SAU_TEST', string $sto = 'SAU'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'DAFTAR HARGA SATUAN');
        $sheet->setCellValue('A3', "PROJECT : {$project}");
        $sheet->setCellValue('A4', "STO : {$sto}");

        foreach (['NO', 'DESIGNATOR', 'URAIAN  PEKERJAAN', 'SATUAN', 'HARGA SATUAN (PAKET-10)', '', 'VOL', 'TOTAL HARGA (Rp.)', '', '', 'KETERANGAN'] as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}6", $h);
        }
        $sheet->setCellValue('E7', 'MATERIAL');
        $sheet->setCellValue('F7', 'JASA');

        $r = 9;
        foreach ($rows as $i => $row) {
            [$code, $uraian, $satuan, $hargaM, $hargaJ, $vol] = $row;
            $sheet->setCellValue("A{$r}", $i + 1);
            $sheet->setCellValue("B{$r}", $code);
            $sheet->setCellValue("C{$r}", $uraian);
            $sheet->setCellValue("D{$r}", $satuan);
            $sheet->setCellValue("E{$r}", $hargaM);
            $sheet->setCellValue("F{$r}", $hargaJ);
            $sheet->setCellValue("G{$r}", $vol);
            $r++;
        }

        $path = tempnam(sys_get_temp_dir(), 'lop').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function sampleRows(): array
    {
        return [
            ['M-TEST-1', 'Kabel udara 12 core', 'meter', 7931, 0, 100],
            ['J-TEST-1', 'Jasa pasang kabel', 'meter', 0, 2327, 100],
            ['M-TEST-2', 'Aksesoris tiang', 'pcs', 1780, 0, null],
        ];
    }

    public function test_preview_parses_meta_and_classifies_rows(): void
    {
        $this->seedAreaBranchServiceArea();
        Package::create(['code' => '5', 'name' => 'Paket 5']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();

        $service = app(LopExcelImportService::class);
        $parsed = $service->parse($this->excelFile($this->sampleRows()));

        $this->assertSame('3DPR_QERELOK_SAU_TEST', $parsed['meta']['project']);
        $this->assertSame('SAU', $parsed['meta']['sto']);
        $this->assertSame('10', $parsed['meta']['package_detected']);
        $this->assertCount(3, $parsed['rows']);

        $preview = $service->preview($parsed);

        $this->assertSame('DENPASAR', $preview['lop']['branch']);
        $this->assertSame('3', $preview['lop']['area']);
        $this->assertSame('relok_utilitas', $preview['lop']['program_type']);
        $this->assertNotEmpty($preview['warnings']); // paket 10 belum ada
        $this->assertSame(1, $preview['totals']['material_count']);
        $this->assertSame(1, $preview['totals']['jasa_count']);
        $this->assertSame(1, $preview['totals']['skipped_count']);
        $this->assertEqualsWithDelta(7931 * 100, $preview['totals']['material_total'], 0.01);
        $this->assertEqualsWithDelta(2327 * 100, $preview['totals']['jasa_total'], 0.01);
    }

    public function test_store_creates_lop_reservation_and_snapshot(): void
    {
        [$area, $region, $branch] = $this->seedAreaBranchServiceArea();
        Package::create(['code' => '5', 'name' => 'Paket 5']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create([
            'branch_id' => $branch->id_branch,
            'admin_scope_type' => 'branch',
        ]);

        // Preview dulu (isi session)
        $this->actingAs($admin)->post(route('lop.import.preview'), [
            'file' => $this->excelFile($this->sampleRows()),
        ])->assertOk();

        $response = $this->actingAs($admin)->post(route('lop.import.store'), [
            'nama_lop' => '3DPR_QERELOK_SAU_TEST',
            'incident' => '',
            'program_type' => 'relok_utilitas',
            'sto' => 'SAU',
            'branch' => 'DENPASAR',
            'area' => '3',
            'segment' => ['feeder'],
            'budget_type' => 'CAPEX',
            'package_code' => '5',
            'job_description' => '3DPR_QERELOK_SAU_TEST',
        ]);

        $response->assertRedirect(route('lop.index'));

        $lop = QeLop::where('nama_lop', '3DPR_QERELOK_SAU_TEST')->firstOrFail();
        $this->assertStringStartsWith('INP', $lop->incident);
        $this->assertSame(['feeder'], $lop->segments());

        // Reservasi draft hanya MATERIAL
        $reservation = $lop->materialReservation;
        $this->assertNotNull($reservation);
        $this->assertSame('draft', $reservation->status);
        $this->assertCount(1, $reservation->items);
        $this->assertSame('M-TEST-1', $reservation->items->first()->designator->code);
        $this->assertEqualsWithDelta(100, (float) $reservation->items->first()->qty, 0.001);

        // Snapshot M+J dengan harga Excel
        $snapshot = $lop->boq_snapshot;
        $this->assertSame('10', $snapshot['package_detected']);
        $this->assertSame('5', $snapshot['package_used']);
        $this->assertEqualsWithDelta(7931 * 100 + 2327 * 100, (float) $snapshot['grand_total'], 0.01);
        $this->assertCount(2, $snapshot['rows']);

        // Designator auto-create
        $this->assertDatabaseHas('designators', ['code' => 'M-TEST-1']);
        $this->assertDatabaseHas('designators', ['code' => 'J-TEST-1']);
    }

    public function test_invalid_designator_prefix_blocks_import(): void
    {
        $this->seedAreaBranchServiceArea();
        Package::create(['code' => '5', 'name' => 'Paket 5']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();

        $service = app(LopExcelImportService::class);
        $parsed = $service->parse($this->excelFile([
            ['X-BAD-1', 'Item salah', 'pcs', 100, 0, 5],
        ]));
        $preview = $service->preview($parsed);

        $this->assertNotEmpty($preview['errors']);
        $this->assertSame(0, Designator::where('code', 'X-BAD-1')->count());
    }

    public function test_non_admin_cannot_access_import(): void
    {
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $this->actingAs($teknisi)->get(route('lop.import.form'))->assertForbidden();
        $this->actingAs($teknisi)->post(route('lop.import.preview'), [
            'file' => $this->excelFile($this->sampleRows()),
        ])->assertForbidden();
    }
}
