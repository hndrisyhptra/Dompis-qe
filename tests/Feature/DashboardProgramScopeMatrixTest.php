<?php

namespace Tests\Feature;

use App\Enums\LopStatus;
use App\Enums\ProgramType;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardProgramScopeMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function locations(): array
    {
        $area = Area::create(['code' => 'M3', 'name' => 'Matrix Area 3']);
        $outsideArea = Area::create(['code' => 'M4', 'name' => 'Matrix Area 4']);
        $jatim = Region::create(['code' => 'MJTM', 'name' => 'Matrix JATIM', 'area_id' => $area->getKey()]);
        $balnus = Region::create(['code' => 'MBAL', 'name' => 'Matrix BALNUS', 'area_id' => $area->getKey()]);
        $emptyRegion = Region::create(['code' => 'MEMPTY', 'name' => 'Region Tanpa LOP', 'area_id' => $area->getKey()]);
        $outsideRegion = Region::create(['code' => 'MOUT', 'name' => 'Region Di Luar Scope', 'area_id' => $outsideArea->getKey()]);
        $sby = Branch::create(['code' => 'MSBY', 'name' => 'Matrix Surabaya', 'region_id' => $jatim->getKey(), 'region' => $jatim->name]);
        $sda = Branch::create(['code' => 'MSDA', 'name' => 'Matrix Sidoarjo', 'region_id' => $jatim->getKey(), 'region' => $jatim->name]);
        $emptyBranch = Branch::create(['code' => 'MEMP', 'name' => 'Branch Tanpa LOP', 'region_id' => $jatim->getKey(), 'region' => $jatim->name]);
        $dps = Branch::create(['code' => 'MDPS', 'name' => 'Matrix Denpasar', 'region_id' => $balnus->getKey(), 'region' => $balnus->name]);
        $outsideBranch = Branch::create(['code' => 'MOUT', 'name' => 'Branch Di Luar Scope', 'region_id' => $outsideRegion->getKey(), 'region' => $outsideRegion->name]);
        $serviceAreas = [];
        foreach (['SBY-A' => $sby, 'SBY-B' => $sby, 'SBY-ZERO' => $sby, 'SDA-A' => $sda, 'DPS-A' => $dps, 'OUT-A' => $outsideBranch] as $workzone => $branch) {
            $serviceAreas[$workzone] = ServiceArea::create(['workzone' => $workzone, 'name' => 'Service Area '.$workzone, 'branch_id' => $branch->getKey(), 'region_id' => $branch->region_id]);
        }

        return compact('area', 'jatim', 'balnus', 'sby', 'sda', 'emptyBranch', 'emptyRegion', 'serviceAreas');
    }

    private function lop(User $creator, ServiceArea $serviceArea, string $incident, string $status = 'draft', string $program = 'recovery', bool $legacy = false): QeLop
    {
        return QeLop::create([
            'incident' => $incident, 'nama_lop' => 'Project '.$incident, 'program_type' => $program, 'status_lop' => $status,
            'branch_id' => $legacy ? null : $serviceArea->branch_id, 'service_area_id' => $legacy ? null : $serviceArea->getKey(),
            'branch' => $serviceArea->branch->name, 'sto' => $serviceArea->workzone, 'created_by' => $creator->getKey(),
        ]);
    }

    public function test_branch_matrix_contains_all_its_service_areas_including_zero_and_legacy_lops(): void
    {
        $locations = $this->locations();
        $admin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'branch', 'branch_id' => $locations['sby']->getKey()]);
        $sa = $locations['serviceAreas'];
        foreach (LopStatus::cases() as $status) {
            $this->lop($admin, $sa['SBY-A'], 'SBY-'.$status->value, $status->value);
        }
        $this->lop($admin, $sa['SBY-B'], 'SBY-LEGACY', 'completed', legacy: true);
        $this->lop($admin, $sa['SDA-A'], 'FOREIGN-LOP', 'completed');
        $this->lop($admin, $sa['SBY-A'], 'ARCHIVED-LOP', 'completed')->delete();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Pilih Program')->assertSee('Service Area / STO')->assertSee('Unassign')
            ->assertViewHas('programMatrix', function ($matrix): bool {
                $rows = collect($matrix['rows']);

                return $matrix['dimension'] === 'service_area' && $rows->count() === 3
                    && $rows->firstWhere('label', 'SBY-A')['summary']['total'] === 8
                    && $rows->firstWhere('label', 'SBY-A')['summary']['assigned'] === 1
                    && $rows->firstWhere('label', 'SBY-A')['summary']['unassigned'] === 1
                    && $rows->firstWhere('label', 'SBY-A')['summary']['in_review'] === 1
                    && $rows->firstWhere('label', 'SBY-A')['summary']['complete'] === 1
                    && $rows->firstWhere('label', 'SBY-B')['summary']['total'] === 1
                    && $rows->firstWhere('label', 'SBY-ZERO')['summary']['total'] === 0
                    && $matrix['summary']['total'] === 9 && $matrix['summary']['percentage'] === 22;
            });
        $this->getJson(route('dashboard.matrix-lops', ['service_area_id' => $sa['SBY-A']->getKey(), 'metric' => 'unassigned']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.incident', 'SBY-draft');
        $this->getJson(route('dashboard.matrix-lops', ['service_area_id' => $sa['SBY-B']->getKey()]))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.incident', 'SBY-LEGACY');
        $this->getJson(route('dashboard.matrix-lops', ['service_area_id' => $sa['SDA-A']->getKey()]))->assertJsonPath('total', 0);
        $this->getJson(route('dashboard.matrix-lops', ['service_area_id' => 999999]))->assertJsonPath('total', 0);
        $this->getJson(route('dashboard.matrix-lops', ['service_area_id' => ['bad']]))->assertUnprocessable();
        $this->getJson(route('dashboard', ['program' => ['bad']]))->assertUnprocessable();
        $this->getJson(route('dashboard.matrix-lops', ['program' => ['bad']]))->assertUnprocessable();
    }

    public function test_region_matrix_is_branch_level_and_area_matrix_is_region_level_with_empty_locations(): void
    {
        $locations = $this->locations();
        $regionAdmin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'region', 'region_id' => $locations['jatim']->getKey()]);
        $areaAdmin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'area', 'area_id' => $locations['area']->getKey()]);
        $sa = $locations['serviceAreas'];
        $this->lop($regionAdmin, $sa['SBY-A'], 'REG-ASSIGNED', 'assigned');
        $this->lop($regionAdmin, $sa['SDA-A'], 'REG-LEGACY', 'draft', legacy: true);
        $this->lop($regionAdmin, $sa['DPS-A'], 'AREA-DPS', 'completed');
        $this->lop($regionAdmin, $sa['OUT-A'], 'OUTSIDE-AREA', 'completed');
        $this->actingAs($regionAdmin)->get(route('dashboard', ['region' => $locations['balnus']->name]))->assertOk()
            ->assertViewHas('programMatrix', fn ($matrix) => $matrix['dimension'] === 'branch'
                && collect($matrix['rows'])->pluck('label')->all() === ['Branch Tanpa LOP', 'Matrix Sidoarjo', 'Matrix Surabaya']
                && $matrix['summary']['total'] === 2 && $matrix['summary']['unassigned'] === 1);
        $this->getJson(route('dashboard.matrix-lops', ['branch_id' => $locations['sda']->getKey()]))->assertJsonPath('total', 1)->assertJsonPath('data.0.incident', 'REG-LEGACY');
        $this->getJson(route('dashboard.matrix-lops', ['branch_id' => $sa['DPS-A']->branch_id]))->assertJsonPath('total', 0);
        $this->actingAs($areaAdmin)->get(route('dashboard'))->assertOk()
            ->assertViewHas('programMatrix', fn ($matrix) => $matrix['dimension'] === 'region'
                && count($matrix['rows']) === 3 && $matrix['summary']['total'] === 3
                && collect($matrix['rows'])->firstWhere('label', 'Region Tanpa LOP')['summary']['total'] === 0);
        $this->getJson(route('dashboard.matrix-lops', ['region_id' => $locations['jatim']->getKey()]))->assertJsonPath('total', 2);
        $this->getJson(route('dashboard.matrix-lops', ['region_id' => $sa['OUT-A']->region_id]))->assertJsonPath('total', 0);
    }

    public function test_multi_service_area_matrix_remains_per_program_without_sibling_or_foreign_lops(): void
    {
        $locations = $this->locations();
        $admin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'service_area']);
        $sa = $locations['serviceAreas'];
        $admin->serviceAreas()->sync([$sa['SBY-A']->getKey(), $sa['DPS-A']->getKey()]);
        $this->lop($admin, $sa['SBY-A'], 'SA-RECOVERY', 'draft');
        $this->lop($admin, $sa['DPS-A'], 'SA-TIANG', 'completed', 'relok_tiang', true);
        $this->lop($admin, $sa['SBY-B'], 'SIBLING-NOT-ALLOWED', 'draft');
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertViewHas('programMatrix', fn ($matrix) => $matrix['dimension'] === 'program'
            && count($matrix['rows']) === 4 && $matrix['summary']['total'] === 2
            && collect($matrix['rows'])->firstWhere('key', 'recovery')['summary']['unassigned'] === 1
            && collect($matrix['rows'])->firstWhere('key', 'relok_tiang')['summary']['complete'] === 1);
        $this->get(route('dashboard', ['program' => 'relok_tiang']))->assertOk()->assertViewHas('programMatrix', fn ($matrix) => count($matrix['rows']) === 1 && $matrix['summary']['total'] === 1);
        $this->getJson(route('dashboard.matrix-lops', ['program' => 'recovery', 'metric' => 'unassigned']))->assertJsonPath('total', 1)->assertJsonPath('data.0.incident', 'SA-RECOVERY');
        $this->getJson(route('dashboard.matrix-lops', ['service_area_id' => $sa['SBY-B']->getKey()]))->assertJsonPath('total', 0);
    }

    public static function programs(): array
    {
        return array_map(fn (ProgramType $program) => [$program->value], ProgramType::cases());
    }

    #[DataProvider('programs')]
    public function test_program_dropdown_filters_counts_for_every_admin_scope_and_retains_filter_context(string $program): void
    {
        $locations = $this->locations();
        $sa = $locations['serviceAreas'];
        $admins = [
            User::factory()->role('ADMIN')->create(['admin_scope_type' => 'area', 'area_id' => $locations['area']->getKey()]),
            User::factory()->role('ADMIN')->create(['admin_scope_type' => 'region', 'region_id' => $locations['jatim']->getKey()]),
            User::factory()->role('ADMIN')->create(['admin_scope_type' => 'branch', 'branch_id' => $locations['sby']->getKey()]),
            User::factory()->role('ADMIN')->create(['admin_scope_type' => 'service_area', 'service_area_id' => $sa['SBY-A']->getKey()]),
        ];
        foreach (ProgramType::cases() as $case) {
            $this->lop($admins[0], $sa['SBY-A'], 'FILTER-'.$case->value, program: $case->value);
        }
        $date = now('Asia/Jakarta')->toDateString();
        foreach ($admins as $admin) {
            $this->actingAs($admin)->get(route('dashboard', ['program' => $program, 'date' => $date, 'tab' => 'program', 'branch' => 'OUTSIDE', 'region' => 'OUTSIDE']))->assertOk()
                ->assertViewHas('filters', fn ($filters) => $filters['program'] === $program && $filters['branch'] === '' && $filters['region'] === '')
                ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1)
                ->assertViewHas('programMatrix', fn ($matrix) => $matrix['summary']['total'] === 1 && $matrix['summary']['unassigned'] === 1)
                ->assertViewHas('monitoring', fn ($monitoring) => $monitoring['date'] === $date && $monitoring['rows']->sum('total_lops') === 1)
                ->assertSee('name="tab" value="program"', false)->assertSee('value="'.$date.'"', false);
            $this->getJson(route('dashboard.matrix-lops', ['program' => $program, 'metric' => 'unassigned']))->assertJsonPath('total', 1)->assertJsonPath('data.0.incident', 'FILTER-'.$program);
        }
    }

    public function test_unmapped_sto_is_explicit_and_drill_down_matches_without_losing_counts(): void
    {
        $locations = $this->locations();
        $admin = User::factory()->role('ADMIN')->create(['branch_id' => $locations['sby']->getKey()]);
        $known = $this->lop($admin, $locations['serviceAreas']['SBY-A'], 'KNOWN');
        $unknown = $this->lop($admin, $locations['serviceAreas']['SBY-A'], 'UNKNOWN');
        $unknown->update(['service_area_id' => null, 'sto' => null]);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertViewHas('programMatrix', fn ($matrix) => count($matrix['rows']) === 4
            && $matrix['summary']['total'] === 2 && collect($matrix['rows'])->firstWhere('key', 'unmapped')['summary']['total'] === 1);
        $this->getJson(route('dashboard.matrix-lops', ['service_area_unmapped' => 1]))->assertJsonPath('total', 1)->assertJsonPath('data.0.incident', $unknown->incident)->assertJsonMissing(['incident' => $known->incident]);
        $this->get(route('dashboard', ['program' => 'not_a_program']))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['total'] === 2);
    }

    public function test_super_admin_keeps_region_branch_accordion_and_new_unassign_filter(): void
    {
        $locations = $this->locations();
        $super = User::factory()->role('SUPER_ADMIN')->create();
        $sa = $locations['serviceAreas'];
        $this->lop($super, $sa['SBY-A'], 'SUPER-TARGET', 'draft', 'relok_tiang');
        $this->lop($super, $sa['SBY-A'], 'SUPER-OTHER', 'assigned', 'recovery');
        $this->actingAs($super)->get(route('dashboard', ['program' => 'relok_tiang', 'region' => $locations['jatim']->name, 'branch' => $locations['sby']->name]))->assertOk()
            ->assertSee('branchOpen', false)->assertSee('Unassign')->assertViewHas('programMatrix', null)
            ->assertViewHas('matrixRegions', fn ($regions) => count($regions) === 1 && $regions[0]['summary']['unassigned'] === 1 && $regions[0]['summary']['assigned'] === 0);
        $this->getJson(route('dashboard.matrix-lops', ['program' => 'relok_tiang', 'metric' => 'unassigned']))->assertJsonPath('total', 1)->assertJsonPath('data.0.incident', 'SUPER-TARGET');
    }
}
