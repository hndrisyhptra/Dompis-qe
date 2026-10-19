<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRole;
use App\Models\Area;
use App\Models\Branch;
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
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

class TicketCoverageTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    private User $super;

    private array $locations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeIncidentTickets([]);
        $this->super = User::factory()->role('SUPER_ADMIN')->create();
        $area = Area::create(['code' => 'TC-A', 'name' => 'Area Tiket']);
        $outsideArea = Area::create(['code' => 'TC-B', 'name' => 'Area Luar']);
        $jatim = Region::create(['code' => 'TC-J', 'name' => 'JATIM', 'area_id' => $area->getKey()]);
        $balnus = Region::create(['code' => 'TC-N', 'name' => 'BALNUS', 'area_id' => $area->getKey()]);
        $outside = Region::create(['code' => 'TC-O', 'name' => 'LUAR', 'area_id' => $outsideArea->getKey()]);
        $branches = [];
        foreach (['SBY' => $jatim, 'SDA' => $jatim, 'DPS' => $balnus, 'OUT' => $outside] as $code => $region) {
            $branches[$code] = Branch::create(['code' => 'TC-'.$code, 'name' => 'Branch '.$code, 'region_id' => $region->getKey(), 'region' => 'Legacy region']);
        }
        $areas = [];
        foreach (['SBY-A' => 'SBY', 'SBY-B' => 'SBY', 'SDA-A' => 'SDA', 'DPS-A' => 'DPS', 'OUT-A' => 'OUT'] as $workzone => $code) {
            $branch = $branches[$code];
            $areas[$workzone] = ServiceArea::create(['workzone' => $workzone, 'name' => $workzone, 'branch_id' => $branch->getKey(), 'region_id' => $branch->region_id]);
        }
        $this->locations = compact('area', 'jatim', 'balnus', 'branches', 'areas');
    }

    private function ticket(string $incident, ?string $type = 'GAMAS GPON', ?string $workzone = 'SBY-A', ?string $reportedDate = null): void
    {
        DB::connection('mysql_dompis')->table('ticket')->insert(['incident' => $incident, 'jenis_tiket_2' => $type, 'workzone' => $workzone, 'reported_date' => $reportedDate]);
    }

    private function lop(string $incident, string $workzone = 'SBY-A', array $attributes = []): QeLop
    {
        $area = $this->locations['areas'][$workzone];

        return QeLop::create(array_replace([
            'incident' => $incident, 'nama_lop' => 'LOP '.$incident, 'program_type' => 'preventive',
            'status_lop' => 'progress', 'ihld_id' => '0012345', 'created_by' => $this->super->getKey(),
            'branch_id' => $area->branch_id, 'service_area_id' => $area->getKey(),
            'branch' => $area->branch->name, 'sto' => $area->workzone,
        ], $attributes));
    }

    public function test_default_page_lists_only_unused_infracare_and_gamas_tickets_and_keeps_coverage_totals(): void
    {
        $this->ticket('INC-FREE', 'GAMAS GPON');
        $this->ticket('INC-INFRACARE', ' infracare ');
        $this->ticket('INC-USED', 'gamas odp');
        $this->ticket('INC-ARCHIVE', 'INFRACARE');
        $this->lop('INC-USED');
        $this->lop('INC-ARCHIVE')->delete();
        foreach (['UNKNOWN', 'REG', 'NON NUMBERING', 'INFRACARE CUSTOMER', null] as $index => $type) {
            $this->ticket('INC-EXCLUDED-'.$index, $type);
        }
        $this->ticket('  ', 'GAMAS');
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets'))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['usage'] === 'without_lop')
            ->assertViewHas('stats', ['total' => 4, 'without_lop' => 2, 'with_lop' => 2])
            ->assertViewHas('chartRows', fn ($rows) => $rows->sum('count') === 4 && $rows->firstWhere('kind', 'gamas')['count'] === 2)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 2 && $rows->first()['incident'] === 'INC-INFRACARE')
            ->assertSee('INC-FREE')->assertSee('INC-INFRACARE')->assertDontSee('INC-USED')->assertDontSee('INC-ARCHIVE')
            ->assertDontSee('INC-EXCLUDED')->assertDontSee('LOP / Status')->assertDontSee('LOP diarsipkan');
        $this->assertDatabaseCount('qe_lops', 2);
    }

    public function test_used_tickets_show_active_lop_details_and_archived_references_remain_occupied_without_an_archive_filter(): void
    {
        $used = $this->lop('INC-USED', attributes: ['status_lop' => 'waiting_approval']);
        $archived = $this->lop('INC-ARCHIVE');
        $archived->delete();
        $this->ticket('INC-USED');
        $this->ticket('INC-ARCHIVE', 'INFRACARE');
        $this->ticket('INC-FREE');
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets', ['usage' => 'with_lop']))->assertOk()
            ->assertSee('LOP INC-USED')->assertSee('0012345')->assertSee('Waiting Approval')->assertSee(route('lop.detail', $used, false), false)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 2 && $rows->firstWhere('incident', 'INC-ARCHIVE')['lop'] === null)
            ->assertDontSee('LOP INC-ARCHIVE')->assertDontSee(route('lop.show', $archived), false)->assertDontSee('LOP diarsipkan');
        $this->getJson(route('incident-monitoring.tickets', ['usage' => 'archived']))->assertUnprocessable()->assertJsonValidationErrors('usage');
        $this->get(route('incident-monitoring.tickets', ['usage' => 'all']))->assertViewHas('tickets', fn ($rows) => $rows->total() === 3);
    }

    public function test_all_admin_scopes_including_multi_sto_and_legacy_branch_scope_apply_to_source_workzones(): void
    {
        foreach (array_keys($this->locations['areas']) as $zone) {
            $this->ticket('INC-'.$zone, 'INFRACARE', $zone);
        }
        $l = $this->locations;
        $cases = [
            [['admin_scope_type' => 'area', 'area_id' => $l['area']->getKey()], 4, []],
            [['admin_scope_type' => 'region', 'region_id' => $l['jatim']->getKey()], 3, []],
            [['admin_scope_type' => 'branch', 'branch_id' => $l['branches']['SBY']->getKey()], 2, []],
            [['branch_id' => $l['branches']['SBY']->getKey()], 2, []],
            [['admin_scope_type' => 'service_area', 'service_area_id' => $l['areas']['SBY-A']->getKey()], 1, []],
            [['admin_scope_type' => 'service_area'], 2, [$l['areas']['SBY-A']->getKey(), $l['areas']['DPS-A']->getKey()]],
        ];
        foreach ($cases as [$attributes, $count, $pivot]) {
            $admin = User::factory()->role('ADMIN')->create($attributes);
            $admin->serviceAreas()->sync($pivot);
            $this->actingAs($admin)->get(route('incident-monitoring.tickets'))->assertOk()
                ->assertViewHas('stats', fn ($stats) => $stats['total'] === $count)
                ->assertViewHas('tickets', fn ($rows) => $rows->total() === $count)->assertDontSee('INC-OUT-A')->assertDontSee('Branch OUT');
        }
    }

    public function test_source_ticket_with_a_lop_outside_scope_is_occupied_without_exposing_lop_identity(): void
    {
        $this->ticket('INC-RESTRICTED');
        $this->ticket('INC-ARCHIVE-RESTRICTED');
        $this->ticket('INC-FREE');
        $hidden = $this->lop('INC-RESTRICTED', 'DPS-A', ['nama_lop' => 'RAHASIA LOP AKTIF', 'ihld_id' => 'RAHASIA-IHLD']);
        $this->lop('INC-ARCHIVE-RESTRICTED', 'DPS-A', ['nama_lop' => 'RAHASIA ARSIP'])->delete();
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $this->locations['branches']['SBY']->getKey()]);
        $this->actingAs($admin)->get(route('incident-monitoring.tickets'))->assertViewHas('stats', ['total' => 3, 'without_lop' => 1, 'with_lop' => 2])
            ->assertSee('INC-FREE')->assertDontSee('INC-RESTRICTED')->assertDontSee('INC-ARCHIVE-RESTRICTED');
        $this->get(route('incident-monitoring.tickets', ['usage' => 'all']))->assertOk()
            ->assertSee('INC-RESTRICTED')->assertSee('Detail LOP tidak tersedia')->assertDontSee('RAHASIA')->assertDontSee(route('lop.show', $hidden), false)
            ->assertViewHas('tickets', fn ($rows) => $rows->where('usage', 'with_lop')->first()['lop'] === null);
    }

    public function test_normalized_source_workzones_and_incidents_match_scope_and_legacy_lop_references(): void
    {
        $this->ticket(' inc-used ', ' gamas ODP ', ' sby-a ');
        $this->ticket('INC-FREE', 'INFRACARE', 'sby-a');
        $this->lop('INC-USED', attributes: ['branch_id' => null, 'service_area_id' => null]);
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $this->locations['branches']['SBY']->getKey()]);
        $this->actingAs($admin)->get(route('incident-monitoring.tickets', ['usage' => 'with_lop']))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 2 && $stats['with_lop'] === 1)
            ->assertSee('LOP INC-USED')->assertSee('0012345')->assertSee('GAMAS ODP');
    }

    public function test_location_type_and_search_filters_are_combined_without_broadening_scope(): void
    {
        $l = $this->locations;
        $this->ticket('INC-TARGET', 'INFRACARE');
        $this->ticket('INC-OTHER', 'GAMAS ODP');
        $this->ticket('INC-OUTSIDE', 'INFRACARE', 'DPS-A');
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $l['branches']['SBY']->getKey()]);
        $filters = ['region' => 'JATIM', 'branch' => $l['branches']['SBY']->getKey(), 'service_area' => $l['areas']['SBY-A']->getKey(), 'kind' => 'infracare', 'q' => 'TARGET'];
        $this->actingAs($admin)->get(route('incident-monitoring.tickets', $filters))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['incident'] === 'INC-TARGET');
        foreach ([['region' => 'BALNUS'], ['branch' => $l['branches']['DPS']->getKey()], ['service_area' => $l['areas']['DPS-A']->getKey()], ['branch' => 999999]] as $outside) {
            $this->get(route('incident-monitoring.tickets', $outside))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['total'] === 0)->assertDontSee('INC-OUTSIDE');
        }
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets', ['region' => 'JATIM', 'branch' => $l['branches']['DPS']->getKey()]))
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 0);
        $this->get(route('incident-monitoring.tickets', ['q' => 'SBY-A']))->assertViewHas('stats', fn ($stats) => $stats['total'] === 2);
    }

    public function test_super_admin_sees_unmapped_workzones_and_admin_cannot_see_them(): void
    {
        $this->ticket('INC-UNMAPPED', 'GAMAS', 'NOT-IN-MASTER');
        $this->ticket('INC-EMPTY-WORKZONE', 'INFRACARE', null);
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 2)->assertSee('INC-UNMAPPED')->assertSee('Belum terisi');
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $this->locations['branches']['SBY']->getKey()]);
        $this->actingAs($admin)->get(route('incident-monitoring.tickets'))->assertViewHas('stats', fn ($stats) => $stats['total'] === 0)
            ->assertDontSee('INC-UNMAPPED')->assertDontSee('INC-EMPTY-WORKZONE');
    }

    public function test_pagination_preserves_zero_search_type_and_usage_and_loads_only_25_source_rows(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->ticket('INC0-'.$i, reportedDate: '15/10/2026 23:59:59');
        }
        $source = DB::connection('mysql_dompis');
        $source->enableQueryLog();
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets', ['q' => '0', 'kind' => 'gamas', 'usage' => 'without_lop', 'date_from' => '2026-10-15', 'date_to' => '2026-10-15', 'page' => 2]))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 30)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 30 && $rows->count() === 5 && $rows->first()['incident'] === 'INC0-5'
                && str_contains($rows->url(1), 'q=0') && str_contains($rows->url(1), 'kind=gamas') && str_contains($rows->url(1), 'usage=without_lop')
                && str_contains($rows->url(1), 'date_from=2026-10-15') && str_contains($rows->url(1), 'date_to=2026-10-15'));
        $queries = collect($source->getQueryLog());
        $this->assertCount(3, $queries);
        $this->assertStringContainsString('limit 25 offset 25', $queries->last()['query']);
        $this->assertStringNotContainsString('select *', $queries->last()['query']);
        $this->get(route('incident-monitoring.tickets', ['page' => 99]))->assertOk()->assertSee('Kembali ke halaman pertama');
    }

    public function test_large_occupancy_list_does_not_hide_free_tickets_or_lose_usage_counts(): void
    {
        $template = $this->lop('INC-USED-0')->getAttributes();
        unset($template['id_qe_lops']);
        $rows = [];
        for ($i = 1; $i <= 505; $i++) {
            $rows[] = [...$template, 'incident' => 'INC-USED-'.$i];
        }
        QeLop::insert($rows);
        $this->ticket('INC-USED-505');
        $this->ticket('INC-FREE');
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets'))->assertOk()
            ->assertViewHas('stats', ['total' => 2, 'without_lop' => 1, 'with_lop' => 1])->assertSee('INC-FREE')->assertDontSee('INC-USED-505');
    }

    public function test_source_outage_is_not_reported_as_zero_tickets_and_recovers_without_stale_cache(): void
    {
        DB::connection('mysql_dompis')->getSchemaBuilder()->drop('ticket');
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets'))->assertOk()
            ->assertViewHas('sourceUnavailable', true)->assertViewHas('stats', null)->assertSee('Data tiket Dompis belum dapat dimuat.')
            ->assertDontSee('Tidak ada tiket pada pilihan ini.');
        $this->fakeIncidentTickets([]);
        $this->ticket('INC-RECOVERED', 'INFRACARE');
        $this->get(route('incident-monitoring.tickets'))->assertOk()->assertViewHas('sourceUnavailable', false)->assertSee('INC-RECOVERED');
    }

    public function test_admin_without_sto_scope_never_queries_the_source(): void
    {
        DB::connection('mysql_dompis')->getSchemaBuilder()->drop('ticket');
        $admin = User::factory()->role('ADMIN')->create();
        $this->actingAs($admin)->get(route('incident-monitoring.tickets'))->assertOk()
            ->assertViewHas('scopeWarning', true)->assertViewHas('sourceUnavailable', false)
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 0);
    }

    public function test_access_is_authorized_in_backend_and_existing_lop_monitoring_remains_available(): void
    {
        $this->get(route('incident-monitoring.tickets'))->assertRedirect(route('login'));
        foreach (['TEKNISI', 'MANAGER', 'APPROVER'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('incident-monitoring.tickets'))->assertForbidden();
        }
        $this->withoutMiddleware(EnsureRole::class)->get(route('incident-monitoring.tickets'))->assertForbidden();
        $this->lop('INP-MANUAL');
        $this->actingAs($this->super)->get(route('incident-monitoring.index'))->assertOk()->assertSee('INP-MANUAL')
            ->assertSee(route('incident-monitoring.tickets'), false)->assertSee('Incident pada LOP');
        $this->get(route('incident-monitoring.tickets'))->assertOk()->assertSee('Tiket Dompis')->assertDontSee('INP-MANUAL');
    }

    public function test_source_values_are_escaped_and_invalid_filters_rejected(): void
    {
        $this->ticket('INC-<script>1</script>', 'GAMAS <img src=x onerror=alert(1)>', '<script>2</script>');
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets'))->assertOk()
            ->assertSee('INC-&lt;SCRIPT&gt;1&lt;/SCRIPT&gt;', false)->assertDontSee('<IMG SRC=X ONERROR=ALERT(1)>', false);
        $this->getJson(route('incident-monitoring.tickets', ['kind' => 'UNKNOWN', 'usage' => 'fake', 'branch' => 'oops', 'page' => 0, 'q' => ['x']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['kind', 'usage', 'branch', 'page', 'q']);
    }

    public function test_reported_date_range_is_inclusive_for_iso_and_day_first_slash_formats_across_counts_chart_and_rows(): void
    {
        $this->ticket('INC-BEFORE', reportedDate: '2026-10-14 23:59:59');
        $this->ticket('INC-START', reportedDate: '2026-10-15 00:00:00');
        $this->ticket('INC-SLASH-START', 'INFRACARE', reportedDate: '15/10/2026 23:59:59');
        $this->ticket('INC-END', reportedDate: '2026-10-16 23:59:59');
        $this->ticket('INC-SLASH-DATE', reportedDate: '16/10/2026');
        $this->ticket('INC-AFTER', reportedDate: '2026-10-17 00:00:00');
        $this->ticket('INC-MISSING');
        $this->ticket('INC-EMPTY', reportedDate: '');
        $this->ticket('INC-INVALID', reportedDate: 'not-a-date');
        $this->lop('INC-START');
        DB::connection('mysql_dompis')->table('ticket')->where('incident', 'INC-START')->update(['reported_date_dt' => '2026-10-14 17:00:00']);
        $filters = ['date_from' => '2026-10-15', 'date_to' => '2026-10-16'];
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets', $filters))->assertOk()
            ->assertViewHas('stats', ['total' => 4, 'without_lop' => 3, 'with_lop' => 1])
            ->assertViewHas('chartRows', fn ($rows) => $rows->sum('count') === 4)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 3 && $rows->first()['reported_date'] === '16/10/2026')
            ->assertSee('INC-SLASH-START')->assertSee('INC-END')->assertDontSee('INC-BEFORE')->assertDontSee('INC-AFTER')->assertDontSee('INC-MISSING');
        $this->get(route('incident-monitoring.tickets', [...$filters, 'usage' => 'with_lop']))->assertOk()
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['incident'] === 'INC-START');
        $this->get(route('incident-monitoring.tickets', [...$filters, 'usage' => 'all']))->assertOk()
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 4)->assertSee('15/10/2026')->assertSee('16/10/2026');
    }

    public function test_one_sided_date_ranges_and_reset_preserve_all_periods_by_default(): void
    {
        $this->ticket('INC-BEFORE', reportedDate: '2026-10-14 23:59:59');
        $this->ticket('INC-DAY', reportedDate: '15/10/2026 00:00:00');
        $this->ticket('INC-AFTER', reportedDate: '2026-10-16 00:00:00');
        $this->ticket('INC-INVALID', reportedDate: 'not-a-date');
        $this->ticket('INC-MISSING');
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets', ['date_from' => '2026-10-15']))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 2)->assertDontSee('INC-INVALID')->assertDontSee('INC-MISSING');
        $this->get(route('incident-monitoring.tickets', ['date_to' => '2026-10-15']))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 2)->assertDontSee('INC-INVALID')->assertDontSee('INC-MISSING');
        $this->get(route('incident-monitoring.tickets'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 5)
            ->assertViewHas('filters', fn ($filters) => $filters['date_from'] === '' && $filters['date_to'] === '');
    }

    public function test_slash_dates_use_day_month_year_and_date_range_keeps_existing_admin_scope(): void
    {
        $this->ticket('INC-TARGET', 'INFRACARE', reportedDate: '5/6/2026 00:01:00');
        $this->ticket('INC-OTHER-DAY', 'INFRACARE', reportedDate: '6/5/2026 23:59:59');
        $this->ticket('INC-OUTSIDE', 'INFRACARE', 'DPS-A', '5/6/2026 12:00:00');
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $this->locations['branches']['SBY']->getKey()]);
        $this->actingAs($admin)->get(route('incident-monitoring.tickets', ['date_from' => '2026-06-05', 'date_to' => '2026-06-05', 'kind' => 'infracare']))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['reported_date'] === '05/06/2026')
            ->assertSee('INC-TARGET')->assertDontSee('INC-OTHER-DAY')->assertDontSee('INC-OUTSIDE');
    }

    public function test_invalid_calendar_dates_and_reversed_ranges_are_rejected_before_source_queries(): void
    {
        $source = DB::connection('mysql_dompis');
        $source->enableQueryLog();
        $this->actingAs($this->super);
        foreach ([['date_from' => '2026-02-30'], ['date_to' => 'not-a-date'], ['date_from' => ['2026-10-15']]] as $filters) {
            $this->getJson(route('incident-monitoring.tickets', $filters))->assertUnprocessable()->assertJsonValidationErrors(array_keys($filters));
        }
        $this->getJson(route('incident-monitoring.tickets', ['date_from' => '2026-10-16', 'date_to' => '2026-10-15']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->assertCount(0, $source->getQueryLog());
    }

    public function test_download_and_filter_actions_remain_same_origin_when_backend_detects_http(): void
    {
        $this->ticket('INC-DOWNLOAD', reportedDate: '2026-10-16');
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $this->locations['branches']['SBY']->getKey()]);
        URL::forceRootUrl('http://internal.example');
        try {
            foreach (['http', 'https'] as $scheme) {
                URL::forceScheme($scheme);
                foreach ([$this->super, $admin] as $user) {
                    $html = $this->actingAs($user)->get('/incident-monitoring/tickets')->assertOk()->getContent();
                    $document = new DOMDocument;
                    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
                    $xpath = new DOMXPath($document);
                    $this->assertSame('/incident-monitoring/tickets', $xpath->query('//form[@id="ticket-filters"]')->item(0)->getAttribute('action'));
                    $buttons = $xpath->query('//button[@data-ticket-download]');
                    $this->assertSame(3, $buttons->length);
                    foreach ($buttons as $button) {
                        $this->assertSame('/incident-monitoring/tickets/download', $button->getAttribute('formaction'));
                        $this->assertSame('ticket-filters', $button->getAttribute('form'));
                        $this->assertSame('GET', $button->getAttribute('formmethod'));
                    }
                }
            }
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }

    public function test_month_filter_is_removed_and_old_month_parameter_does_not_limit_page_or_download(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->ticket('INC-'.$i, reportedDate: '2026-10-16');
        }
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets', ['month' => '2026-09']))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 26)
            ->assertViewHas('filters', fn ($filters) => ! array_key_exists('month', $filters))
            ->assertViewHas('tickets', fn ($rows) => ! str_contains($rows->url(2), 'month'))
            ->assertDontSee('name="month"', false)->assertDontSee('Bulan laporan');
        $sheet = $this->downloadSheet($this->super, ['month' => '2026-09']);
        $this->assertCount(26, $this->exportedIncidents($sheet));
        $this->assertSame('Dasar tanggal', $sheet->getCell('A3')->getValue());
        $this->assertSame('reported_date tiket Dompis', $sheet->getCell('B3')->getValue());
    }

    public function test_date_range_handles_leap_day_for_kpi_chart_and_rows(): void
    {
        $this->ticket('INC-JAN', reportedDate: '2024-01-31 23:59:59');
        $this->ticket('INC-FEB-START', reportedDate: '2024-02-01 00:00:00');
        $this->ticket('INC-FEB-END', 'INFRACARE', reportedDate: '29/2/2024 23:59:59');
        $this->ticket('INC-MAR', reportedDate: '2024-03-01 00:00:00');
        $this->ticket('INC-NO-DATE');
        $this->lop('INC-FEB-START');
        $this->actingAs($this->super)->get(route('incident-monitoring.tickets', ['date_from' => '2024-02-01', 'date_to' => '2024-02-29', 'usage' => 'all']))->assertOk()
            ->assertViewHas('stats', ['total' => 2, 'without_lop' => 1, 'with_lop' => 1])
            ->assertViewHas('chartRows', fn ($rows) => $rows->sum('count') === 2)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 2)
            ->assertDontSee('Bulan laporan')->assertSee('Download Excel')->assertDontSee('INC-JAN')->assertDontSee('INC-MAR')->assertDontSee('INC-NO-DATE');
        $this->get(route('incident-monitoring.tickets', ['date_from' => '2024-02-28', 'date_to' => '2024-02-29']))->assertOk()
            ->assertViewHas('stats', ['total' => 1, 'without_lop' => 1, 'with_lop' => 0])->assertSee('INC-FEB-END')->assertDontSee('INC-FEB-START');
        $this->get(route('incident-monitoring.tickets', ['date_from' => '2024-03-02', 'date_to' => '2024-03-31']))->assertOk()
            ->assertViewHas('stats', ['total' => 0, 'without_lop' => 0, 'with_lop' => 0]);
    }

    private function downloadSheet(User $user, array $filters = []): Worksheet
    {
        $response = $this->actingAs($user)->get(route('incident-monitoring.tickets.download', $filters))->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));

        return IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    }

    private function exportedIncidents(Worksheet $sheet): array
    {
        return $sheet->getHighestRow() <= 16 ? [] : array_column($sheet->rangeToArray('A17:A'.$sheet->getHighestRow(), null, false, false), 0);
    }

    public function test_three_download_categories_include_consistent_summary_and_correct_rows_beyond_pagination(): void
    {
        for ($i = 1; $i <= 31; $i++) {
            $this->ticket('INC-'.$i, $i % 2 ? 'INFRACARE' : 'GAMAS', reportedDate: '2026-10-16');
            if ($i % 2 === 0) {
                $this->lop('INC-'.$i);
            }
        }
        $this->ticket('INC-OUTSIDE-DATE', reportedDate: '2026-11-01');
        foreach (['all' => 31, 'without_lop' => 16, 'with_lop' => 15] as $usage => $count) {
            $sheet = $this->downloadSheet($this->super, ['date_from' => '2026-10-16', 'date_to' => '2026-10-16', 'usage' => $usage, 'page' => 2]);
            $this->assertSame([31, 16, 15], array_column($sheet->rangeToArray('B12:B14', null, false, false), 0));
            $incidents = $this->exportedIncidents($sheet);
            $this->assertCount($count, $incidents);
            $this->assertNotContains('INC-OUTSIDE-DATE', $incidents);
            foreach ($incidents as $incident) {
                $used = (int) substr($incident, 4) % 2 === 0;
                $this->assertTrue($usage === 'all' || ($usage === 'with_lop' ? $used : ! $used));
            }
        }
    }

    public function test_excel_preserves_leading_zero_and_formula_text_with_numeric_dates_and_counts(): void
    {
        $this->ticket('0000123', reportedDate: '16/10/2026 23:59:59');
        $this->lop('0000123', attributes: ['ihld_id' => '0009876', 'nama_lop' => '=1+1']);
        $sheet = $this->downloadSheet($this->super, ['usage' => 'all']);
        $this->assertSame('0000123', $sheet->getCell('A17')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A17')->getDataType());
        $this->assertSame('0009876', $sheet->getCell('G17')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('G17')->getDataType());
        $this->assertSame('=1+1', $sheet->getCell('H17')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('H17')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('B17')->getDataType());
        $this->assertSame('2026-10-16', Date::excelToDateTimeObject($sheet->getCell('B17')->getValue())->format('Y-m-d'));
        $this->assertSame('dd/mm/yyyy', $sheet->getStyle('B17')->getNumberFormat()->getFormatCode());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('B12')->getDataType());
        $this->assertSame('B17', $sheet->getFreezePane());
        $this->assertSame('A16:J17', $sheet->getAutoFilter()->getRange());
        $this->assertTrue($sheet->getStyle('H17')->getAlignment()->getWrapText());
        $this->assertSame(-1.0, $sheet->getRowDimension(17)->getRowHeight());
    }

    public function test_download_retains_all_scope_levels_multi_sto_and_outside_or_archived_identity_is_hidden(): void
    {
        foreach (array_keys($this->locations['areas']) as $zone) {
            $this->ticket('INC-'.$zone, 'INFRACARE', $zone, '2026-10-16');
        }
        $hidden = $this->lop('INC-SBY-A', 'DPS-A', ['nama_lop' => 'RAHASIA LUAR SCOPE', 'ihld_id' => 'SECRET-IHLD']);
        $archived = $this->lop('INC-SBY-B');
        $archived->delete();
        $l = $this->locations;
        foreach ([
            [['admin_scope_type' => 'area', 'area_id' => $l['area']->getKey()], 4, []],
            [['admin_scope_type' => 'region', 'region_id' => $l['jatim']->getKey()], 3, []],
            [['admin_scope_type' => 'branch', 'branch_id' => $l['branches']['SBY']->getKey()], 2, []],
            [['branch_id' => $l['branches']['SBY']->getKey()], 2, []],
            [['admin_scope_type' => 'service_area', 'service_area_id' => $l['areas']['SBY-A']->getKey()], 1, []],
            [['admin_scope_type' => 'service_area'], 2, [$l['areas']['SBY-A']->getKey(), $l['areas']['DPS-A']->getKey()]],
        ] as [$attributes, $count, $pivot]) {
            $admin = User::factory()->role('ADMIN')->create($attributes);
            $admin->serviceAreas()->sync($pivot);
            $sheet = $this->downloadSheet($admin, ['usage' => 'all']);
            $this->assertCount($count, $this->exportedIncidents($sheet));
            $this->assertSame($count, $sheet->getCell('B12')->getValue());
            $this->assertNotContains('INC-OUT-A', $this->exportedIncidents($sheet));
        }
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $l['branches']['SBY']->getKey()]);
        $sheet = $this->downloadSheet($admin, ['usage' => 'with_lop']);
        $this->assertSame([2, 0, 2], array_column($sheet->rangeToArray('B12:B14', null, false, false), 0));
        foreach ($sheet->rangeToArray('G17:J18', null, false, false) as $row) {
            $this->assertSame([null, null, null, 'Detail LOP tidak tersedia'], $row);
        }
        $this->assertNotNull($hidden->fresh());
    }

    public function test_download_uses_all_combined_filters_and_rejects_invalid_parameters_before_query(): void
    {
        $this->ticket('INC0', 'INFRACARE', 'SBY-A', '2026-10-16');
        $this->ticket('INC1', 'INFRACARE', 'SBY-A', '2026-10-16');
        $this->ticket('INC0-OTHER', 'GAMAS', 'SBY-A', '2026-10-16');
        $this->ticket('INC0-REGION', 'INFRACARE', 'DPS-A', '2026-10-16');
        $l = $this->locations;
        $sheet = $this->downloadSheet($this->super, ['date_from' => '2026-10-16', 'date_to' => '2026-10-16', 'region' => $l['jatim']->name, 'branch' => $l['branches']['SBY']->getKey(), 'service_area' => $l['areas']['SBY-A']->getKey(), 'kind' => 'infracare', 'q' => '0']);
        $this->assertSame(['INC0'], $this->exportedIncidents($sheet));
        $this->assertSame('0', $sheet->getCell('B8')->getValue());
        $source = DB::connection('mysql_dompis');
        $source->enableQueryLog();
        foreach ([['kind' => 'bad'], ['date_from' => ['2026-10-16']], ['date_from' => '2026-02-30'], ['date_from' => '2026-10-16', 'date_to' => '2026-10-15'], ['usage' => 'bad']] as $filters) {
            $this->getJson(route('incident-monitoring.tickets.download', $filters))->assertUnprocessable();
        }
        $this->assertCount(0, $source->getQueryLog());
    }

    public function test_download_authorization_and_source_failure_do_not_produce_a_misleading_excel(): void
    {
        $this->get(route('incident-monitoring.tickets.download'))->assertRedirect(route('login'));
        $this->withoutMiddleware(EnsureRole::class);
        foreach (['TEKNISI', 'MANAGER', 'APPROVER'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('incident-monitoring.tickets.download'))->assertForbidden();
        }
        DB::connection('mysql_dompis')->getSchemaBuilder()->drop('ticket');
        $response = $this->actingAs($this->super)->getJson(route('incident-monitoring.tickets.download'))->assertStatus(503);
        $this->assertNull($response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->get(route('incident-monitoring.tickets'))->assertOk()->assertSee('Sumber tiket belum tersedia');
    }

    public function test_download_button_category_overrides_current_list_and_submits_current_dates(): void
    {
        $this->ticket('INC-FREE', reportedDate: '2026-10-16');
        $this->ticket('INC-USED', reportedDate: '2026-10-16');
        $this->ticket('INC-OTHER-MONTH', reportedDate: '2026-09-16');
        $this->lop('INC-USED');
        $sheet = $this->downloadSheet($this->super, ['usage' => 'without_lop', 'download_usage' => 'with_lop', 'date_from' => '2026-10-16', 'date_to' => '2026-10-16']);
        $this->assertSame(['INC-USED'], $this->exportedIncidents($sheet));
        $this->assertSame('Sudah Memiliki LOP', $sheet->getCell('B9')->getValue());
        $this->get(route('incident-monitoring.tickets'))->assertOk()->assertSee('form="ticket-filters"', false)->assertSee('data-ticket-download', false)->assertDontSee('type="month"', false);
        $this->getJson(route('incident-monitoring.tickets.download', ['download_usage' => 'bad']))->assertUnprocessable()->assertJsonValidationErrors('download_usage');
    }

    public function test_download_more_than_one_chunk_has_no_duplicates_and_respects_date_range_at_year_end(): void
    {
        $rows = [];
        for ($i = 0; $i < 505; $i++) {
            $rows[] = ['incident' => 'INC-'.$i, 'jenis_tiket_2' => 'GAMAS', 'workzone' => 'SBY-A', 'reported_date' => '2026-12-31 23:59:59'];
        }
        DB::connection('mysql_dompis')->table('ticket')->insert($rows);
        $this->ticket('INC-NEXT-YEAR', reportedDate: '2027-01-01');
        $sheet = $this->downloadSheet($this->super, ['date_from' => '2026-12-01', 'date_to' => '2026-12-31', 'page' => 3]);
        $incidents = $this->exportedIncidents($sheet);
        $this->assertCount(505, $incidents);
        $this->assertCount(505, array_unique($incidents));
        $this->assertSame('INC-504', $incidents[0]);
        $this->assertSame('INC-0', $incidents[504]);
        $this->assertSame(505, $sheet->getCell('B12')->getValue());
    }

    public function test_empty_scope_and_conflicting_filters_produce_true_zero_without_dummy_rows(): void
    {
        $this->ticket('INC1', reportedDate: '2026-10-16');
        $sheet = $this->downloadSheet($this->super, ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']);
        $this->assertSame([0, 0, 0], array_column($sheet->rangeToArray('B12:B14', null, false, false), 0));
        $this->assertSame(16, $sheet->getHighestRow());
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $this->locations['branches']['SBY']->getKey()]);
        $sheet = $this->downloadSheet($admin, ['branch' => $this->locations['branches']['DPS']->getKey()]);
        $this->assertCount(0, $this->exportedIncidents($sheet));
        DB::connection('mysql_dompis')->getSchemaBuilder()->drop('ticket');
        $sheet = $this->downloadSheet(User::factory()->role('ADMIN')->create());
        $this->assertSame(0, $sheet->getCell('B12')->getValue());
    }
}
