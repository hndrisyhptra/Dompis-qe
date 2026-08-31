<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\QeLop;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_stores_file_and_creates_pending_evidence(): void
    {
        Storage::fake('public');

        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lop = QeLop::create([
            'incident' => 'LOP-WF-01',
            'nama_lop' => 'Workflow Test',
            'wbs_type' => 'recovery',
            'status_lop' => 'progress',
            'created_by' => $admin->id_user,
        ]);

        $file = UploadedFile::fake()->image('progress.jpg');

        $evidence = app(EvidenceService::class)->upload($lop, [
            'step' => 'PROGRESS',
            'type' => 'PHOTO',
        ], $file, $teknisi);

        $this->assertEquals('pending', $evidence->status->value);
        $this->assertEquals($teknisi->id_user, $evidence->uploaded_by);
        Storage::disk('public')->assertExists($evidence->file_path);
        $this->assertSame('progress.jpg', $evidence->metadata['original_name']);
    }

    public function test_approve_and_reject_set_status_and_reviewer(): void
    {
        Storage::fake('public');

        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $approver = User::factory()->role(UserRole::APPROVER->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lop = QeLop::create([
            'incident' => 'LOP-WF-02',
            'nama_lop' => 'Workflow Test 2',
            'wbs_type' => 'recovery',
            'status_lop' => 'progress',
            'created_by' => $admin->id_user,
        ]);

        $service = app(EvidenceService::class);

        $approved = $service->upload($lop, ['step' => 'AFTER', 'type' => 'PHOTO'], UploadedFile::fake()->image('a.jpg'), $teknisi);
        $rejected = $service->upload($lop, ['step' => 'AFTER', 'type' => 'PHOTO'], UploadedFile::fake()->image('b.jpg'), $teknisi);

        $service->approve($approved, $approver);
        $service->reject($rejected, $approver, 'Foto buram');

        $this->assertEquals('approved', $approved->fresh()->status->value);
        $this->assertEquals($approver->id_user, $approved->fresh()->reviewed_by);
        $this->assertNotNull($approved->fresh()->reviewed_at);

        $this->assertEquals('rejected', $rejected->fresh()->status->value);
        $this->assertEquals('Foto buram', $rejected->fresh()->review_note);
    }

    public function test_delete_removes_file_and_row(): void
    {
        Storage::fake('public');

        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lop = QeLop::create([
            'incident' => 'LOP-WF-03',
            'nama_lop' => 'Workflow Test 3',
            'wbs_type' => 'recovery',
            'status_lop' => 'progress',
            'created_by' => $admin->id_user,
        ]);

        $evidence = app(EvidenceService::class)->upload($lop, ['step' => 'SURVEY', 'type' => 'PHOTO'], UploadedFile::fake()->image('c.jpg'), $teknisi);
        $path = $evidence->file_path;

        app(EvidenceService::class)->delete($evidence);

        Storage::disk('public')->assertMissing($path);
        $this->assertSoftDeleted('qe_evidences', ['id_evidence' => $evidence->id_evidence]);
    }
}
