<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Designator;
<<<<<<< HEAD
use App\Models\DesignatorType;
=======
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
use App\Models\QeEvidence;
use App\Models\QeLop;
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
            'incident' => 'LOP-EVD-'.uniqid(),
            'nama_lop' => 'Evidence Test',
            'wbs_type' => 'recovery',
            'status_lop' => $status,
            'created_by' => $admin->id_user,
        ]);
    }

    private function assignLop(QeLop $lop, User $admin, User $technician): void
    {
        $lop->assignments()->create([
            'technician_id' => $technician->id_user,
            'assigned_by' => $admin->id_user,
            'assigned_at' => now(),
            'status' => 'active',
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
        $this->assignLop($lop, $admin, $teknisi);
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

    public function test_approval_index_groups_multiple_evidences_by_lop(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = $this->makeLop($admin);
        $this->assignLop($lop, $admin, $teknisi);

        foreach (['progress-1.jpg', 'progress-2.jpg'] as $fileName) {
            QeEvidence::create([
                'qe_lop_id' => $lop->id_qe_lops,
                'uploaded_by' => $teknisi->id_user,
                'step' => 'PROGRESS',
                'type' => 'PHOTO',
                'category' => 'progress',
                'file_path' => "evidences/{$fileName}",
                'status' => 'pending',
            ]);
        }

        $this->actingAs($admin)
            ->get(route('evidence-approval.index'))
            ->assertOk()
            ->assertSee('Review Evidence')
            ->assertSee('2 file')
            ->assertViewHas('lops', fn ($lops) => $lops->count() === 1
                && $lops->first()->evidences->count() === 2
                && $lops->first()->evidences_count === 2);

        $this->actingAs($admin)
            ->get(route('evidence-approval.lop.review', [$lop, 'step' => 3]))
            ->assertOk()
            ->assertSee('2 file')
            ->assertSee('Evidence global untuk step ini');
    }

    public function test_admin_only_reviews_lops_they_assigned_while_super_admin_reviews_all(): void
    {
        $adminA = User::factory()->role(UserRole::ADMIN->value)->create();
        $adminB = User::factory()->role(UserRole::ADMIN->value)->create();
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lopA = $this->makeLop($adminA);
        $lopB = $this->makeLop($adminB);
        $this->assignLop($lopA, $adminA, $technician);
        $this->assignLop($lopB, $adminB, $technician);

        $evidenceA = QeEvidence::create([
            'qe_lop_id' => $lopA->id_qe_lops,
            'uploaded_by' => $technician->id_user,
            'step' => 'BEFORE',
            'type' => 'PHOTO',
            'file_path' => 'evidences/a.jpg',
            'status' => 'pending',
        ]);
        $evidenceB = QeEvidence::create([
            'qe_lop_id' => $lopB->id_qe_lops,
            'uploaded_by' => $technician->id_user,
            'step' => 'AFTER',
            'type' => 'PHOTO',
            'file_path' => 'evidences/b.jpg',
            'status' => 'pending',
        ]);

        $this->actingAs($adminA)
            ->get(route('evidence-approval.index'))
            ->assertViewHas('lops', fn ($lops) => $lops->count() === 1
                && $lops->first()->is($lopA));
        $this->actingAs($adminA)
            ->get(route('evidence-approval.lop.review', $lopA))
            ->assertOk()
            ->assertSee('Survey &amp; Material', false);
        $this->actingAs($adminA)
            ->get(route('evidence-approval.lop.review', $lopB))
            ->assertForbidden();
        $this->actingAs($adminA)
            ->post(route('evidence-approval.approve', $evidenceB))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('evidence-approval.index'))
            ->assertViewHas('lops', fn ($lops) => $lops->count() === 2);
        $this->actingAs($superAdmin)
            ->get(route('evidence-approval.lop.review', $lopB))
            ->assertOk();
        $this->actingAs($adminA)
            ->post(route('evidence-approval.approve', $evidenceA))
            ->assertRedirect();
    }

    public function test_step_review_groups_multiple_photos_by_category_and_designator(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = $this->makeLop($admin);
        $this->assignLop($lop, $admin, $technician);
        $designator = Designator::create([
            'code' => 'ODP-CLOSURE-01',
            'item_name' => 'Optical Distribution Point Closure',
            'unit' => 'unit',
<<<<<<< HEAD
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
=======
            'type' => 'material',
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
            'created_by' => $admin->id_user,
        ]);

        $evidences = collect(['before-wide.jpg', 'before-close.jpg'])->map(fn (string $fileName) => QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'designator_id' => $designator->id_designator,
            'uploaded_by' => $technician->id_user,
            'step' => 'BEFORE',
            'type' => 'PHOTO',
            'category' => 'before',
            'file_path' => "evidences/{$fileName}",
            'metadata' => ['original_name' => $fileName, 'mime' => 'image/jpeg'],
            'status' => 'pending',
        ]));

        $response = $this->actingAs($admin)
            ->get(route('evidence-approval.lop.review', [$lop, 'step' => 2]))
            ->assertOk()
            ->assertSee('Evidence Before · ODP-CLOSURE-01')
            ->assertSee('Optical Distribution Point Closure')
            ->assertSee('before-wide.jpg')
            ->assertSee('before-close.jpg')
            ->assertSee('Klik foto untuk preview')
            ->assertSee("evidence-preview-{$evidences->first()->id_evidence}", false)
            ->assertSee("evidence-detail-{$evidences->first()->id_evidence}", false);

        $this->assertSame(1, substr_count($response->getContent(), 'Evidence Before · ODP-CLOSURE-01'));
    }

    public function test_only_authorized_reviewer_can_reset_a_reviewed_evidence(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $otherAdmin = User::factory()->role(UserRole::ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = $this->makeLop($admin);
        $this->assignLop($lop, $admin, $technician);
        $evidence = QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'uploaded_by' => $technician->id_user,
            'step' => 'PROGRESS',
            'type' => 'PHOTO',
            'category' => 'progress',
            'file_path' => 'evidences/reviewed.jpg',
            'status' => 'approved',
            'reviewed_by' => $admin->id_user,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($otherAdmin)
            ->post(route('evidence-approval.reset', $evidence))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('evidence-approval.reset', $evidence))
            ->assertRedirect();

        $this->assertSame('pending', $evidence->fresh()->status->value);

        $this->actingAs($admin)
            ->post(route('evidence-approval.reset', $evidence))
            ->assertForbidden();
    }
}
