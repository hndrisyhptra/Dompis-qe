<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\QeLop;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceAsyncUploadTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User, 2: QeLop} */
    private function assignedProject(string $status = 'survey'): array
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = QeLop::create([
            'incident' => 'LOP-ASYNC-01',
            'nama_lop' => 'Async Upload',
            'wbs_type' => 'recovery',
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

        $noThumb = $service->storeOne($lop, ['step' => 'PROGRESS', 'type' => 'PHOTO'], UploadedFile::fake()->image('a.webp'), $technician);
        $this->assertNull($noThumb->thumb_path);
        Storage::disk('public')->assertExists($noThumb->file_path);

        $withThumb = $service->storeOne(
            $lop,
            ['step' => 'PROGRESS', 'type' => 'PHOTO'],
            UploadedFile::fake()->image('b.webp'),
            $technician,
            UploadedFile::fake()->image('b_thumb.webp'),
        );
        $this->assertNotNull($withThumb->thumb_path);
        Storage::disk('public')->assertExists($withThumb->file_path);
        Storage::disk('public')->assertExists($withThumb->thumb_path);
        $this->assertStringContainsString('_thumb.', $withThumb->thumb_path);
    }

    public function test_evidence_file_endpoint_uploads_one_and_returns_json(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();

        $response = $this->actingAs($technician)->postJson(route('technician.projects.evidence.file', $lop), [
            'category' => 'progress',
            'type' => 'PHOTO',
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
        ]);
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
}
