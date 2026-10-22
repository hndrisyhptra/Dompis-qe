<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\ServiceArea;
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

        $this->makeLop($otherAdmin, 'DASH-SDA', 'SIDOARJO', ihldId: null);
        $this->makeLop($admin, 'DASH-SBY', 'SURABAYA', ihldId: 'IHLD-SBY');

        $this->actingAs($admin)
            ->get(route('dashboard', ['region' => 'REGION BALNUS']))
            ->assertOk()
            ->assertSee('Summary per Branch')
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 1
                && $stats['missing_ihld'] === 1)
            ->assertViewHas('monitoring', fn (array $monitoring) => $monitoring['rows']->count() === 1
                && $monitoring['rows']->first()['name'] === 'SIDOARJO'
                && $monitoring['rows']->first()['total_lops'] === 1);
    }

    public function test_admin_dashboard_exposes_capture_control_next_to_program_filter(): void
    {
        $sidoarjo = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $sidoarjo->id_branch]);
        $this->makeLop($admin, 'CAPTURE-SDA', 'SIDOARJO');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("dashboardRegionCapture('SIDOARJO', '#dashboard-program-matrix')", false)
            ->assertSeeInOrder(['Monitoring Wilayah', 'dashboard-program-image-format', 'Pilih Program'], false)
            ->assertSee('id="dashboard-program-matrix"', false)
            ->assertSee('dashboard-program-image-format', false)
            ->assertSee('Capture', false);
    }

    public function test_admin_without_branch_sees_safe_empty_dashboard(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => null]);
        $this->makeLop($admin, 'DASH-NO-BRANCH', 'SIDOARJO');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Scope akun belum dikonfigurasi')
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
            ->assertViewHas('monitoring', fn (array $monitoring) => $monitoring['rows']->sum('total_lops') === 2
                && ! $monitoring['rows']->contains('name', 'DENPASAR'))
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 2
                && $stats['missing_ihld'] === 1);

        $this->get(route('dashboard', [
            'region' => 'REGION JATIM',
            'branch' => 'SIDOARJO',
            'program' => 'recovery',
            'status' => 'draft',
        ]))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 1
                && $stats['active'] === 1
                && $stats['missing_ihld'] === 1)
            ->assertViewHas('monitoring', fn (array $monitoring) => $monitoring['rows']->sum('total_lops') === 1
                && $monitoring['stats']['activities'] === 2);
    }

    public function test_matrix_breaks_down_region_branch_program_and_pipeline_values(): void
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
        $completed = $this->makeLop($admin, 'MATRIX-COMPLETE', 'SIDOARJO', 'preventive', 'completed');
        $completed->assignments()->create([
            'technician_id' => $technician->id_user,
            'assigned_by' => $admin->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);
        $this->makeLop($admin, 'MATRIX-DRAFT', 'SIDOARJO', 'relok_utilitas', 'draft');
        $this->makeLop($admin, 'MATRIX-SBY', 'SURABAYA', 'preventive', 'completed');

        $assertMatrix = function (array $regions, bool $includeSurabaya): bool {
            $jatim = collect($regions)->firstWhere('name', 'REGION JATIM');
            $sidoarjo = collect($jatim['branches'])->firstWhere('name', 'SIDOARJO');
            $recovery = collect($sidoarjo['program'])->firstWhere('value', 'recovery');

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
            ->assertSee('Monitoring Wilayah')
            ->assertSee('Ringkasan performa', false)
            ->assertSee('Branch / Program', false)
            ->assertSee('branchOpen', false)
            ->assertViewHas('matrixRegions', fn (array $regions) => $assertMatrix($regions, true));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('matrixRegions', fn (array $regions) => $assertMatrix($regions, false));
    }

    public function test_service_area_matrix_groups_sto_rows_by_name_and_stays_collapseable(): void
    {
        $surabaya = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $surabaya->id_branch]);

        ServiceArea::create(['workzone' => 'ABT', 'name' => 'SUMENEP', 'branch_id' => $surabaya->id_branch]);
        ServiceArea::create(['workzone' => 'ABU', 'name' => 'SUMENEP', 'branch_id' => $surabaya->id_branch]);
        $sampang = ServiceArea::create(['workzone' => 'KPP', 'name' => 'SAMPANG', 'branch_id' => $surabaya->id_branch]);
        ServiceArea::create(['workzone' => 'ZZZ', 'name' => '', 'branch_id' => $surabaya->id_branch]);

        // STO legacy lewat kolom sto dan FK service_area_id harus masuk kelompok yang sama.
        $this->makeLop($admin, 'GROUP-ABT', 'SURABAYA', 'recovery', 'draft')->update(['sto' => 'ABT']);
        $this->makeLop($admin, 'GROUP-ABU', 'SURABAYA', 'preventive', 'completed')->update(['sto' => 'ABU']);
        $this->makeLop($admin, 'GROUP-KPP', 'SURABAYA', 'recovery', 'assigned')->update(['service_area_id' => $sampang->id_service_area]);
        $this->makeLop($admin, 'GROUP-ZZZ', 'SURABAYA', 'recovery', 'waiting_approval')->update(['sto' => 'ZZZ']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('openGroups', false)
            ->assertSee('Lipat semua', false)
            ->assertViewHas('programMatrix', function (array $matrix): bool {
                $groups = collect($matrix['groups'])->keyBy('name');

                return $matrix['dimension'] === 'service_area'
                    && collect($matrix['rows'])->every(fn (array $row) => $row['group'] !== null)
                    && count($matrix['rows']) === 4
                    // SAMPANG harus mendahului SUMENEP agar satu kelompok selalu berdekatan.
                    && collect($matrix['groups'])->pluck('name')->search('SAMPANG')
                        < collect($matrix['groups'])->pluck('name')->search('SUMENEP')
                    && $groups['SUMENEP']['count'] === 2
                    && $groups['SUMENEP']['summary']['total'] === 2
                    && $groups['SUMENEP']['summary']['complete'] === 1
                    && $groups['SUMENEP']['summary']['percentage'] === 50
                    && count($groups['SUMENEP']['rows']) === 2
                    && $groups['SAMPANG']['count'] === 1
                    && $groups['SAMPANG']['summary']['assigned'] === 1
                    && $groups['STO TANPA NAMA']['count'] === 1
                    && collect($matrix['groups'])->sum(fn (array $group) => $group['summary']['total']) === $matrix['summary']['total'];
            });
    }

    public function test_matrix_drill_down_returns_matching_lops_without_leaking_other_admin_scope(): void
    {
        $sidoarjo = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $sidoarjo->id_branch]);

        $target = $this->makeLop($admin, 'DRILL-SDA', 'SIDOARJO', 'recovery', 'waiting_approval');
        $this->makeLop($admin, 'DRILL-SBY', 'SURABAYA', 'recovery', 'waiting_approval');
        $this->makeLop($admin, 'DRILL-OTHER', 'SIDOARJO', 'preventive', 'draft');

        $this->actingAs($admin)
            ->getJson(route('dashboard.matrix-lops', [
                'branch' => 'SIDOARJO',
                'program' => 'recovery',
                'status' => 'waiting_approval',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.incident', 'DRILL-SDA')
            ->assertJsonPath('data.0.detail_url', route('lop.detail', $target, false))
            ->assertJsonMissing(['incident' => 'DRILL-SBY']);
        $this->get(route('lop.detail', $target))->assertOk()->assertViewIs('lop.detail')
            ->assertSee('Project DRILL-SDA')->assertDontSee('Project DRILL-SBY');
    }

    public function test_super_admin_dashboard_exposes_region_image_capture(): void
    {
        Branch::create(['code' => 'DPS', 'name' => 'DENPASAR', 'region' => 'REGION BALNUS']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $this->makeLop($admin, 'CAPTURE-BALNUS', 'DENPASAR');

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("dashboardRegionCapture('REGION BALNUS')", false)
            ->assertSee('data-capture-ignore', false)
            ->assertSeeInOrder(['Ringkasan performa', 'region-image-format-0'], false)
            ->assertSee('Capture', false)
            ->assertSee('JPG / JPEG', false);
    }

    private function makeLop(
        User $creator,
        string $incident,
        string $branch,
        string $program = 'recovery',
        string $status = 'draft',
        ?string $ihldId = 'IHLD-TEST',
    ): QeLop {
        return QeLop::create([
            'incident' => $incident,
            'nama_lop' => "Project {$incident}",
            'program_type' => $program,
            'branch' => $branch,
            'status_lop' => $status,
            'ihld_id' => $ihldId,
            'created_by' => $creator->id_user,
        ]);
    }
}
