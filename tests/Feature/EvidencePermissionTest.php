<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\QeLop;
use App\Models\QeEvidence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidencePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeLop(User $admin, string $status = 'assigned'): QeLop
    {
        return QeLop::create([
            'kode_lop' => 'LOP-EVD-'.uniqid(),
            'nama_lop' => 'Evidence Test',
            'wbs_type' => 'recovery',
            'status_lop' => $status,
            'created_by' => $admin->id_user,
        ]);
    }

    public function test_assigned_teknisi_can_upload_evidence_but_unassigned_teknisi_cannot(): void
    {
        Storage::fake('public');

        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $assignedTeknisi = User::factory()->role(UserRole::TEKNISI->value)->create();
        $otherTeknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lop = $this->makeLop($admin);
        $lop->assignments()->create([
            'technician_id' => $assignedTeknisi->id_user,
            'assigned_by' => $admin->id_user,
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $payload = [
            'step' => 'BEFORE',
            'type' => 'PHOTO',
            'file' => UploadedFile::fake()->image('before.jpg'),
        ];

        $forbidden = $this->actingAs($otherTeknisi)->post(route('lop.evidence.store', $lop), $payload);
        $forbidden->assertForbidden();

        $ok = $this->actingAs($assignedTeknisi)->post(route('lop.evidence.store', $lop), $payload);
        $ok->assertRedirect(route('lop.show', $lop));
        $this->assertDatabaseHas('qe_evidences', ['qe_lop_id' => $lop->id_qe_lops, 'step' => 'BEFORE']);
    }

    public function test_only_approve_evidence_permission_holders_can_review(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();
        $approver = User::factory()->role(UserRole::APPROVER->value)->create();
        $manager = User::factory()->role(UserRole::MANAGER->value)->create();

        $lop = $this->makeLop($admin);
        $evidence = QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'uploaded_by' => $teknisi->id_user,
            'step' => 'BEFORE',
            'type' => 'PHOTO',
            'file_path' => 'evidences/1/BEFORE/fake.jpg',
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->get(route('evidence-approval.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('evidence-approval.approve', $evidence))->assertForbidden();

        $this->actingAs($approver)->get(route('evidence-approval.index'))->assertOk();
        $this->actingAs($admin)->get(route('evidence-approval.index'))->assertOk();
    }
}
