<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\QeBoq;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class DataLopExportTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    private array $locations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->role('SUPER_ADMIN')->create();
        $area = Area::create(['code' => 'DLE-A', 'name' => 'Area Export']);
        $otherArea = Area::create(['code' => 'DLE-X', 'name' => 'Area Outside']);
        $jatim = Region::create(['code' => 'DLE-J', 'name' => 'Region J', 'area_id' => $area->getKey()]);
        $balnus = Region::create(['code' => 'DLE-B', 'name' => 'Region B', 'area_id' => $area->getKey()]);
        $outside = Region::create(['code' => 'DLE-O', 'name' => 'Region Outside', 'area_id' => $otherArea->getKey()]);
        $branches = [];
        foreach (['SBY' => $jatim, 'SDA' => $jatim, 'DPS' => $balnus, 'OUT' => $outside] as $code => $region) {
            $branches[$code] = Branch::create(['code' => 'DLE-'.$code, 'name' => 'Branch '.$code, 'region_id' => $region->getKey(), 'region' => $region->name]);
        }
        $areas = [];
        foreach (['SBY-A' => 'SBY', 'SBY-B' => 'SBY', 'SDA-A' => 'SDA', 'DPS-A' => 'DPS', 'OUT-A' => 'OUT'] as $code => $branchCode) {
            $branch = $branches[$branchCode];
            $areas[$code] = ServiceArea::create(['workzone' => $code, 'name' => $code, 'branch_id' => $branch->getKey(), 'region_id' => $branch->region_id]);
        }
        $this->locations = compact('area', 'jatim', 'branches', 'areas');
    }

    private function lop(string $incident, string $zone = 'SBY-A', array $attributes = []): QeLop
    {
        $location = $this->locations['areas'][$zone];

        return QeLop::create(array_replace([
            'incident' => $incident, 'nama_lop' => 'LOP '.$incident,
            'program_type' => 'preventive', 'status_lop' => 'progress',
            'service_area_id' => $location->getKey(), 'branch_id' => $location->branch_id,
            'sto' => $zone, 'branch' => $location->branch->name,
            'area' => '3', 'segment' => ['odp', 'tiang'], 'budget_type' => 'OPEX',
            'created_by' => $this->super->getKey(),
        ], $attributes));
    }

    private function download(User $user, array $filters = []): Worksheet
    {
        $response = $this->actingAs($user)->get(route('data-lops.export', $filters))->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));

        return IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    }

    private function incidents(Worksheet $sheet): array
    {
        return $sheet->getHighestRow() <= 1 ? [] : array_column($sheet->rangeToArray('B2:B'.$sheet->getHighestRow(), null, false, false), 0);
    }

    public function test_excel_contains_lop_fields_and_preserves_text_identifiers_and_numeric_counts_and_dates(): void
    {
        $lop = $this->lop('000123', attributes: [
            'ihld_id' => '000987654321', 'nama_lop' => '=1+1',
            'status_aiss' => 'AISS Survey', 'job_description' => 'Deskripsi pekerjaan lengkap',
            'created_at' => '2026-10-16 00:30:00',
        ]);
        QeBoq::create(['qe_lop_id' => $lop->getKey(), 'source' => 'import', 'status' => 'draft', 'item_count' => 3, 'grand_total' => 1234, 'created_by' => $this->super->getKey()]);
        $sheet = $this->download($this->super);
        $this->assertSame(['ID IHLD', 'Incident', 'Nama LOP', 'STO', 'Branch', 'Area', 'Program', 'Segmen', 'Budget Type', 'Status Dompis', 'Status AISS', 'BOQ', 'Jumlah Item BOQ', 'Deskripsi Pekerjaan', 'Dibuat Oleh', 'Tanggal Dibuat (WIB)'], $sheet->rangeToArray('A1:P1')[0]);
        $this->assertSame('000987654321', $sheet->getCell('A2')->getValue());
        $this->assertSame('000123', $sheet->getCell('B2')->getValue());
        $this->assertSame('@', $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        $this->assertSame('=1+1', $sheet->getCell('C2')->getValue());
        foreach (['A', 'B', 'C'] as $column) {
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($column.'2')->getDataType());
        }
        $this->assertSame(['SBY-A', 'Branch SBY', '3', 'QE Preventive', 'ODP, Tiang', 'OPEX', 'Progress', 'AISS Survey', 'BOQ Plan', 3, 'Deskripsi pekerjaan lengkap', $this->super->name], $sheet->rangeToArray('D2:O2', null, false, false)[0]);
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('M2')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('P2')->getDataType());
        $this->assertSame($lop->created_at->copy()->timezone('Asia/Jakarta')->format('Y-m-d H:i'), Date::excelToDateTimeObject($sheet->getCell('P2')->getValue())->format('Y-m-d H:i'));
        $this->assertSame('dd/mm/yyyy hh:mm', $sheet->getStyle('P2')->getNumberFormat()->getFormatCode());
        $this->assertSame('C2', $sheet->getFreezePane());
        $this->assertSame('A1:P2', $sheet->getAutoFilter()->getRange());
        $this->assertDatabaseHas('qe_lops', ['id_qe_lops' => $lop->getKey(), 'nama_lop' => '=1+1', 'status_lop' => 'progress']);
    }

    public function test_export_matches_combined_page_filters_and_includes_rows_beyond_pagination(): void
    {
        for ($i = 1; $i <= 31; $i++) {
            $this->lop('MATCH-'.$i);
        }
        $this->lop('WRONG-SEARCH');
        $this->lop('MATCH-STATUS', attributes: ['status_lop' => 'completed']);
        $this->lop('MATCH-PROGRAM', attributes: ['program_type' => 'relok_tiang']);
        $this->lop('MATCH-BRANCH', 'DPS-A');
        $this->lop('MATCH-ARCHIVED')->delete();
        $filters = ['q' => ' MATCH- ', 'program' => 'preventive', 'status' => 'progress', 'branch' => 'Branch SBY', 'page' => 2];
        $this->actingAs($this->super)->get(route('data-lops.index', $filters))->assertOk()
            ->assertViewHas('lops', fn ($rows) => $rows->total() === 31 && $rows->count() === 11);
        $incidents = $this->incidents($this->download($this->super, $filters));
        $this->assertCount(31, $incidents);
        $this->assertEqualsCanonicalizing(array_map(fn ($i) => 'MATCH-'.$i, range(1, 31)), $incidents);
        foreach (['q' => 'SBY-A', 'program' => 'relok_tiang', 'status' => 'completed', 'branch' => 'Branch DPS'] as $key => $value) {
            $expected = $this->actingAs($this->super)->get(route('data-lops.index', [$key => $value]))->assertOk()->viewData('lops');
            $this->assertSame($expected->total(), count($this->incidents($this->download($this->super, [$key => $value]))));
        }
    }

    public function test_reservation_boq_counts_and_foreign_key_location_fallback_do_not_mutate_data(): void
    {
        $lop = $this->lop('RESERVATION', attributes: ['program_type' => 'recovery', 'sto' => null, 'branch' => null]);
        $reservation = $lop->materialReservation()->create(['technician_id' => $this->super->getKey(), 'status' => 'draft']);
        foreach (['M-EXPORT-ONE', 'M-EXPORT-TWO'] as $code) {
            $designator = Designator::create(['code' => $code, 'item_name' => $code, 'unit' => 'pcs', 'created_by' => $this->super->getKey()]);
            $reservation->items()->create(['designator_id' => $designator->getKey(), 'qty' => 5, 'qty_actual' => 0]);
        }
        $sheet = $this->download($this->super, ['q' => 'RESERVATION']);
        $this->assertSame(['SBY-A', 'Branch SBY'], $sheet->rangeToArray('D2:E2')[0]);
        $this->assertSame(['Reservasi Teknisi', 2], $sheet->rangeToArray('L2:M2', null, false, false)[0]);
        $this->assertDatabaseCount('qe_boqs', 0);
        $this->assertDatabaseCount('qe_material_reservation_items', 2);
        $this->assertDatabaseHas('qe_material_reservation_items', ['reservation_id' => $reservation->getKey(), 'qty' => 5, 'qty_actual' => 0]);
        $reservation->delete();
        $sheet = $this->download($this->super, ['q' => 'RESERVATION']);
        $this->assertSame(['Belum ada', 0], $sheet->rangeToArray('L2:M2', null, false, false)[0]);
    }

    public function test_all_admin_scopes_legacy_locations_and_outside_filter_are_respected(): void
    {
        foreach (array_keys($this->locations['areas']) as $zone) {
            $this->lop('INC-'.$zone, $zone);
        }
        $this->lop('LEGACY', attributes: ['branch_id' => null, 'service_area_id' => null]);
        $l = $this->locations;
        foreach ([
            [['admin_scope_type' => 'area', 'area_id' => $l['area']->getKey()], 5, []],
            [['admin_scope_type' => 'region', 'region_id' => $l['jatim']->getKey()], 4, []],
            [['admin_scope_type' => 'branch', 'branch_id' => $l['branches']['SBY']->getKey()], 3, []],
            [['branch_id' => $l['branches']['SBY']->getKey()], 3, []],
            [['admin_scope_type' => 'service_area', 'service_area_id' => $l['areas']['SBY-A']->getKey()], 2, []],
            [['admin_scope_type' => 'service_area'], 3, [$l['areas']['SBY-A']->getKey(), $l['areas']['DPS-A']->getKey()]],
            [[], 0, []],
        ] as [$attributes, $count, $ids]) {
            $admin = User::factory()->role('ADMIN')->create($attributes);
            $admin->serviceAreas()->sync($ids);
            $this->assertCount($count, $this->incidents($this->download($admin)));
        }
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $l['branches']['SBY']->getKey()]);
        $this->assertSame([], $this->incidents($this->download($admin, ['branch' => 'Branch DPS'])));
        $this->assertCount(6, $this->incidents($this->download($this->super)));
    }

    public function test_search_zero_and_no_results_keep_missing_values_blank_and_counts_zero(): void
    {
        $this->lop('0001');
        $this->lop('OTHER');
        $sheet = $this->download($this->super, ['q' => '0']);
        $this->assertSame(['0001'], $this->incidents($sheet));
        $this->assertNull($sheet->getCell('A2')->getValue());
        $this->assertNull($sheet->getCell('K2')->getValue());
        $this->assertSame('Belum ada', $sheet->getCell('L2')->getValue());
        $this->assertSame(0, $sheet->getCell('M2')->getValue());
        $this->actingAs($this->super)->get(route('data-lops.index', ['q' => '0']))->assertOk()->assertViewHas('lops', fn ($rows) => $rows->total() === 1);
        $empty = $this->download($this->super, ['q' => 'no-match']);
        $this->assertSame(1, $empty->getHighestRow());
        $this->assertSame('A1:P1', $empty->getAutoFilter()->getRange());
    }

    public function test_export_rejects_non_admin_even_without_role_middleware_and_validates_filters(): void
    {
        $this->get(route('data-lops.export'))->assertRedirect(route('login'));
        $this->withoutMiddleware(EnsureRole::class);
        foreach (['TEKNISI', 'MANAGER', 'APPROVER'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('data-lops.export'))->assertForbidden();
        }
        $this->actingAs($this->super);
        foreach ([['q' => ['bad']], ['q' => str_repeat('x', 256)], ['program' => 'bad'], ['status' => 'bad'], ['branch' => ['bad']], ['page' => -1]] as $filters) {
            foreach (['data-lops.index', 'data-lops.export'] as $route) {
                $this->getJson(route($route, $filters))->assertUnprocessable();
            }
        }
    }

    public function test_download_button_submits_current_filter_form_on_same_origin_without_page_parameter(): void
    {
        URL::forceRootUrl('http://internal.example');
        URL::forceScheme('http');
        try {
            $html = $this->actingAs($this->super)->get('/master-data/lops?q=chosen&program=preventive&status=progress&branch=Branch+SBY&page=2')->assertOk()->getContent();
            $document = new DOMDocument;
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $button = $xpath->query('//button[@formaction="/master-data/lops/export"]')->item(0);
            $this->assertNotNull($button);
            $this->assertSame('data-lop-filters', $button->getAttribute('form'));
            $this->assertSame('GET', $button->getAttribute('formmethod'));
            $form = $xpath->query('//form[@id="data-lop-filters"]')->item(0);
            $this->assertSame('/master-data/lops', $form->getAttribute('action'));
            $this->assertSame(4, $xpath->query('.//*[@name="q" or @name="program" or @name="status" or @name="branch"]', $form)->length);
            $this->assertSame(0, $xpath->query('.//*[@name="page"]', $form)->length);
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }

    public function test_large_export_reads_multiple_chunks_without_loading_evidence_or_losing_rows(): void
    {
        $rows = [];
        for ($i = 1; $i <= 255; $i++) {
            $rows[] = ['incident' => 'CHUNK-'.$i, 'nama_lop' => 'Chunk '.$i, 'program_type' => 'recovery', 'status_lop' => 'draft', 'created_by' => $this->super->getKey(), 'created_at' => '2026-10-16 00:00:00'];
        }
        DB::table('qe_lops')->insert($rows);
        DB::enableQueryLog();
        $incidents = $this->incidents($this->download($this->super));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(255, $incidents);
        $this->assertCount(255, array_unique($incidents));
        $this->assertSame('CHUNK-255', $incidents[0]);
        $this->assertSame('CHUNK-1', $incidents[254]);
        $this->assertLessThan(60, count($queries));
        $this->assertFalse(collect($queries)->contains(fn ($q) => str_contains($q['query'], 'qe_evidences')));
    }
}
