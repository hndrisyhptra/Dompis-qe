<?php

namespace Tests\Feature;

use App\Enums\ExcludeReason;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\IncidentExclude;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

class TicketExcludeMonitoringTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    private User $super;

    private Area $area;

    private array $branches = [];

    private array $areas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 10, 26)->startOfDay());
        config()->set('incident-monitoring.reported_from', '2026-09-01');
        $this->fakeIncidentTickets([]);
        $this->super = User::factory()->role('SUPER_ADMIN')->create();
        $this->area = Area::create(['code' => 'EX-A', 'name' => 'Area Exclude']);
        foreach (['SBY' => ['JATIM', 'REGION JATIM'], 'SMG' => ['JATENGDIY', 'REGION JATENG DIY'], 'DPS' => ['BALNUS', 'REGION BALNUS']] as $sto => [$code, $name]) {
            $region = Region::create(['code' => 'EX-'.$code, 'name' => $name, 'area_id' => $this->area->getKey()]);
            $this->branches[$sto] = Branch::create(['code' => 'EX-'.$sto, 'name' => 'Branch '.$sto, 'region_id' => $region->getKey(), 'region' => 'Legacy']);
            $this->areas[$sto] = ServiceArea::create(['workzone' => $sto, 'name' => $sto, 'branch_id' => $this->branches[$sto]->getKey(), 'region_id' => $region->getKey()]);
        }
    }

    private function ticket(string $incident, ?string $type = 'GAMAS ODP', ?string $sto = 'SBY', ?string $date = '2026-10-26'): void
    {
        DB::connection('mysql_dompis')->table('ticket')->insert([
            'incident' => $incident, 'jenis_tiket_2' => $type, 'workzone' => $sto, 'reported_date' => $date,
        ]);
    }

    private function exclude(string $incident, string $reason = 'non_material'): void
    {
        IncidentExclude::create(['incident' => $incident, 'keterangan_exclude' => $reason, 'created_by' => $this->super->getKey()]);
    }

    public function test_matrix_matches_reference_and_exclusion_is_independent_of_lop_occupancy(): void
    {
        foreach (ExcludeReason::cases() as $index => $reason) {
            $this->ticket('INC-EX-'.$index);
            $this->exclude('INC-EX-'.$index, $reason->value);
        }
        $this->ticket('INC-USED');
        $this->ticket('INC-NORMAL', ' preman ', ' smg ');
        $this->ticket('INC-INFRA', ' infracare ', 'DPS');
        foreach (['INC-USED', 'INC-EX-0'] as $incident) {
            QeLop::create(['incident' => $incident, 'nama_lop' => 'LOP '.$incident,
                'program_type' => 'recovery', 'status_lop' => 'progress', 'created_by' => $this->super->getKey()]);
        }
        foreach (['PREMAN PLUS', 'INFRACARE CUSTOMER', 'OTHER', null] as $index => $type) {
            $this->ticket('INC-IGNORED-'.$index, $type);
        }
        $this->ticket('  ');
        $this->actingAs($this->super)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertSee('Monitoring Exclude')->assertSee('JATENG DIY')->assertSee('JAWA TIMUR')->assertSee('BALI NUSRA')
            ->assertViewHas('totals', fn ($row) => $row['count'] === 7 && $row['cells']['gamas:excluded'] === 4
                && $row['cells']['gamas:not_excluded'] === 1 && $row['cells']['preman:not_excluded'] === 1)
            ->assertViewHas('matrixRows', fn ($rows) => $rows['branch:'.$this->branches['SBY']->getKey()]['count'] === 5
                && $rows['branch:'.$this->branches['SMG']->getKey()]['count'] === 1 && $rows['branch:'.$this->branches['DPS']->getKey()]['count'] === 1)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 7 && $rows->firstWhere('incident', 'INC-EX-0')['is_excluded']
                && ! $rows->firstWhere('incident', 'INC-USED')['is_excluded']);
        $this->get(route('incident-monitoring.tickets'))->assertOk()->assertSee(route('incident-monitoring.exclusions'), false);
    }

    public function test_every_matrix_cell_including_zero_and_total_opens_exact_matching_details(): void
    {
        foreach (['SBY', 'SMG', 'DPS'] as $sto) {
            foreach (['GAMAS ODP', 'PREMAN', 'INFRACARE'] as $type) {
                $incident = 'INC-'.$sto.'-'.$type;
                $this->ticket($incident, $type, $sto);
                $this->exclude($incident, $sto === 'SMG' ? 'temporer' : 'non_material');
                $this->ticket($incident.'-FREE', $type, $sto);
            }
        }
        $response = $this->actingAs($this->super)->get(route('incident-monitoring.exclusions', ['period' => 'month', 'month' => '2026-10']));
        $response->assertOk();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $cells = (new DOMXPath($dom))->query('//a[@data-exclude-cell]');
        $this->assertSame(126, $cells->count());
        foreach ($cells as $cell) {
            $url = $cell->getAttribute('href');
            $count = (int) $cell->getAttribute('data-count');
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame('2026-10', $query['month']);
            $this->assertSame('ticket-details', parse_url($url, PHP_URL_FRAGMENT));
            $this->get($url)->assertOk()->assertViewHas('tickets', function ($tickets) use ($count, $query) {
                $this->assertSame($count, $tickets->total());
                foreach ($tickets as $ticket) {
                    $this->assertSame($query['exclude_state'] === 'excluded', $ticket['is_excluded']);
                    if (isset($query['reason'])) {
                        $this->assertSame($query['reason'], $ticket['exclude_reason']);
                    }
                    $this->assertStringContainsString(strtoupper($query['detail_kind']), $ticket['ticket_type']);
                    if (isset($query['matrix_region'])) {
                        $zones = ['jatim' => 'SBY', 'jatengdiy' => 'SMG', 'balnus' => 'DPS'];
                        foreach ($this->branches as $zone => $branch) {
                            $zones['branch:'.$branch->getKey()] = $zone;
                        }
                        $this->assertSame($zones[$query['matrix_region']], $ticket['workzone']);
                    }
                }

                return true;
            })->assertViewHas('totals', fn ($row) => $row['count'] === 18);
        }
    }

    public function test_date_floor_default_today_and_mixed_source_dates_are_consistent(): void
    {
        foreach (['2026-08-31', '01/09/2026 00:00:00', '2026-10-15 23:59:59', '26/10/2026 23:59:59', '2026-10-27', null, 'invalid'] as $index => $date) {
            $this->ticket('INC-DATE-'.$index, date: $date);
            $this->exclude('INC-DATE-'.$index);
        }
        $this->actingAs($this->super)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertViewHas('totals', fn ($row) => $row['count'] === 3)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 3)
            ->assertViewHas('filters', fn ($filters) => $filters['date_from'] === '2026-09-01' && $filters['date_to'] === '2026-10-26');
        $this->get(route('incident-monitoring.exclusions', ['date_from' => '2026-10-15', 'date_to' => '2026-10-26', 'reason' => 'non_material']))->assertOk()
            ->assertViewHas('totals', fn ($row) => $row['count'] === 2)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 2);
        $this->get(route('incident-monitoring.exclusions', ['date_to' => '2026-10-27']))->assertOk()
            ->assertViewHas('totals', fn ($row) => $row['count'] === 4);
    }

    public function test_month_filter_uses_month_boundaries_and_ignores_inactive_date_fields(): void
    {
        foreach (['2026-09-01', '30/09/2026 23:59:59', '2026-10-01', '2026-10-31', '2026-11-01'] as $index => $date) {
            $this->ticket('INC-MONTH-'.$index, date: $date);
        }
        $this->actingAs($this->super)->get(route('incident-monitoring.exclusions', ['period' => 'month', 'month' => '2026-09', 'date_from' => 'invalid', 'date_to' => '2026-08-01']))->assertOk()
            ->assertViewHas('totals', fn ($row) => $row['count'] === 2)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 2)
            ->assertViewHas('filters', fn ($filters) => $filters['date_from'] === '2026-09-01' && $filters['date_to'] === '2026-09-30');
        $this->get(route('incident-monitoring.exclusions', ['period' => 'month', 'month' => '2026-10']))->assertOk()
            ->assertViewHas('totals', fn ($row) => $row['count'] === 2);
        config()->set('incident-monitoring.reported_from', '2024-02-15');
        foreach (['2024-02-14', '2024-02-15', '29/02/2024 23:59:59', '2024-03-01'] as $index => $date) {
            $this->ticket('INC-LEAP-'.$index, date: $date);
        }
        $this->get(route('incident-monitoring.exclusions', ['period' => 'month', 'month' => '2024-02']))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['date_from'] === '2024-02-15' && $filters['date_to'] === '2024-02-29')
            ->assertViewHas('totals', fn ($row) => $row['count'] === 2);
    }

    public function test_invalid_filters_fail_validation_before_source_queries(): void
    {
        $source = DB::connection('mysql_dompis');
        $source->enableQueryLog();
        foreach ([
            ['period' => 'year'], ['period' => 'month'], ['period' => 'month', 'month' => '2026-13'],
            ['period' => 'month', 'month' => '2026-08'], ['date_from' => '2026-08-31'],
            ['date_to' => '2026-08-31'], ['date_from' => '2026-10-20', 'date_to' => '2026-10-19'],
            ['reason' => 'unknown'], ['exclude_state' => 'unknown'], ['detail_kind' => 'unknown'], ['page' => 0],
        ] as $query) {
            $this->actingAs($this->super)->getJson(route('incident-monitoring.exclusions', $query))->assertUnprocessable();
        }
        $this->assertCount(0, $source->getQueryLog());
    }

    public function test_scope_area_region_branch_legacy_and_multiple_service_areas_apply_to_counts_and_details(): void
    {
        foreach (['SBY', 'SMG', 'DPS'] as $sto) {
            $this->ticket('INC-'.$sto, sto: $sto);
            $this->exclude('INC-'.$sto);
        }
        $this->ticket('INC-UNKNOWN', sto: 'UNKNOWN');
        $outside = Area::create(['code' => 'EX-OUT', 'name' => 'Outside']);
        $region = Region::create(['code' => 'EX-OUT', 'name' => 'Outside region', 'area_id' => $outside->getKey()]);
        $branch = Branch::create(['code' => 'EX-OUT', 'name' => 'Hidden Branch', 'region_id' => $region->getKey()]);
        ServiceArea::create(['workzone' => 'OUT', 'name' => 'OUT', 'branch_id' => $branch->getKey()]);
        $this->ticket('INC-OUTSIDE', sto: 'OUT');
        foreach ([
            [['admin_scope_type' => 'area', 'area_id' => $this->area->getKey()], 3, []],
            [['admin_scope_type' => 'region', 'region_id' => $this->branches['SBY']->region_id], 1, []],
            [['admin_scope_type' => 'branch', 'branch_id' => $this->branches['SBY']->getKey()], 1, []],
            [['branch_id' => $this->branches['SBY']->getKey()], 1, []],
            [['admin_scope_type' => 'service_area', 'service_area_id' => $this->areas['SBY']->getKey()], 1, []],
            [['admin_scope_type' => 'service_area'], 2, [$this->areas['SBY']->getKey(), $this->areas['DPS']->getKey()]],
            [[], 0, []],
        ] as [$attributes, $count, $pivot]) {
            $admin = User::factory()->role('ADMIN')->create($attributes);
            $admin->serviceAreas()->sync($pivot);
            $this->actingAs($admin)->get(route('incident-monitoring.exclusions'))->assertOk()
                ->assertViewHas('totals', fn ($row) => $row['count'] === $count)
                ->assertViewHas('tickets', fn ($rows) => $rows->total() === $count)
                ->assertDontSee('INC-OUTSIDE')->assertDontSee('INC-UNKNOWN')->assertDontSee('Hidden Branch');
            $this->get(route('incident-monitoring.exclusions', ['matrix_region' => 'unmapped']))->assertOk()
                ->assertViewHas('tickets', fn ($rows) => $rows->total() === 0);
        }
    }

    public function test_branch_and_service_area_admin_matrix_groups_rows_by_sto_instead_of_region(): void
    {
        ServiceArea::create([
            'workzone' => 'SBY2', 'name' => 'SBY',
            'branch_id' => $this->branches['SBY']->getKey(), 'region_id' => $this->branches['SBY']->region_id,
        ]);
        foreach (['SBY', 'SBY2', 'SMG', 'DPS'] as $sto) {
            $this->ticket('INC-STO-'.$sto, sto: $sto);
        }

        $branchAdmin = User::factory()->role('ADMIN')->create([
            'admin_scope_type' => 'branch', 'branch_id' => $this->branches['SBY']->getKey(),
        ]);
        $this->actingAs($branchAdmin)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertViewHas('groupBySto', true)
            ->assertViewHas('matrixRows', fn ($rows) => array_keys($rows) === ['sto:SBY', 'sto:SBY2']
                && $rows['sto:SBY']['label'] === 'SBY' && $rows['sto:SBY']['count'] === 1
                && $rows['sto:SBY2']['label'] === 'SBY2' && $rows['sto:SBY2']['count'] === 1)
            ->assertViewHas('matrixGroups', fn ($groups) => count($groups) === 1
                && $groups[0]['name'] === 'SBY' && $groups[0]['row_count'] === 2
                && $groups[0]['count'] === 2 && $groups[0]['cells']['gamas:not_excluded'] === 2)
            ->assertSee('SERVICE AREA / STO')
            ->assertSee('kelompok service area')
            ->assertSee('bg-brand-500', false)
            ->assertSee('bg-brand-100/70', false)
            ->assertSee('id="exclude-group-0"', false)
            ->assertSee('aria-controls="exclude-group-0"', false)
            ->assertSee('2 STO')
            ->assertDontSee('JAWA TIMUR');

        $this->get(route('incident-monitoring.exclusions', ['matrix_region' => 'sto:SBY']))->assertOk()
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['incident'] === 'INC-STO-SBY')
            ->assertViewHas('detailLabel', fn ($label) => str_starts_with($label, 'SBY ·'));
        $this->get(route('incident-monitoring.exclusions', ['matrix_region' => 'jatim']))->assertOk()
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 0);

        $legacyBranchAdmin = User::factory()->role('ADMIN')->create(['branch_id' => $this->branches['SBY']->getKey()]);
        $this->actingAs($legacyBranchAdmin)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertViewHas('groupBySto', true)
            ->assertViewHas('matrixRows', fn ($rows) => array_keys($rows) === ['sto:SBY', 'sto:SBY2']);

        $serviceAreaAdmin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'service_area']);
        $serviceAreaAdmin->serviceAreas()->sync([$this->areas['SBY']->getKey(), $this->areas['DPS']->getKey()]);
        $this->actingAs($serviceAreaAdmin)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertViewHas('groupBySto', true)
            ->assertViewHas('matrixRows', fn ($rows) => array_keys($rows) === ['sto:DPS', 'sto:SBY']
                && $rows['sto:DPS']['label'] === 'DPS' && $rows['sto:SBY']['label'] === 'SBY')
            ->assertViewHas('matrixGroups', fn ($groups) => array_column($groups, 'name') === ['DPS', 'SBY']
                && array_column($groups, 'row_count') === [1, 1])
            ->assertSee('SERVICE AREA / STO')
            ->assertDontSee('BALI NUSRA');

        $areaAdmin = User::factory()->role('ADMIN')->create([
            'admin_scope_type' => 'area', 'area_id' => $this->area->getKey(),
        ]);
        $this->actingAs($areaAdmin)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertViewHas('groupBySto', false)
            ->assertViewHas('groupByBranch', true)
            ->assertViewHas('matrixGroups', fn ($groups) => array_column($groups, 'key') === ['jatengdiy', 'jatim', 'balnus']);

        $regionAdmin = User::factory()->role('ADMIN')->create([
            'admin_scope_type' => 'region', 'region_id' => $this->branches['SBY']->region_id,
        ]);
        $this->actingAs($regionAdmin)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertViewHas('groupBySto', false)
            ->assertViewHas('matrixGroups', [])
            ->assertViewHas('matrixRows', fn ($rows) => array_keys($rows) === ['jatim'] && $rows['jatim']['label'] === 'JAWA TIMUR')
            ->assertSee('>REGIONAL<', false);
    }

    public function test_location_search_and_drill_filters_intersect_without_broadening_admin_scope(): void
    {
        $this->ticket('INC-TARGET', 'PREMAN', ' sby ');
        $this->exclude(' inc-target ', 'temporer');
        $this->ticket('INC-OTHER', 'PREMAN');
        $this->ticket('INC-HIDDEN', 'PREMAN', 'DPS');
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $this->branches['SBY']->getKey()]);
        $filters = ['region' => 'REGION JATIM', 'branch' => $this->branches['SBY']->getKey(), 'service_area' => $this->areas['SBY']->getKey(),
            'q' => 'TARGET', 'matrix_region' => 'sto:SBY', 'detail_kind' => 'preman', 'reason' => 'temporer'];
        $this->actingAs($admin)->get(route('incident-monitoring.exclusions', $filters))->assertOk()
            ->assertViewHas('totals', fn ($row) => $row['count'] === 1)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['incident'] === 'INC-TARGET' && $rows->first()['is_excluded']);
        foreach ([['region' => 'REGION BALNUS'], ['branch' => $this->branches['DPS']->getKey()], ['service_area' => $this->areas['DPS']->getKey()], ['branch' => 999999]] as $filter) {
            $this->get(route('incident-monitoring.exclusions', $filter))->assertOk()
                ->assertViewHas('totals', fn ($row) => $row['count'] === 0)->assertViewHas('tickets', fn ($rows) => $rows->total() === 0)
                ->assertDontSee('INC-HIDDEN');
        }
        $this->get(route('incident-monitoring.exclusions', ['matrix_region' => 'sto:DPS']))->assertOk()
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 0)->assertDontSee('INC-HIDDEN');
    }

    public function test_unmapped_regions_and_legacy_exclude_reasons_remain_visible_and_reconcile(): void
    {
        $this->ticket('INC-UNKNOWN', sto: 'UNKNOWN');
        $this->ticket('INC-NO-STO', sto: null);
        $this->exclude('INC-UNKNOWN', 'legacy reason');
        $this->actingAs($this->super)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertSee('Wilayah belum terpetakan')->assertSee('Alasan lainnya')
            ->assertViewHas('totals', fn ($row) => $row['count'] === 2 && $row['cells']['gamas:other'] === 1)
            ->assertViewHas('matrixRows', fn ($rows) => $rows['unmapped']['count'] === 2);
        $this->get(route('incident-monitoring.exclusions', ['matrix_region' => 'unmapped', 'reason' => 'other']))->assertOk()
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['incident'] === 'INC-UNKNOWN');
        $this->get(route('incident-monitoring.exclusions', ['matrix_region' => 'unmapped', 'exclude_state' => 'not_excluded']))->assertOk()
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['incident'] === 'INC-NO-STO');
    }

    public function test_duplicate_source_rows_and_pagination_match_counts_and_preserve_all_filters(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->ticket('INC-DUPLICATE', 'PREMAN');
        }
        $this->exclude('INC-DUPLICATE');
        $source = DB::connection('mysql_dompis');
        $source->enableQueryLog();
        $this->actingAs($this->super)->get(route('incident-monitoring.exclusions', [
            'period' => 'month', 'month' => '2026-10', 'region' => 'REGION JATIM', 'branch' => $this->branches['SBY']->getKey(),
            'service_area' => $this->areas['SBY']->getKey(), 'q' => 'DUPLICATE', 'matrix_region' => 'jatim',
            'detail_kind' => 'preman', 'reason' => 'non_material', 'page' => 2,
        ]))->assertOk()->assertViewHas('totals', fn ($row) => $row['count'] === 30)
            ->assertViewHas('tickets', function ($rows) {
                $this->assertSame(30, $rows->total());
                $this->assertCount(5, $rows);
                parse_str(parse_url($rows->url(1), PHP_URL_QUERY), $query);
                foreach (['period' => 'month', 'month' => '2026-10', 'q' => 'DUPLICATE', 'matrix_region' => 'jatim', 'detail_kind' => 'preman', 'reason' => 'non_material'] as $key => $value) {
                    $this->assertSame($value, $query[$key]);
                }
                $this->assertSame('ticket-details', parse_url($rows->url(1), PHP_URL_FRAGMENT));

                return true;
            });
        $queries = $source->getQueryLog();
        $this->assertCount(5, $queries);
        $this->assertStringContainsString('limit 25 offset 25', end($queries)['query']);
    }

    public function test_empty_scope_does_not_query_ticket_source(): void
    {
        $admin = User::factory()->role('ADMIN')->create();
        $source = DB::connection('mysql_dompis');
        $source->enableQueryLog();
        $this->actingAs($admin)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertViewHas('totals', fn ($row) => $row['count'] === 0)
            ->assertViewHas('sourceUnavailable', false)->assertViewHas('scopeWarning', true);
        $this->assertCount(0, $source->getQueryLog());
    }

    public function test_source_outage_displays_unknown_counts_instead_of_zero_or_stale_results(): void
    {
        DB::connection('mysql_dompis')->getSchemaBuilder()->drop('ticket');
        $this->actingAs($this->super)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertViewHas('sourceUnavailable', true)->assertSee('Data tiket Dompis belum dapat dimuat.')
            ->assertSee('Detail belum tersedia')->assertDontSee('data-exclude-cell', false)
            ->assertDontSee('exclude-ticket-image-format', false);
    }

    public function test_capture_uses_filtered_matrix_with_period_and_scope_without_filters_or_detail_list(): void
    {
        $this->ticket('INC-CAPTURE', 'PREMAN');
        $this->exclude('INC-CAPTURE');
        $this->ticket('INC-OUTSIDE', 'PREMAN', 'DPS');
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $this->branches['SBY']->getKey()]);
        foreach ([$this->super, $admin] as $user) {
            $response = $this->actingAs($user)->get(route('incident-monitoring.exclusions', [
                'period' => 'month', 'month' => '2026-10', 'q' => 'CAPTURE',
                'region' => 'REGION JATIM', 'branch' => $this->branches['SBY']->getKey(), 'service_area' => $this->areas['SBY']->getKey(),
            ]))->assertOk()->assertViewHas('totals', fn ($row) => $row['count'] === 1);
            $dom = new DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new DOMXPath($dom);
            $capture = $xpath->query('//section[@x-ref="captureArea"]')->item(0);
            $this->assertNotNull($capture);
            $this->assertStringContainsString('dashboardRegionCapture(', $capture->getAttribute('x-data'));
            $this->assertStringContainsString('2026-10-01-2026-10-31', $capture->getAttribute('x-data'));
            $this->assertStringContainsString('01/10/2026', $capture->textContent);
            $this->assertStringContainsString('31/10/2026', $capture->textContent);
            $this->assertStringContainsString('Branch SBY', $capture->textContent);
            $this->assertStringContainsString('Pencarian: CAPTURE', $capture->textContent);
            $this->assertStringContainsString('SUDAH EXCLUDE', $capture->textContent);
            $this->assertSame(1, $xpath->query('.//table', $capture)->count());
            $this->assertSame($user->hasRole(UserRole::SUPER_ADMIN) ? 54 : 36, $xpath->query('.//a[@data-exclude-cell]', $capture)->count());
            $this->assertSame(0, $xpath->query('.//form | .//*[@id="ticket-details"] | .//*[@data-incident]', $capture)->count());
            $this->assertSame(2, $xpath->query('.//select[@id="exclude-ticket-image-format"]/option[@value="png" or @value="jpeg"]', $capture)->count());
            $this->assertSame(1, $xpath->query('.//*[@data-capture-ignore]//button[@title="Capture gambar Monitoring Exclude Tiket"]', $capture)->count());
        }
    }

    public function test_super_and_area_admins_group_branches_by_region_and_keep_filters_and_scope_in_details(): void
    {
        $regionId = $this->branches['SBY']->region_id;
        $jember = Branch::create(['code' => 'EX-JBR', 'name' => 'Branch Jember', 'region_id' => $regionId]);
        $empty = Branch::create(['code' => 'EX-EMPTY', 'name' => 'Branch Empty', 'region_id' => $regionId]);
        ServiceArea::create(['workzone' => 'JBR', 'name' => 'Jember', 'branch_id' => $jember->getKey()]);
        ServiceArea::create(['workzone' => 'SBY2', 'name' => 'Surabaya', 'branch_id' => $this->branches['SBY']->getKey()]);
        $outsideArea = Area::create(['code' => 'EX-OUT', 'name' => 'Outside Area']);
        $outsideRegion = Region::create(['code' => 'EX-OUT', 'name' => 'Outside Region', 'area_id' => $outsideArea->getKey()]);
        $outsideBranch = Branch::create(['code' => 'EX-OUT', 'name' => 'Hidden Branch', 'region_id' => $outsideRegion->getKey()]);
        ServiceArea::create(['workzone' => 'OUT', 'name' => 'Outside STO', 'branch_id' => $outsideBranch->getKey()]);
        $this->ticket('INC-SBY-EX', sto: ' sby ');
        $this->exclude('INC-SBY-EX');
        $this->ticket('INC-SBY-FREE');
        $this->ticket('INC-SBY2-EX', 'PREMAN', 'SBY2');
        $this->exclude('INC-SBY2-EX', 'temporer');
        $this->ticket('INC-JBR-EX', 'INFRACARE', 'JBR');
        $this->exclude('INC-JBR-EX');
        $this->ticket('INC-JBR-OLD', sto: 'JBR', date: '2026-09-30');
        $this->ticket('INC-OUTSIDE', sto: 'OUT');
        $areaAdmin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'area', 'area_id' => $this->area->getKey()]);
        $period = ['period' => 'month', 'month' => '2026-10'];
        foreach ([$this->super, $areaAdmin] as $user) {
            $response = $this->actingAs($user)->get(route('incident-monitoring.exclusions', $period))->assertOk()
                ->assertViewHas('groupByBranch', true)->assertViewHas('groupBySto', false)
                ->assertSee('REGIONAL / BRANCH')->assertSee('kelompok regional')->assertSee('3 Branch')
                ->assertViewHas('totals', fn ($row) => $row['count'] === ($user->hasRole(UserRole::SUPER_ADMIN) ? 5 : 4))
                ->assertViewHas('matrixRows', fn ($rows) => $rows['branch:'.$empty->getKey()]['count'] === 0
                    && $rows['branch:'.$jember->getKey()]['count'] === 1
                    && $rows['branch:'.$this->branches['SBY']->getKey()]['count'] === 3)
                ->assertViewHas('matrixGroups', function ($groups) {
                    $jatim = collect($groups)->firstWhere('key', 'jatim');
                    $this->assertSame('JAWA TIMUR', $jatim['name']);
                    $this->assertSame(3, $jatim['row_count']);
                    $this->assertSame(4, $jatim['count']);
                    foreach (['gamas:excluded', 'gamas:not_excluded', 'preman:excluded', 'infracare:excluded'] as $cell) {
                        $this->assertSame(1, $jatim['cells'][$cell]);
                    }
                    foreach ($jatim['cells'] as $cell => $count) {
                        $this->assertSame($count, array_sum(array_column(array_column($jatim['rows'], 'cells'), $cell)));
                    }

                    return true;
                });
            $this->get(route('incident-monitoring.exclusions', [...$period, 'matrix_region' => 'jatim']))->assertOk()
                ->assertViewHas('tickets', fn ($rows) => $rows->total() === 4)
                ->assertViewHas('detailLabel', fn ($label) => str_starts_with($label, 'JAWA TIMUR ·'));
            $this->get(route('incident-monitoring.exclusions', [...$period, 'matrix_region' => 'branch:'.$this->branches['SBY']->getKey()]))->assertOk()
                ->assertViewHas('tickets', fn ($rows) => $rows->total() === 3 && $rows->pluck('workzone')->every(fn ($zone) => in_array($zone, ['SBY', 'SBY2'], true)))
                ->assertViewHas('detailLabel', fn ($label) => str_starts_with($label, 'Branch SBY ·'));
            $this->get(route('incident-monitoring.exclusions', [...$period, 'matrix_region' => 'branch:'.$jember->getKey(), 'detail_kind' => 'infracare', 'reason' => 'non_material']))->assertOk()
                ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['incident'] === 'INC-JBR-EX');
            $this->get(route('incident-monitoring.exclusions', [...$period, 'branch' => $jember->getKey()]))->assertOk()
                ->assertViewHas('matrixRows', fn ($rows) => array_keys($rows) === ['branch:'.$jember->getKey()]);
            $this->get(route('incident-monitoring.exclusions', [...$period, 'service_area' => $this->areas['SBY']->getKey()]))->assertOk()
                ->assertViewHas('matrixRows', fn ($rows) => array_keys($rows) === ['branch:'.$this->branches['SBY']->getKey()])
                ->assertViewHas('totals', fn ($row) => $row['count'] === 2);
            $this->get(route('incident-monitoring.exclusions', [...$period, 'q' => 'JBR']))->assertOk()
                ->assertViewHas('totals', fn ($row) => $row['count'] === 1);
            $this->get(route('incident-monitoring.exclusions', ['date_from' => '2026-09-30', 'date_to' => '2026-09-30', 'matrix_region' => 'branch:'.$jember->getKey()]))->assertOk()
                ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['incident'] === 'INC-JBR-OLD');
            if ($user->hasRole(UserRole::ADMIN)) {
                $response->assertDontSee('Hidden Branch')->assertDontSee('INC-OUTSIDE');
                foreach (['branch:'.$outsideBranch->getKey(), 'region:OUTSIDE REGION', 'sto:OUT', 'branch:999999'] as $key) {
                    $this->get(route('incident-monitoring.exclusions', [...$period, 'matrix_region' => $key]))->assertOk()
                        ->assertViewHas('tickets', fn ($rows) => $rows->total() === 0)->assertDontSee('INC-OUTSIDE');
                }
            }
        }
    }

    public function test_branch_groups_keep_legacy_regions_and_unmapped_tickets_with_exact_parent_and_child_details(): void
    {
        $legacy = Branch::create(['code' => 'EX-LEG', 'name' => 'Legacy Branch', 'region' => 'REGION JATIM']);
        $unknownRegion = Branch::create(['code' => 'EX-NOREGION', 'name' => 'Branch without region']);
        foreach (['LEG' => $legacy, 'NOREGION' => $unknownRegion] as $zone => $branch) {
            ServiceArea::create(['workzone' => $zone, 'name' => $zone, 'branch_id' => $branch->getKey()]);
            $this->ticket('INC-'.$zone, sto: $zone);
        }
        $this->ticket('INC-UNMAPPED', sto: 'UNKNOWN');
        $this->ticket('INC-NO-STO', sto: null);
        $this->actingAs($this->super)->get(route('incident-monitoring.exclusions'))->assertOk()
            ->assertViewHas('matrixGroups', fn ($groups) => collect($groups)->firstWhere('key', 'jatim')['count'] === 1
                && collect($groups)->firstWhere('key', 'region:unmapped')['count'] === 3)
            ->assertViewHas('matrixRows', fn ($rows) => $rows['unmapped']['count'] === 2 && $rows['branch:'.$unknownRegion->getKey()]['count'] === 1);
        foreach (['jatim' => 1, 'region:unmapped' => 3, 'unmapped' => 2, 'branch:'.$legacy->getKey() => 1, 'branch:'.$unknownRegion->getKey() => 1] as $key => $count) {
            $this->get(route('incident-monitoring.exclusions', ['matrix_region' => $key]))->assertOk()
                ->assertViewHas('tickets', fn ($rows) => $rows->total() === $count);
        }
    }

    public function test_not_excluded_detail_offers_reason_selector_and_exclude_action_then_updates_matrix(): void
    {
        $this->ticket('INC-ACTION', 'PREMAN', 'DPS');
        $this->ticket('INC-ALREADY', 'PREMAN', 'DPS');
        $this->exclude('INC-ALREADY', 'legacy reason');
        QeLop::create(['incident' => 'INC-ACTION', 'nama_lop' => 'Existing LOP',
            'program_type' => 'recovery', 'status_lop' => 'progress', 'created_by' => $this->super->getKey()]);
        $url = route('incident-monitoring.exclusions', ['period' => 'month', 'month' => '2026-10',
            'matrix_region' => 'balnus', 'detail_kind' => 'preman', 'exclude_state' => 'not_excluded']).'#ticket-details';
        $response = $this->actingAs($this->super)->get($url)->assertOk();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($dom);
        $form = $xpath->query('//tr[@data-incident="INC-ACTION"]//form')->item(0);
        $this->assertNotNull($form);
        $this->assertSame(route('incident-monitoring.tickets.exclude'), $form->getAttribute('action'));
        $this->assertSame('POST', $form->getAttribute('method'));
        $this->assertSame('INC-ACTION', $xpath->query('.//input[@name="incident"]', $form)->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->count());
        $options = $xpath->query('.//select[@name="reason"]/option[@value!=""]', $form);
        $this->assertSame(array_column(ExcludeReason::cases(), 'value'), array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($options)));
        $this->assertSame(1, $xpath->query('.//button[@type="submit"]', $form)->count());
        $this->from($url)->post($form->getAttribute('action'), ['incident' => 'INC-ACTION', 'reason' => 'temporer'])
            ->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('status', 'Tiket berhasil di-exclude.');
        $this->assertDatabaseHas('incident_excludes', ['incident' => 'INC-ACTION', 'keterangan_exclude' => 'temporer', 'created_by' => $this->super->getKey()]);
        $this->get($url)->assertOk()->assertViewHas('tickets', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('totals', fn ($row) => $row['cells']['preman:not_excluded'] === 0
                && $row['cells']['preman:excluded'] === 2 && $row['cells']['preman:temporer'] === 1);
        $this->get(route('incident-monitoring.exclusions', ['matrix_region' => 'balnus', 'detail_kind' => 'preman', 'exclude_state' => 'excluded']))->assertOk()
            ->assertSee('Batalkan exclude tiket INC-ACTION')->assertDontSee('Alasan exclude tiket INC-ACTION');
        $this->assertDatabaseHas('qe_lops', ['incident' => 'INC-ACTION', 'status_lop' => 'progress']);
    }

    public function test_admin_exclude_action_keeps_scope_checks_and_requires_a_valid_reason(): void
    {
        $this->ticket('INC-IN-SCOPE', 'PREMAN', 'DPS');
        $this->ticket('INC-OUTSIDE', 'PREMAN', 'SBY');
        $admin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'area', 'area_id' => $this->area->getKey()]);
        $url = route('incident-monitoring.exclusions', ['matrix_region' => 'branch:'.$this->branches['DPS']->getKey(),
            'detail_kind' => 'preman', 'exclude_state' => 'not_excluded']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Alasan exclude tiket INC-IN-SCOPE')->assertDontSee('Alasan exclude tiket INC-OUTSIDE');
        foreach (['', 'other', 'unknown'] as $reason) {
            $this->from($url)->post(route('incident-monitoring.tickets.exclude'), ['incident' => 'INC-IN-SCOPE', 'reason' => $reason])
                ->assertRedirect($url)->assertSessionHasErrors('reason');
            $this->assertDatabaseMissing('incident_excludes', ['incident' => 'INC-IN-SCOPE']);
        }
        $this->from($url)->post(route('incident-monitoring.tickets.exclude'), ['incident' => 'INC-IN-SCOPE', 'reason' => 'non_material'])
            ->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incident_excludes', ['incident' => 'INC-IN-SCOPE', 'created_by' => $admin->getKey()]);

        $branchAdmin = User::factory()->role('ADMIN')->create(['branch_id' => $this->branches['DPS']->getKey()]);
        $this->actingAs($branchAdmin)->from($url)->post(route('incident-monitoring.tickets.exclude'), ['incident' => 'INC-OUTSIDE', 'reason' => 'non_material'])
            ->assertForbidden();
        $this->assertDatabaseMissing('incident_excludes', ['incident' => 'INC-OUTSIDE']);
    }

    public function test_access_is_limited_to_admin_and_super_admin(): void
    {
        $this->get(route('incident-monitoring.exclusions'))->assertRedirect(route('login'));
        foreach (['TEKNISI', 'MANAGER', 'APPROVER'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('incident-monitoring.exclusions'))->assertForbidden();
        }
    }
}
