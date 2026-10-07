<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeBoq;
use App\Models\QeBoqHistory;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\QeLopHistory;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_activity_uses_wib_boundaries_and_distinct_lops_and_actors(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $actor = User::factory()->role(UserRole::ADMIN->value)->create();
        $sda = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $sby = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'EMPTY', 'name' => 'EMPTY', 'region' => 'REGION JATIM']);
        $lop = $this->lop($actor, $sda, 'DAILY-SDA', '2026-10-01 01:00:00');
        $idle = $this->lop($actor, $sda, 'DAILY-IDLE', '2026-10-02 01:00:00');
        $other = $this->lop($actor, $sby, 'DAILY-SBY', '2026-10-01 01:00:00');
        $this->lop($actor, $sda, 'FUTURE', '2026-10-07 17:00:00');

        // 07 Oct WIB = 06 Oct 17:00 UTC through 07 Oct 16:59:59 UTC.
        $this->history($lop, $actor, '2026-10-06 16:59:59');
        $this->history($lop, $actor, '2026-10-06 17:00:00');
        $this->history($lop, $actor, '2026-10-07 16:59:59');
        $this->history($lop, $super, '2026-10-07 17:00:00');
        $this->history($other, $actor, '2026-10-07 01:00:00');
        $this->history($idle, $actor, '2026-10-02 09:10:00');

        $response = $this->actingAs($super)->get(route('dashboard', ['tab' => 'summary', 'date' => '2026-10-07']));
        $response->assertOk()->assertViewHas('dashboardTab', 'summary')
            ->assertSee('Summary per Branch')->assertSee('Rabu, 07 Oktober 2026')
            ->assertDontSee('Kesiapan Data &amp; Penugasan', false)->assertDontSee('Prioritas Operasional');
        $monitoring = $response->viewData('monitoring');
        $this->assertSame([
            'branches' => 3, 'moving_branches' => 2, 'idle_branches' => 1,
            'moving_lops' => 2, 'activities' => 3, 'actors' => 1,
        ], $monitoring['stats']);
        $this->assertSame('EMPTY', $monitoring['rows']->first()['name']);
        $sdaRow = $monitoring['rows']->firstWhere('name', 'SIDOARJO');
        $this->assertSame(2, $sdaRow['total_lops']);
        $this->assertSame(1, $sdaRow['moving_lops']);
        $this->assertSame(1, $sdaRow['idle_lops']);
        $this->assertSame('07 Oct 2026 23:59', $sdaRow['last_update']);

        $this->getJson(route('dashboard.monitoring-lops', ['date' => '2026-10-07', 'branch' => 'SIDOARJO', 'movement' => 'moving']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.incident', 'DAILY-SDA')
            ->assertJsonPath('data.0.activities', 2)->assertJsonPath('data.0.actors', 1)
            ->assertJsonPath('data.0.last_update', '07 Oct 2026 23:59');
        $this->getJson(route('dashboard.monitoring-lops', ['date' => '2026-10-07', 'branch' => 'SIDOARJO', 'movement' => 'idle', 'q' => 'IDLE']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.incident', 'DAILY-IDLE');
    }

    public function test_creation_upload_review_and_boq_events_are_counted_without_duplicate_creation(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $actor = User::factory()->role(UserRole::ADMIN->value)->create();
        $branch = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $lop = $this->lop($actor, $branch, 'SOURCES', '2026-10-07 01:00:00');
        $this->history($lop, $actor, '2026-10-07 01:00:00', 'created');
        $this->lop($actor, $branch, 'BULK-FALLBACK', '2026-10-07 02:00:00');
        $evidence = QeEvidence::create([
            'qe_lop_id' => $lop->getKey(), 'uploaded_by' => $actor->getKey(),
            'step' => 'PROGRESS', 'type' => 'PHOTO', 'category' => 'progress',
            'file_path' => 'test.jpg', 'status' => 'approved', 'reviewed_by' => $super->getKey(),
            'reviewed_at' => '2026-10-07 05:00:00',
        ]);
        $evidence->forceFill(['created_at' => '2026-10-07 03:00:00'])->save();
        $boq = QeBoq::create(['qe_lop_id' => $lop->getKey(), 'created_by' => $actor->getKey()]);
        $boqHistory = QeBoqHistory::create(['qe_boq_id' => $boq->getKey(), 'user_id' => $actor->getKey(), 'event_type' => 'created']);
        $boqHistory->forceFill(['created_at' => '2026-10-07 04:00:00'])->save();

        $this->actingAs($super)->get(route('dashboard', ['date' => '2026-10-07']))
            ->assertOk()->assertViewHas('monitoring', fn (array $data) => $data['stats']['activities'] === 5
                && $data['stats']['actors'] === 2 && $data['stats']['moving_lops'] === 2);
        $this->getJson(route('dashboard.monitoring-activities', ['lopId' => $lop->getKey(), 'date' => '2026-10-07']))
            ->assertOk()->assertJsonPath('total', 4)
            ->assertJsonPath('data.0.label', 'Approve Progress')
            ->assertJsonPath('data.0.actor_role', 'Super Admin')
            ->assertJsonPath('data.1.label', 'Input / Import BOQ')
            ->assertJsonPath('data.2.label', 'Upload Progress')
            ->assertJsonPath('data.3.label', 'LOP dibuat');
    }

    public function test_monitoring_enforces_area_region_branch_and_multiple_service_area_scopes(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $area = Area::create(['code' => '3', 'name' => 'Area 3']);
        $outsideArea = Area::create(['code' => '4', 'name' => 'Area 4']);
        $jatim = Region::where('code', 'JATIM')->firstOrFail();
        $balnus = Region::where('code', 'BALNUS')->firstOrFail();
        $jatim->update(['area_id' => $area->getKey()]);
        $balnus->update(['area_id' => $outsideArea->getKey()]);
        $sda = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => $jatim->name, 'region_id' => $jatim->getKey()]);
        $sby = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => $jatim->name, 'region_id' => $jatim->getKey()]);
        $dps = Branch::create(['code' => 'DPS', 'name' => 'DENPASAR', 'region' => $balnus->name, 'region_id' => $balnus->getKey()]);
        $areas = collect([$sda, $sda, $sby, $dps])->map(fn ($branch, $i) => ServiceArea::create([
            'workzone' => "SA-{$i}", 'name' => "Service Area {$i}", 'branch_id' => $branch->getKey(), 'region_id' => $branch->region_id,
        ]));
        $creator = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        foreach ($areas as $i => $sa) {
            $lop = $this->lop($creator, $sa->branch, "SCOPED-{$i}", '2026-10-01 00:00:00');
            $lop->update(['service_area_id' => $sa->getKey()]);
            $this->history($lop, $creator, '2026-10-07 01:00:00');
        }
        $configs = [
            [['admin_scope_type' => 'area', 'area_id' => $area->getKey()], 3, 2],
            [['admin_scope_type' => 'region', 'region_id' => $jatim->getKey()], 3, 2],
            [['admin_scope_type' => 'branch', 'branch_id' => $sda->getKey()], 2, 1],
            [['admin_scope_type' => 'service_area', 'service_area_id' => $areas[0]->getKey()], 2, 2],
        ];
        foreach ($configs as [$config, $expectedLops, $expectedBranches]) {
            $admin = User::factory()->role(UserRole::ADMIN->value)->create($config);
            if ($config['admin_scope_type'] === 'service_area') {
                $admin->serviceAreas()->sync([$areas[0]->getKey(), $areas[3]->getKey()]);
            }
            $this->actingAs($admin)->get(route('dashboard', ['date' => '2026-10-07', 'branch' => 'DENPASAR']))
                ->assertOk()->assertViewHas('monitoring', fn (array $data) => $data['stats']['moving_lops'] === $expectedLops
                    && $data['stats']['branches'] === $expectedBranches
                    && $data['rows']->sum('total_lops') === $expectedLops);
            $this->getJson(route('dashboard.monitoring-lops', ['date' => '2026-10-07', 'branch' => 'DENPASAR']))
                ->assertOk()->assertJsonPath('total', $config['admin_scope_type'] === 'service_area' ? 1 : 0);
            $this->getJson(route('dashboard.monitoring-lops', ['date' => '2026-10-07', 'branch' => 'SIDOARJO']))
                ->assertOk()->assertJsonPath('total', $config['admin_scope_type'] === 'service_area' ? 1 : 2);
        }
    }

    public function test_monitoring_filters_use_branch_foreign_key_and_pagination_is_bounded(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $branch = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        for ($i = 0; $i < 21; $i++) {
            $lop = $this->lop($super, $branch, "PAGINATED-{$i}", '2026-10-07 01:00:00');
            $lop->update(['branch' => 'NAMA LEGACY LAMA']);
        }
        $this->actingAs($super)->get(route('dashboard', ['region' => 'REGION JATIM', 'branch' => 'SIDOARJO', 'date' => '2026-10-07']))
            ->assertOk()->assertViewHas('monitoring', fn (array $data) => $data['rows']->sum('total_lops') === 21)
            ->assertViewHas('matrixRegions', fn (array $data) => $data[0]['branches'][0]['summary']['total'] === 21);
        $this->getJson(route('dashboard.monitoring-lops', ['branch' => 'SIDOARJO', 'date' => '2026-10-07']))
            ->assertOk()->assertJsonPath('total', 21)->assertJsonCount(20, 'data')->assertJsonPath('last_page', 2);
        $this->getJson(route('dashboard.monitoring-lops', ['branch' => 'SIDOARJO', 'date' => '2026-10-07', 'page' => 2]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('page', 2);
    }

    public function test_monitoring_rejects_invalid_dates_and_unauthorized_roles(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create();
        $this->getJson(route('dashboard.monitoring-lops'))->assertUnauthorized();
        $this->actingAs($tech)->getJson(route('dashboard.monitoring-lops'))->assertForbidden();
        $this->actingAs($super)->getJson(route('dashboard.monitoring-lops', ['branch' => 'SIDOARJO', 'date' => '2026-02-30']))
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson(route('dashboard', ['date' => '2099-01-01']))->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_last_update_identifies_the_actual_actor_and_role_not_the_assigned_technician(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['name' => 'Budi']);
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create(['name' => 'Adi']);
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $branch = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $lop = $this->lop($admin, $branch, 'ACTOR-ADMIN', '2026-10-01 01:00:00');
        $this->history($lop, $admin, '2026-10-07 01:00:00', 'reassigned');
        $uploadedLop = $this->lop($admin, $branch, 'ACTOR-TECH', '2026-10-01 01:00:00');
        $evidence = QeEvidence::create([
            'qe_lop_id' => $uploadedLop->getKey(), 'uploaded_by' => $tech->getKey(), 'step' => 'PROGRESS',
            'type' => 'PHOTO', 'category' => 'progress', 'file_path' => 'test.jpg', 'status' => 'pending',
        ]);
        $evidence->forceFill(['created_at' => '2026-10-07 02:00:00'])->save();
        $response = $this->actingAs($super)->getJson(route('dashboard.monitoring-lops', ['branch' => 'SIDOARJO', 'date' => '2026-10-07']));
        $response->assertOk();
        $rows = collect($response->json('data'))->keyBy('incident');
        $this->assertSame('Budi — Admin', $rows['ACTOR-ADMIN']['last_activity']['actor_label']);
        $this->assertSame('Reassign Teknisi', $rows['ACTOR-ADMIN']['last_activity']['label']);
        $this->assertSame('Adi — Teknisi', $rows['ACTOR-TECH']['last_activity']['actor_label']);
        $this->assertSame('Upload Progress', $rows['ACTOR-TECH']['last_activity']['label']);
        $this->get(route('dashboard', ['date' => '2026-10-07', 'tab' => 'summary']))
            ->assertOk()->assertSee('Adi — Teknisi')
            ->assertViewHas('monitoring', fn (array $data) => $data['rows']->first()['last_activity']['actor_label'] === 'Adi — Teknisi');
        // Reviewer identity, not uploader, becomes the latest activity.
        $evidence->update(['status' => 'approved', 'reviewed_by' => $admin->getKey(), 'reviewed_at' => '2026-10-07 02:00:00']);
        $this->getJson(route('dashboard.monitoring-lops', ['branch' => 'SIDOARJO', 'date' => '2026-10-07']))
            ->assertOk()->assertJsonPath('data.0.last_activity.actor_label', 'Budi — Admin')
            ->assertJsonPath('data.0.last_activity.label', 'Approve Progress');
    }

    public function test_activity_accordion_is_daily_paginated_and_cannot_leak_another_branch(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $branch = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $otherBranch = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->getKey(), 'name' => 'Budi']);
        $otherAdmin = User::factory()->role(UserRole::ADMIN->value)->create(['name' => 'Other Admin']);
        $lop = $this->lop($admin, $branch, 'TIMELINE', '2026-10-01 01:00:00');
        $other = $this->lop($otherAdmin, $otherBranch, 'FORBIDDEN', '2026-10-01 01:00:00');
        for ($i = 0; $i < 21; $i++) {
            $this->history($lop, $admin, '2026-10-07 01:00:00');
        }
        $this->history($lop, $admin, '2026-10-06 16:59:59');
        $this->history($lop, $otherAdmin, '2026-10-07 17:00:00');
        $this->actingAs($admin)->getJson(route('dashboard.monitoring-activities', ['lopId' => $lop->getKey(), 'date' => '2026-10-07']))
            ->assertOk()->assertJsonPath('total', 21)->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.actor_label', 'Budi — Admin')->assertJsonPath('data.0.time', '07 Oct 2026 08:00')
            ->assertJsonMissing(['actor_name' => 'Other Admin']);
        $this->getJson(route('dashboard.monitoring-activities', ['lopId' => $lop->getKey(), 'date' => '2026-10-07', 'page' => 2]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('page', 2);
        $this->getJson(route('dashboard.monitoring-activities', ['lopId' => $other->getKey(), 'date' => '2026-10-07']))->assertNotFound();
        $this->getJson(route('dashboard.monitoring-activities', ['lopId' => $lop->getKey()]))->assertUnprocessable()->assertJsonValidationErrors('date');
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create();
        $this->actingAs($tech)->getJson(route('dashboard.monitoring-activities', ['lopId' => $lop->getKey(), 'date' => '2026-10-07']))->assertForbidden();
    }

    public function test_ties_are_stable_and_deleted_actors_still_show_the_recorded_identity(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['name' => 'Budi']);
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create(['name' => 'Adi']);
        $branch = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $lop = $this->lop($admin, $branch, 'TIE', '2026-10-01 01:00:00');
        $this->history($lop, $admin, '2026-10-07 01:00:00');
        $this->history($lop, $tech, '2026-10-07 01:00:00', 'reassigned');
        $tech->delete();
        $this->actingAs($super)->getJson(route('dashboard.monitoring-lops', ['branch' => 'SIDOARJO', 'date' => '2026-10-07']))
            ->assertOk()->assertJsonPath('data.0.last_activity.actor_label', 'Adi — Teknisi')
            ->assertJsonPath('data.0.last_activity.label', 'Reassign Teknisi');
        $this->get(route('dashboard', ['date' => '2026-10-07']))->assertOk()->assertSee('Adi — Teknisi');
    }

    private function lop(User $creator, Branch $branch, string $incident, string $createdAt): QeLop
    {
        $lop = QeLop::create([
            'incident' => $incident, 'nama_lop' => "Project {$incident}", 'created_by' => $creator->getKey(),
            'program_type' => 'recovery', 'status_lop' => 'draft', 'branch_id' => $branch->getKey(), 'branch' => $branch->name,
        ]);
        $lop->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $lop;
    }

    private function history(QeLop $lop, User $actor, string $time, string $type = 'status_changed'): void
    {
        $history = QeLopHistory::create(['qe_lop_id' => $lop->getKey(), 'user_id' => $actor->getKey(), 'event_type' => $type, 'status_after' => 'draft']);
        $history->forceFill(['created_at' => $time])->save();
    }
}
