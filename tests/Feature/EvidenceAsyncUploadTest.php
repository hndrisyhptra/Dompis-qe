<?php

namespace Tests\Feature;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\EvidenceApprovalService;
use App\Services\EvidenceService;
use App\Services\ProjectProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceAsyncUploadTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User, 2: QeLop} */
    private function assignedProject(string $status = 'survey', string $program = 'recovery'): array
    {
        $region = Region::query()->where('code', 'JATIM')->firstOrFail();
        $branch = Branch::create([
            'code' => 'ASYNC-SDA',
            'name' => 'SIDOARJO ASYNC',
            'region' => $region->name,
            'region_id' => $region->id_region,
            'is_active' => true,
        ]);
        $serviceArea = ServiceArea::create([
            'workzone' => 'ASYNC-SDA',
            'name' => 'Sidoarjo Async',
            'branch_id' => $branch->id_branch,
            'region_id' => $region->id_region,
            'is_active' => true,
        ]);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create([
            'admin_scope_type' => AdminScopeType::BRANCH->value,
            'branch_id' => $branch->id_branch,
        ]);
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = QeLop::create([
            'incident' => 'LOP-ASYNC-01',
            'nama_lop' => 'Async Upload',
            'program_type' => $program,
            'sto' => $serviceArea->workzone,
            'branch' => $branch->name,
            'branch_id' => $branch->id_branch,
            'service_area_id' => $serviceArea->id_service_area,
            'status_lop' => $status,
            'created_by' => $admin->id_user,
        ]);
        $lop->assignments()->create([
            'technician_id' => $technician->id_user,
            'assigned_by' => $admin->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        return [$admin, $technician, $lop];
    }

    private function reservedDesignator(QeLop $lop, User $technician): Designator
    {
        $designator = Designator::create([
            'code' => 'M-ASYNC', 'item_name' => 'ODP', 'unit' => 'pcs',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);
        $reservation = $lop->materialReservation()->create([
            'technician_id' => $technician->id_user,
            'status' => 'draft',
        ]);
        $reservation->items()->create(['designator_id' => $designator->id_designator, 'qty' => 1]);

        return $designator;
    }

    public function test_migration_adds_thumb_path_column(): void
    {
        $this->assertTrue(Schema::hasColumn('qe_evidences', 'thumb_path'));
    }

    public function test_store_one_persists_file_and_optional_thumb(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();

        $service = app(EvidenceService::class);

        $noThumb = $service->storeOne($lop, ['step' => 'PROGRESS', 'type' => 'PHOTO'], UploadedFile::fake()->image('a.jpg'), $technician);
        $this->assertNull($noThumb->thumb_path);
        $this->assertStringEndsWith('.jpg', $noThumb->file_path);
        $this->assertSame('a.jpg', $noThumb->metadata['original_name']);
        Storage::disk('public')->assertExists($noThumb->file_path);

        $withThumb = $service->storeOne(
            $lop,
            ['step' => 'PROGRESS', 'type' => 'PHOTO'],
            UploadedFile::fake()->image('b.png'),
            $technician,
            UploadedFile::fake()->image('b_thumb.webp'),
        );
        $this->assertNotNull($withThumb->thumb_path);
        $this->assertStringEndsWith('.png', $withThumb->file_path);
        $this->assertSame('b.png', $withThumb->metadata['original_name']);
        Storage::disk('public')->assertExists($withThumb->file_path);
        Storage::disk('public')->assertExists($withThumb->thumb_path);
        $this->assertStringContainsString('_thumb.', $withThumb->thumb_path);
    }

    public function test_evidence_file_endpoint_uploads_one_and_returns_json(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        $designator = $this->reservedDesignator($lop, $technician);

        $response = $this->actingAs($technician)->postJson(route('technician.projects.evidence.file', $lop), [
            'category' => 'progress',
            'type' => 'PHOTO',
            'designator_id' => $designator->id_designator,
            'file' => UploadedFile::fake()->image('progress.webp'),
            'thumb' => UploadedFile::fake()->image('progress_thumb.webp'),
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'url', 'thumb_url', 'name', 'status', 'category']);

        $this->assertDatabaseCount('qe_evidences', 1);
        $this->assertDatabaseHas('qe_evidences', [
            'qe_lop_id' => $lop->id_qe_lops,
            'category' => 'progress',
            'step' => 'PROGRESS',
            'designator_id' => $designator->id_designator,
        ]);
    }

    public function test_uploaded_photo_is_streamed_through_authenticated_route_for_technician_and_reviewer(): void
    {
        Storage::fake('public');
        [$admin, $technician, $lop] = $this->assignedProject();

        $evidence = app(EvidenceService::class)->storeOne(
            $lop,
            ['step' => 'BEFORE', 'type' => 'PHOTO', 'category' => 'material_arrival'],
            UploadedFile::fake()->image('mobile-photo.jpg'),
            $technician,
            UploadedFile::fake()->image('mobile-photo-thumb.webp'),
        );

        $this->assertStringContainsString('/evidence-files/'.$evidence->id_evidence, $evidence->url());
        $this->assertStringNotContainsString('/storage/', $evidence->url());

        $this->actingAs($technician)
            ->get($evidence->url())
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $this->actingAs($admin)
            ->get($evidence->thumbUrl())
            ->assertOk()
            ->assertHeader('content-type', 'image/webp');

        $otherTechnician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $this->actingAs($otherTechnician)->get($evidence->url())->assertForbidden();
    }

    public function test_evidence_file_endpoint_accepts_webp_and_rejects_bad_type(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();

        $this->actingAs($technician)->postJson(route('technician.projects.evidence.file', $lop), [
            'category' => 'progress', 'type' => 'PHOTO',
            'file' => UploadedFile::fake()->create('x.txt', 10, 'text/plain'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_evidence_file_endpoint_forbidden_for_unassigned_technician(): void
    {
        Storage::fake('public');
        [, , $lop] = $this->assignedProject();
        $other = User::factory()->role(UserRole::TEKNISI->value)->create();

        $this->actingAs($other)->postJson(route('technician.projects.evidence.file', $lop), [
            'category' => 'progress', 'type' => 'PHOTO',
            'file' => UploadedFile::fake()->image('p.webp'),
        ])->assertForbidden();
    }

    public function test_evidence_file_endpoint_rejects_before_designator_outside_reservation(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        $this->reservedDesignator($lop, $technician);

        $stray = Designator::create([
            'code' => 'M-STRAY', 'item_name' => 'Closure', 'unit' => 'pcs',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);

        $this->actingAs($technician)->postJson(route('technician.projects.evidence.file', $lop), [
            'category' => 'before',
            'type' => 'PHOTO',
            'designator_id' => $stray->id_designator,
            'file' => UploadedFile::fake()->image('before.webp'),
        ])->assertStatus(422)->assertJsonValidationErrors('designator_id');
    }

    public function test_admin_can_upload_multiple_request_letters_for_preventive_lop(): void
    {
        Storage::fake('public');
        [$admin, , $lop] = $this->assignedProject('survey', 'preventive');

        $response = $this->actingAs($admin)
            ->from(route('program.show', 'preventive'))
            ->post(route('lop.request-letters.store', $lop), [
                'files' => [
                    UploadedFile::fake()->image('surat-lokasi.jpg'),
                    UploadedFile::fake()->create('surat-permintaan.pdf', 120, 'application/pdf'),
                ],
            ]);

        $response->assertRedirect(route('program.show', 'preventive'));
        $this->assertDatabaseCount('qe_evidences', 2);
        $this->assertDatabaseHas('qe_evidences', [
            'qe_lop_id' => $lop->id_qe_lops,
            'category' => 'request_letter',
            'step' => 'SURVEY',
            'type' => 'PHOTO',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('qe_evidences', [
            'qe_lop_id' => $lop->id_qe_lops,
            'category' => 'request_letter',
            'type' => 'DOCUMENT',
            'status' => 'approved',
        ]);

        $lop->refresh()->load('evidences');
        $this->assertSame(0, app(EvidenceApprovalService::class)->summary($lop)['total']);
        $this->assertSame(0, app(ProjectProgressService::class)->summary($lop)['evidence_count']);
        foreach ($lop->evidences as $evidence) {
            Storage::disk('public')->assertExists($evidence->file_path);
        }

        $this->actingAs($admin)
            ->get(route('program.show', 'preventive'))
            ->assertOk()
            ->assertSee('Kelola Surat Permintaan');

        $deletedLetter = $lop->evidences->first();
        $this->actingAs($admin)
            ->delete(route('lop.request-letters.destroy', [$lop, $deletedLetter]))
            ->assertRedirect();
        $this->assertSoftDeleted('qe_evidences', ['id_evidence' => $deletedLetter->id_evidence]);
        Storage::disk('public')->assertMissing($deletedLetter->file_path);
    }

    public function test_request_letter_is_not_available_for_recovery_lop(): void
    {
        Storage::fake('public');
        [$admin, , $lop] = $this->assignedProject('survey', 'recovery');

        $this->actingAs($admin)->post(route('lop.request-letters.store', $lop), [
            'files' => [UploadedFile::fake()->create('surat.pdf', 20, 'application/pdf')],
        ])->assertNotFound();

        $this->assertDatabaseCount('qe_evidences', 0);
        $this->actingAs($admin)
            ->get(route('program.show', 'recovery'))
            ->assertOk()
            ->assertDontSee('Kelola Surat Permintaan');
    }

    public function test_technician_can_upload_request_letter_only_when_admin_has_not_provided_one(): void
    {
        Storage::fake('public');
        [$admin, $technician, $lop] = $this->assignedProject('survey', 'relok_utilitas');

        $this->actingAs($technician)->postJson(route('technician.projects.evidence.file', $lop), [
            'category' => 'request_letter',
            'type' => 'DOCUMENT',
            'file' => UploadedFile::fake()->create('surat-teknisi.pdf', 30, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('status', 'approved');

        $this->actingAs($admin)->post(route('lop.request-letters.store', $lop), [
            'files' => [UploadedFile::fake()->image('surat-admin.jpg')],
        ])->assertRedirect();

        $this->actingAs($technician)->postJson(route('technician.projects.evidence.file', $lop), [
            'category' => 'request_letter',
            'type' => 'DOCUMENT',
            'file' => UploadedFile::fake()->create('surat-tambahan.pdf', 30, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('request_letter');

        $this->assertDatabaseCount('qe_evidences', 2);
    }

    public function test_request_letter_appears_in_technician_step_three(): void
    {
        Storage::fake('public');
        [$admin, $technician, $lop] = $this->assignedProject('survey', 'preventive');
        $this->reservedDesignator($lop, $technician);

        app(EvidenceService::class)->upload($lop, [
            'step' => 'BEFORE',
            'type' => 'PHOTO',
            'category' => 'material_arrival',
        ], UploadedFile::fake()->image('material.jpg'), $technician);

        $this->actingAs($admin)->post(route('lop.request-letters.store', $lop), [
            'files' => [UploadedFile::fake()->create('surat-admin.pdf', 30, 'application/pdf')],
        ]);

        $this->actingAs($technician)
            ->get(route('technician.projects.show', [$lop, 'step' => 3]))
            ->assertOk()
            ->assertSee('Surat Permintaan')
            ->assertSee('surat-admin.pdf')
            ->assertSee('sudah disediakan admin');
    }
}
