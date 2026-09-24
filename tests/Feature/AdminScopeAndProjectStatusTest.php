<?php

namespace Tests\Feature;

use App\Enums\AdminScopeType;
use App\Enums\ProgramType;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\Role;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\LopService;
use App\Services\LopVisibilityService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScopeAndProjectStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_status_follows_program_and_assignment_lifecycle(): void
    {
        [$superAdmin, $technician, $locations] = $this->actorsAndLocations();
        $service = app(LopService::class);

        $preventive = $service->create($this->lopPayload('SCOPE-PREV', ProgramType::PREVENTIVE, $locations['sda']), $superAdmin);
        $relok = $service->create($this->lopPayload('SCOPE-RELOK', ProgramType::RELOK_UTILITAS, $locations['sda']), $superAdmin);
        $recovery = $service->create($this->lopPayload('SCOPE-REC', ProgramType::RECOVERY, $locations['sda']), $superAdmin);

        $this->assertSame(ProjectStatus::USULAN, $preventive->status_project);
        $this->assertSame(ProjectStatus::USULAN, $relok->status_project);
        $this->assertNull($recovery->status_project);

        $service->assign($preventive, $technician, $superAdmin);
        $this->assertSame(ProjectStatus::ON_GOING, $preventive->refresh()->status_project);

        $service->unassign($preventive, $superAdmin);
        $this->assertSame(ProjectStatus::USULAN, $preventive->refresh()->status_project);
    }

    public function test_area_region_branch_and_multiple_service_area_scopes_are_enforced(): void
    {
        [$superAdmin, , $locations] = $this->actorsAndLocations();
        $service = app(LopService::class);
        $visibility = app(LopVisibilityService::class);

        $sda = $service->create($this->lopPayload('SCOPE-SDA', ProgramType::PREVENTIVE, $locations['sda']), $superAdmin);
        $sby = $service->create($this->lopPayload('SCOPE-SBY', ProgramType::PREVENTIVE, $locations['sby']), $superAdmin);
        $dps = $service->create($this->lopPayload('SCOPE-DPS', ProgramType::PREVENTIVE, $locations['dps']), $superAdmin);

        $areaAdmin = $this->admin(['admin_scope_type' => 'area', 'area_id' => $locations['area']->id_area]);
        $regionAdmin = $this->admin(['admin_scope_type' => 'region', 'region_id' => $locations['jatim']->id_region]);
        $branchAdmin = $this->admin(['admin_scope_type' => 'branch', 'branch_id' => $locations['sda']->branch_id]);
        $serviceAdmin = $this->admin(['admin_scope_type' => 'service_area', 'service_area_id' => $locations['sda']->id_service_area]);
        $serviceAdmin->serviceAreas()->sync([$locations['sda']->id_service_area, $locations['dps']->id_service_area]);

        $this->assertEqualsCanonicalizing([$sda->getKey(), $sby->getKey(), $dps->getKey()], $visibility->apply(QeLop::query(), $areaAdmin)->pluck('id_qe_lops')->all());
        $this->assertEqualsCanonicalizing([$sda->getKey(), $sby->getKey()], $visibility->apply(QeLop::query(), $regionAdmin)->pluck('id_qe_lops')->all());
        $this->assertSame([$sda->getKey()], $visibility->apply(QeLop::query(), $branchAdmin)->pluck('id_qe_lops')->all());
        $this->assertEqualsCanonicalizing([$sda->getKey(), $dps->getKey()], $visibility->apply(QeLop::query(), $serviceAdmin)->pluck('id_qe_lops')->all());
    }

    public function test_user_service_normalizes_multiple_service_area_scope(): void
    {
        [$superAdmin, , $locations] = $this->actorsAndLocations();
        $adminRole = Role::query()->where('code', UserRole::ADMIN->value)->firstOrFail();

        $admin = app(UserService::class)->create([
            'role_id' => $adminRole->id,
            'name' => 'Admin Multi SA',
            'username' => 'admin-multi-sa',
            'password' => 'password123',
            'status' => 'active',
            'admin_scope_type' => AdminScopeType::SERVICE_AREA->value,
            'service_area_ids' => [$locations['sda']->id_service_area, $locations['dps']->id_service_area],
        ], $superAdmin);

        $this->assertSame(AdminScopeType::SERVICE_AREA, $admin->admin_scope_type);
        $this->assertNull($admin->branch_id, 'Branch induk harus null ketika Service Area lintas Branch.');
        $this->assertEqualsCanonicalizing(
            [$locations['sda']->id_service_area, $locations['dps']->id_service_area],
            $admin->serviceAreas()->pluck('service_areas.id_service_area')->all(),
        );
    }

    public function test_program_tabs_separate_usulan_and_on_going(): void
    {
        [$superAdmin, $technician, $locations] = $this->actorsAndLocations();
        $service = app(LopService::class);
        $usulan = $service->create($this->lopPayload('TAB-USULAN', ProgramType::PREVENTIVE, $locations['sda']), $superAdmin);
        $ongoing = $service->create($this->lopPayload('TAB-ONGOING', ProgramType::PREVENTIVE, $locations['sda']), $superAdmin);
        $service->assign($ongoing, $technician, $superAdmin);

        $this->actingAs($superAdmin)
            ->get(route('program.show', ProgramType::PREVENTIVE->value))
            ->assertOk()
            ->assertSee($usulan->incident)
            ->assertDontSee($ongoing->incident)
            ->assertSee('Usulan')
            ->assertSee('On Going');

        $this->get(route('program.show', [ProgramType::PREVENTIVE->value, 'project_status' => ProjectStatus::ON_GOING->value]))
            ->assertOk()
            ->assertSee($ongoing->incident)
            ->assertDontSee($usulan->incident);
    }

    private function actorsAndLocations(): array
    {
        $area = Area::create(['code' => '3', 'name' => 'Area 3', 'is_active' => true]);
        $jatim = Region::query()->where('code', 'JATIM')->firstOrFail();
        $balnus = Region::query()->where('code', 'BALNUS')->firstOrFail();
        $jatim->update(['area_id' => $area->id_area]);
        $balnus->update(['area_id' => $area->id_area]);

        $sdaBranch = Branch::create(['code' => 'SDA-X', 'name' => 'SIDOARJO X', 'region' => $jatim->name, 'region_id' => $jatim->id_region, 'is_active' => true]);
        $sbyBranch = Branch::create(['code' => 'SBY-X', 'name' => 'SURABAYA X', 'region' => $jatim->name, 'region_id' => $jatim->id_region, 'is_active' => true]);
        $dpsBranch = Branch::create(['code' => 'DPS-X', 'name' => 'DENPASAR X', 'region' => $balnus->name, 'region_id' => $balnus->id_region, 'is_active' => true]);

        $sda = ServiceArea::create(['workzone' => 'SDX', 'name' => 'Sidoarjo X', 'branch_id' => $sdaBranch->id_branch, 'region_id' => $jatim->id_region, 'is_active' => true]);
        $sby = ServiceArea::create(['workzone' => 'SBX', 'name' => 'Surabaya X', 'branch_id' => $sbyBranch->id_branch, 'region_id' => $jatim->id_region, 'is_active' => true]);
        $dps = ServiceArea::create(['workzone' => 'DPX', 'name' => 'Denpasar X', 'branch_id' => $dpsBranch->id_branch, 'region_id' => $balnus->id_region, 'is_active' => true]);

        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create(['branch_id' => $sdaBranch->id_branch]);

        return [$superAdmin, $technician, compact('area', 'jatim', 'balnus', 'sda', 'sby', 'dps')];
    }

    private function admin(array $attributes): User
    {
        return User::factory()->role(UserRole::ADMIN->value)->create($attributes);
    }

    private function lopPayload(string $incident, ProgramType $program, ServiceArea $serviceArea): array
    {
        return [
            'incident' => $incident,
            'nama_lop' => "Project {$incident}",
            'program_type' => $program->value,
            'sto' => $serviceArea->workzone,
            'branch' => $serviceArea->branch->name,
            'branch_id' => $serviceArea->branch_id,
            'service_area_id' => $serviceArea->id_service_area,
            'area' => '3',
            'segment' => ['odp'],
            'budget_type' => $program === ProgramType::RELOK_UTILITAS ? 'CAPEX' : null,
            'job_description' => "Pekerjaan {$incident}",
        ];
    }
}
