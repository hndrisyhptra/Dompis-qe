<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LopReturnNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_lop_returns_to_fresh_input_form_for_admin_and_superadmin(): void
    {
        [$admin, , $lop] = $this->fixture();
        foreach ([$admin, User::factory()->role(UserRole::SUPER_ADMIN->value)->create()] as $index => $user) {
            $this->actingAs($user)->from(route('lop.create'))->post(route('lop.store'), [
                ...$this->payload($lop), 'incident' => 'INC-NEW-'.$index, 'nama_lop' => '',
            ])->assertRedirect(route('lop.create'))->assertSessionHasNoErrors()->assertSessionHas('status');
            $this->get(route('lop.create'))->assertOk()->assertSee('Simpan LOP')->assertDontSee('INC-NEW-'.$index);
        }
    }

    public function test_edit_returns_to_original_list_with_filters_and_superadmin_legacy_routes_do_not_open_approval(): void
    {
        [$admin, , $lop] = $this->fixture();
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        foreach ([$admin, $super] as $user) {
            $origin = route('data-lops.index', ['q' => $lop->incident, 'page' => 2]);
            $this->actingAs($user)->get(route('lop.edit', [$lop, 'return_url' => $origin]))->assertOk()->assertSee('name="return_url"', false)->assertSee($origin);
            $this->put(route('lop.update', $lop), [...$this->payload($lop), 'return_url' => $origin])->assertRedirect($origin)->assertSessionHasNoErrors();
            $this->from($origin)->get(route('lop.show', $lop))->assertRedirect($origin);
        }
        $this->actingAs($super)->get(route('lop.index'))->assertRedirect(route('data-lops.index'));
        $this->get(route('lop.history'))->assertRedirect(route('data-lops.index', ['status' => 'completed']));
    }

    public function test_assign_and_unassign_preserve_program_filters(): void
    {
        [$admin, $tech, $lop] = $this->fixture();
        $origin = route('program.show', ['recovery', 'bucket' => 'assigned', 'q' => $lop->incident]);
        $this->actingAs($admin)->from($origin)->post(route('lop.assign', $lop), [
            'technician_id' => $tech->getKey(), 'return_to' => 'program:recovery', 'return_url' => $origin,
        ])->assertRedirect($origin)->assertSessionHasNoErrors();
        $this->delete(route('lop.unassign', $lop), ['return_url' => $origin])->assertRedirect($origin)->assertSessionHasNoErrors();
    }

    public function test_review_steps_and_completion_keep_origin_and_direct_approval_keeps_its_filters(): void
    {
        [$admin, $tech, $lop] = $this->fixture();
        $lop->update(['status_lop' => 'waiting_approval']);
        QeEvidence::create(['qe_lop_id' => $lop->getKey(), 'step' => 'AFTER', 'category' => 'after', 'type' => 'PHOTO', 'file_path' => 'test/photo.jpg', 'uploaded_by' => $tech->getKey(), 'status' => 'approved']);
        $origin = route('lop.index', ['program' => 'recovery', 'q' => $lop->incident]);
        $this->actingAs($admin)->get(route('evidence-approval.lop.review', [$lop, 'step' => 5, 'return_url' => $origin]))->assertOk()->assertSee('Kembali ke halaman asal')->assertSee('name="return_url"', false)->assertSee($origin);
        $this->post(route('evidence-approval.lop.complete', $lop), ['return_url' => $origin])->assertRedirect($origin)->assertSessionHasNoErrors();
        $approvalOrigin = route('evidence-approval.index', ['status' => 'approved']);
        $this->from($approvalOrigin)->get(route('evidence-approval.lop.review', [$lop, 'step' => 2]))->assertOk()->assertViewHas('returnUrl', $approvalOrigin);
    }

    public function test_return_destinations_reject_external_urls_actions_and_unknown_pages(): void
    {
        [$admin, , $lop] = $this->fixture();
        $this->actingAs($admin);
        foreach (['https://evil.example/master-data/lops', '//evil.example/master-data/lops', '/\\evil.example', '/login', '/lop/999', 'javascript:alert(1)', '/program/unknown', '/master-data/lops#fragment', ['invalid']] as $invalid) {
            $this->put(route('lop.update', $lop), [...$this->payload($lop), 'return_url' => $invalid])->assertRedirect(route('lop.index'))->assertSessionHasNoErrors();
        }
        $origin = route('program.show', ['recovery', 'q' => 'INC-NAV']);
        $relative = parse_url($origin, PHP_URL_PATH).'?'.parse_url($origin, PHP_URL_QUERY);
        $this->put(route('lop.update', $lop), [...$this->payload($lop), 'return_url' => $relative])->assertRedirect($origin);
        $this->from($origin)->put(route('lop.update', $lop), $this->payload($lop))->assertRedirect($origin);
    }

    private function payload(QeLop $lop): array
    {
        return ['incident' => $lop->incident, 'nama_lop' => $lop->nama_lop, 'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'segment' => ['odp'], 'program_type' => 'recovery', 'job_description' => 'Pekerjaan test'];
    }

    private function fixture(): array
    {
        $area = Area::firstOrCreate(['code' => '3'], ['name' => 'Area 3', 'is_active' => true]);
        $region = Region::where('code', 'JATIM')->firstOrFail();
        $region->update(['area_id' => $area->getKey()]);
        $branch = Branch::firstOrCreate(['code' => 'SDA'], ['name' => 'SIDOARJO', 'region_id' => $region->getKey(), 'region' => 'REGION JATIM', 'is_active' => true]);
        $sa = ServiceArea::firstOrCreate(['workzone' => 'SDA'], ['name' => 'SIDOARJO', 'branch_id' => $branch->getKey(), 'region_id' => $region->getKey(), 'is_active' => true]);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->getKey(), 'admin_scope_type' => 'branch']);
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create(['branch_id' => $branch->getKey()]);
        $lop = QeLop::create(['incident' => 'INC-NAV', 'nama_lop' => 'Navigasi LOP', 'program_type' => 'recovery', 'status_lop' => 'draft', 'branch' => $branch->name, 'branch_id' => $branch->getKey(), 'service_area_id' => $sa->getKey(), 'sto' => $sa->workzone, 'area' => '3', 'segment' => ['odp'], 'job_description' => 'Pekerjaan test', 'created_by' => $admin->getKey()]);

        return [$admin, $tech, $lop];
    }
}
