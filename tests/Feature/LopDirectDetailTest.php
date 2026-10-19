<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

class LopDirectDetailTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    private User $super;

    private QeLop $target;

    private QeLop $outside;

    private array $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->role('SUPER_ADMIN')->create();
        foreach (['DIRECT-IN', 'DIRECT-OUT'] as $code) {
            $area = Area::create(['code' => $code, 'name' => $code]);
            $region = Region::create(['code' => $code, 'name' => $code, 'area_id' => $area->getKey()]);
            $branch = Branch::create(['code' => $code, 'name' => $code, 'region' => $region->name, 'region_id' => $region->getKey()]);
            $sa = ServiceArea::create(['workzone' => $code, 'name' => $code, 'branch_id' => $branch->getKey(), 'region_id' => $region->getKey()]);
            $lop = QeLop::create([
                'incident' => 'INC-'.$code, 'nama_lop' => 'LOP '.$code,
                'program_type' => 'preventive', 'status_lop' => 'completed', 'created_by' => $this->super->getKey(),
                'branch_id' => $branch->getKey(), 'service_area_id' => $sa->getKey(), 'branch' => $branch->name, 'sto' => $sa->workzone,
            ]);
            if ($code === 'DIRECT-IN') {
                $this->target = $lop;
                $this->location = compact('area', 'region', 'branch', 'sa');
            } else {
                $this->outside = $lop;
            }
        }
    }

    private function admin(string $scope): User
    {
        $user = User::factory()->role('ADMIN')->create([
            'admin_scope_type' => $scope, 'area_id' => $this->location['area']->getKey(),
            'region_id' => $this->location['region']->getKey(), 'branch_id' => $this->location['branch']->getKey(),
            'service_area_id' => $this->location['sa']->getKey(),
        ]);
        if ($scope === 'service_area') {
            $user->serviceAreas()->sync([$this->location['sa']->getKey()]);
        }

        return $user;
    }

    public function test_direct_detail_renders_exact_lop_for_all_admin_scopes_and_superadmin_without_a_redirect(): void
    {
        foreach (['area', 'region', 'branch', 'service_area'] as $scope) {
            $admin = $this->admin($scope);
            $this->actingAs($admin)->get(route('lop.detail', $this->target))->assertOk()->assertViewIs('lop.detail')
                ->assertViewHas('lop', fn ($lop) => $lop->is($this->target))
                ->assertSee('LOP DIRECT-IN')->assertDontSee('LOP DIRECT-OUT')
                ->assertSee('Material & Laporan', false)->assertSee('Tiket & Jaringan', false)
                ->assertDontSee('<dialog id="lop-record-detail"', false);
            $this->get(route('lop.detail', $this->outside))->assertForbidden();
        }
        $this->actingAs($this->super)->get(route('lop.detail', $this->outside))->assertOk()->assertSee('LOP DIRECT-OUT');
        $this->assertDatabaseCount('qe_lop_histories', 0);
        $this->assertDatabaseCount('qe_lops', 2);
    }

    public function test_direct_detail_cannot_be_used_for_archived_lops_or_unassigned_technicians(): void
    {
        $this->get(route('lop.detail', $this->target))->assertRedirect(route('login'));
        $tech = User::factory()->role('TEKNISI')->create();
        $this->actingAs($tech)->get(route('lop.detail', $this->target))->assertForbidden();
        $this->target->assignments()->create(['technician_id' => $tech->getKey(), 'assigned_by' => $this->super->getKey(), 'assigned_at' => now(), 'status' => 'active']);
        $this->get(route('lop.detail', $this->target))->assertRedirect(route('technician.projects.show', $this->target));
        $this->target->delete();
        $this->actingAs($this->super)->get(route('lop.detail', $this->target))->assertNotFound();
    }

    public function test_back_link_preserves_dashboard_and_ticket_filters_but_rejects_external_destinations(): void
    {
        $this->actingAs($this->admin('branch'));
        foreach ([route('dashboard', ['tab' => 'program', 'program' => 'preventive']), route('incident-monitoring.tickets', ['usage' => 'with_lop', 'date_from' => '2026-10-01', 'q' => 'INC'])] as $origin) {
            $this->from($origin)->get(route('lop.detail', $this->target))->assertOk()->assertViewHas('returnUrl', $origin);
        }
        $this->withHeader('referer', 'https://example.org/untrusted')->get(route('lop.detail', [$this->target, 'return_url' => 'https://example.org/untrusted']))
            ->assertOk()->assertViewHas('returnUrl', route('data-lops.index'));
    }

    public function test_ticket_detail_buttons_on_mobile_and_desktop_point_directly_to_the_selected_lop(): void
    {
        $this->fakeIncidentTickets(['INC-DIRECT-IN' => 'GAMAS']);
        DB::connection('mysql_dompis')->table('ticket')->where('incident', 'INC-DIRECT-IN')->update(['workzone' => $this->location['sa']->workzone]);
        $this->actingAs($this->admin('branch'));
        $page = $this->get(route('incident-monitoring.tickets', ['usage' => 'with_lop']))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($page->getContent());
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a[@aria-label="Detail LOP INC-DIRECT-IN"]');
        $this->assertSame(2, $links->length);
        foreach ($links as $link) {
            $this->assertSame(route('lop.detail', $this->target, false), $link->getAttribute('href'));
            $this->from(route('incident-monitoring.tickets', ['usage' => 'with_lop']))->get($link->getAttribute('href'))
                ->assertOk()->assertViewIs('lop.detail')->assertSee('LOP DIRECT-IN');
        }
    }
}
