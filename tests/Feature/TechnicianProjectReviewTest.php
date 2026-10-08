<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeLop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicianProjectReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_project_keeps_all_steps_and_evidence_in_read_only_mode(): void
    {
        [$technician, $lop, $material, $service] = $this->project('COMPLETE', 'recovery');
        $lop->update(['status_lop' => 'completed']);
        foreach (['material_arrival', 'pre', 'insera', 'progress', 'after', 'slot_port'] as $category) {
            $this->evidence($lop, $technician, $category, 'approved', in_array($category, ['progress', 'after']) ? $material->getKey() : null);
        }
        $this->evidence($lop, $technician, 'progress', 'approved', $service->getKey());
        foreach ([1 => 'Reservasi material', 2 => 'Material tiba', 3 => 'Capture Ticket Insera', 4 => 'Progress · M-COMPLETE', 5 => 'After · M-COMPLETE'] as $step => $title) {
            $this->actingAs($technician)->get(route('technician.projects.show', [$lop, 'step' => $step]))
                ->assertOk()->assertViewHas('maxStep', 5)->assertViewHas('readOnly', true)
                ->assertSee($title)->assertSee('?step=5', false)
                ->assertDontSee('Review evidence pekerjaan', false)
                ->assertDontSee('Ajukan Approval')->assertDontSee('Simpan rekap material')
                ->assertDontSee('Ambil foto')->assertDontSee('Galeri / file')
                ->assertDontSee('Upload ulang evidence');
        }
        $this->get(route('technician.projects.show', [$lop, 'step' => 4]))->assertOk()->assertSee('Progress · J-COMPLETE');
    }

    public function test_waiting_and_rejected_projects_can_review_every_step_with_rejection_markers(): void
    {
        [$technician, $lop, $material] = $this->project('REJECT', 'preventive');
        $lop->update(['status_lop' => 'waiting_approval']);
        $this->actingAs($technician)->get(route('technician.projects.show', [$lop, 'step' => 5]))
            ->assertOk()->assertViewHas('readOnly', true)->assertSee('Menunggu approval')
            ->assertDontSee('Ajukan Approval')->assertDontSee('Simpan rekap material');
        $lop->update(['status_lop' => 'rejected']);
        $this->evidence($lop, $technician, 'material_arrival', 'rejected', null, 'Material tidak jelas');
        $this->evidence($lop, $technician, 'after', 'rejected', $material->getKey(), 'After terlalu gelap');
        $this->actingAs($technician)->get(route('technician.projects.show', $lop))
            ->assertOk()->assertViewHas('step', 2)->assertViewHas('maxStep', 5)
            ->assertViewHas('state', fn ($state) => $state['rejectedSteps']->all() === [2 => 1, 5 => 1])
            ->assertSee('1 reject')->assertSee('Material tidak jelas')->assertSee('Upload ulang evidence')
            ->assertDontSee('Review evidence pekerjaan', false);
        $this->get(route('technician.projects.show', [$lop, 'step' => 5]))
            ->assertOk()->assertSee('After terlalu gelap')->assertSee('Upload ulang evidence');
        $this->get(route('technician.projects.show', [$lop, 'step' => 3]))
            ->assertOk()->assertSee('Surat Permintaan')->assertDontSee('Simpan lokasi');
    }

    public function test_preventive_and_relok_review_imported_plan_against_technician_actual(): void
    {
        foreach (['preventive', 'relok_utilitas'] as $program) {
            [$technician, $lop, $material, $service, $package] = $this->project(strtoupper($program), $program);
            $boq = $lop->boq()->create(['package_id' => $package->getKey(), 'source' => 'import', 'created_by' => $lop->created_by]);
            foreach ([$material, $service] as $designator) {
                $price = $designator->is($material) ? 100 : 50;
                $boq->items()->create([
                    'designator_id' => $designator->getKey(), 'designator_code' => $designator->code,
                    'item_name' => $designator->item_name, 'unit' => 'meter', 'type' => $designator->is($material) ? 'MATERIAL' : 'JASA',
                    'qty' => 10, 'unit_price' => $price, 'total_price' => 10 * $price,
                ]);
            }
            $lop->update(['status_lop' => 'completed']);
            $this->actingAs($technician)->get(route('technician.projects.boq-review', [$lop, 'step' => 1]))
                ->assertOk()->assertSee('Review BOQ Plan vs BOQ Actual')->assertSee('Qty Plan')->assertSee('Qty Actual')
                ->assertSee('Rp 1.500')->assertSee('Rp 1.050')->assertSee($service->code)
                ->assertViewHas('boqPlan', fn ($report) => $report['lines'][0]['qty'] === 10.0 && $report['grand']['total_plan'] === 1500.0)
                ->assertViewHas('boqActual', fn ($report) => $report['lines'][0]['qty'] === 12.0 && $report['lines'][0]['qty_actual'] === 7.0
                    && $report['grand']['total_actual'] === 1050.0);
        }
    }

    public function test_recovery_only_shows_actual_including_paired_service_and_is_assignment_protected(): void
    {
        [$technician, $lop, , $service] = $this->project('RECOVERY', 'recovery');
        $lop->update(['status_lop' => 'completed']);
        $this->actingAs($technician)->get(route('technician.projects.boq-review', [$lop, 'step' => 1]))
            ->assertOk()->assertSee('Review BOQ Actual')->assertSee('Rp 1.050')->assertSee($service->code)
            ->assertDontSee('BOQ Plan')->assertDontSee('Qty Plan')->assertDontSee('Nilai Plan');
        $otherTechnician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $this->actingAs($otherTechnician)->get(route('technician.projects.show', $lop))->assertForbidden();
        $this->get(route('technician.projects.boq-review', $lop))->assertForbidden();
    }

    public function test_boq_review_button_only_appears_after_all_actual_quantities_are_saved_including_zero(): void
    {
        [$technician, $lop] = $this->project('ACTUAL-GATE', 'recovery');
        $items = $lop->materialReservation->items();
        $items->update(['qty_actual' => null]);

        $this->actingAs($technician)->get(route('technician.projects.show', $lop))
            ->assertOk()->assertViewHas('showBoqReview', false)
            ->assertViewMissing('boqActual')->assertViewMissing('boqPlan')
            ->assertDontSee('id="technician-boq-review-trigger"', false)
            ->assertDontSee('id="technician-boq-review"', false);
        $this->get(route('technician.projects.boq-review', [$lop, 'step' => 1]))
            ->assertRedirect(route('technician.projects.show', [$lop, 'step' => 1]))
            ->assertSessionHasErrors('workflow');

        $items->update(['qty_actual' => 0]);
        $this->get(route('technician.projects.show', $lop))
            ->assertOk()->assertViewHas('showBoqReview', true)
            ->assertSeeInOrder(['id="technician-workflow-stepper"', 'id="technician-project-summary"', 'id="technician-boq-review-trigger"'], false)
            ->assertSee(route('technician.projects.boq-review', [$lop, 'step' => 2]), false)
            ->assertViewMissing('boqActual')->assertViewMissing('boqPlan')
            ->assertDontSee("document.getElementById('technician-boq-review').showModal()", false)
            ->assertDontSee('<dialog id="technician-boq-review"', false);
        $this->get(route('technician.projects.boq-review', [$lop, 'step' => 2]))
            ->assertOk()->assertViewIs('technician.boq-review')
            ->assertSee(route('technician.projects.show', [$lop, 'step' => 2]), false)
            ->assertSee('Review BOQ Actual')->assertSee('Qty Actual')->assertSee('Rp 0')
            ->assertDontSee('BOQ Plan')->assertDontSee('<dialog', false);

        $extra = Designator::create([
            'code' => 'M-EXTRA-GATE', 'item_name' => 'Material tambahan', 'unit' => 'meter',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);
        $items->create(['designator_id' => $extra->getKey(), 'qty' => 2, 'qty_actual' => null]);
        $this->get(route('technician.projects.show', $lop))
            ->assertOk()->assertViewHas('showBoqReview', false)
            ->assertDontSee('id="technician-boq-review-trigger"', false);
        $this->get(route('technician.projects.boq-review', $lop))
            ->assertRedirect(route('technician.projects.show', [$lop, 'step' => 5]))
            ->assertSessionHasErrors('workflow');
    }

    public function test_boq_review_requires_technician_login_and_preserves_a_bounded_return_step(): void
    {
        [$technician, $lop] = $this->project('REVIEW-PAGE', 'recovery');
        $url = route('technician.projects.boq-review', $lop);
        $this->get($url)->assertRedirect(route('login'));
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $this->actingAs($admin)->get($url)->assertForbidden();

        foreach ([[null, 5], [-1, 1], [3, 3], [99, 5]] as [$requestedStep, $expectedStep]) {
            $params = $requestedStep === null ? [$lop] : [$lop, 'step' => $requestedStep];
            $this->actingAs($technician)->get(route('technician.projects.boq-review', $params))
                ->assertOk()->assertViewHas('returnStep', $expectedStep)
                ->assertSee('Kembali ke pekerjaan · Step '.$expectedStep)
                ->assertSee(route('technician.projects.show', [$lop, 'step' => $expectedStep]), false)
                ->assertDontSee('<dialog', false);
        }
    }

    public function test_technician_uses_original_card_style_without_a_frozen_workflow_stepper(): void
    {
        [$technician, $lop] = $this->project('UI-STYLE', 'recovery');
        $lop->update(['status_lop' => 'completed']);

        $this->actingAs($technician)->get(route('technician.projects.show', [$lop, 'step' => 5]))
            ->assertOk()->assertViewHas('maxStep', 5)->assertViewHas('readOnly', true)
            ->assertSee('id="technician-workflow-stepper" class="rounded-2xl', false)
            ->assertDontSee('id="technician-workflow-stepper" class="sticky', false)
            ->assertSee('id="technician-project-summary" class="mt-4 rounded-3xl bg-ink-900', false)
            ->assertSee(route('technician.projects.boq-review', [$lop, 'step' => 5]), false);
        $this->get(route('technician.projects.boq-review', [$lop, 'step' => 5]))
            ->assertOk()->assertSee('rounded-3xl bg-ink-900 p-5 text-white', false)
            ->assertSee('Kembali ke pekerjaan · Step 5')->assertDontSee('<dialog', false);
    }

    private function project(string $code, string $program): array
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create();
        $package = Package::create(['code' => $code, 'name' => "Paket {$code}"]);
        $material = Designator::create([
            'code' => "M-{$code}", 'item_name' => "Material {$code}", 'unit' => 'meter',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);
        $service = Designator::create([
            'code' => "J-{$code}", 'item_name' => "Jasa {$code}", 'unit' => 'meter',
            'designator_type_id' => DesignatorType::where('code', 'JASA')->value('id_designator_type'),
        ]);
        foreach ([$material, $service] as $d) {
            DesignatorPackagePrice::create(['designator_id' => $d->getKey(), 'package_id' => $package->getKey(), 'price' => $d->is($material) ? 100 : 50]);
        }
        $lop = QeLop::create([
            'incident' => $code, 'nama_lop' => "Project {$code}", 'program_type' => $program,
            'status_lop' => 'progress', 'created_by' => $admin->getKey(), 'package_id' => $package->getKey(),
        ]);
        $lop->assignments()->create(['technician_id' => $tech->getKey(), 'assigned_by' => $admin->getKey(), 'assigned_at' => now(), 'status' => 'active']);
        $reservation = $lop->materialReservation()->create(['technician_id' => $tech->getKey(), 'status' => 'draft']);
        $reservation->items()->create(['designator_id' => $material->getKey(), 'qty' => 12, 'qty_actual' => 7]);

        return [$tech, $lop, $material, $service, $package];
    }

    private function evidence(QeLop $lop, User $tech, string $category, string $status, ?int $designator = null, ?string $reason = null): void
    {
        $lop->evidences()->create([
            'uploaded_by' => $tech->getKey(), 'step' => match ($category) {
                'progress' => 'PROGRESS', 'after', 'slot_port' => 'AFTER', default => 'BEFORE'
            },
            'category' => $category, 'type' => 'PHOTO', 'designator_id' => $designator,
            'file_path' => "{$category}.jpg", 'status' => $status, 'review_note' => $reason,
        ]);
    }
}
