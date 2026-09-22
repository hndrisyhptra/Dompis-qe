<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LopIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_ihld_filter_shows_only_lops_without_ihld(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);

        QeLop::create([
            'incident' => 'FILT-1', 'nama_lop' => 'Tanpa IHLD',
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);
        QeLop::create([
            'incident' => 'FILT-2', 'nama_lop' => 'Ada IHLD',
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'ihld_id' => 'IHLD-1',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        $this->actingAs($admin)->get(route('lop.index', ['missing_ihld' => 1]))
            ->assertOk()
            ->assertSee('FILT-1')
            ->assertDontSee('FILT-2');
    }

    public function test_unassigned_filter_shows_only_draft_without_active_assignment(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);

        $free = QeLop::create([
            'incident' => 'FREE-1', 'nama_lop' => 'Belum ditugaskan',
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);
        $taken = QeLop::create([
            'incident' => 'TAKEN-1', 'nama_lop' => 'Sudah ditugaskan',
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);
        $taken->assignments()->create([
            'technician_id' => $teknisi->id_user,
            'assigned_by' => $admin->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('lop.index', ['unassigned' => 1]))
            ->assertOk()
            ->assertSee('FREE-1')
            ->assertDontSee('TAKEN-1');

        $this->actingAs($admin)->get(route('lop.index', ['assigned' => 1]))
            ->assertOk()
            ->assertSee('TAKEN-1')
            ->assertDontSee('FREE-1');

        $this->assertTrue($free->fresh()->activeAssignment === null);
    }

    public function test_branch_filter_limits_inbox_to_branch(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        Branch::create(['code' => 'SBY', 'name' => 'SURABAYA', 'region' => 'REGION JATIM']);

        QeLop::create([
            'incident' => 'BR-1', 'nama_lop' => 'Sidoarjo',
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);
        QeLop::create([
            'incident' => 'BR-2', 'nama_lop' => 'Surabaya',
            'program_type' => 'recovery', 'sto' => 'SBY', 'branch' => 'SURABAYA',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        $this->actingAs($admin)->get(route('lop.index', ['branch' => 'SURABAYA']))
            ->assertOk()
            ->assertSee('BR-2')
            ->assertDontSee('BR-1');
    }
}
