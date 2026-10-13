<?php

namespace Tests\Feature;

use App\Enums\AdminScopeType;
use App\Enums\LopStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeBoq;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\BoqService;
use App\Services\LopBoqProjectionService;
use App\Services\LopService;
use App\Services\ReservationBoqService;
use App\Services\RevenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationBoqManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_remove_and_adjust_reserved_qty_without_plan_or_actual_loss(): void
    {
        [$admin, $tech, $lop, $keep, $remove, $new] = $this->fixture();
        $reservation = $lop->materialReservation;
        $existing = $reservation->items->firstWhere('designator_id', $keep->getKey());
        $existingId = $existing->getKey();
        $version = ReservationBoqService::version($reservation);
        $this->actingAs($admin)->get(route('data-boqs.index'))->assertOk()
            ->assertSee('BOQ Aktual dari Reservasi Teknisi')->assertSee('Edit BOQ Aktual')->assertSee('Cari kode atau nama designator')->assertSee('Ya, hapus')->assertSee('Ya, simpan')
            ->assertSee(route('data-boqs.reservations.update', $lop, false), false);
        $this->from(route('data-boqs.index'))->put(route('data-boqs.reservations.update', $lop), [
            'reservation_version' => $version, 'confirmed' => 1,
            'items' => [['designator_id' => $keep->getKey(), 'qty' => 12, 'qty_actual' => 999], ['designator_id' => $new->getKey(), 'qty' => 4]],
        ])->assertRedirect(route('data-boqs.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('qe_material_reservation_items', ['id_reservation_item' => $existingId, 'qty' => 12, 'qty_actual' => 7]);
        $this->assertDatabaseMissing('qe_material_reservation_items', ['reservation_id' => $reservation->getKey(), 'designator_id' => $remove->getKey()]);
        $this->assertDatabaseHas('qe_material_reservation_items', ['reservation_id' => $reservation->getKey(), 'designator_id' => $new->getKey(), 'qty' => 4, 'qty_actual' => null]);
        $this->assertDatabaseMissing('qe_boqs', ['qe_lop_id' => $lop->getKey()]);
        $this->assertSame($tech->getKey(), $reservation->fresh()->technician_id);
        $this->assertSame(LopStatus::PROGRESS, $lop->fresh()->status_lop);
        $history = $lop->histories()->where('event_type', 'reservation_updated')->firstOrFail();
        $this->assertSame($admin->getKey(), $history->user_id);
        $this->assertSame('progress', $history->status_before);
        $this->assertSame('progress', $history->status_after);
        $this->assertSame(7, $history->meta['after']['items'][0]['qty_actual']);
        $this->assertNotSame($version, ReservationBoqService::version($reservation->fresh()));

        $added = $reservation->fresh()->items()->where('designator_id', $new->getKey())->firstOrFail();
        $added->update(['qty_actual' => 2]);
        $actual = app(LopBoqProjectionService::class)->report($lop->fresh(), 'actual');
        $this->assertFalse($actual['has_plan']);
        $this->assertSame(['J-KEEP', 'J-NEW', 'M-KEEP', 'M-NEW'], collect($actual['lines'])->pluck('designator_code')->sort()->values()->all());
        $this->assertSame(1610.0, $actual['grand']['total_actual']);
        $this->actingAs($tech)->get(route('technician.projects.show', ['qe_lop' => $lop, 'step' => 1]))->assertOk()->assertSee('M-NEW');
    }

    public function test_items_with_actual_including_zero_or_evidence_cannot_be_deleted_and_transaction_rolls_back(): void
    {
        foreach (['actual', 'zero', 'evidence'] as $protection) {
            [$admin, $tech, $lop, $keep, $remove] = $this->fixture($protection);
            $item = $lop->materialReservation->items()->where('designator_id', $remove->getKey())->firstOrFail();
            if ($protection === 'actual' || $protection === 'zero') {
                $item->update(['qty_actual' => $protection === 'actual' ? 1 : 0]);
            } else {
                QeEvidence::create(['qe_lop_id' => $lop->getKey(), 'step' => 'PROGRESS', 'category' => 'progress', 'type' => 'PHOTO', 'designator_id' => $remove->getKey(), 'file_path' => 'test/photo.jpg', 'uploaded_by' => $tech->getKey(), 'status' => 'pending']);
            }
            $this->actingAs($admin)->put(route('data-boqs.reservations.update', $lop), [
                'reservation_version' => ReservationBoqService::version($lop->fresh()->materialReservation), 'confirmed' => 1,
                'items' => [['designator_id' => $keep->getKey(), 'qty' => 20]],
            ])->assertSessionHasErrors('items');
            $this->assertDatabaseHas('qe_material_reservation_items', ['reservation_id' => $lop->materialReservation->getKey(), 'designator_id' => $keep->getKey(), 'qty' => 10, 'qty_actual' => 7]);
            $this->assertNotNull($item->fresh());
            $this->assertSame(0, $lop->histories()->where('event_type', 'reservation_updated')->count());
        }
    }

    public function test_scope_is_enforced_for_area_region_branch_multi_service_area_and_superadmin(): void
    {
        [$admin, , $lop] = $this->fixture();
        $branch = $admin->branch;
        $region = $branch->regionRef;
        $sa = $lop->serviceArea;
        foreach ([AdminScopeType::AREA, AdminScopeType::REGION, AdminScopeType::BRANCH, AdminScopeType::SERVICE_AREA] as $scope) {
            $user = User::factory()->role(UserRole::ADMIN->value)->create(['admin_scope_type' => $scope, 'area_id' => $scope === AdminScopeType::AREA ? $region->area_id : null, 'region_id' => $scope === AdminScopeType::REGION ? $region->getKey() : null, 'branch_id' => $scope === AdminScopeType::BRANCH ? $branch->getKey() : null]);
            if ($scope === AdminScopeType::SERVICE_AREA) {
                $user->serviceAreas()->attach($sa);
            }
            $this->actingAs($user)->put(route('data-boqs.reservations.update', $lop), $this->payload($lop))->assertRedirect()->assertSessionHasNoErrors();
        }
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $this->actingAs($super)->put(route('data-boqs.reservations.update', $lop), $this->payload($lop))->assertRedirect()->assertSessionHasNoErrors();
        $outside = User::factory()->role(UserRole::ADMIN->value)->create(['admin_scope_type' => 'branch', 'branch_id' => null]);
        $this->actingAs($outside)->put(route('data-boqs.reservations.update', $lop), $this->payload($lop))->assertForbidden();
        $this->get(route('data-boqs.index'))->assertOk()->assertDontSee('Edit BOQ Aktual')->assertDontSee($lop->incident);
        foreach ([UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $user = User::factory()->role($role->value)->create();
            $this->actingAs($user)->put(route('data-boqs.reservations.update', $lop), $this->payload($lop))->assertForbidden();
        }
    }

    public function test_waiting_approval_is_editable_but_completed_requires_reset_and_plan_has_own_editor(): void
    {
        [$admin, , $lop] = $this->fixture();
        $lop->update(['status_lop' => 'waiting_approval']);
        $this->actingAs($admin)->get(route('data-boqs.index'))->assertOk()->assertSee('Edit BOQ Aktual')->assertDontSee('Reset LOP untuk edit BOQ');
        $this->put(route('data-boqs.reservations.update', $lop), $this->payload($lop))->assertSessionHasNoErrors();
        $this->assertSame(LopStatus::WAITING_APPROVAL, $lop->fresh()->status_lop);
        $lop->update(['status_lop' => 'completed']);
        $this->get(route('data-boqs.index'))->assertOk()->assertDontSee('Edit BOQ Aktual')->assertSee('Terkunci')->assertSee('Reset LOP untuk edit BOQ')->assertSee('Ya, reset');
        $this->put(route('data-boqs.reservations.update', $lop), $this->payload($lop))->assertSessionHasErrors('items');
        $historyCount = $lop->histories()->where('event_type', 'reservation_updated')->count();
        $lop->update(['status_lop' => 'progress']);
        QeBoq::create(['qe_lop_id' => $lop->getKey(), 'package_id' => $lop->package_id, 'status' => 'ready', 'source' => 'manual', 'item_count' => 0, 'grand_total' => 0, 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey()]);
        $this->put(route('data-boqs.reservations.update', $lop), $this->payload($lop))->assertSessionHasErrors('items');
        $this->assertSame($historyCount, $lop->histories()->where('event_type', 'reservation_updated')->count());
    }

    public function test_adding_material_during_review_reopens_progress_and_blocks_stale_completion(): void
    {
        [$admin, $tech, $lop, , , $new] = $this->fixture();
        $lop->update(['status_lop' => 'waiting_approval']);
        $photo = QeEvidence::create(['qe_lop_id' => $lop->getKey(), 'step' => 'AFTER', 'category' => 'after', 'type' => 'PHOTO', 'file_path' => 'test/photo.jpg', 'uploaded_by' => $tech->getKey(), 'status' => 'approved']);
        $payload = $this->payload($lop);
        $payload['items'][] = ['designator_id' => $new->getKey(), 'qty' => 4];
        $this->actingAs($admin)->put(route('data-boqs.reservations.update', $lop), $payload)->assertSessionHasNoErrors();
        $this->assertSame(LopStatus::PROGRESS, $lop->fresh()->status_lop);
        $this->assertSame('approved', $photo->fresh()->status->value);
        $this->post(route('evidence-approval.lop.complete', $lop))->assertSessionHasErrors('review');
        $this->assertDatabaseHas('qe_lop_histories', ['qe_lop_id' => $lop->getKey(), 'status_before' => 'waiting_approval', 'status_after' => 'progress']);
    }

    public function test_reset_is_confirmed_audited_preserves_data_and_removes_revenue_until_completed_again(): void
    {
        [$admin, $tech, $lop, $keep] = $this->fixture();
        $lop->update(['status_lop' => 'completed']);
        $reservation = $lop->materialReservation;
        $reservation->update(['status' => 'submitted', 'submitted_at' => now()]);
        $photo = QeEvidence::create(['qe_lop_id' => $lop->getKey(), 'step' => 'AFTER', 'category' => 'after', 'type' => 'PHOTO', 'designator_id' => $keep->getKey(), 'file_path' => 'test/photo.jpg', 'uploaded_by' => $tech->getKey(), 'status' => 'approved', 'reviewed_by' => $admin->getKey(), 'reviewed_at' => now()]);
        $items = $reservation->items()->get()->toArray();
        $revenue = app(RevenueService::class);
        $this->assertGreaterThan(0, $revenue->dashboard($admin, [])['stats']['actual_value']);
        $this->actingAs($admin)->post(route('data-boqs.reset', $lop), [])->assertSessionHasErrors('confirmed');
        $this->assertSame(LopStatus::COMPLETED, $lop->fresh()->status_lop);
        $this->post(route('lop.transition', $lop), ['status' => 'progress'])->assertForbidden();
        $this->from(route('data-boqs.index', ['q' => $lop->incident]))->post(route('data-boqs.reset', $lop), ['confirmed' => 1])->assertRedirect(route('data-boqs.index', ['q' => $lop->incident]))->assertSessionHasNoErrors();
        $this->assertSame(LopStatus::PROGRESS, $lop->fresh()->status_lop);
        $this->assertSame($items, $reservation->items()->get()->toArray());
        $this->assertSame('draft', $reservation->fresh()->status);
        $this->assertSame('approved', $photo->fresh()->status->value);
        $this->assertSame('test/photo.jpg', $photo->fresh()->file_path);
        $this->assertSame($tech->getKey(), $lop->fresh()->activeAssignment->technician_id);
        $this->assertSame(0.0, $revenue->dashboard($admin, [])['stats']['actual_value']);
        $this->assertDatabaseHas('qe_lop_histories', ['qe_lop_id' => $lop->getKey(), 'user_id' => $admin->getKey(), 'event_type' => 'status_change', 'status_before' => 'completed', 'status_after' => 'progress']);
        $this->post(route('data-boqs.reset', $lop), ['confirmed' => 1])->assertSessionHasErrors('boq');
        $this->get(route('data-boqs.index'))->assertOk()->assertSee('Edit BOQ Aktual')->assertDontSee('Reset LOP untuk edit BOQ');
        $this->put(route('data-boqs.reservations.update', $lop), $this->payload($lop))->assertSessionHasNoErrors();
        app(LopService::class)->transitionStatus($lop->fresh(), LopStatus::WAITING_APPROVAL, $tech);
        $this->post(route('evidence-approval.lop.complete', $lop))->assertSessionHasNoErrors();
        $this->assertGreaterThan(0, $revenue->dashboard($admin, [])['stats']['actual_value']);
    }

    public function test_reset_enforces_admin_scope_and_rejects_technician_manager_and_approver(): void
    {
        [$admin, $tech, $lop] = $this->fixture();
        $lop->update(['status_lop' => 'completed']);
        foreach ([UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $user = $role === UserRole::TEKNISI ? $tech : User::factory()->role($role->value)->create();
            $this->actingAs($user)->post(route('data-boqs.reset', $lop), ['confirmed' => 1])->assertForbidden();
        }
        $outside = User::factory()->role(UserRole::ADMIN->value)->create(['admin_scope_type' => 'branch', 'branch_id' => null]);
        $this->actingAs($outside)->post(route('data-boqs.reset', $lop), ['confirmed' => 1])->assertForbidden();
        foreach ([AdminScopeType::AREA, AdminScopeType::REGION, AdminScopeType::BRANCH, AdminScopeType::SERVICE_AREA] as $scope) {
            $user = User::factory()->role(UserRole::ADMIN->value)->create(['admin_scope_type' => $scope, 'area_id' => $scope === AdminScopeType::AREA ? $lop->serviceArea->region->area_id : null, 'region_id' => $scope === AdminScopeType::REGION ? $lop->serviceArea->region_id : null, 'branch_id' => $scope === AdminScopeType::BRANCH ? $admin->branch_id : null]);
            if ($scope === AdminScopeType::SERVICE_AREA) {
                $user->serviceAreas()->attach($lop->service_area_id);
            }
            $lop->refresh()->update(['status_lop' => 'completed']);
            $this->actingAs($user)->post(route('data-boqs.reset', $lop), ['confirmed' => 1])->assertSessionHasNoErrors();
            $this->assertSame(LopStatus::PROGRESS, $lop->fresh()->status_lop);
        }
        $lop->refresh()->update(['status_lop' => 'completed']);
        $this->actingAs(User::factory()->role(UserRole::SUPER_ADMIN->value)->create())->post(route('data-boqs.reset', $lop), ['confirmed' => 1])->assertSessionHasNoErrors();
    }

    public function test_plan_editor_allows_waiting_approval_and_reset_does_not_change_plan_snapshot(): void
    {
        [$admin, , $lop, $keep, , $new] = $this->fixture();
        $lop->update(['program_type' => 'preventive', 'status_project' => 'on_going']);
        $service = app(BoqService::class);
        $boq = $service->save($lop, [['designator_id' => $keep->getKey(), 'qty' => 10, 'unit_price' => 100]], $lop->package, $admin);
        $lop->update(['status_lop' => 'waiting_approval']);
        $this->actingAs($admin)->get(route('data-boqs.index'))->assertOk()->assertSee('Kelola item BOQ')->assertDontSee('Terkunci');
        $this->put(route('data-boqs.update', $boq), ['package_id' => $lop->package_id, 'items' => [['designator_id' => $keep->getKey(), 'qty' => 12, 'unit_price' => 100]]])->assertSessionHasNoErrors();
        $this->assertSame(LopStatus::WAITING_APPROVAL, $lop->fresh()->status_lop);
        $lop->update(['status_lop' => 'completed']);
        $snapshot = $lop->fresh()->boq_snapshot;
        $this->put(route('data-boqs.update', $boq), ['items' => [['designator_id' => $keep->getKey(), 'qty' => 14]]])->assertSessionHasErrors('boq');
        $this->post(route('data-boqs.reset', $lop), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame($snapshot, $lop->fresh()->boq_snapshot);
        $this->assertSame('on_going', $lop->fresh()->status_project->value);
        $lop->update(['status_lop' => 'waiting_approval']);
        $this->put(route('data-boqs.update', $boq), ['items' => [['designator_id' => $keep->getKey(), 'qty' => 12], ['designator_id' => $new->getKey(), 'qty' => 2]]])->assertSessionHasNoErrors();
        $this->assertSame(LopStatus::PROGRESS, $lop->fresh()->status_lop);
    }

    public function test_stale_editor_cannot_overwrite_technician_recap_or_reservation_changes(): void
    {
        [$admin, , $lop, $keep] = $this->fixture();
        $payload = $this->payload($lop);
        $lop->materialReservation->items()->where('designator_id', $keep->getKey())->update(['qty_actual' => 8]);
        $this->actingAs($admin)->put(route('data-boqs.reservations.update', $lop), $payload)->assertSessionHasErrors('items');
        $this->assertDatabaseHas('qe_material_reservation_items', ['reservation_id' => $lop->materialReservation->getKey(), 'designator_id' => $keep->getKey(), 'qty_actual' => 8]);
        $this->assertSame(0, $lop->histories()->where('event_type', 'reservation_updated')->count());
    }

    public function test_validation_confirmation_duplicate_non_material_and_qty_below_usage_are_rejected(): void
    {
        [$admin, , $lop, $keep, , $new] = $this->fixture();
        $this->actingAs($admin);
        $payload = $this->payload($lop);
        $service = Designator::where('code', 'J-NEW')->firstOrFail();
        foreach ([
            [...$payload, 'confirmed' => 0],
            [...$payload, 'items' => []],
            [...$payload, 'items' => [['designator_id' => $keep->getKey(), 'qty' => 3.5]]],
            [...$payload, 'items' => [['designator_id' => $keep->getKey(), 'qty' => 10], ['designator_id' => $keep->getKey(), 'qty' => 1]]],
            [...$payload, 'items' => [['designator_id' => $keep->getKey(), 'qty' => 1]]],
            [...$payload, 'items' => [...$payload['items'], ['designator_id' => $service->getKey(), 'qty' => 1]]],
        ] as $invalid) {
            $this->put(route('data-boqs.reservations.update', $lop), $invalid)->assertSessionHasErrors();
        }
        $new->delete();
        $this->put(route('data-boqs.reservations.update', $lop), [...$payload, 'items' => [...$payload['items'], ['designator_id' => $new->getKey(), 'qty' => 1]]])->assertSessionHasErrors('items');
        $this->assertSame(0, $lop->histories()->where('event_type', 'reservation_updated')->count());
    }

    private function payload(QeLop $lop): array
    {
        $reservation = $lop->fresh()->materialReservation;

        return ['reservation_version' => ReservationBoqService::version($reservation), 'confirmed' => 1, 'items' => $reservation->items->map(fn ($item) => ['designator_id' => $item->designator_id, 'qty' => (int) $item->qty])->all()];
    }

    private function fixture(string $suffix = ''): array
    {
        $area = Area::firstOrCreate(['code' => '3'], ['name' => 'Area 3']);
        $region = Region::where('code', 'JATIM')->firstOrFail();
        $region->update(['area_id' => $area->getKey()]);
        $branch = Branch::firstOrCreate(['code' => 'SDA'], ['name' => 'SIDOARJO', 'region_id' => $region->getKey(), 'region' => 'REGION JATIM']);
        $sa = ServiceArea::firstOrCreate(['workzone' => 'SDA'], ['name' => 'SIDOARJO', 'branch_id' => $branch->getKey(), 'region_id' => $region->getKey()]);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->getKey(), 'admin_scope_type' => 'branch']);
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create(['branch_id' => $branch->getKey()]);
        $materialType = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material']);
        $serviceType = DesignatorType::firstOrCreate(['code' => 'JASA'], ['name' => 'Jasa']);
        $package = Package::firstOrCreate(['code' => 'Paket-5'], ['name' => 'Paket 5']);
        foreach (['KEEP', 'REMOVE', 'NEW'] as $code) {
            foreach ([['M', $materialType, $code === 'NEW' ? 200 : 100], ['J', $serviceType, $code === 'NEW' ? 80 : 50]] as [$prefix, $type, $price]) {
                $designator = Designator::firstOrCreate(['code' => $prefix.'-'.$code], ['item_name' => $prefix.' item '.$code, 'unit' => 'unit', 'designator_type_id' => $type->getKey()]);
                DesignatorPackagePrice::firstOrCreate(['package_id' => $package->getKey(), 'designator_id' => $designator->getKey()], ['price' => $price]);
            }
        }
        [$keep, $remove, $new] = array_map(fn ($code) => Designator::where('code', 'M-'.$code)->firstOrFail(), ['KEEP', 'REMOVE', 'NEW']);
        $lop = QeLop::create(['incident' => 'INC-RESERVATION-'.$suffix, 'nama_lop' => 'LOP reservasi '.$suffix, 'program_type' => 'recovery', 'status_lop' => 'progress', 'branch' => $branch->name, 'branch_id' => $branch->getKey(), 'service_area_id' => $sa->getKey(), 'sto' => $sa->workzone, 'area' => '3', 'segment' => ['odp'], 'package_id' => $package->getKey(), 'created_by' => $admin->getKey()]);
        $lop->assignments()->create(['technician_id' => $tech->getKey(), 'assigned_by' => $admin->getKey(), 'status' => 'active', 'assigned_at' => now()]);
        $reservation = $lop->materialReservation()->create(['technician_id' => $tech->getKey(), 'status' => 'draft']);
        $reservation->items()->createMany([['designator_id' => $keep->getKey(), 'qty' => 10, 'qty_actual' => 7], ['designator_id' => $remove->getKey(), 'qty' => 5]]);

        return [$admin, $tech, $lop->fresh(), $keep, $remove, $new];
    }
}
