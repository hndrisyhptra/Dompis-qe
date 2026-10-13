<?php

namespace Tests\Feature;

use App\Enums\LopStatus;
use App\Models\Branch;
use App\Models\QeBoq;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\QeMaterialReservation;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LopCancellationTest extends TestCase
{
    use RefreshDatabase;

    public static function statuses(): array
    {
        return array_map(fn ($status) => [$status->value], LopStatus::cases());
    }

    private function admin(): User
    {
        $branch = Branch::create(['code' => 'CANCEL', 'name' => 'Branch Cancel', 'is_active' => true]);

        return User::factory()->role('ADMIN')->create(['admin_scope_type' => 'branch', 'branch_id' => $branch->getKey()]);
    }

    private function lop(User $creator, string $status = 'draft', string $incident = 'INC-CANCEL'): QeLop
    {
        return QeLop::create([
            'incident' => $incident, 'nama_lop' => 'LOP '.$incident, 'program_type' => 'recovery',
            'status_lop' => $status, 'branch' => $creator->branch?->name, 'branch_id' => $creator->branch_id,
            'created_by' => $creator->getKey(),
        ]);
    }

    private function verification(QeLop $lop): array
    {
        return ['incident_confirmation' => $lop->incident, 'cancellation_reason' => 'Pekerjaan dibatalkan pelanggan.', 'confirmed' => '1'];
    }

    #[DataProvider('statuses')]
    public function test_admin_can_archive_cancelled_lop_at_any_status_while_preserving_children(string $status): void
    {
        Storage::fake(config('evidence.disk', 'public'));
        $admin = $this->admin();
        $tech = User::factory()->create();
        $lop = $this->lop($admin, $status);
        $assignment = $lop->assignments()->create(['technician_id' => $tech->getKey(), 'assigned_by' => $admin->getKey(), 'assigned_at' => now(), 'status' => 'active']);
        $boq = QeBoq::create(['qe_lop_id' => $lop->getKey(), 'source' => 'manual', 'status' => 'ready', 'created_by' => $admin->getKey(), 'grand_total' => 10000]);
        $reservation = QeMaterialReservation::create(['qe_lop_id' => $lop->getKey(), 'technician_id' => $tech->getKey(), 'status' => 'draft']);
        $evidence = QeEvidence::create(['qe_lop_id' => $lop->getKey(), 'uploaded_by' => $tech->getKey(), 'step' => 'PROGRESS', 'type' => 'PHOTO', 'category' => 'progress', 'file_path' => 'cancel/photo.jpg', 'status' => 'pending']);
        Storage::disk(config('evidence.disk', 'public'))->put($evidence->file_path, 'preserved-photo');

        $this->actingAs($admin)->get(route('data-lops.index'))->assertOk()->assertSee('Alasan pembatalan')->assertSee('incident_confirmation', false)->assertSee('Ya, Hapus LOP');
        $origin = route('data-lops.index', ['q' => $lop->incident]);
        $this->from($origin)->delete(route('data-lops.destroy', $lop), $this->verification($lop))->assertRedirect($origin)->assertSessionHasNoErrors();
        $this->assertSoftDeleted('qe_lops', ['id_qe_lops' => $lop->getKey()]);
        $this->assertSame($status, QeLop::withTrashed()->find($lop->getKey())->status_lop->value);
        $this->assertFalse($assignment->fresh()->status->value === 'active');
        $this->assertNotNull($assignment->fresh()->unassigned_at);
        $history = $lop->histories()->where('event_type', 'deleted')->sole();
        $this->assertSame($admin->getKey(), $history->user_id);
        $this->assertSame($status, $history->status_before);
        $this->assertStringContainsString('Pekerjaan dibatalkan pelanggan.', $history->note);
        $this->assertNotNull($boq->fresh());
        $this->assertNotNull($reservation->fresh());
        $this->assertNotNull($evidence->fresh());
        Storage::disk(config('evidence.disk', 'public'))->assertExists($evidence->file_path);
        $this->get(route('data-lops.index'))->assertOk()->assertDontSee($lop->incident);
        $this->get(route('lop.show', $lop))->assertNotFound();
        $this->delete(route('data-lops.destroy', $lop), $this->verification($lop))->assertNotFound();
        foreach (['view', 'approve', 'reject', 'resetReview', 'delete', 'replace'] as $ability) {
            $this->assertFalse(Gate::forUser($admin)->allows($ability, $evidence->fresh()));
        }
        $this->get(route('evidence-files.show', $evidence))->assertForbidden();
    }

    public function test_cancellation_requires_matching_incident_reason_and_confirmation(): void
    {
        $admin = $this->admin();
        $lop = $this->lop($admin, 'completed');
        $this->actingAs($admin)->delete(route('data-lops.destroy', $lop))->assertSessionHasErrors(['incident_confirmation', 'cancellation_reason', 'confirmed']);
        $this->delete(route('data-lops.destroy', $lop), array_merge($this->verification($lop), ['incident_confirmation' => 'OTHER-INCIDENT', 'confirmed' => '0']))->assertSessionHasErrors(['incident_confirmation', 'confirmed']);
        $this->assertNotNull($lop->fresh());
        $this->assertDatabaseCount('qe_lop_histories', 0);
    }

    public function test_other_roles_and_out_of_scope_admin_cannot_delete_but_super_admin_can(): void
    {
        $admin = $this->admin();
        $lop = $this->lop($admin, 'progress');
        foreach (['TEKNISI', 'MANAGER', 'APPROVER', 'ADMIN'] as $role) {
            $actor = User::factory()->role($role)->create();
            $this->actingAs($actor)->delete(route('data-lops.destroy', $lop), $this->verification($lop))->assertForbidden();
            $this->assertNotNull($lop->fresh());
        }
        $super = User::factory()->role('SUPER_ADMIN')->create();
        $this->actingAs($super)->delete(route('data-lops.destroy', $lop), $this->verification($lop))->assertRedirect();
        $this->assertSoftDeleted($lop);
    }

    public function test_archived_boq_is_hidden_and_cannot_be_modified_and_revenue_is_excluded(): void
    {
        $super = User::factory()->role('SUPER_ADMIN')->create();
        $lop = $this->lop($super, 'completed');
        $boq = QeBoq::create(['qe_lop_id' => $lop->getKey(), 'source' => 'manual', 'status' => 'ready', 'created_by' => $super->getKey(), 'grand_total' => 50000]);
        $this->actingAs($super)->get(route('revenue.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === 50000.0);
        $this->delete(route('data-lops.destroy', $lop), $this->verification($lop))->assertRedirect();
        $this->get(route('data-boqs.index'))->assertOk()->assertViewHas('boqs', fn ($boqs) => $boqs->isEmpty());
        $this->get(route('revenue.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === 0.0);
        $this->put(route('data-boqs.update', $boq))->assertForbidden();
        $this->delete(route('data-boqs.destroy', $boq))->assertNotFound();
        $this->assertNotNull($boq->fresh());
    }

    public function test_upload_with_stale_parent_does_not_store_file_after_cancellation(): void
    {
        Storage::fake(config('evidence.disk', 'public'));
        $admin = $this->admin();
        $lop = $this->lop($admin, 'progress');
        $this->actingAs($admin)->delete(route('data-lops.destroy', $lop), $this->verification($lop))->assertRedirect();
        try {
            app(EvidenceService::class)->upload($lop, ['step' => 'PROGRESS', 'type' => 'PHOTO', 'category' => 'progress'], UploadedFile::fake()->image('photo.jpg'), $admin);
            $this->fail('Upload tidak boleh berhasil setelah LOP diarsipkan.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('qe_evidences', 0);
            $this->assertSame([], Storage::disk(config('evidence.disk', 'public'))->allFiles());
        }
    }

    public function test_active_lop_list_and_counts_are_creator_only_without_narrowing_master_data(): void
    {
        $admin = $this->admin();
        $other = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'branch', 'branch_id' => $admin->branch_id]);
        $own = $this->lop($admin, 'draft', 'OWN-CREATED');
        $foreign = $this->lop($other, 'waiting_approval', 'OTHER-CREATED');
        $completed = $this->lop($admin, 'completed', 'OWN-COMPLETED');
        $this->actingAs($admin)->get(route('lop.index'))->assertOk()->assertSee($own->incident)->assertDontSee($foreign->incident)->assertDontSee($completed->incident)
            ->assertViewHas('stats', fn ($stats) => $stats['active'] === 1 && $stats['waiting'] === 0);
        $this->get(route('data-lops.index'))->assertOk()->assertSee($own->incident)->assertSee($foreign->incident)->assertSee($completed->incident);
        $this->get(route('lop.history'))->assertOk()->assertSee($completed->incident);
    }

    public function test_stale_evidence_actions_cannot_modify_archived_data_or_remove_original_files(): void
    {
        Storage::fake(config('evidence.disk', 'public'));
        $admin = $this->admin();
        $lop = $this->lop($admin, 'progress');
        $evidence = QeEvidence::create(['qe_lop_id' => $lop->getKey(), 'uploaded_by' => $admin->getKey(), 'step' => 'PROGRESS', 'type' => 'PHOTO', 'category' => 'progress', 'file_path' => 'cancel/original.jpg', 'status' => 'pending']);
        Storage::disk(config('evidence.disk', 'public'))->put($evidence->file_path, 'original-photo');
        $evidence->load('lop');
        $this->actingAs($admin)->delete(route('data-lops.destroy', $lop), $this->verification($lop))->assertRedirect();
        $service = app(EvidenceService::class);
        foreach ([
            fn () => $service->approve($evidence, $admin),
            fn () => $service->reject($evidence, $admin, 'Rejected'),
            fn () => $service->resetReview($evidence, $admin),
            fn () => $service->approveMany($lop, [$evidence->getKey()], $admin),
            fn () => $service->replace($evidence, UploadedFile::fake()->image('new.jpg'), $admin),
            fn () => $service->delete($evidence),
        ] as $action) {
            try {
                $action();
                $this->fail('Aksi evidence arsip seharusnya diblokir.');
            } catch (ModelNotFoundException) {
                $this->assertSame('pending', $evidence->fresh()->status->value);
                $this->assertSame('cancel/original.jpg', $evidence->fresh()->file_path);
                $this->assertSame(['cancel/original.jpg'], Storage::disk(config('evidence.disk', 'public'))->allFiles());
            }
        }
    }
}
