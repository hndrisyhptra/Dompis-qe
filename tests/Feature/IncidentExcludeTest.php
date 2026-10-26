<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

class IncidentExcludeTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    private User $super;

    private User $admin;

    private User $technician;

    private array $locations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeIncidentTickets([]);
        $this->super = User::factory()->role('SUPER_ADMIN')->create();
        $area = Area::create(['code' => 'IE-A', 'name' => 'Area Exclude']);
        $jatim = Region::create(['code' => 'IE-J', 'name' => 'JATIM', 'area_id' => $area->getKey()]);
        $branch = Branch::create(['code' => 'IE-SBY', 'name' => 'Branch SBY', 'region_id' => $jatim->getKey(), 'region' => 'JATIM']);
        $sa = ServiceArea::create(['workzone' => 'IE-A', 'name' => 'SA Exclude', 'branch_id' => $branch->getKey(), 'region_id' => $jatim->getKey()]);
        $this->locations = compact('area', 'jatim', 'branch', 'sa');
        $this->admin = User::factory()->role('ADMIN')->create(['branch_id' => $branch->getKey()]);
        $this->technician = User::factory()->role('TEKNISI')->create();
    }

    private function ticket(string $incident, ?string $type = 'GAMAS GPON', ?string $workzone = 'IE-A'): void
    {
        DB::connection('mysql_dompis')->table('ticket')->insert([
            'incident' => $incident, 'jenis_tiket_2' => $type, 'workzone' => $workzone,
            'reported_date' => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function test_exclude_is_available_to_admin_and_super_admin_but_not_technician(): void
    {
        $this->ticket('INC-EXC-1');
        $payload = ['incident' => 'INC-EXC-1', 'reason' => 'non_material'];
        $this->actingAs($this->technician)->post(route('incident-monitoring.tickets.exclude'), $payload)->assertForbidden();
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), $payload)->assertRedirect();
        $this->assertDatabaseHas('incident_excludes', ['incident' => 'INC-EXC-1', 'keterangan_exclude' => 'non_material']);
    }

    public function test_exclude_stores_incident_with_reason_and_removes_from_without_lop(): void
    {
        $this->ticket('INC-A');
        $this->ticket('INC-B');
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), [
            'incident' => 'INC-A', 'reason' => 'temporer',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('incident_excludes', ['incident' => 'INC-A', 'keterangan_exclude' => 'temporer']);
        // INC-A no longer in without_lop, but still in total.
        $this->get(route('incident-monitoring.tickets', ['usage' => 'without_lop']))
            ->assertOk()->assertSee('INC-B')->assertDontSee('INC-A')
            ->assertViewHas('stats', fn ($stats) => $stats['without_lop'] === 1 && $stats['excluded'] === 1 && $stats['total'] === 2);
    }

    public function test_excluded_tab_shows_tickets_with_reason_and_all_tab_shows_badge(): void
    {
        $this->ticket('INC-X');
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), [
            'incident' => 'INC-X', 'reason' => 'duplikat_open',
        ])->assertRedirect();
        $this->get(route('incident-monitoring.tickets', ['usage' => 'excluded']))
            ->assertOk()->assertSee('INC-X')->assertSee('Duplikat Tiket Open')
            ->assertViewHas('stats', fn ($stats) => $stats['excluded'] === 1)
            ->assertViewHas('tickets', fn ($rows) => $rows->total() === 1 && $rows->first()['exclude_reason'] === 'duplikat_open');
        // All tab: excluded badge visible, exclude action button hidden.
        $this->get(route('incident-monitoring.tickets', ['usage' => 'all']))
            ->assertOk()->assertSee('INC-X')->assertSee('Tiket Exclude');
    }

    public function test_reexclude_updates_reason_without_duplicate(): void
    {
        $this->ticket('INC-R');
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), [
            'incident' => 'INC-R', 'reason' => 'non_material',
        ])->assertRedirect();
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), [
            'incident' => 'INC-R', 'reason' => 'temporer',
        ])->assertRedirect();
        $this->assertDatabaseCount('incident_excludes', 1);
        $this->assertDatabaseHas('incident_excludes', ['incident' => 'INC-R', 'keterangan_exclude' => 'temporer']);
    }

    public function test_unexclude_returns_ticket_to_without_lop(): void
    {
        $this->ticket('INC-U');
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), [
            'incident' => 'INC-U', 'reason' => 'non_material',
        ])->assertRedirect();
        $this->assertDatabaseCount('incident_excludes', 1);
        $this->actingAs($this->super)->delete(route('incident-monitoring.tickets.unexclude', 'INC-U'))->assertRedirect();
        $this->assertDatabaseCount('incident_excludes', 0);
        $this->get(route('incident-monitoring.tickets', ['usage' => 'without_lop']))
            ->assertOk()->assertSee('INC-U')
            ->assertViewHas('stats', fn ($stats) => $stats['without_lop'] === 1 && $stats['excluded'] === 0);
    }

    public function test_exclude_incident_is_normalized_uppercase(): void
    {
        $this->ticket('INC-NORM');
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), [
            'incident' => '  inc-norm  ', 'reason' => 'temporer',
        ])->assertRedirect();
        $this->assertDatabaseHas('incident_excludes', ['incident' => 'INC-NORM']);
    }

    public function test_exclude_rejects_invalid_reason(): void
    {
        $this->ticket('INC-INV');
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), [
            'incident' => 'INC-INV', 'reason' => 'bukan_alasan',
        ])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('incident_excludes', 0);
    }

    public function test_exclude_stats_and_tab_visible_in_view(): void
    {
        $this->ticket('INC-V1');
        $this->ticket('INC-V2');
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), [
            'incident' => 'INC-V1', 'reason' => 'non_material',
        ])->assertRedirect();
        // Verify tab & stats card exist.
        $this->get(route('incident-monitoring.tickets'))
            ->assertOk()->assertSee('Tiket Exclude')
            ->assertViewHas('excludeReasons', fn ($reasons) => $reasons->count() === 4
                && $reasons->get('non_material') === 'Non Material'
                && $reasons->get('temporer') === 'Temporer'
                && $reasons->get('duplikat_open') === 'Duplikat Tiket Open'
                && $reasons->get('magu_ganti_pasif_odp') === 'MAGU Ganti Pasif ODP');
    }

    public function test_magu_ganti_pasif_odp_reason_can_be_used_and_is_displayed(): void
    {
        $this->ticket('INC-MAGU');
        $this->actingAs($this->super)->post(route('incident-monitoring.tickets.exclude'), [
            'incident' => 'INC-MAGU', 'reason' => 'magu_ganti_pasif_odp',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('incident_excludes', [
            'incident' => 'INC-MAGU',
            'keterangan_exclude' => 'magu_ganti_pasif_odp',
        ]);

        $this->get(route('incident-monitoring.tickets', ['usage' => 'excluded']))
            ->assertOk()
            ->assertSee('INC-MAGU')
            ->assertSee('MAGU Ganti Pasif ODP')
            ->assertViewHas('excludeReasons', fn ($reasons) => $reasons->get('magu_ganti_pasif_odp') === 'MAGU Ganti Pasif ODP');
    }
}
