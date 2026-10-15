<?php

namespace Tests\Feature;

use App\Enums\LopStatus;
use App\Http\Middleware\EnsureRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\TicketLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

class IncidentMonitoringTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    private User $super;

    private array $locations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeIncidentTickets([]);
        $this->super = User::factory()->role('SUPER_ADMIN')->create();
        $area = Area::create(['code' => 'INC-A', 'name' => 'Area Monitoring']);
        $outsideArea = Area::create(['code' => 'INC-B', 'name' => 'Area Luar']);
        $jatim = Region::create(['code' => 'INC-J', 'name' => 'JATIM', 'area_id' => $area->getKey()]);
        $balnus = Region::create(['code' => 'INC-N', 'name' => 'BALNUS', 'area_id' => $area->getKey()]);
        $outside = Region::create(['code' => 'INC-O', 'name' => 'LUAR', 'area_id' => $outsideArea->getKey()]);
        $sby = Branch::create(['code' => 'INC-SBY', 'name' => 'Surabaya', 'region_id' => $jatim->getKey(), 'region' => 'Legacy berbeda']);
        $sda = Branch::create(['code' => 'INC-SDA', 'name' => 'Sidoarjo', 'region_id' => $jatim->getKey(), 'region' => $jatim->name]);
        $dps = Branch::create(['code' => 'INC-DPS', 'name' => 'Denpasar', 'region_id' => $balnus->getKey(), 'region' => $balnus->name]);
        $foreign = Branch::create(['code' => 'INC-OUT', 'name' => 'Branch Luar', 'region_id' => $outside->getKey(), 'region' => $outside->name]);
        $sa = [];
        foreach (['SBY-A' => $sby, 'SBY-B' => $sby, 'SDA-A' => $sda, 'DPS-A' => $dps, 'OUT-A' => $foreign] as $code => $branch) {
            $sa[$code] = ServiceArea::create(['workzone' => $code, 'name' => $code, 'branch_id' => $branch->getKey(), 'region_id' => $branch->region_id]);
        }
        $this->locations = compact('area', 'jatim', 'balnus', 'sby', 'sda', 'dps', 'sa');
    }

    private function lop(string $incident, array $attributes = [], string $sto = 'SBY-A', bool $legacy = false): QeLop
    {
        $sa = $this->locations['sa'][$sto];

        return QeLop::create(array_replace([
            'incident' => $incident, 'nama_lop' => 'Pekerjaan '.$incident, 'program_type' => 'recovery',
            'status_lop' => 'draft', 'created_by' => $this->super->getKey(),
            'branch_id' => $legacy ? null : $sa->branch_id, 'branch' => $sa->branch->name,
            'service_area_id' => $legacy ? null : $sa->getKey(), 'sto' => $sa->workzone,
        ], $attributes));
    }

    private function ticket(string $incident, ?string $type): void
    {
        DB::connection('mysql_dompis')->table('ticket')->insert(['incident' => $incident, 'jenis_tiket_2' => $type]);
    }

    public function test_access_requires_admin_role_in_both_middleware_and_backend_gate(): void
    {
        $this->get(route('incident-monitoring.index'))->assertRedirect(route('login'));
        foreach (['TEKNISI', 'MANAGER', 'APPROVER'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('incident-monitoring.index'))->assertForbidden();
        }
        $this->withoutMiddleware(EnsureRole::class)->get(route('incident-monitoring.index'))->assertForbidden();
        $this->actingAs($this->super)->get(route('incident-monitoring.index'))->assertOk()->assertSee('Monitoring Incident');
        $admin = User::factory()->role('ADMIN')->create();
        $this->actingAs($admin)->get(route('incident-monitoring.index'))->assertOk()
            ->assertViewHas('scopeWarning', true)->assertViewHas('stats', fn ($stats) => $stats['total'] === 0);
    }

    public function test_totals_chart_and_rows_distinguish_manual_unknown_empty_and_missing_tickets(): void
    {
        foreach (['INC1' => ' gamas ', 'INC2' => 'GAMAS', 'INC3' => 'unknown', 'INC4' => 'INFRACARE', 'INC5' => 'NON   NUMBERING', 'INC6' => null] as $incident => $type) {
            $this->ticket($incident, $type);
            $this->lop($incident, ['status_lop' => $incident === 'INC1' ? 'completed' : ($incident === 'INC2' ? 'waiting_approval' : 'draft'), 'ihld_id' => $incident === 'INC1' ? '0010513828' : null]);
        }
        $this->lop('INC-NOT-FOUND');
        $this->lop('INP31020001');
        $this->lop('');
        $this->lop('INC-ARCHIVED')->delete();
        $this->ticket('INC-UNUSED', 'UNUSED TYPE');
        $this->actingAs($this->super)->get(route('incident-monitoring.index'))->assertOk()
            ->assertViewHas('stats', ['total' => 9, 'completed' => 1, 'waiting_approval' => 1, 'missing_ihld' => 8])
            ->assertViewHas('chartRows', fn ($rows) => $rows->sum('count') === 9 && $rows->first()['label'] === 'GAMAS' && $rows->first()['count'] === 2
                && $rows->firstWhere('key', 'type:UNKNOWN')['count'] === 1
                && $rows->firstWhere('key', 'manual')['count'] === 1
                && $rows->firstWhere('key', 'empty_type')['count'] === 1
                && $rows->firstWhere('key', 'not_found')['count'] === 1
                && $rows->firstWhere('key', 'no_incident')['count'] === 1)
            ->assertViewHas('lops', fn ($rows) => $rows->total() === 9)
            ->assertSee('0010513828')->assertSee('Tiket Manual')->assertSee('NON NUMBERING')->assertSee('Belum diisi')
            ->assertDontSee('INC-ARCHIVED')->assertDontSee('UNUSED TYPE');
    }

    public function test_every_admin_scope_including_multi_sto_and_legacy_only_reads_visible_lops(): void
    {
        $l = $this->locations;
        $this->lop('INP1');
        $this->lop('INP2', sto: 'SBY-B', legacy: true);
        $this->lop('INP3', sto: 'SDA-A', legacy: true);
        $this->lop('INP4', sto: 'DPS-A', legacy: true);
        $this->lop('INP5', sto: 'OUT-A');
        $cases = [
            [['admin_scope_type' => 'area', 'area_id' => $l['area']->getKey()], 4, []],
            [['admin_scope_type' => 'region', 'region_id' => $l['jatim']->getKey()], 3, []],
            [['admin_scope_type' => 'branch', 'branch_id' => $l['sby']->getKey()], 2, []],
            [['branch_id' => $l['sby']->getKey()], 2, []],
            [['admin_scope_type' => 'service_area', 'service_area_id' => $l['sa']['SBY-A']->getKey()], 1, []],
            [['admin_scope_type' => 'service_area'], 2, [$l['sa']['SBY-A']->getKey(), $l['sa']['DPS-A']->getKey()]],
            [['admin_scope_type' => 'region'], 0, []],
        ];
        foreach ($cases as [$attributes, $total, $pivot]) {
            $admin = User::factory()->role('ADMIN')->create($attributes);
            $admin->serviceAreas()->sync($pivot);
            $this->actingAs($admin)->get(route('incident-monitoring.index'))->assertOk()
                ->assertViewHas('stats', fn ($stats) => $stats['total'] === $total)
                ->assertViewHas('lops', fn ($rows) => $rows->total() === $total)
                ->assertViewHas('chartRows', fn ($rows) => $rows->sum('count') === $total)
                ->assertDontSee('INP5')->assertDontSee('Branch Luar');
        }
        $this->actingAs($this->super)->get(route('incident-monitoring.index'))->assertViewHas('stats', fn ($stats) => $stats['total'] === 5);
    }

    public function test_out_of_scope_or_conflicting_location_filters_return_no_data_and_never_broaden_access(): void
    {
        $l = $this->locations;
        $this->lop('INP1');
        $this->lop('INP2', sto: 'DPS-A');
        $admin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'branch', 'branch_id' => $l['sby']->getKey()]);
        foreach ([['branch' => $l['dps']->getKey()], ['service_area' => $l['sa']['DPS-A']->getKey()], ['region' => 'BALNUS'], ['branch' => 999999]] as $filters) {
            $this->actingAs($admin)->get(route('incident-monitoring.index', $filters))->assertOk()
                ->assertViewHas('stats', fn ($stats) => $stats['total'] === 0)->assertDontSee('INP1')->assertDontSee('INP2');
        }
        $this->actingAs($this->super)->get(route('incident-monitoring.index', ['region' => 'JATIM', 'branch' => $l['dps']->getKey()]))
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 0);
    }

    public function test_combined_filters_and_drill_down_keep_summary_chart_and_table_consistent(): void
    {
        $target = $this->lop('INC-TARGET', ['program_type' => 'preventive', 'status_lop' => 'completed', 'ihld_id' => '0010523'], legacy: true);
        $this->ticket($target->incident, 'INFRACARE');
        $this->lop('INP-OTHER', ['program_type' => 'preventive', 'status_lop' => 'completed']);
        $filters = ['q' => '0010523', 'program' => 'preventive', 'status' => 'completed', 'region' => 'JATIM', 'branch' => $this->locations['sby']->getKey(), 'service_area' => $this->locations['sa']['SBY-A']->getKey(), 'ticket_type' => 'type:INFRACARE'];
        $this->actingAs($this->super)->get(route('incident-monitoring.index', $filters))->assertOk()
            ->assertViewHas('stats', ['total' => 1, 'completed' => 1, 'waiting_approval' => 0, 'missing_ihld' => 0])
            ->assertViewHas('chartRows', fn ($rows) => $rows->sum('count') === 1 && $rows->first()['key'] === 'type:INFRACARE')
            ->assertViewHas('lops', fn ($rows) => $rows->total() === 1 && $rows->first()->getKey() === $target->getKey())
            ->assertSee(route('lop.show', $target), false)->assertDontSee('INP-OTHER');
        foreach (['INC-TARGET', 'Pekerjaan INC-TARGET'] as $search) {
            $this->get(route('incident-monitoring.index', ['q' => $search]))->assertViewHas('stats', fn ($stats) => $stats['total'] === 1);
        }
        $this->get(route('incident-monitoring.index', ['ticket_type' => 'manual', 'missing_ihld' => 1]))
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1 && $stats['missing_ihld'] === 1)
            ->assertViewHas('chartRows', fn ($rows) => $rows->count() === 1 && $rows->first()['key'] === 'manual');
        $this->get(route('incident-monitoring.index', ['ticket_type' => 'does-not-exist']))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 0)->assertViewHas('chartRows', fn ($rows) => $rows->isEmpty());
    }

    public function test_pagination_preserves_filters_and_global_totals_with_stable_latest_order(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->lop('INP'.$i, ['ihld_id' => '0000123']);
        }
        $this->actingAs($this->super)->get(route('incident-monitoring.index', ['ticket_type' => 'manual', 'q' => '0', 'page' => 2]))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 30)
            ->assertViewHas('chartRows', fn ($rows) => $rows->sum('count') === 30)
            ->assertViewHas('lops', fn ($rows) => $rows->count() === 5 && $rows->first()->incident === 'INP5'
                && str_contains($rows->url(1), 'ticket_type=manual') && str_contains($rows->url(1), 'q=0'));
        $this->get(route('incident-monitoring.index', ['page' => 99]))->assertOk()->assertSee('Kembali ke halaman pertama');
    }

    public function test_cache_expires_after_ten_minutes_and_status_remains_current(): void
    {
        $this->travelTo(now()->startOfMinute());
        $lop = $this->lop('INC-CACHE');
        $this->ticket($lop->incident, 'GAMAS');
        $source = DB::connection('mysql_dompis');
        $source->enableQueryLog();
        $this->actingAs($this->super)->get(route('incident-monitoring.index'))->assertSee('GAMAS');
        $this->assertCount(1, $source->getQueryLog());
        $source->table('ticket')->where('incident', $lop->incident)->update(['jenis_tiket_2' => 'UNKNOWN']);
        $lop->update(['status_lop' => 'completed']);
        $source->flushQueryLog();
        $this->travel(9)->minutes();
        $this->get(route('incident-monitoring.index'))->assertViewHas('stats', fn ($stats) => $stats['completed'] === 1)
            ->assertViewHas('chartRows', fn ($rows) => $rows->first()['label'] === 'GAMAS');
        $this->assertCount(0, $source->getQueryLog());
        $this->travel(2)->minutes();
        $this->get(route('incident-monitoring.index'))->assertViewHas('chartRows', fn ($rows) => $rows->first()['label'] === 'UNKNOWN');
        $this->assertCount(1, $source->getQueryLog());
    }

    public function test_source_failure_does_not_become_unknown_or_hide_lops_and_retry_recovers(): void
    {
        $this->lop('INC-DOWN', ['status_lop' => 'progress']);
        $this->lop('INP1');
        $this->mock(TicketLookupService::class, function ($mock): void {
            $mock->shouldReceive('lookupTypes')->with(['INC-DOWN'])->once()->andReturn(['INC-DOWN' => ['found' => false, 'error' => true]]);
            $mock->shouldReceive('lookupTypes')->with(['INC-DOWN'])->once()->andReturn(['INC-DOWN' => ['found' => true, 'jenis_tiket_2' => 'UNKNOWN']]);
        });
        $this->actingAs($this->super)->get(route('incident-monitoring.index'))->assertOk()->assertSee('Coba kembali')->assertSee('Progress')
            ->assertViewHas('sourceUnavailable', true)->assertViewHas('stats', fn ($stats) => $stats['total'] === 2)
            ->assertViewHas('chartRows', fn ($rows) => $rows->firstWhere('key', 'unavailable')['count'] === 1 && ! $rows->contains('key', 'type:UNKNOWN'));
        $this->get(route('incident-monitoring.index', ['retry' => 1]))->assertOk()->assertViewHas('sourceUnavailable', false)
            ->assertViewHas('chartRows', fn ($rows) => $rows->firstWhere('key', 'type:UNKNOWN')['count'] === 1);
    }

    public function test_missing_ticket_is_not_cached_and_can_be_found_on_next_request(): void
    {
        $this->lop('INC-LATER');
        $this->actingAs($this->super)->get(route('incident-monitoring.index'))->assertSee('Tiket tidak ditemukan');
        $this->ticket('INC-LATER', 'REG');
        $this->get(route('incident-monitoring.index'))->assertViewHas('chartRows', fn ($rows) => $rows->first()['label'] === 'REG');
    }

    public function test_external_lookup_is_batched_only_after_scope_and_never_runs_for_manual_tickets(): void
    {
        $this->lop('INC-LOCAL');
        $this->lop('INC-FOREIGN', sto: 'DPS-A');
        $this->lop('INP1');
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $this->locations['sby']->getKey()]);
        $this->mock(TicketLookupService::class, fn ($mock) => $mock->shouldReceive('lookupTypes')->once()->with(['INC-LOCAL'])
            ->andReturn(['INC-LOCAL' => ['found' => true, 'jenis_tiket_2' => 'GAMAS']]));
        $this->actingAs($admin)->get(route('incident-monitoring.index'))->assertViewHas('stats', fn ($stats) => $stats['total'] === 2);
        $this->get(route('incident-monitoring.index', ['q' => 'INP']))->assertOk()->assertViewHas('checkedAt', null);
    }

    public function test_more_than_one_batch_preserves_all_counts_and_type_filter_rows(): void
    {
        $this->lop('INC-BATCH-1');
        $template = QeLop::first()->getAttributes();
        unset($template['id_qe_lops']);
        $rows = [];
        $tickets = [];
        for ($i = 2; $i <= 505; $i++) {
            $rows[] = [...$template, 'incident' => 'INC-BATCH-'.$i, 'nama_lop' => 'Batch '.$i];
        }
        for ($i = 1; $i <= 505; $i++) {
            $tickets[] = ['incident' => 'INC-BATCH-'.$i, 'jenis_tiket_2' => $i <= 500 ? 'GAMAS' : 'REG'];
        }
        QeLop::insert($rows);
        DB::connection('mysql_dompis')->table('ticket')->insert($tickets);
        $source = DB::connection('mysql_dompis');
        $source->enableQueryLog();
        $this->actingAs($this->super)->get(route('incident-monitoring.index', ['ticket_type' => 'type:REG']))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 5)
            ->assertViewHas('chartRows', fn ($rows) => $rows->sum('count') === 5)
            ->assertViewHas('lops', fn ($rows) => $rows->total() === 5 && $rows->first()->incident === 'INC-BATCH-505');
        $this->assertCount(2, $source->getQueryLog());
    }

    public function test_all_status_labels_and_escaped_references_are_present_in_desktop_and_mobile_markup(): void
    {
        foreach (LopStatus::cases() as $status) {
            $this->lop('INP-'.$status->value, ['status_lop' => $status->value]);
        }
        $lop = $this->lop('INP-XSS', ['nama_lop' => '<script>alert(1)</script>', 'ihld_id' => '0000123']);
        $response = $this->actingAs($this->super)->get(route('incident-monitoring.index'))->assertOk()
            ->assertSee('0000123')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Salin ID IHLD 0000123')->assertSee('lg:hidden', false)->assertSee('hidden overflow-x-auto lg:block', false)
            ->assertSee('aria-controls="incident-filters"', false)->assertSee('aria-label="Detail LOP '.$lop->incident.'"', false);
        foreach (LopStatus::cases() as $status) {
            $response->assertSee($status->label());
        }
    }

    public function test_malformed_filters_are_rejected(): void
    {
        $this->actingAs($this->super)->getJson(route('incident-monitoring.index', ['program' => 'fake', 'status' => 'fake', 'branch' => 'abc', 'service_area' => -1, 'page' => 0, 'q' => ['fake'], 'ticket_type' => ['fake'], 'retry' => 2]))
            ->assertUnprocessable()->assertJsonValidationErrors(['program', 'status', 'branch', 'service_area', 'page', 'q', 'ticket_type', 'retry']);
    }
}
