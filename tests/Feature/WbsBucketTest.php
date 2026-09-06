<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WbsBucketTest extends TestCase
{
    use RefreshDatabase;

    private function lop(User $creator, string $incident, string $wbs, string $status, string $branch = 'SURABAYA'): QeLop
    {
        return QeLop::create([
            'incident' => $incident,
            'nama_lop' => "LOP {$incident}",
            'wbs_type' => $wbs,
            'sto' => 'SBY',
            'branch' => $branch,
            'status_lop' => $status,
            'created_by' => $creator->id_user,
        ]);
    }

    private function userForBranch(UserRole $role, string $branchName): User
    {
        $branch = Branch::firstOrCreate(
            ['code' => substr(str_replace(' ', '', $branchName), 0, 6)],
            ['name' => $branchName, 'region' => 'REGION JATIM'],
        );

        return User::factory()->role($role->value)->create(['branch_id' => $branch->id_branch]);
    }

    public function test_show_is_accessible_to_monitoring_roles_and_404_for_bad_slug(): void
    {
        foreach ([UserRole::SUPER_ADMIN, UserRole::MANAGER, UserRole::APPROVER, UserRole::ADMIN] as $role) {
            $user = User::factory()->role($role->value)->create();
            $this->actingAs($user)->get(route('wbs.show', 'recovery'))->assertOk();
        }

        $this->actingAs(User::factory()->role(UserRole::ADMIN->value)->create())
            ->get(route('wbs.show', 'ngawur'))->assertNotFound();
    }

    public function test_technician_is_redirected_to_their_inbox(): void
    {
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $this->actingAs($teknisi)->get(route('wbs.index'))->assertRedirect(route('technician.inbox'));
        $this->actingAs($teknisi)->get(route('wbs.show', 'recovery'))->assertRedirect(route('technician.inbox'));
    }

    public function test_show_only_lists_lops_of_that_wbs_including_completed(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->lop($admin, 'REC-ACTIVE', 'recovery', 'progress');
        $this->lop($admin, 'REC-DONE', 'recovery', 'completed');
        $this->lop($admin, 'PREV-ONE', 'preventive', 'draft');

        $this->actingAs($admin)->get(route('wbs.show', 'recovery'))
            ->assertOk()
            ->assertSee('REC-ACTIVE')
            ->assertSee('REC-DONE')
            ->assertDontSee('PREV-ONE');
    }

    public function test_non_super_admin_is_locked_to_their_own_branch(): void
    {
        $creator = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $adminSby = $this->userForBranch(UserRole::ADMIN, 'SURABAYA');

        $this->lop($creator, 'AT-SBY', 'recovery', 'assigned', 'SURABAYA');
        $this->lop($creator, 'AT-SDA', 'recovery', 'assigned', 'SIDOARJO');

        $this->actingAs($adminSby)->get(route('wbs.show', 'recovery'))
            ->assertOk()
            ->assertViewHas('total', 1)
            ->assertViewHas('canFilterLocation', false)
            ->assertSee('AT-SBY')
            ->assertDontSee('AT-SDA');
    }

    public function test_non_super_admin_cannot_widen_scope_via_query_params(): void
    {
        $creator = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $managerSby = $this->userForBranch(UserRole::MANAGER, 'SURABAYA');

        $this->lop($creator, 'M-SBY', 'recovery', 'assigned', 'SURABAYA');
        $this->lop($creator, 'M-SDA', 'recovery', 'assigned', 'SIDOARJO');

        // Coba paksa lihat branch lain lewat query param -> tetap terkunci.
        $this->actingAs($managerSby)->get(route('wbs.show', ['recovery', 'branch' => 'SIDOARJO']))
            ->assertOk()
            ->assertViewHas('total', 1)
            ->assertSee('M-SBY')
            ->assertDontSee('M-SDA');
    }

    public function test_user_without_branch_sees_nothing_with_warning(): void
    {
        $creator = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $orphan = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => null]);

        $this->lop($creator, 'SOMEWHERE', 'recovery', 'assigned', 'SURABAYA');

        $this->actingAs($orphan)->get(route('wbs.show', 'recovery'))
            ->assertOk()
            ->assertViewHas('total', 0)
            ->assertViewHas('scopeWarning', true)
            ->assertDontSee('SOMEWHERE')
            ->assertSee('belum terhubung ke branch');

        $this->actingAs($orphan)->get(route('wbs.index'))
            ->assertOk()
            ->assertViewHas('scopeWarning', true)
            ->assertViewHas('wbsSummaries', fn (array $s) => collect($s)->sum('total') === 0);
    }

    public function test_super_admin_can_still_filter_by_region_and_branch(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'DPS', 'name' => 'DENPASAR', 'region' => 'REGION BALNUS']);

        $this->lop($admin, 'X-SBY', 'recovery', 'assigned', 'SURABAYA');
        $this->lop($admin, 'X-DPS', 'recovery', 'assigned', 'DENPASAR');

        $this->actingAs($admin)->get(route('wbs.show', ['recovery', 'branch' => 'DENPASAR']))
            ->assertOk()
            ->assertViewHas('canFilterLocation', true)
            ->assertViewHas('total', 1)
            ->assertSee('X-DPS')
            ->assertDontSee('X-SBY');
    }

    public function test_bucket_counts_and_filtering(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->lop($admin, 'R-DRAFT', 'recovery', 'draft');
        $this->lop($admin, 'R-SURVEY', 'recovery', 'survey');
        $this->lop($admin, 'R-PROGRESS', 'recovery', 'progress');
        $this->lop($admin, 'R-REVIEW', 'recovery', 'waiting_approval');

        $this->actingAs($admin)->get(route('wbs.show', 'recovery'))
            ->assertOk()
            ->assertViewHas('total', 4)          // draft ikut, sebagai bucket "Belum Ditugaskan"
            ->assertSee('R-DRAFT')
            ->assertViewHas('buckets', function (array $buckets) {
                $byKey = collect($buckets)->keyBy('key');

                return ! $byKey->has('draft')
                    && $byKey['unassigned']['count'] === 1
                    && $byKey['progress']['count'] === 2   // survey + progress
                    && $byKey['review']['count'] === 1
                    && $byKey['done']['count'] === 0;
            });

        // bucket=unassigned -> hanya draft
        $this->actingAs($admin)->get(route('wbs.show', ['recovery', 'bucket' => 'unassigned']))
            ->assertOk()
            ->assertSee('R-DRAFT')
            ->assertDontSee('R-SURVEY')
            ->assertDontSee('R-REVIEW');

        // bucket=progress -> hanya survey + progress
        $this->actingAs($admin)->get(route('wbs.show', ['recovery', 'bucket' => 'progress']))
            ->assertOk()
            ->assertSee('R-SURVEY')
            ->assertSee('R-PROGRESS')
            ->assertDontSee('R-DRAFT')
            ->assertDontSee('R-REVIEW');

        // bucket=review -> hanya waiting_approval
        $this->actingAs($admin)->get(route('wbs.show', ['recovery', 'bucket' => 'review']))
            ->assertOk()
            ->assertSee('R-REVIEW')
            ->assertDontSee('R-PROGRESS');
    }

    public function test_region_and_branch_filters_reslice_the_page(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'DPS', 'name' => 'DENPASAR', 'region' => 'REGION BALNUS']);

        $this->lop($admin, 'SBY-1', 'recovery', 'progress', 'SURABAYA');
        $this->lop($admin, 'SDA-1', 'recovery', 'assigned', 'SIDOARJO');
        $this->lop($admin, 'DPS-1', 'recovery', 'progress', 'DENPASAR');

        // Filter region JATIM -> Surabaya + Sidoarjo saja
        $this->actingAs($admin)->get(route('wbs.show', ['recovery', 'region' => 'REGION JATIM']))
            ->assertOk()
            ->assertViewHas('total', 2)
            ->assertSee('SBY-1')->assertSee('SDA-1')->assertDontSee('DPS-1')
            ->assertViewHas('regionFilter', 'REGION JATIM');

        // Filter branch SURABAYA -> hanya 1
        $this->actingAs($admin)->get(route('wbs.show', ['recovery', 'branch' => 'SURABAYA']))
            ->assertOk()
            ->assertViewHas('total', 1)
            ->assertSee('SBY-1')->assertDontSee('SDA-1')
            ->assertViewHas('buckets', fn (array $b) => collect($b)->firstWhere('key', 'progress')['count'] === 1);

        // Region tidak dikenal -> diabaikan (tampil semua)
        $this->actingAs($admin)->get(route('wbs.show', ['recovery', 'region' => 'REGION NGAWUR']))
            ->assertOk()
            ->assertViewHas('total', 3)
            ->assertViewHas('regionFilter', '');
    }

    public function test_index_respects_region_filter(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'DPS', 'name' => 'DENPASAR', 'region' => 'REGION BALNUS']);

        $this->lop($admin, 'J-1', 'recovery', 'assigned', 'SURABAYA');
        $this->lop($admin, 'B-1', 'recovery', 'assigned', 'DENPASAR');

        $this->actingAs($admin)->get(route('wbs.index', ['region' => 'REGION JATIM']))
            ->assertOk()
            ->assertViewHas('wbsSummaries', fn (array $s) => collect($s)->firstWhere('slug', 'recovery')['total'] === 1);
    }

    public function test_index_lists_all_three_wbs_with_totals(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->lop($admin, 'A', 'recovery', 'assigned');
        $this->lop($admin, 'B', 'recovery', 'completed');
        $this->lop($admin, 'C', 'preventive', 'assigned');

        $this->actingAs($admin)->get(route('wbs.index'))
            ->assertOk()
            ->assertViewHas('wbsSummaries', function (array $summaries) {
                $byslug = collect($summaries)->keyBy('slug');

                return $byslug['recovery']['total'] === 2
                    && $byslug['preventive']['total'] === 1
                    && $byslug['relok_utilitas']['total'] === 0;
            });
    }

    public function test_draft_lops_appear_in_unassigned_bucket(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->lop($admin, 'DRAFT-ONLY', 'recovery', 'draft');
        $this->lop($admin, 'REAL-ONE', 'recovery', 'assigned');

        $this->actingAs($admin)->get(route('wbs.show', 'recovery'))
            ->assertOk()
            ->assertViewHas('total', 2)
            ->assertSee('REAL-ONE')
            ->assertSee('DRAFT-ONLY')
            ->assertViewHas('buckets', fn (array $b) => collect($b)->firstWhere('key', 'unassigned')['count'] === 1);

        $this->actingAs($admin)->get(route('wbs.show', ['recovery', 'bucket' => 'unassigned']))
            ->assertOk()
            ->assertSee('DRAFT-ONLY')
            ->assertDontSee('REAL-ONE');

        $this->actingAs($admin)->get(route('wbs.index'))
            ->assertViewHas('wbsSummaries', fn (array $s) => collect($s)->firstWhere('slug', 'recovery')['total'] === 2);
    }
}
