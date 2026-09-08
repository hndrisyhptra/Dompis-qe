<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\User;
use App\Services\EvidenceArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class EvidenceArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function completedLop(User $creator, string $name = '3PME_QEREC_INC50390302_DISTRIBUSI'): QeLop
    {
        return QeLop::create([
            'incident' => 'INC'.random_int(10000, 99999),
            'nama_lop' => $name,
            'program_type' => 'recovery',
            'status_lop' => 'completed',
            'created_by' => $creator->id_user,
        ]);
    }

    private function evidence(QeLop $lop, string $category, string $file, string $status = 'approved'): QeEvidence
    {
        $path = UploadedFile::fake()->image($file)->store('evidences', 'public');

        return QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'uploaded_by' => $lop->created_by,
            'step' => 'AFTER',
            'type' => 'PHOTO',
            'category' => $category,
            'file_path' => $path,
            'metadata' => ['original_name' => $file],
            'status' => $status,
        ]);
    }

    public function test_service_builds_zip_named_after_lop_with_matching_folder_and_only_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $lop = $this->completedLop($admin);

        $this->evidence($lop, 'after', 'a.jpg');
        $this->evidence($lop, 'slot_port', 'b.png');
        // PDF harus diabaikan
        $pdfPath = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')->store('evidences', 'public');
        QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops, 'uploaded_by' => $admin->id_user,
            'step' => 'AFTER', 'type' => 'DOCUMENT', 'category' => 'after',
            'file_path' => $pdfPath, 'status' => 'approved',
        ]);

        [$path, $filename] = app(EvidenceArchiveService::class)->build($lop->fresh());

        $this->assertSame('3PME_QEREC_INC50390302_DISTRIBUSI.zip', $filename);
        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($path);

        $this->assertCount(2, $entries, 'PDF tidak boleh ikut, hanya 2 gambar');
        foreach ($entries as $entry) {
            $this->assertStringStartsWith('3PME_QEREC_INC50390302_DISTRIBUSI/', $entry);
        }
    }

    public function test_service_throws_when_lop_has_no_image_evidence(): void
    {
        Storage::fake('public');
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $lop = $this->completedLop($admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('empty');

        app(EvidenceArchiveService::class)->build($lop->fresh());
    }

    public function test_download_route_returns_zip_for_completed_lop(): void
    {
        Storage::fake('public');
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $lop = $this->completedLop($admin);
        $this->evidence($lop, 'after', 'a.jpg');

        $response = $this->actingAs($admin)->get(route('lop.evidence-archive', $lop));

        $response->assertOk();
        $this->assertStringContainsString('application/zip', $response->headers->get('content-type'));
        $this->assertStringContainsString('3PME_QEREC_INC50390302_DISTRIBUSI.zip', $response->headers->get('content-disposition'));
    }

    public function test_download_route_404_when_lop_not_completed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $lop = $this->completedLop($admin);
        $lop->update(['status_lop' => 'waiting_approval']);
        $this->evidence($lop, 'after', 'a.jpg');

        $this->actingAs($admin)
            ->get(route('lop.evidence-archive', $lop))
            ->assertNotFound();
    }

    public function test_download_route_forbidden_for_unassigned_technician(): void
    {
        Storage::fake('public');
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $stranger = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = $this->completedLop($admin);
        $this->evidence($lop, 'after', 'a.jpg');

        $this->actingAs($stranger)
            ->get(route('lop.evidence-archive', $lop))
            ->assertForbidden();
    }
}
