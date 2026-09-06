<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BranchRegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_master_has_region_and_expected_operational_data(): void
    {
        $this->assertTrue(Schema::hasColumn('branches', 'region'));

        $this->seed(BranchSeeder::class);

        $this->assertDatabaseCount('branches', 16);
        $this->assertSame(6, Branch::where('region', 'REGION JATIM')->count());
        $this->assertSame(6, Branch::where('region', 'REGION JATENG DIY')->count());
        $this->assertSame(4, Branch::where('region', 'REGION BALNUS')->count());
        $this->assertDatabaseHas('branches', ['name' => 'SURABAYA', 'region' => 'REGION JATIM']);
        $this->assertDatabaseHas('branches', ['name' => 'YOGYAKARTA', 'region' => 'REGION JATENG DIY']);
        $this->assertDatabaseHas('branches', ['name' => 'DENPASAR', 'region' => 'REGION BALNUS']);
    }

    public function test_super_admin_can_filter_approval_lops_by_region(): void
    {
        $this->seed(BranchSeeder::class);
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();

        foreach (['SURABAYA', 'DENPASAR'] as $branch) {
            $lop = QeLop::create([
                'incident' => "LOP-{$branch}",
                'nama_lop' => "Project {$branch}",
                'program_type' => 'recovery',
                'branch' => $branch,
                'status_lop' => 'waiting_approval',
                'created_by' => $superAdmin->id_user,
            ]);
            $lop->assignments()->create([
                'technician_id' => $technician->id_user,
                'assigned_by' => $superAdmin->id_user,
                'assigned_at' => now(),
                'status' => 'active',
            ]);
            QeEvidence::create([
                'qe_lop_id' => $lop->id_qe_lops,
                'uploaded_by' => $technician->id_user,
                'step' => 'PROGRESS',
                'type' => 'PHOTO',
                'file_path' => "evidences/{$branch}.jpg",
                'status' => 'pending',
            ]);
        }

        $this->actingAs($superAdmin)
            ->get(route('evidence-approval.index', ['region' => 'REGION JATIM']))
            ->assertOk()
            ->assertSee('LOP-SURABAYA')
            ->assertDontSee('LOP-DENPASAR');
    }
}
