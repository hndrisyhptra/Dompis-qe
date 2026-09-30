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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidencePermissionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $defaultBranch;

    private ServiceArea $defaultServiceArea;

    protected function setUp(): void
    {
        parent::setUp();

        $region = Region::query()->where('code', 'JATIM')->firstOrFail();
        $this->defaultBranch = Branch::create([
            'code' => 'EVD-SDA',
            'name' => 'SIDOARJO EVIDENCE',
            'region' => $region->name,
            'region_id' => $region->id_region,
            'is_active' => true,
        ]);
        $this->defaultServiceArea = ServiceArea::create([
            'workzone' => 'EVD-SDA',
            'name' => 'Sidoarjo Evidence',
            'branch_id' => $this->defaultBranch->id_branch,
            'region_id' => $region->id_region,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(array $attributes = []): User
    {
        return User::factory()->role(UserRole::ADMIN->value)->create(array_merge([
            'admin_scope_type' => AdminScopeType::BRANCH->value,
            'branch_id' => $this->defaultBranch->id_branch,
        ], $attributes));
    }

    private function makeBranch(string $code, string $name, string $regionCode = 'JATIM'): Branch
    {
        $region = Region::query()->where('code', $regionCode)->firstOrFail();
        $branch = Branch::create([
            'code' => $code,
            'name' => $name,
            'region' => $region->name,
            'region_id' => $region->id_region,
            'is_active' => true,
        ]);
        ServiceArea::create([
            'workzone' => $code,
            'name' => $name,
            'branch_id' => $branch->id_branch,
            'region_id' => $region->id_region,
            'is_active' => true,
        ]);

        return $branch;
    }

    private function makeLop(User $admin, string $status = 'assigned'): QeLop
    {
        $branch = Branch::query()->find($admin->branch_id) ?? $this->defaultBranch;
        $serviceArea = ServiceArea::query()->where('branch_id', $branch->id_branch)->first();

        return QeLop::create([
            'incident' => 'LOP-EVD-'.uniqid(),
            'nama_lop' => 'Evidence Test',
            'program_type' => 'recovery',
            'sto' => $serviceArea?->workzone,
            'branch' => $branch->name,
            'branch_id' => $branch->id_branch,
            'service_area_id' => $serviceArea?->id_service_area,
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

    private function makeEvidence(QeLop $lop, User $technician, string $fileName): QeEvidence
    {
        return QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'uploaded_by' => $technician->id_user,
            'step' => 'BEFORE',
            'type' => 'PHOTO',
            'category' => 'before',
            'file_path' => "evidences/{$fileName}",
            'status' => 'pending',
        ]);
    }

    public function test_assigned_teknisi_can_upload_evidence_but_unassigned_teknisi_cannot(): void
    {
        Storage::fake('public');

        $admin = $this->makeAdmin();
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
        $admin = $this->makeAdmin();
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
        $admin = $this->makeAdmin();
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
            ->get(route('evidence-approval.lop.review', [$lop, 'step' => 4]))
            ->assertOk()
            ->assertSee('2 file')
            ->assertSee('Evidence global untuk step ini');
    }

    public function test_admin_reviews_all_lops_in_branch_scope_regardless_of_who_assigned_them(): void
    {
        $adminA = $this->makeAdmin();
        $adminB = $this->makeAdmin();
        $outsideBranch = $this->makeBranch('EVD-DPS', 'DENPASAR EVIDENCE', 'BALNUS');
        $outsideAdmin = $this->makeAdmin(['branch_id' => $outsideBranch->id_branch]);
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();

        $lopA = $this->makeLop($adminA);
        $lopB = $this->makeLop($adminB);
        $outsideLop = $this->makeLop($outsideAdmin);
        $this->assignLop($lopA, $adminA, $technician);
        $this->assignLop($lopB, $adminB, $technician);
        $this->assignLop($outsideLop, $outsideAdmin, $technician);

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
        $outsideEvidence = QeEvidence::create([
            'qe_lop_id' => $outsideLop->id_qe_lops,
            'uploaded_by' => $technician->id_user,
            'step' => 'AFTER',
            'type' => 'PHOTO',
            'file_path' => 'evidences/outside.jpg',
            'status' => 'pending',
        ]);

        $this->actingAs($adminA)
            ->get(route('evidence-approval.index'))
            ->assertViewHas('lops', fn ($lops) => $lops->count() === 2
                && $lops->contains(fn (QeLop $lop) => $lop->is($lopA))
                && $lops->contains(fn (QeLop $lop) => $lop->is($lopB)));
        $this->actingAs($adminA)
            ->get(route('evidence-approval.lop.review', $lopB))
            ->assertOk()
            ->assertSee('Reservasi &amp; Lokasi', false);
        $this->actingAs($adminA)
            ->get(route('evidence-approval.lop.review', $outsideLop))
            ->assertForbidden();
        $this->actingAs($adminA)
            ->post(route('evidence-approval.approve', $outsideEvidence))
            ->assertForbidden();
        $this->actingAs($adminA)
            ->post(route('evidence-approval.approve', $evidenceB))
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->get(route('evidence-approval.index'))
            ->assertViewHas('lops', fn ($lops) => $lops->count() === 2);
        $this->actingAs($superAdmin)
            ->get(route('evidence-approval.lop.review', $outsideLop))
            ->assertOk();
        $this->actingAs($adminA)
            ->post(route('evidence-approval.approve', $evidenceA))
            ->assertRedirect();
    }

    public function test_area_region_and_branch_admins_can_review_approve_and_reject_evidence_in_scope(): void
    {
        $area = Area::create(['code' => 'EVD-A3', 'name' => 'Area Evidence', 'is_active' => true]);
        $jatim = Region::query()->where('code', 'JATIM')->firstOrFail();
        $jatim->update(['area_id' => $area->id_area]);

        $secondBranch = $this->makeBranch('EVD-SBY', 'SURABAYA EVIDENCE');
        $outsideBranch = $this->makeBranch('EVD-DPS', 'DENPASAR EVIDENCE', 'BALNUS');
        $defaultOwner = $this->makeAdmin();
        $secondOwner = $this->makeAdmin(['branch_id' => $secondBranch->id_branch]);
        $outsideOwner = $this->makeAdmin(['branch_id' => $outsideBranch->id_branch]);
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();

        $defaultLop = $this->makeLop($defaultOwner);
        $secondLop = $this->makeLop($secondOwner);
        $outsideLop = $this->makeLop($outsideOwner);
        $this->assignLop($defaultLop, $defaultOwner, $technician);
        $this->assignLop($secondLop, $secondOwner, $technician);
        $this->assignLop($outsideLop, $outsideOwner, $technician);

        $defaultEvidence = $this->makeEvidence($defaultLop, $technician, 'default.jpg');
        $secondEvidenceForArea = $this->makeEvidence($secondLop, $technician, 'second-area.jpg');
        $secondEvidenceForRegion = $this->makeEvidence($secondLop, $technician, 'second-region.jpg');
        $outsideEvidence = $this->makeEvidence($outsideLop, $technician, 'outside.jpg');

        $areaAdmin = $this->makeAdmin([
            'admin_scope_type' => AdminScopeType::AREA->value,
            'area_id' => $area->id_area,
            'branch_id' => null,
        ]);
        $regionAdmin = $this->makeAdmin([
            'admin_scope_type' => AdminScopeType::REGION->value,
            'region_id' => $jatim->id_region,
            'branch_id' => null,
        ]);
        $branchAdmin = $this->makeAdmin();

        foreach ([$areaAdmin, $regionAdmin] as $admin) {
            $this->actingAs($admin)
                ->get(route('evidence-approval.index'))
                ->assertOk()
                ->assertViewHas('lops', fn ($lops) => $lops->count() === 2
                    && $lops->contains(fn (QeLop $lop) => $lop->is($defaultLop))
                    && $lops->contains(fn (QeLop $lop) => $lop->is($secondLop)));
        }

        $this->actingAs($branchAdmin)
            ->get(route('evidence-approval.index'))
            ->assertViewHas('lops', fn ($lops) => $lops->count() === 1
                && $lops->first()->is($defaultLop));

        $this->actingAs($areaAdmin)
            ->post(route('evidence-approval.approve', $secondEvidenceForArea))
            ->assertRedirect();
        $this->actingAs($regionAdmin)
            ->post(route('evidence-approval.reject', $secondEvidenceForRegion), ['review_note' => 'Foto kurang jelas.'])
            ->assertRedirect();
        $this->actingAs($branchAdmin)
            ->post(route('evidence-approval.approve', $defaultEvidence))
            ->assertRedirect();

        $this->actingAs($branchAdmin)
            ->get(route('evidence-approval.lop.review', $secondLop))
            ->assertForbidden();
        $this->actingAs($areaAdmin)
            ->post(route('evidence-approval.reject', $outsideEvidence), ['review_note' => 'Di luar scope.'])
            ->assertForbidden();

        $this->assertSame('approved', $secondEvidenceForArea->fresh()->status->value);
        $this->assertSame('rejected', $secondEvidenceForRegion->fresh()->status->value);
        $this->assertSame('approved', $defaultEvidence->fresh()->status->value);
        $this->assertSame('pending', $outsideEvidence->fresh()->status->value);
    }

    public function test_step_review_groups_multiple_photos_by_category_and_designator(): void
    {
        $admin = $this->makeAdmin();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = $this->makeLop($admin);
        $this->assignLop($lop, $admin, $technician);
        $designator = Designator::create([
            'code' => 'ODP-CLOSURE-01',
            'item_name' => 'Optical Distribution Point Closure',
            'unit' => 'unit',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
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
            ->get(route('evidence-approval.lop.review', [$lop, 'step' => 3]))
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
        $admin = $this->makeAdmin();
        $outsideBranch = $this->makeBranch('EVD-SBY', 'SURABAYA EVIDENCE');
        $otherAdmin = $this->makeAdmin(['branch_id' => $outsideBranch->id_branch]);
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

    public function test_review_opens_at_first_step_that_still_has_pending_evidence(): void
    {
        $admin = $this->makeAdmin();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = $this->makeLop($admin, 'waiting_approval');
        $this->assignLop($lop, $admin, $technician);

        // Step 4 (progress) sudah approved, step 5 (after) masih pending.
        QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops, 'uploaded_by' => $technician->id_user,
            'step' => 'PROGRESS', 'type' => 'PHOTO', 'category' => 'progress',
            'file_path' => 'evidences/p.jpg', 'status' => 'approved',
        ]);
        QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops, 'uploaded_by' => $technician->id_user,
            'step' => 'AFTER', 'type' => 'PHOTO', 'category' => 'after',
            'file_path' => 'evidences/a.jpg', 'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('evidence-approval.lop.review', $lop))
            ->assertOk()
            ->assertViewHas('currentStep', 5);

        // ?step= eksplisit tetap dihormati.
        $this->actingAs($admin)
            ->get(route('evidence-approval.lop.review', [$lop, 'step' => 2]))
            ->assertViewHas('currentStep', 2);
    }

    public function test_reuploaded_evidence_after_reject_is_flagged_on_the_index(): void
    {
        $admin = $this->makeAdmin();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = $this->makeLop($admin, 'waiting_approval');
        $this->assignLop($lop, $admin, $technician);

        QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops, 'uploaded_by' => $technician->id_user,
            'step' => 'AFTER', 'type' => 'PHOTO', 'category' => 'after',
            'file_path' => 'evidences/fixed.jpg', 'status' => 'pending',
            'metadata' => ['original_name' => 'fixed.jpg', 'replaced_at' => now()->toIso8601String()],
        ]);

        $this->actingAs($admin)
            ->get(route('evidence-approval.index'))
            ->assertOk()
            ->assertSee('evidence diperbaiki teknisi')
            ->assertViewHas('lops', fn ($lops) => (int) $lops->first()->reuploaded_pending_count === 1);
    }
}
