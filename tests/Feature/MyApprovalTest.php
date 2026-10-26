<?php

namespace Tests\Feature;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\MyApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_and_reminder_use_current_assigner_plus_scope_and_exclude_supporting_files(): void
    {
        [$admin, $other, $tech, $sa] = $this->people();
        $pending = $this->lop($admin, $tech, $sa, 'PENDING', 'waiting_approval', $other);
        $this->photo($pending, $tech, 'pending', daysAgo: 8);
        $this->photo($pending, $tech, 'pending', daysAgo: 2);
        $this->photo($pending, $tech, 'approved');
        $this->photo($pending, $tech, 'rejected');
        $ready = $this->lop($admin, $tech, $sa, 'READY', 'waiting_approval');
        $this->photo($ready, $tech, 'approved');
        $this->photo($ready, $tech, 'pending', 'request_letter');
        $rejected = $this->lop($admin, $tech, $sa, 'REJECTED', 'rejected');
        $this->photo($rejected, $tech, 'rejected');
        $completed = $this->lop($admin, $tech, $sa, 'COMPLETE', 'completed');
        $this->photo($completed, $tech, 'approved');
        $this->photo($completed, $tech, 'pending');
        $empty = $this->lop($admin, $tech, $sa, 'EMPTY', 'progress');
        $this->photo($empty, $tech, 'pending', 'request_letter');
        $this->photo($empty, $tech, 'pending')->delete();
        $otherLop = $this->lop($other, $tech, $sa, 'OTHER', 'waiting_approval', $admin);
        $this->photo($otherLop, $tech, 'pending');
        $historic = $this->lop($admin, $tech, $sa, 'HISTORIC', 'progress');
        $historic->assignments()->update(['status' => 'replaced', 'unassigned_at' => now()]);
        $historic->assignments()->create(['technician_id' => $tech->getKey(), 'assigned_by' => $other->getKey(), 'assigned_at' => now(), 'status' => 'active']);
        $this->photo($historic, $tech, 'pending');
        $cancelled = $this->lop($admin, $tech, $sa, 'CANCELLED', 'draft');
        $cancelled->assignments()->update(['status' => 'replaced', 'unassigned_at' => now()]);
        $outsideSa = $this->location('OUT', 'Outside', $sa->region_id);
        $outside = $this->lop($admin, $tech, $outsideSa, 'OUTSIDE', 'waiting_approval');
        $this->photo($outside, $tech, 'pending');
        $archived = $this->lop($admin, $tech, $sa, 'ARCHIVED', 'waiting_approval');
        $this->photo($archived, $tech, 'pending');
        $archived->delete();

        $stats = app(MyApprovalService::class)->stats($admin);
        $this->assertSame(['total_lops' => 5, 'pending_lops' => 1, 'pending_evidences' => 2, 'ready_lops' => 1, 'rejected_lops' => 2, 'completed_lops' => 1, 'empty_lops' => 1, 'actionable_lops' => 2], $stats);
        $response = $this->actingAs($admin)->get(route('lop.my-approvals', ['tab' => 'all']))->assertOk()
            ->assertSee('Approval Saya')->assertSee('INC-PENDING')->assertSee('INC-READY')->assertSee('INC-REJECTED')->assertSee('INC-COMPLETE')->assertSee('INC-EMPTY')
            ->assertDontSee('INC-OTHER')->assertDontSee('INC-OUTSIDE')->assertDontSee('INC-HISTORIC')->assertDontSee('INC-CANCELLED')->assertDontSee('INC-ARCHIVED');
        $this->assertTrue($response->viewData('lops')->every(fn ($lop) => ! $lop->relationLoaded('evidences')));
        $readyRow = $response->viewData('lops')->firstWhere('incident', 'INC-READY');
        $this->assertSame(1, $readyRow->approval_summary['total']);
        $this->assertSame(100, $readyRow->approval_summary['approval_percentage']);
        $this->assertTrue($readyRow->approval_ready);
        $this->assertSame(6, $readyRow->review_step);
        $reminder = app(MyApprovalService::class)->reminder($admin);
        $this->assertSame($pending->getKey(), $reminder['priority']->getKey());
        $this->assertSame($stats, $reminder['stats']);
        // Menu approval umum tetap mengikuti scope, bukan dibatasi assigner.
        $this->get(route('evidence-approval.index'))->assertOk()->assertSee('INC-OTHER')->assertDontSee('INC-OUTSIDE');
    }

    public function test_filters_pagination_and_review_completion_return_to_my_approval_origin(): void
    {
        [$admin, , $tech, $sa] = $this->people();
        $pending = $this->lop($admin, $tech, $sa, 'SEARCH', 'waiting_approval');
        $this->photo($pending, $tech, 'pending');
        $ready = $this->lop($admin, $tech, $sa, 'READY', 'waiting_approval');
        $ready->update(['program_type' => 'relok_tiang', 'status_project' => 'on_going']);
        $this->photo($ready, $tech, 'approved');
        $this->actingAs($admin)->get(route('lop.my-approvals'))->assertOk()->assertSee('INC-SEARCH')->assertSee('INC-READY');
        $this->get(route('lop.my-approvals', ['tab' => 'ready', 'program' => 'relok_tiang', 'lop_status' => 'waiting_approval']))->assertOk()->assertSee('INC-READY')->assertDontSee('INC-SEARCH');
        $this->get(route('lop.my-approvals', ['tab' => 'all', 'q' => 'SEARCH']))->assertOk()->assertSee('INC-SEARCH')->assertDontSee('INC-READY');
        $this->get(route('lop.my-approvals', ['tab' => 'unknown']))->assertOk()->assertViewHas('tab', 'actionable');
        $this->get(route('lop.my-approvals', ['q' => ['invalid']]))->assertSessionHasErrors('q');
        $origin = route('lop.my-approvals', ['tab' => 'ready', 'program' => 'relok_tiang']);
        $this->get(route('evidence-approval.lop.review', ['qe_lop' => $ready, 'step' => 5, 'return_url' => $origin]))->assertOk()->assertViewHas('returnUrl', $origin);
        $this->post(route('evidence-approval.lop.complete', $ready), ['return_url' => $origin])->assertRedirect($origin)->assertSessionHasNoErrors();
        $this->get($origin)->assertOk()->assertViewHas('lops', fn ($lops) => $lops->isEmpty());
        $this->get(route('lop.my-approvals', ['tab' => 'completed']))->assertOk()->assertSee('INC-READY');
        for ($index = 0; $index < 16; $index++) {
            $this->lop($admin, $tech, $sa, 'PAGE-'.$index, 'progress');
        }
        $page = $this->get(route('lop.my-approvals', ['tab' => 'all', 'q' => 'PAGE-']));
        $page->assertOk();
        $this->assertSame(16, $page->viewData('lops')->total());
        $this->assertCount(15, $page->viewData('lops'));
        $page->assertSee('tab=all', false)->assertSee('page=2', false);
    }

    public function test_popup_is_shown_once_per_successful_admin_login_and_uses_intended_first_page(): void
    {
        [$admin, , $tech, $sa] = $this->people();
        $pending = $this->lop($admin, $tech, $sa, 'REMINDER', 'waiting_approval');
        $this->photo($pending, $tech, 'pending');
        $this->post(route('login'), ['username' => $admin->username, 'password' => 'password'])->assertRedirect(route('dashboard'))->assertSessionHas('approval_reminder_user_id', $admin->getKey());
        $first = $this->get(route('dashboard'))->assertOk()->assertSee('id="admin-approval-reminder"', false)->assertSee('INC-REMINDER')->assertSee('Buka Approval Saya');
        $first->assertSessionMissing('approval_reminder_user_id');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="admin-approval-reminder"', false);
        $this->get(route('lop.my-approvals'))->assertOk()->assertDontSee('id="admin-approval-reminder"', false);
        $this->post(route('logout'));
        $this->withSession(['url.intended' => route('revenue.index')])->post(route('login'), ['username' => $admin->username, 'password' => 'password'])->assertRedirect(route('revenue.index'));
        $this->get(route('revenue.index'))->assertOk()->assertSee('id="admin-approval-reminder"', false);
    }

    public function test_popup_is_absent_for_no_work_wrong_account_completed_reject_only_and_nonadmin(): void
    {
        [$admin, $other, $tech, $sa] = $this->people();
        $done = $this->lop($admin, $tech, $sa, 'DONE', 'completed');
        $this->photo($done, $tech, 'pending');
        $reject = $this->lop($admin, $tech, $sa, 'FIX', 'rejected');
        $this->photo($reject, $tech, 'rejected');
        $letter = $this->lop($admin, $tech, $sa, 'LETTER', 'progress');
        $this->photo($letter, $tech, 'pending', 'request_letter');
        $this->assertNull(app(MyApprovalService::class)->reminder($admin));
        $this->post(route('login'), ['username' => $admin->username, 'password' => 'password']);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="admin-approval-reminder"', false)->assertSessionMissing('approval_reminder_user_id');
        $pending = $this->lop($admin, $tech, $sa, 'OTHER-REMINDER', 'waiting_approval');
        $this->photo($pending, $tech, 'pending');
        $this->actingAs($other)->withSession(['approval_reminder_user_id' => $admin->getKey()])->get(route('dashboard'))->assertOk()->assertDontSee('id="admin-approval-reminder"', false)->assertSessionMissing('approval_reminder_user_id');
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $this->actingAs($super)->withSession(['approval_reminder_user_id' => $super->getKey()])->get(route('dashboard'))->assertOk()->assertDontSee('id="admin-approval-reminder"', false)->assertDontSee('Approval Saya');
        $this->post(route('logout'));
        $this->post(route('login'), ['username' => $admin->username, 'password' => 'invalid-password'])->assertSessionHasErrors('username')->assertSessionMissing('approval_reminder_user_id');
    }

    public function test_scope_area_region_branch_and_multiple_service_areas_are_intersected_with_assigner(): void
    {
        [$admin, , $tech, $sa] = $this->people();
        $outsideRegion = Region::where('code', 'BALNUS')->firstOrFail();
        $outsideRegion->update(['area_id' => $sa->region->area_id]);
        $second = $this->location('DPS', 'DENPASAR', $outsideRegion->getKey());
        foreach ([AdminScopeType::AREA, AdminScopeType::REGION, AdminScopeType::BRANCH, AdminScopeType::SERVICE_AREA] as $scope) {
            $user = User::factory()->role('ADMIN')->create(['admin_scope_type' => $scope, 'area_id' => $scope === AdminScopeType::AREA ? $sa->region->area_id : null, 'region_id' => $scope === AdminScopeType::REGION ? $sa->region_id : null, 'branch_id' => $scope === AdminScopeType::BRANCH ? $admin->branch_id : null]);
            if ($scope === AdminScopeType::SERVICE_AREA) {
                $user->serviceAreas()->attach([$sa->getKey(), $second->getKey()]);
            }
            $own = $this->lop($user, $tech, $sa, $scope->value.'-OWN', 'waiting_approval');
            $this->photo($own, $tech, 'pending');
            $otherBranch = $this->lop($user, $tech, $second, $scope->value.'-SECOND', 'waiting_approval');
            $this->photo($otherBranch, $tech, 'pending');
            $expected = in_array($scope, [AdminScopeType::AREA, AdminScopeType::SERVICE_AREA], true) ? 2 : 1;
            $this->assertSame($expected, app(MyApprovalService::class)->stats($user)['pending_lops']);
            $this->actingAs($user)->get(route('lop.my-approvals'))->assertOk()->assertSee($own->incident)->assertViewHas('stats', fn ($stats) => $stats['pending_lops'] === $expected);
        }
        $unscoped = User::factory()->role('ADMIN')->create(['branch_id' => null, 'admin_scope_type' => null]);
        $this->assertSame(0, app(MyApprovalService::class)->stats($unscoped)['total_lops']);
    }

    public function test_access_permission_menu_position_and_ready_only_reminder(): void
    {
        [$admin, , $tech, $sa] = $this->people();
        $ready = $this->lop($admin, $tech, $sa, 'READY-ONLY', 'waiting_approval');
        $this->photo($ready, $tech, 'approved');
        $reminder = app(MyApprovalService::class)->reminder($admin);
        $this->assertSame(0, $reminder['stats']['pending_evidences']);
        $this->assertSame(1, $reminder['stats']['ready_lops']);
        $html = $this->actingAs($admin)->get(route('lop.my-approvals'))->assertOk()->getContent();
        $revenuePosition = strpos($html, 'Revenue Overview');
        $inboxPosition = strpos($html, 'Inbox');
        $importPosition = strpos($html, 'Bulk Import Data');
        $this->assertLessThan($inboxPosition, $revenuePosition);
        $this->assertLessThan($importPosition, $inboxPosition);
        $this->assertSame(1, preg_match_all('/>\s*Inbox\s*</', $html));
        foreach ([UserRole::SUPER_ADMIN, UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $this->actingAs(User::factory()->role($role->value)->create())->get(route('lop.my-approvals'))->assertForbidden();
        }
        $permission = Permission::where('code', 'approve_evidence')->firstOrFail();
        $admin->role->permissions()->detach($permission->getKey());
        $admin->unsetRelation('role');
        $this->actingAs($admin)->get(route('lop.my-approvals'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Approval Saya');
    }

    public function test_sidebar_badge_counts_lops_not_photos_and_updates_after_review(): void
    {
        [$admin, $other, $tech, $sa] = $this->people();
        $own = $this->lop($admin, $tech, $sa, 'BADGE-OWN', 'waiting_approval');
        $first = $this->photo($own, $tech, 'pending');
        $second = $this->photo($own, $tech, 'pending');
        $this->photo($own, $tech, 'pending', 'request_letter');
        $foreign = $this->lop($other, $tech, $sa, 'BADGE-OTHER', 'waiting_approval');
        $this->photo($foreign, $tech, 'pending');
        $done = $this->lop($admin, $tech, $sa, 'BADGE-DONE', 'completed');
        $this->photo($done, $tech, 'pending');
        $service = app(MyApprovalService::class);
        $this->assertSame(1, $service->pendingLopCount($admin));
        $this->assertSame($service->stats($admin)['pending_lops'], $service->pendingLopCount($admin));
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('data-my-approval-count', false)->assertSee('1 LOP menunggu review')->assertSee('bg-red-600');
        $this->get(route('lop.my-approvals', ['tab' => 'completed']))->assertOk()->assertSee('1 LOP menunggu review');
        $this->post(route('evidence-approval.approve', $first))->assertSessionHasNoErrors();
        $this->assertSame(1, $service->pendingLopCount($admin));
        $this->post(route('evidence-approval.approve', $second))->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertOk()->assertSee('0 LOP menunggu review');
        $this->assertSame(0, $service->pendingLopCount($admin));
        $this->assertSame(1, $service->stats($admin)['ready_lops']);
        $own->assignments()->update(['status' => 'replaced', 'unassigned_at' => now()]);
        $this->photo($own, $tech, 'pending');
        $this->assertSame(0, $service->pendingLopCount($admin));
        $this->actingAs(User::factory()->role('SUPER_ADMIN')->create())->get(route('dashboard'))->assertOk()->assertDontSee('data-my-approval-count', false);
    }

    private function people(): array
    {
        $area = Area::firstOrCreate(['code' => '3'], ['name' => 'Area 3', 'is_active' => true]);
        $region = Region::where('code', 'JATIM')->firstOrFail();
        $region->update(['area_id' => $area->getKey()]);
        $sa = $this->location('SDA', 'SIDOARJO', $region->getKey());
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $sa->branch_id, 'admin_scope_type' => 'branch']);
        $other = User::factory()->role('ADMIN')->create(['branch_id' => $sa->branch_id, 'admin_scope_type' => 'branch']);
        $tech = User::factory()->role('TEKNISI')->create(['branch_id' => $sa->branch_id]);

        return [$admin, $other, $tech, $sa];
    }

    private function location(string $code, string $name, int $regionId): ServiceArea
    {
        $branch = Branch::firstOrCreate(['code' => $code], ['name' => $name, 'region_id' => $regionId, 'region' => Region::findOrFail($regionId)->name, 'is_active' => true]);

        return ServiceArea::firstOrCreate(['workzone' => $code], ['name' => $name, 'branch_id' => $branch->getKey(), 'region_id' => $regionId, 'is_active' => true]);
    }

    private function lop(User $assigner, User $tech, ServiceArea $sa, string $code, string $status, ?User $creator = null): QeLop
    {
        $lop = QeLop::create(['ba_bongkar_required' => false, 'incident' => 'INC-'.$code, 'nama_lop' => 'LOP '.$code, 'program_type' => 'recovery', 'status_lop' => $status, 'sto' => $sa->workzone, 'branch' => $sa->branch->name, 'branch_id' => $sa->branch_id, 'service_area_id' => $sa->getKey(), 'segment' => ['odp'], 'created_by' => ($creator ?? $assigner)->getKey()]);
        $lop->assignments()->create(['technician_id' => $tech->getKey(), 'assigned_by' => $assigner->getKey(), 'status' => 'active', 'assigned_at' => now()]);

        return $lop;
    }

    private function photo(QeLop $lop, User $tech, string $status, string $category = 'after', int $daysAgo = 0): QeEvidence
    {
        return QeEvidence::create(['qe_lop_id' => $lop->getKey(), 'step' => 'AFTER', 'category' => $category, 'type' => 'PHOTO', 'file_path' => 'test/photo.jpg', 'uploaded_by' => $tech->getKey(), 'status' => $status, 'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)]);
    }
}
