<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgramBucketTest extends TestCase
{
    use RefreshDatabase;

    private function lop(User $creator, string $incident, string $program, string $status, string $branch = 'SURABAYA'): QeLop
    {
        return QeLop::create([
            'incident' => $incident,
            'nama_lop' => "LOP {$incident}",
            'program_type' => $program,
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
            $this->actingAs($user)->get(route('program.show', 'recovery'))->assertOk();
        }

        $this->actingAs(User::factory()->role(UserRole::ADMIN->value)->create())
            ->get(route('program.show', 'ngawur'))->assertNotFound();
    }

    public function test_technician_is_redirected_to_their_inbox(): void
    {
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $this->actingAs($teknisi)->get(route('program.index'))->assertRedirect(route('technician.inbox'));
        $this->actingAs($teknisi)->get(route('program.show', 'recovery'))->assertRedirect(route('technician.inbox'));
    }

    public function test_show_only_lists_lops_of_that_program_including_completed(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->lop($admin, 'REC-ACTIVE', 'recovery', 'progress');
        $this->lop($admin, 'REC-DONE', 'recovery', 'completed');
        $this->lop($admin, 'PREV-ONE', 'preventive', 'draft');

        $this->actingAs($admin)->get(route('program.show', 'recovery'))
            ->assertOk()
            ->assertSee('REC-ACTIVE')
            ->assertSee('REC-DONE')
            ->assertDontSee('PREV-ONE');
    }

    public function test_show_displays_ten_lops_per_page_and_keeps_filtered_total(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        for ($index = 1; $index <= 12; $index++) {
            $this->lop($admin, "PAGE-{$index}", 'recovery', 'assigned');
        }

        $firstPage = $this->actingAs($admin)->get(route('program.show', ['recovery', 'bucket' => 'assigned']))
            ->assertOk()
            ->assertViewHas('lops', fn ($lops) => $lops->total() === 12
                && $lops->perPage() === 10
                && $lops->count() === 10);

        $this->assertStringContainsString('bucket=assigned', $firstPage->getContent());
        $this->get(route('program.show', ['recovery', 'bucket' => 'assigned', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('lops', fn ($lops) => $lops->total() === 12
                && $lops->perPage() === 10
                && $lops->count() === 2);
    }

    public function test_program_page_loads_row_modals_on_demand_and_preserves_filter_context(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $lop = $this->lop($admin, 'LAZY-MODAL', 'recovery', 'assigned');
        $filters = ['bucket' => 'assigned', 'q' => 'LAZY-MODAL'];

        $pageResponse = $this->actingAs($admin)->get(route('program.show', ['recovery', ...$filters]))
            ->assertOk()
            ->assertSee('LAZY-MODAL')
            ->assertSee('data-lazy-modal-url=', false)
            ->assertSee('/program/recovery/lops/'.$lop->getKey().'/modal/detail?q=LAZY-MODAL&amp;bucket=assigned', false)
            ->assertDontSee('<dialog id="program-detail-'.$lop->getKey().'"', false)
            ->assertDontSee('<dialog id="program-tracking-'.$lop->getKey().'"', false);

        $this->get(route('program.modal', ['program' => 'recovery', 'qe_lop' => $lop, 'kind' => 'detail', ...$filters]))
            ->assertOk()
            ->assertSee('<dialog id="program-detail-'.$lop->getKey().'"', false)
            ->assertSee('LAZY-MODAL');
    }

    public function test_lazy_program_modal_enforces_lop_scope_and_assignment_permission(): void
    {
        $admin = $this->userForBranch(UserRole::ADMIN, 'SURABAYA');
        $otherLop = $this->lop($admin, 'OUTSIDE-LAZY', 'recovery', 'assigned', 'SIDOARJO');

        $this->actingAs($admin)
            ->get(route('program.modal', ['program' => 'recovery', 'qe_lop' => $otherLop, 'kind' => 'assign']))
            ->assertForbidden();

        $visibleLop = $this->lop($admin, 'VISIBLE-LAZY', 'recovery', 'assigned', 'SURABAYA');
        $this->get(route('program.modal', ['program' => 'recovery', 'qe_lop' => $visibleLop, 'kind' => 'assign']))
            ->assertOk()
            ->assertSee('Assign Teknisi')
            ->assertSee('VISIBLE-LAZY');
    }

    public function test_progress_summary_boq_relations_are_loaded_in_a_batch(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        for ($index = 1; $index <= 5; $index++) {
            $this->lop($admin, "BOQ-BATCH-{$index}", 'recovery', 'assigned');
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->actingAs($admin)->get(route('program.show', 'recovery'))->assertOk();
        $boqQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'qe_boqs'));
        $this->assertCount(1, $boqQueries);
        $this->assertSame(5, $response->viewData('lops')->getCollection()->count());
        $this->assertNotEmpty($response->viewData('lops')->getCollection()->first()->progress_summary);
    }

    public function test_admin_row_actions_do_not_run_one_visibility_query_per_lop(): void
    {
        $admin = $this->userForBranch(UserRole::ADMIN, 'SURABAYA');
        for ($index = 1; $index <= 10; $index++) {
            $this->lop($admin, "SCOPE-BATCH-{$index}", 'recovery', 'assigned');
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($admin)->get(route('program.show', 'recovery'))
            ->assertOk()
            ->assertViewHas('lops', fn ($lops) => $lops->count() === 10);

        $perLopVisibilityChecks = collect(DB::getQueryLog())->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'select exists')
                && str_contains($sql, 'qe_lops')
                && str_contains($sql, 'id_qe_lops');
        });

        $this->assertCount(0, $perLopVisibilityChecks);
    }

    public function test_program_index_uses_one_status_aggregate_for_all_programs(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $this->lop($admin, 'IDX-REC', 'recovery', 'assigned');
        $this->lop($admin, 'IDX-PREV', 'preventive', 'completed');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($admin)->get(route('program.index'))->assertOk();

        $aggregates = collect(DB::getQueryLog())->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'qe_lops')
                && str_contains($sql, 'program_type')
                && str_contains($sql, 'status_lop')
                && str_contains($sql, 'group by');
        });
        $this->assertCount(1, $aggregates);
    }

    public function test_download_evidence_action_shows_only_for_completed_lops(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $done = $this->lop($admin, 'REC-DONE', 'recovery', 'completed');
        $active = $this->lop($admin, 'REC-ACTIVE', 'recovery', 'progress');

        $this->actingAs($admin)->get(route('program.show', 'recovery'))
            ->assertOk()
            ->assertSee(route('lop.evidence-archive', $done), false)
            ->assertDontSee(route('lop.evidence-archive', $active), false);
    }

    public function test_non_super_admin_is_locked_to_their_own_branch(): void
    {
        $creator = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $adminSby = $this->userForBranch(UserRole::ADMIN, 'SURABAYA');

        $this->lop($creator, 'AT-SBY', 'recovery', 'assigned', 'SURABAYA');
        $this->lop($creator, 'AT-SDA', 'recovery', 'assigned', 'SIDOARJO');

        $this->actingAs($adminSby)->get(route('program.show', 'recovery'))
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
        $this->actingAs($managerSby)->get(route('program.show', ['recovery', 'branch' => 'SIDOARJO']))
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

        $this->actingAs($orphan)->get(route('program.show', 'recovery'))
            ->assertOk()
            ->assertViewHas('total', 0)
            ->assertViewHas('scopeWarning', true)
            ->assertDontSee('SOMEWHERE')
            ->assertSee('Scope lokasi akun Anda belum diatur');

        $this->actingAs($orphan)->get(route('program.index'))
            ->assertOk()
            ->assertViewHas('scopeWarning', true)
            ->assertViewHas('programSummaries', fn (array $s) => collect($s)->sum('total') === 0);
    }

    public function test_super_admin_can_still_filter_by_region_and_branch(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'DPS', 'name' => 'DENPASAR', 'region' => 'REGION BALNUS']);

        $this->lop($admin, 'X-SBY', 'recovery', 'assigned', 'SURABAYA');
        $this->lop($admin, 'X-DPS', 'recovery', 'assigned', 'DENPASAR');

        $this->actingAs($admin)->get(route('program.show', ['recovery', 'branch' => 'DENPASAR']))
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

        $this->actingAs($admin)->get(route('program.show', 'recovery'))
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
        $this->actingAs($admin)->get(route('program.show', ['recovery', 'bucket' => 'unassigned']))
            ->assertOk()
            ->assertSee('R-DRAFT')
            ->assertDontSee('R-SURVEY')
            ->assertDontSee('R-REVIEW');

        // bucket=progress -> hanya survey + progress
        $this->actingAs($admin)->get(route('program.show', ['recovery', 'bucket' => 'progress']))
            ->assertOk()
            ->assertSee('R-SURVEY')
            ->assertSee('R-PROGRESS')
            ->assertDontSee('R-DRAFT')
            ->assertDontSee('R-REVIEW');

        // bucket=review -> hanya waiting_approval
        $this->actingAs($admin)->get(route('program.show', ['recovery', 'bucket' => 'review']))
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
        $this->actingAs($admin)->get(route('program.show', ['recovery', 'region' => 'REGION JATIM']))
            ->assertOk()
            ->assertViewHas('total', 2)
            ->assertSee('SBY-1')->assertSee('SDA-1')->assertDontSee('DPS-1')
            ->assertViewHas('regionFilter', 'REGION JATIM');

        // Filter branch SURABAYA -> hanya 1
        $this->actingAs($admin)->get(route('program.show', ['recovery', 'branch' => 'SURABAYA']))
            ->assertOk()
            ->assertViewHas('total', 1)
            ->assertSee('SBY-1')->assertDontSee('SDA-1')
            ->assertViewHas('buckets', fn (array $b) => collect($b)->firstWhere('key', 'progress')['count'] === 1);

        // Region tidak dikenal -> diabaikan (tampil semua)
        $this->actingAs($admin)->get(route('program.show', ['recovery', 'region' => 'REGION NGAWUR']))
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

        $this->actingAs($admin)->get(route('program.index', ['region' => 'REGION JATIM']))
            ->assertOk()
            ->assertViewHas('programSummaries', fn (array $s) => collect($s)->firstWhere('slug', 'recovery')['total'] === 1);
    }

    public function test_index_lists_all_three_program_with_totals(): void
    {
        $admin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->lop($admin, 'A', 'recovery', 'assigned');
        $this->lop($admin, 'B', 'recovery', 'completed');
        $this->lop($admin, 'C', 'preventive', 'assigned');

        $this->actingAs($admin)->get(route('program.index'))
            ->assertOk()
            ->assertViewHas('programSummaries', function (array $summaries) {
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

        $this->actingAs($admin)->get(route('program.show', 'recovery'))
            ->assertOk()
            ->assertViewHas('total', 2)
            ->assertSee('REAL-ONE')
            ->assertSee('DRAFT-ONLY')
            ->assertViewHas('buckets', fn (array $b) => collect($b)->firstWhere('key', 'unassigned')['count'] === 1);

        $this->actingAs($admin)->get(route('program.show', ['recovery', 'bucket' => 'unassigned']))
            ->assertOk()
            ->assertSee('DRAFT-ONLY')
            ->assertDontSee('REAL-ONE');

        $this->actingAs($admin)->get(route('program.index'))
            ->assertViewHas('programSummaries', fn (array $s) => collect($s)->firstWhere('slug', 'recovery')['total'] === 2);
    }
}
