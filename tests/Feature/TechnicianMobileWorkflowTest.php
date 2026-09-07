<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Designator;
use App\Models\DesignatorType;
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
            'program_type' => 'recovery',
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
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
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
            ->assertSee('Evidence Material Tiba');

        $this->actingAs($technician)
            ->get(route('technician.projects.show', [$lop, 'step' => 3]))
            ->assertOk()
            ->assertSee('Evidence Pra')
            ->assertSee('Tag lokasi pekerjaan');
    }

    public function test_step_pra_needs_location_pre_and_insera_not_per_designator(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        $type = DesignatorType::where('code', 'MATERIAL')->value('id_designator_type');
        $d1 = Designator::create(['code' => 'M-PRA-1', 'item_name' => 'ODP', 'unit' => 'pcs', 'designator_type_id' => $type]);
        $d2 = Designator::create(['code' => 'M-PRA-2', 'item_name' => 'Closure', 'unit' => 'pcs', 'designator_type_id' => $type]);

        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));
        $this->actingAs($technician)->put(route('technician.projects.materials', $lop), [
            'items' => [
                ['designator_id' => $d1->id_designator, 'qty' => 2],
                ['designator_id' => $d2->id_designator, 'qty' => 3],
            ],
        ]);
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'material_arrival', 'type' => 'PHOTO',
            'files' => [UploadedFile::fake()->image('m.jpg')],
        ]);
        $this->actingAs($technician)->put(route('technician.projects.location', $lop), [
            'latitude' => -6.2, 'longitude' => 106.8, 'accuracy' => 8, 'location_source' => 'gps',
        ]);

        $progress = app(ProjectProgressService::class);

        // Lokasi ada, foto pra belum -> step 3 belum lengkap.
        $this->assertFalse($progress->summary($lop->fresh())['steps'][3]);

        // Foto pra global saja belum cukup -> masih butuh capture Insera.
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'pre', 'type' => 'PHOTO',
            'files' => [UploadedFile::fake()->image('pra.jpg')],
        ]);
        $this->assertFalse($progress->summary($lop->fresh())['steps'][3]);

        // + capture tiket Insera (global, tanpa designator) -> step 3 lengkap.
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'insera', 'type' => 'PHOTO',
            'files' => [UploadedFile::fake()->image('insera.jpg')],
        ]);
        $this->assertTrue($progress->summary($lop->fresh())['steps'][3]);

        $this->actingAs($technician)
            ->post(route('technician.projects.survey-complete', $lop))
            ->assertRedirect();
        $this->assertSame('progress', $lop->fresh()->status_lop->value);
    }

    public function test_material_arrival_is_its_own_step_two_and_gates_evidence_pra(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        $designator = Designator::create([
            'code' => 'M-MOBILE-STEP2',
            'item_name' => 'ODP',
            'unit' => 'pcs',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);

        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));
        $this->actingAs($technician)->put(route('technician.projects.materials', $lop), [
            'items' => [['designator_id' => $designator->id_designator, 'qty' => 2]],
        ]);

        $progress = app(ProjectProgressService::class);

        // Setelah reservasi: 1/5 step, step "Material Tiba" belum lengkap.
        $summary = $progress->summary($lop->fresh());
        $this->assertSame(5, $summary['total_steps']);
        $this->assertSame(1, $summary['completed_steps']);
        $this->assertFalse($summary['steps'][2]);

        // Upload material_arrival -> redirect ke step 2, step 2 jadi lengkap.
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'material_arrival',
            'type' => 'PHOTO',
            'files' => [UploadedFile::fake()->image('material.jpg')],
        ])->assertRedirect(route('technician.projects.show', [$lop, 'step' => 2]));

        $summary = $progress->summary($lop->fresh());
        $this->assertSame(2, $summary['completed_steps']);
        $this->assertTrue($summary['steps'][2]);
        $this->assertSame(40, $summary['percentage']);
    }

    public function test_material_usage_recap_rejects_qty_above_reservation_and_computes_sisa(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        $type = DesignatorType::where('code', 'MATERIAL')->value('id_designator_type');
        $d = Designator::create(['code' => 'M-USE', 'item_name' => 'Kabel', 'unit' => 'meter', 'designator_type_id' => $type]);

        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));
        $this->actingAs($technician)->put(route('technician.projects.materials', $lop), [
            'items' => [['designator_id' => $d->id_designator, 'qty' => 10]],
        ]);

        // Terpakai > reservasi -> ditolak.
        $this->actingAs($technician)->put(route('technician.projects.material-usage', $lop), [
            'usage' => [['designator_id' => $d->id_designator, 'qty_actual' => 12]],
        ])->assertSessionHasErrors('usage.0.qty_actual');

        // Terpakai < reservasi -> tersimpan, sisa dihitung.
        $this->actingAs($technician)->put(route('technician.projects.material-usage', $lop), [
            'usage' => [['designator_id' => $d->id_designator, 'qty_actual' => 6.5]],
        ])->assertRedirect();

        $item = $lop->materialReservation->items()->first();
        $this->assertSame('6.500', $item->qty_actual);
        $this->assertEqualsWithDelta(3.5, $item->sisa(), 0.001);
    }

    public function test_progress_evidence_is_required_per_reserved_designator(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        $type = DesignatorType::where('code', 'MATERIAL')->value('id_designator_type');
        $d1 = Designator::create(['code' => 'M-PROG-1', 'item_name' => 'ODP', 'unit' => 'pcs', 'designator_type_id' => $type]);
        $d2 = Designator::create(['code' => 'M-PROG-2', 'item_name' => 'Closure', 'unit' => 'pcs', 'designator_type_id' => $type]);

        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));
        $this->actingAs($technician)->put(route('technician.projects.materials', $lop), [
            'items' => [
                ['designator_id' => $d1->id_designator, 'qty' => 1],
                ['designator_id' => $d2->id_designator, 'qty' => 1],
            ],
        ]);

        // Progress harus punya designator_id.
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'progress', 'type' => 'PHOTO',
            'files' => [UploadedFile::fake()->image('p.jpg')],
        ])->assertSessionHasErrors('designator_id');

        // Upload progress untuk d1 saja -> step 4 belum lengkap.
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'progress', 'type' => 'PHOTO', 'designator_id' => $d1->id_designator,
            'files' => [UploadedFile::fake()->image('p1.jpg')],
        ]);
        $this->assertFalse(app(ProjectProgressService::class)->summary($lop->fresh())['steps'][4]);

        // Lengkapi d2 -> step 4 lengkap.
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'progress', 'type' => 'PHOTO', 'designator_id' => $d2->id_designator,
            'files' => [UploadedFile::fake()->image('p2.jpg')],
        ]);
        $this->assertTrue(app(ProjectProgressService::class)->summary($lop->fresh())['steps'][4]);
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
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
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

    public function test_complete_five_step_workflow_can_be_submitted_for_approval(): void
    {
        Storage::fake('public');
        [$admin, $technician, $lop] = $this->assignedProject();
        $designator = Designator::create([
            'code' => 'M-MOBILE-03',
            'item_name' => 'Kabel Distribusi',
            'unit' => 'meter',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
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
            ['insera', null],
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
            ['progress', $designator->id_designator],
            ['after', $designator->id_designator],
        ] as [$category, $designatorId]) {
            $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
                'category' => $category,
                'type' => 'PHOTO',
                'designator_id' => $designatorId,
                'files' => [UploadedFile::fake()->image("{$category}.jpg")],
            ]);
        }

        // Belum rekap material -> step 5 belum lengkap.
        $this->assertSame(80, app(ProjectProgressService::class)->summary($lop->fresh())['percentage']);

        $this->actingAs($technician)->put(route('technician.projects.material-usage', $lop), [
            'usage' => [['designator_id' => $designator->id_designator, 'qty_actual' => 3]],
        ])->assertRedirect();

        $this->assertSame('3.000', $lop->materialReservation->items()->first()->qty_actual);
        $this->assertEqualsWithDelta(2.0, $lop->materialReservation->items()->first()->sisa(), 0.001);

        $summary = app(ProjectProgressService::class)->summary($lop->fresh());
        $this->assertSame(100, $summary['percentage']);
        $this->assertSame('waiting_review', $summary['review_key']);

        $this->actingAs($technician)
            ->get(route('technician.inbox'))
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Waiting Review');

        $this->actingAs($technician)
            ->get(route('technician.projects.show', [$lop, 'step' => 5]))
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
