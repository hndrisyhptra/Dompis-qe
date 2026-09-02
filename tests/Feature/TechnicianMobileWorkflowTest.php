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
use App\Services\EvidenceService;
use App\Services\ProjectProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicianMobileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function assignedProject(): array
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = QeLop::create([
            'incident' => 'LOP-MOBILE-01',
            'nama_lop' => 'Project Mobile Teknisi',
            'wbs_type' => 'recovery',
            'status_lop' => 'assigned',
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

    public function test_only_technician_can_open_mobile_dashboard(): void
    {
        [$admin, $technician] = $this->assignedProject();

        $this->actingAs($technician)->get(route('technician.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('technician.dashboard'))->assertForbidden();
    }

    public function test_technician_is_redirected_from_legacy_lop_pages_to_mobile_workspace(): void
    {
        [, $technician, $lop] = $this->assignedProject();

        $this->actingAs($technician)->get('/')->assertRedirect(route('technician.dashboard'));
        $this->actingAs($technician)->get(route('lop.index'))->assertRedirect(route('technician.inbox'));
        $this->actingAs($technician)->get(route('lop.history'))
            ->assertRedirect(route('technician.inbox', ['tab' => 'complete']));
        $this->actingAs($technician)->get(route('lop.show', $lop))
            ->assertRedirect(route('technician.projects.show', $lop));
    }

    public function test_pickup_and_material_reservation_advance_project_to_survey(): void
    {
        [, $technician, $lop] = $this->assignedProject();
        $designator = Designator::create([
            'code' => 'M-MOBILE-01',
            'item_name' => 'Kabel Fiber Optik',
            'unit' => 'meter',
<<<<<<< HEAD
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
=======
            'type' => 'material',
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
        ]);

        $this->actingAs($technician)
            ->post(route('technician.projects.pickup', $lop))
            ->assertRedirect();
        $this->assertSame('picked_up', $lop->fresh()->status_lop->value);
        $this->actingAs($technician)
            ->get(route('technician.projects.show', [$lop, 'step' => 1]))
            ->assertOk()
            ->assertSee('Reservasi material');

        $this->actingAs($technician)
            ->put(route('technician.projects.materials', $lop), [
                'items' => [['designator_id' => $designator->id_designator, 'qty' => 12.5]],
            ])->assertRedirect();

        $this->assertSame('survey', $lop->fresh()->status_lop->value);
        $this->assertDatabaseHas('qe_material_reservation_items', [
            'designator_id' => $designator->id_designator,
            'qty' => 12.5,
        ]);
        $this->actingAs($technician)
            ->get(route('technician.projects.show', [$lop, 'step' => 2]))
            ->assertOk()
            ->assertSee('Evidence Pra');
    }

    public function test_multiple_evidence_upload_is_scoped_to_reserved_designator(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        $lop->update(['status_lop' => 'survey']);
        $designator = Designator::create([
            'code' => 'M-MOBILE-02',
            'item_name' => 'ODP',
            'unit' => 'pcs',
<<<<<<< HEAD
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
=======
            'type' => 'material',
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
        ]);
        $reservation = $lop->materialReservation()->create([
            'technician_id' => $technician->id_user,
            'status' => 'draft',
        ]);
        $reservation->items()->create(['designator_id' => $designator->id_designator, 'qty' => 1]);

        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'before',
            'type' => 'PHOTO',
            'designator_id' => $designator->id_designator,
            'files' => [
                UploadedFile::fake()->image('before-1.jpg'),
                UploadedFile::fake()->image('before-2.jpg'),
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('qe_evidences', 2);
        $this->assertDatabaseHas('qe_evidences', [
            'qe_lop_id' => $lop->id_qe_lops,
            'category' => 'before',
            'designator_id' => $designator->id_designator,
        ]);
    }

    public function test_rejected_evidence_can_be_replaced_without_creating_new_record(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        Storage::disk('public')->put('evidences/old.jpg', 'old-file');
        $evidence = QeEvidence::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'uploaded_by' => $technician->id_user,
            'step' => 'PROGRESS',
            'type' => 'PHOTO',
            'category' => 'progress',
            'file_path' => 'evidences/old.jpg',
            'metadata' => ['original_name' => 'old.jpg', 'mime' => 'image/jpeg', 'size' => 8],
            'status' => 'rejected',
            'review_note' => 'Foto terlalu gelap',
        ]);

        $this->actingAs($technician)->put(
            route('technician.projects.evidence.replace', [$lop, $evidence]),
            ['file' => UploadedFile::fake()->image('replacement.jpg')]
        )->assertRedirect();

        $fresh = $evidence->fresh();
        $this->assertSame('pending', $fresh->status->value);
        $this->assertNull($fresh->review_note);
        $this->assertSame('replacement.jpg', $fresh->metadata['original_name']);
        $this->assertDatabaseCount('qe_evidences', 1);
        Storage::disk('public')->assertMissing('evidences/old.jpg');
        Storage::disk('public')->assertExists($fresh->file_path);
    }

    public function test_evidence_gallery_shows_review_statuses_and_rejection_reason(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        $lop->update(['status_lop' => 'rejected']);

        foreach ([
            ['pending', 'pending.jpg', null],
            ['approved', 'approved.jpg', null],
            ['rejected', 'rejected.jpg', 'Foto terlalu gelap dan objek tidak terlihat jelas.'],
        ] as [$status, $fileName, $reason]) {
            QeEvidence::create([
                'qe_lop_id' => $lop->id_qe_lops,
                'uploaded_by' => $technician->id_user,
                'step' => 'PROGRESS',
                'type' => 'PHOTO',
                'category' => 'progress',
                'file_path' => "evidences/{$fileName}",
                'metadata' => ['original_name' => $fileName, 'mime' => 'image/jpeg', 'size' => 1024],
                'status' => $status,
                'review_note' => $reason,
            ]);
        }

        $this->actingAs($technician)
            ->get(route('technician.projects.show', $lop))
            ->assertOk()
            ->assertSee('Menunggu Review')
            ->assertSee('Disetujui')
            ->assertSee('Ditolak')
            ->assertSee('Foto terlalu gelap dan objek tidak terlihat jelas.')
            ->assertSee('Upload ulang evidence');

        $summary = app(ProjectProgressService::class)->summary($lop->fresh());
        $this->assertSame('rejected', $summary['review_key']);
        $this->assertSame('Reject', $summary['review_label']);
    }

    public function test_complete_four_step_workflow_can_be_submitted_for_approval(): void
    {
        Storage::fake('public');
        [$admin, $technician, $lop] = $this->assignedProject();
        $designator = Designator::create([
            'code' => 'M-MOBILE-03',
            'item_name' => 'Kabel Distribusi',
            'unit' => 'meter',
<<<<<<< HEAD
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
=======
            'type' => 'material',
>>>>>>> a86f15e45cd25dd3304798754e1cd5bfc0ffbc8c
        ]);

        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));
        $this->actingAs($technician)->put(route('technician.projects.materials', $lop), [
            'items' => [['designator_id' => $designator->id_designator, 'qty' => 5]],
        ]);
        $this->actingAs($technician)->put(route('technician.projects.location', $lop), [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'accuracy' => 8,
            'location_source' => 'gps',
        ]);

        foreach ([
            ['pre', null],
            ['material_arrival', null],
            ['before', $designator->id_designator],
        ] as [$category, $designatorId]) {
            $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
                'category' => $category,
                'type' => 'PHOTO',
                'designator_id' => $designatorId,
                'files' => [UploadedFile::fake()->image("{$category}.jpg")],
            ]);
        }

        $this->actingAs($technician)
            ->post(route('technician.projects.survey-complete', $lop))
            ->assertRedirect();
        $this->assertSame('progress', $lop->fresh()->status_lop->value);

        foreach ([
            ['progress', null],
            ['after', $designator->id_designator],
        ] as [$category, $designatorId]) {
            $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
                'category' => $category,
                'type' => 'PHOTO',
                'designator_id' => $designatorId,
                'files' => [UploadedFile::fake()->image("{$category}.jpg")],
            ]);
        }

        $summary = app(ProjectProgressService::class)->summary($lop->fresh());
        $this->assertSame(100, $summary['percentage']);
        $this->assertSame('waiting_review', $summary['review_key']);

        $this->actingAs($technician)
            ->get(route('technician.inbox'))
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Waiting Review');

        $this->actingAs($technician)
            ->get(route('technician.projects.show', [$lop, 'step' => 4]))
            ->assertOk()
            ->assertSee('Ajukan Approval');

        $this->actingAs($technician)
            ->post(route('technician.projects.submit', $lop))
            ->assertRedirect();

        $this->assertSame('waiting_approval', $lop->fresh()->status_lop->value);

        foreach ($lop->evidences as $evidence) {
            app(EvidenceService::class)->approve($evidence, $admin);
        }

        $summary = app(ProjectProgressService::class)->summary($lop->fresh());
        $this->assertSame('approved', $summary['review_key']);
        $this->assertSame('Approve', $summary['review_label']);
    }
}
