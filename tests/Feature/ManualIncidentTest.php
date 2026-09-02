<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use App\Services\ManualIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManualIncidentTest extends TestCase
{
    use RefreshDatabase;

    private function adminForBranch(Branch $branch): User
    {
        return User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);
    }

    public function test_service_builds_expected_format(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));

        $branch = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);

        $incident = app(ManualIncidentService::class)->generate($branch, \App\Enums\WbsType::RECOVERY);

        // INP + id_branch + wbs(1) + DDMMYY + urut(01)
        $this->assertSame(sprintf('INP%d1020926%s', $branch->id_branch, '01'), $incident);

        Carbon::setTestNow();
    }

    public function test_sequence_increments_per_branch_wbs_and_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $branch = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $admin = $this->adminForBranch($branch);
        $service = app(ManualIncidentService::class);

        $first = $service->generate($branch, \App\Enums\WbsType::RECOVERY);
        QeLop::create([
            'incident' => $first,
            'nama_lop' => 'LOP A',
            'wbs_type' => 'recovery',
            'status_lop' => 'draft',
            'created_by' => $admin->id_user,
        ]);

        $second = $service->generate($branch, \App\Enums\WbsType::RECOVERY);
        $this->assertSame(substr($first, 0, -2).'02', $second);

        // WBS berbeda -> urutan mulai dari 01 lagi.
        $preventive = $service->generate($branch, \App\Enums\WbsType::PREVENTIVE);
        $this->assertStringEndsWith('020926'.'01', $preventive);
        $this->assertStringContainsString('INP'.$branch->id_branch.'2', $preventive);

        Carbon::setTestNow();
    }

    public function test_endpoint_returns_generated_incident_using_login_branch(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $branch = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $admin = $this->adminForBranch($branch);

        $this->actingAs($admin)
            ->getJson(route('lop.manual-incident', ['wbs_type' => 'recovery']))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'incident' => sprintf('INP%d1020926%s', $branch->id_branch, '01'),
                'branch' => 'SURABAYA',
            ]);

        Carbon::setTestNow();
    }

    public function test_endpoint_fails_when_user_has_no_branch(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => null]);

        $this->actingAs($admin)
            ->getJson(route('lop.manual-incident', ['wbs_type' => 'recovery']))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_endpoint_validates_wbs_type(): void
    {
        $branch = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $admin = $this->adminForBranch($branch);

        $this->actingAs($admin)
            ->getJson(route('lop.manual-incident', ['wbs_type' => 'bogus']))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->getJson(route('lop.manual-incident'))
            ->assertStatus(422);
    }

    public function test_endpoint_requires_create_permission(): void
    {
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $this->actingAs($teknisi)
            ->getJson(route('lop.manual-incident', ['wbs_type' => 'recovery']))
            ->assertForbidden();
    }

    public function test_generated_incident_can_be_used_to_create_lop(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $branch = Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $admin = $this->adminForBranch($branch);

        $incident = $this->actingAs($admin)
            ->getJson(route('lop.manual-incident', ['wbs_type' => 'recovery']))
            ->json('incident');

        $this->actingAs($admin)->post(route('lop.store'), [
            'incident' => $incident,
            'wbs_type' => 'recovery',
            'sto' => 'SBY',
            'branch' => 'SURABAYA',
            'area' => '3',
            'segment' => 'odp',
            'job_description' => 'Recovery manual',
        ])->assertRedirect();

        $this->assertDatabaseHas('qe_lops', ['incident' => $incident]);

        Carbon::setTestNow();
    }
}
