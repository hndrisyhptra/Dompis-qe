<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_only_available_to_admin_and_super_admin(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $manager = User::factory()->role(UserRole::MANAGER->value)->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($superAdmin)->get(route('dashboard'))->assertOk();
        $this->actingAs($technician)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($manager)->get(route('dashboard'))->assertForbidden();
    }

    public function test_admin_dashboard_contains_all_lops_from_their_branch_only(): void
    {
        $sidoarjo = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $surabaya = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $sidoarjo->id_branch]);
        $otherAdmin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $surabaya->id_branch]);

        $sidoarjoLop = $this->makeLop($otherAdmin, 'DASH-SDA', 'SIDOARJO', ihldId: null);
        $this->makeLop($admin, 'DASH-SBY', 'SURABAYA', ihldId: 'IHLD-SBY');

        $this->actingAs($admin)
            ->get(route('dashboard', ['region' => 'REGION BALNUS']))
            ->assertOk()
            ->assertSee('DASH-SDA')
            ->assertDontSee('DASH-SBY')
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 1
                && $stats['missing_ihld'] === 1)
            ->assertViewHas('priorityLops', fn ($lops) => $lops->count() === 1
                && $lops->first()->is($sidoarjoLop));
    }

    public function test_admin_without_branch_sees_safe_empty_dashboard(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => null]);
        $this->makeLop($admin, 'DASH-NO-BRANCH', 'SIDOARJO');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Branch akun belum dikonfigurasi')
            ->assertViewHas('scopeWarning', true)
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 0);
    }

    public function test_super_admin_filters_update_all_dashboard_totals(): void
    {
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'DPS', 'name' => 'DENPASAR', 'region' => 'REGION BALNUS']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $target = $this->makeLop($admin, 'DASH-TARGET', 'SIDOARJO', 'recovery', 'draft', null);
        $this->makeLop($admin, 'DASH-JATIM-OTHER', 'SURABAYA', 'preventive', 'completed', 'IHLD-1');
        $this->makeLop($admin, 'DASH-BALNUS', 'DENPASAR', 'recovery', 'draft', null);

        QeEvidence::create([
            'qe_lop_id' => $target->id_qe_lops,
            'uploaded_by' => $admin->id_user,
            'step' => 'PROGRESS',
            'type' => 'PHOTO',
            'category' => 'progress',
            'file_path' => 'evidences/dashboard.jpg',
            'status' => 'pending',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard', ['region' => 'REGION JATIM']))
            ->assertOk()
            ->assertSee('DASH-TARGET')
            ->assertSee('DASH-JATIM-OTHER')
            ->assertDontSee('DASH-BALNUS')
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 2
                && $stats['missing_ihld'] === 1);

        $this->get(route('dashboard', [
            'region' => 'REGION JATIM',
            'branch' => 'SIDOARJO',
            'wbs' => 'recovery',
            'status' => 'draft',
        ]))
            ->assertOk()
            ->assertSee('DASH-TARGET')
            ->assertDontSee('DASH-JATIM-OTHER')
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 1
                && $stats['active'] === 1
                && $stats['missing_ihld'] === 1)
            ->assertViewHas('evidenceStats', fn (array $stats) => $stats['total'] === 1
                && $stats['pending'] === 1);
    }

    public function test_matrix_breaks_down_region_branch_wbs_and_pipeline_values(): void
    {
        $sidoarjo = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $sidoarjo->id_branch]);
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();

        $assigned = $this->makeLop($admin, 'MATRIX-ASSIGNED', 'SIDOARJO', 'recovery', 'assigned');
        $assigned->assignments()->create([
            'technician_id' => $technician->id_user,
            'assigned_by' => $admin->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);
        $this->makeLop($admin, 'MATRIX-REVIEW', 'SIDOARJO', 'recovery', 'waiting_approval');
        $this->makeLop($admin, 'MATRIX-COMPLETE', 'SIDOARJO', 'preventive', 'completed');
        $this->makeLop($admin, 'MATRIX-DRAFT', 'SIDOARJO', 'relok_utilitas', 'draft');
        $this->makeLop($admin, 'MATRIX-SBY', 'SURABAYA', 'preventive', 'completed');

        $assertMatrix = function (array $regions, bool $includeSurabaya): bool {
            $jatim = collect($regions)->firstWhere('name', 'REGION JATIM');
            $sidoarjo = collect($jatim['branches'])->firstWhere('name', 'SIDOARJO');
            $recovery = collect($sidoarjo['wbs'])->firstWhere('value', 'recovery');

            return $sidoarjo['summary']['total'] === 4
                && $sidoarjo['summary']['assigned'] === 1
                && $sidoarjo['summary']['in_review'] === 1
                && $sidoarjo['summary']['complete'] === 1
                && $sidoarjo['summary']['percentage'] === 25
                && $sidoarjo['summary']['pipeline']['draft'] === 1
                && $sidoarjo['summary']['pipeline']['assigned'] === 1
                && $recovery['total'] === 2
                && $recovery['assigned'] === 1
                && $recovery['in_review'] === 1
                && $includeSurabaya === collect($jatim['branches'])->contains('name', 'SURABAYA');
        };

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Matrix Kinerja WBS')
            ->assertSee('<details', false)
            ->assertSee('branchOpen', false)
            ->assertViewHas('matrixRegions', fn (array $regions) => $assertMatrix($regions, true));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('matrixRegions', fn (array $regions) => $assertMatrix($regions, false));
    }

    private function makeLop(
        User $creator,
        string $incident,
        string $branch,
        string $wbs = 'recovery',
        string $status = 'draft',
        ?string $ihldId = 'IHLD-TEST',
    ): QeLop {
        return QeLop::create([
            'incident' => $incident,
            'nama_lop' => "Project {$incident}",
            'wbs_type' => $wbs,
            'branch' => $branch,
            'status_lop' => $status,
            'ihld_id' => $ihldId,
            'created_by' => $creator->id_user,
        ]);
    }
}
