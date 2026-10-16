<?php

namespace Tests\Feature;

use App\Enums\EvidenceCategory;
use App\Enums\ProgramType;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\User;
use App\Services\EvidenceService;
use App\Services\ProjectProgressService;
use App\Services\TechnicianWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaterialArrivalPerDesignatorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tech;

    private QeLop $lop;

    private array $designators;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('evidence.disk', 'public'));
        $this->admin = User::factory()->role('SUPER_ADMIN')->create();
        $this->tech = User::factory()->role('TEKNISI')->create();
        $this->lop = QeLop::create([
            'incident' => 'ARRIVAL-TEST', 'nama_lop' => 'Material Arrival Test',
            'program_type' => 'relok_utilitas', 'status_lop' => 'survey', 'created_by' => $this->admin->getKey(),
        ]);
        $this->lop->assignments()->create([
            'technician_id' => $this->tech->getKey(), 'assigned_by' => $this->admin->getKey(),
            'assigned_at' => now(), 'status' => 'active',
        ]);
        $boq = $this->lop->boq()->create(['source' => 'import', 'status' => 'ready', 'created_by' => $this->admin->getKey()]);
        $reservation = $this->lop->materialReservation()->create(['technician_id' => $this->tech->getKey(), 'status' => 'draft']);
        foreach (['M-ONE', 'M-TWO', 'J-CO-OF', 'M-ZERO', 'M-EXTRA'] as $code) {
            $type = str_starts_with($code, 'M-') ? 'MATERIAL' : 'JASA';
            $d = $this->designators[$code] = Designator::create([
                'code' => $code, 'item_name' => 'Master '.$code, 'unit' => 'pcs',
                'designator_type_id' => DesignatorType::where('code', $type)->value('id_designator_type'),
            ]);
            if ($code !== 'M-EXTRA') {
                $boq->items()->create([
                    'designator_id' => $d->getKey(), 'designator_code' => $code, 'item_name' => 'Snapshot '.$code,
                    'type' => $type, 'unit' => 'pcs', 'qty' => $code === 'M-ZERO' ? 0 : 2,
                    'unit_price' => 100, 'total_price' => $code === 'M-ZERO' ? 0 : 200,
                ]);
            }
            $reservation->items()->create(['designator_id' => $d->getKey(), 'qty' => 2]);
        }
    }

    private function evidence(?string $code = null, string $status = 'pending'): QeEvidence
    {
        return $this->lop->evidences()->create([
            'uploaded_by' => $this->tech->getKey(), 'category' => 'material_arrival', 'step' => 'BEFORE',
            'type' => 'PHOTO', 'designator_id' => $code ? $this->designators[$code]->getKey() : null,
            'status' => $status, 'file_path' => 'arrival/'.uniqid().'.jpg',
        ]);
    }

    private function upload(?string $code = null): TestResponse
    {
        return $this->actingAs($this->tech)->postJson(route('technician.projects.evidence.file', $this->lop), [
            'category' => 'material_arrival', 'type' => 'PHOTO',
            'designator_id' => $code ? $this->designators[$code]->getKey() : null,
            'file' => UploadedFile::fake()->image('material.jpg'),
        ]);
    }

    public function test_utilitas_requires_all_positive_plan_materials_not_global_photos_reservation_extras_or_services(): void
    {
        $this->evidence();
        $workflow = app(TechnicianWorkflowService::class);
        $state = $workflow->state($this->lop->fresh());
        $this->assertTrue($state['materialArrivalPerItem']);
        $this->assertSame(['M-ONE', 'M-TWO'], $state['materialArrivalItems']->pluck('designator_code')->all());
        $this->assertFalse($state['step2Complete']);
        $this->evidence('M-ONE');
        $this->assertFalse($workflow->state($this->lop->fresh())['step2Complete']);
        $second = $this->evidence('M-TWO', 'rejected');
        $this->assertFalse($workflow->state($this->lop->fresh())['step2Complete']);
        $this->assertFalse(app(ProjectProgressService::class)->summary($this->lop->fresh())['steps'][2]);
        $this->actingAs($this->tech)->get(route('technician.projects.show', [$this->lop, 'step' => 3]))
            ->assertRedirect(route('technician.projects.show', [$this->lop, 'step' => 2]));
        $second->update(['status' => 'pending']);
        $this->assertTrue($workflow->state($this->lop->fresh())['step2Complete']);
        $this->assertTrue(app(ProjectProgressService::class)->summary($this->lop->fresh())['steps'][2]);
    }

    public function test_async_and_multiple_uploads_keep_material_designator_and_original_files(): void
    {
        $this->upload('M-ONE')->assertSuccessful();
        $this->actingAs($this->tech)->post(route('technician.projects.evidence', $this->lop), [
            'category' => 'material_arrival', 'type' => 'PHOTO', 'designator_id' => $this->designators['M-TWO']->getKey(),
            'files' => [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, $this->lop->evidences()->count());
        foreach ($this->lop->evidences as $e) {
            $this->assertNotNull($e->designator_id);
            Storage::disk(config('evidence.disk', 'public'))->assertExists($e->file_path);
        }
        $this->assertTrue(app(TechnicianWorkflowService::class)->state($this->lop->fresh())['step2Complete']);
    }

    public function test_upload_rejects_missing_service_zero_volume_and_non_plan_material_even_when_reserved(): void
    {
        foreach ([null, 'J-CO-OF', 'M-ZERO', 'M-EXTRA'] as $code) {
            $this->upload($code)->assertUnprocessable()->assertJsonValidationErrors('designator_id');
        }
        $this->assertDatabaseCount('qe_evidences', 0);
        $this->assertSame([], Storage::disk(config('evidence.disk', 'public'))->allFiles());
        // Jalur multi-file juga tidak boleh melewati aturan yang sama.
        $this->actingAs($this->tech)->post(route('technician.projects.evidence', $this->lop), [
            'category' => 'material_arrival', 'type' => 'PHOTO', 'files' => [UploadedFile::fake()->image('invalid.jpg')],
        ])->assertSessionHasErrors('designator_id');
        $this->upload('M-ONE')->assertSuccessful();
        $other = User::factory()->role('TEKNISI')->create();
        $this->actingAs($other)->postJson(route('technician.projects.evidence.file', $this->lop), [
            'category' => 'material_arrival', 'type' => 'PHOTO', 'designator_id' => $this->designators['M-ONE']->getKey(),
            'file' => UploadedFile::fake()->image('forbidden.jpg'),
        ])->assertForbidden();
    }

    public function test_missing_plan_blocks_checklist_and_explains_how_to_proceed_without_creating_data(): void
    {
        $this->lop->boq->delete();
        $this->evidence();
        $this->assertFalse(app(TechnicianWorkflowService::class)->state($this->lop->fresh())['step2Complete']);
        $this->upload('M-ONE')->assertUnprocessable()->assertJsonValidationErrors('designator_id');
        $this->actingAs($this->tech)->get(route('technician.projects.show', [$this->lop, 'step' => 2]))
            ->assertOk()->assertSee('Hubungi admin untuk melengkapi BOQ Plan')->assertSee('Dokumentasi lama');
        $this->assertSame(1, $this->lop->evidences()->count());
    }

    public function test_technician_and_admin_review_show_grouped_item_photos_and_keep_legacy_documentation(): void
    {
        $this->evidence();
        $this->evidence('M-ONE');
        $this->evidence('M-ONE');
        $this->actingAs($this->tech)->get(route('technician.projects.show', [$this->lop, 'step' => 2]))
            ->assertOk()->assertSee('Material tiba · M-ONE')->assertSee('Material tiba · M-TWO')
            ->assertSee('Snapshot M-ONE')->assertSee('Dokumentasi lama')->assertDontSee('Material tiba · J-CO-OF');
        $this->lop->update(['status_lop' => 'waiting_approval']);
        $this->actingAs($this->tech)->get(route('technician.projects.show', [$this->lop, 'step' => 2]))
            ->assertOk()->assertSee('Material tiba · M-ONE')->assertSee('Material tiba · M-TWO');
        $this->actingAs($this->admin)->get(route('evidence-approval.lop.review', [$this->lop, 'step' => 2]))
            ->assertOk()->assertSee('Checklist material BOQ Plan')->assertSee('Snapshot M-TWO')
            ->assertSee('tidak memenuhi checklist per item BOQ Plan');
    }

    public function test_admin_cannot_complete_review_using_only_global_or_partial_plan_arrival(): void
    {
        $this->lop->update(['status_lop' => 'waiting_approval']);
        $this->evidence(null, 'approved');
        $this->evidence('M-ONE', 'approved');
        $this->actingAs($this->admin)->post(route('evidence-approval.lop.complete', $this->lop))->assertSessionHasErrors('review');
        $this->assertSame('waiting_approval', $this->lop->fresh()->status_lop->value);
        $this->assertDatabaseCount('qe_lop_histories', 0);
        // Tidak ada jasa khusus pada kasus ini agar hanya menguji kelengkapan Material Tiba.
        $this->lop->boq->items()->where('type', 'JASA')->delete();
        $this->lop->materialReservation->items()->where('designator_id', $this->designators['J-CO-OF']->getKey())->delete();
        $this->evidence('M-TWO', 'approved');
        $this->post(route('evidence-approval.lop.complete', $this->lop))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $this->lop->fresh()->status_lop->value);
        $this->assertDatabaseCount('qe_lop_histories', 1);
    }

    public function test_rejected_per_item_photo_can_be_replaced_without_losing_its_designator(): void
    {
        $evidence = $this->evidence('M-ONE', 'rejected');
        $this->actingAs($this->tech)->putJson(route('technician.projects.evidence.replace', [$this->lop, $evidence]), [
            'file' => UploadedFile::fake()->image('replacement.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->designators['M-ONE']->getKey(), $evidence->fresh()->designator_id);
        $this->assertSame('pending', $evidence->fresh()->status->value);
        $this->assertSame(EvidenceCategory::MATERIAL_ARRIVAL, $evidence->fresh()->category);
        $this->assertDatabaseCount('qe_evidences', 1);
    }

    public function test_submission_is_blocked_when_other_steps_are_ready_but_a_plan_material_has_no_arrival_photo(): void
    {
        $this->lop->update(['status_lop' => 'progress']);
        $this->evidence('M-ONE');
        $this->lop->survey()->create(['latitude' => -7, 'longitude' => 112, 'captured_by' => $this->tech->getKey(), 'captured_at' => now()]);
        $this->lop->evidences()->create([
            'category' => 'pre', 'step' => 'SURVEY', 'type' => 'PHOTO', 'status' => 'pending',
            'uploaded_by' => $this->tech->getKey(), 'file_path' => 'arrival/pre.jpg',
        ]);
        foreach ($this->lop->materialReservation->items as $item) {
            $item->update(['qty_actual' => 2]);
            foreach (['progress', 'after'] as $category) {
                $this->lop->evidences()->create([
                    'category' => $category, 'step' => strtoupper($category), 'type' => 'PHOTO', 'status' => 'pending',
                    'designator_id' => $item->designator_id, 'uploaded_by' => $this->tech->getKey(), 'file_path' => "arrival/{$category}.jpg",
                ]);
            }
        }
        $state = app(TechnicianWorkflowService::class)->state($this->lop->fresh());
        $this->assertTrue($state['step4Complete']);
        $this->assertTrue($state['step5Complete']);
        $this->actingAs($this->tech)->post(route('technician.projects.submit', $this->lop))->assertSessionHasErrors('workflow');
        $this->assertSame('progress', $this->lop->fresh()->status_lop->value);
        $this->evidence('M-TWO');
        $this->post(route('technician.projects.submit', $this->lop))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('waiting_approval', $this->lop->fresh()->status_lop->value);
    }

    public function test_storage_service_rechecks_latest_plan_before_writing_any_file(): void
    {
        $staleLop = $this->lop->load('boq.items');
        $staleLop->boq->items()->where('designator_id', $this->designators['M-ONE']->getKey())->delete();
        try {
            app(EvidenceService::class)->upload($staleLop, [
                'category' => 'material_arrival', 'step' => 'BEFORE', 'type' => 'PHOTO',
                'designator_id' => $this->designators['M-ONE']->getKey(),
            ], UploadedFile::fake()->image('stale.jpg'), $this->tech);
            $this->fail('Designator yang sudah dihapus dari BOQ Plan harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('designator_id', $exception->errors());
        }
        $this->assertDatabaseCount('qe_evidences', 0);
        $this->assertSame([], Storage::disk(config('evidence.disk', 'public'))->allFiles());
    }

    public static function otherPrograms(): array
    {
        return [[ProgramType::RECOVERY], [ProgramType::PREVENTIVE], [ProgramType::RELOK_TIANG]];
    }

    #[DataProvider('otherPrograms')]
    public function test_other_programs_keep_global_material_arrival(ProgramType $program): void
    {
        $this->lop->update(['program_type' => $program]);
        $this->upload()->assertSuccessful();
        $state = app(TechnicianWorkflowService::class)->state($this->lop->fresh());
        $this->assertFalse($state['materialArrivalPerItem']);
        $this->assertTrue($state['step2Complete']);
        $this->assertNull($this->lop->evidences()->first()->designator_id);
    }
}
