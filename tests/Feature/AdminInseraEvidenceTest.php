<?php

namespace Tests\Feature;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

class AdminInseraEvidenceTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    private Branch $branch;

    private ServiceArea $serviceArea;

    private User $admin;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::updateOrCreate(['code' => '3'], ['name' => 'Area 3', 'is_active' => true]);
        $region = Region::query()->where('code', 'JATIM')->firstOrFail();
        $region->update(['area_id' => $area->id_area]);

        $this->branch = Branch::create([
            'code' => 'INS-SDA',
            'name' => 'SIDOARJO INSERA',
            'region' => $region->name,
            'region_id' => $region->id_region,
            'is_active' => true,
        ]);
        $this->serviceArea = ServiceArea::create([
            'workzone' => 'INS-SDA',
            'name' => 'Sidoarjo Insera',
            'branch_id' => $this->branch->id_branch,
            'region_id' => $region->id_region,
            'is_active' => true,
        ]);
        $this->admin = User::factory()->role(UserRole::ADMIN->value)->create([
            'admin_scope_type' => AdminScopeType::BRANCH->value,
            'branch_id' => $this->branch->id_branch,
        ]);
        $this->technician = User::factory()->role(UserRole::TEKNISI->value)->create();
    }

    private function makeLop(string $status = 'draft', ?User $creator = null, ?ServiceArea $serviceArea = null): QeLop
    {
        $serviceArea ??= $this->serviceArea;
        $creator ??= $this->admin;

        return QeLop::create([
            'incident' => 'LOP-INS-'.uniqid(),
            'nama_lop' => 'Capture Insera Test',
            'program_type' => 'recovery',
            'sto' => $serviceArea->workzone,
            'branch' => $serviceArea->branch->name,
            'branch_id' => $serviceArea->branch_id,
            'service_area_id' => $serviceArea->id_service_area,
            'status_lop' => $status,
            'created_by' => $creator->id_user,
        ]);
    }

    private function assignLop(QeLop $lop): void
    {
        $lop->assignments()->create([
            'technician_id' => $this->technician->id_user,
            'assigned_by' => $this->admin->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);
    }

    private function reserveDesignator(QeLop $lop): Designator
    {
        $designator = Designator::create([
            'code' => 'M-INS-'.uniqid(),
            'item_name' => 'ODP',
            'unit' => 'pcs',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);
        $reservation = $lop->materialReservation()->create([
            'technician_id' => $this->technician->id_user,
            'status' => 'draft',
        ]);
        $reservation->items()->create(['designator_id' => $designator->id_designator, 'qty' => 1]);

        return $designator;
    }

    public function test_admin_can_upload_insera_from_data_lop_and_technician_sees_it_in_step_three(): void
    {
        Storage::fake('public');
        $lop = $this->makeLop('survey');
        $this->assignLop($lop);
        $designator = $this->reserveDesignator($lop);

        app(EvidenceService::class)->upload($lop, [
            'step' => 'BEFORE',
            'type' => 'PHOTO',
            'category' => 'material_arrival',
            'designator_id' => $designator->id_designator,
        ], UploadedFile::fake()->image('material.jpg'), $this->technician);

        $response = $this->actingAs($this->admin)->post(route('data-lops.insera.store', $lop), [
            'files' => [UploadedFile::fake()->image('insera-admin.png')],
        ]);

        $response->assertRedirect(route('data-lops.index'));
        $this->assertDatabaseHas('qe_evidences', [
            'qe_lop_id' => $lop->id_qe_lops,
            'category' => 'insera',
            'step' => 'SURVEY',
            'type' => 'PHOTO',
            'status' => 'pending',
            'uploaded_by' => $this->admin->id_user,
        ]);

        $this->actingAs($this->technician)
            ->get(route('technician.projects.show', [$lop, 'step' => 3]))
            ->assertOk()
            ->assertSee('Capture Ticket Insera')
            ->assertSee('insera-admin.png');
    }

    public function test_create_lop_with_insera_file_saves_lop_and_evidence_together(): void
    {
        Storage::fake('public');
        $this->fakeIncidentTickets(['INCINS001' => 'GAMAS ODP']);

        $response = $this->actingAs($this->admin)->post(route('lop.store'), [
            'incident' => 'INCINS001',
            'sto' => $this->serviceArea->workzone,
            'branch' => $this->branch->name,
            'area' => '3',
            'segment' => 'odp',
            'program_type' => 'recovery',
            'job_description' => 'Penggantian ODP',
            'insera_files' => [UploadedFile::fake()->image('capture-insera.png')],
        ]);

        $response->assertRedirect(route('data-lops.index', ['q' => 'INCINS001']));
        $lop = QeLop::where('incident', 'INCINS001')->firstOrFail();
        $evidence = QeEvidence::where('qe_lop_id', $lop->id_qe_lops)->firstOrFail();

        $this->assertSame('insera', $evidence->category->value);
        $this->assertSame('SURVEY', $evidence->step->value);
        $this->assertSame('pending', $evidence->status->value);
        $this->assertSame($this->admin->id_user, $evidence->uploaded_by);
        Storage::disk('public')->assertExists($evidence->file_path);
    }

    public function test_create_lop_rejects_non_image_insera_file(): void
    {
        Storage::fake('public');
        $this->fakeIncidentTickets(['INCINS002' => 'GAMAS ODP']);

        $this->actingAs($this->admin)->post(route('lop.store'), [
            'incident' => 'INCINS002',
            'sto' => $this->serviceArea->workzone,
            'branch' => $this->branch->name,
            'area' => '3',
            'segment' => 'odp',
            'program_type' => 'recovery',
            'job_description' => 'Penggantian ODP',
            'insera_files' => [UploadedFile::fake()->create('capture.pdf', 30, 'application/pdf')],
        ])->assertSessionHasErrors('insera_files.0');

        $this->assertDatabaseMissing('qe_lops', ['incident' => 'INCINS002']);
    }

    public function test_insera_upload_is_forbidden_for_out_of_scope_technician_and_completed_lop(): void
    {
        Storage::fake('public');

        $otherBranch = Branch::create([
            'code' => 'INS-SBY',
            'name' => 'SURABAYA INSERA',
            'region' => $this->branch->region,
            'region_id' => $this->branch->region_id,
            'is_active' => true,
        ]);
        $otherServiceArea = ServiceArea::create([
            'workzone' => 'INS-SBY',
            'name' => 'Surabaya Insera',
            'branch_id' => $otherBranch->id_branch,
            'region_id' => $otherBranch->region_id,
            'is_active' => true,
        ]);
        $outsideLop = $this->makeLop('draft', null, $otherServiceArea);
        $completedLop = $this->makeLop('completed');
        $assignedLop = $this->makeLop('assigned');
        $this->assignLop($assignedLop);

        $payload = ['files' => [UploadedFile::fake()->image('insera.png')]];

        $this->actingAs($this->admin)->post(route('data-lops.insera.store', $outsideLop), $payload)->assertForbidden();
        $this->actingAs($this->technician)->post(route('data-lops.insera.store', $assignedLop), $payload)->assertForbidden();
        $this->actingAs($this->admin)->post(route('data-lops.insera.store', $completedLop), $payload)->assertForbidden();

        $this->assertDatabaseCount('qe_evidences', 0);
    }

    public function test_data_lop_shows_insera_action_only_for_non_completed_lop(): void
    {
        $draft = $this->makeLop('draft');
        $completed = $this->makeLop('completed');

        $this->actingAs($this->admin)
            ->get(route('data-lops.index'))
            ->assertOk()
            ->assertSee(route('data-lops.insera.store', $draft), false)
            ->assertDontSee(route('data-lops.insera.store', $completed), false)
            ->assertSee('Upload capture Insera');
    }
}
