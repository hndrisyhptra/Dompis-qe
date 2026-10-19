<?php

namespace Tests\Feature;

use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\EvidenceApprovalService;
use App\Services\EvidenceService;
use App\Services\ProjectProgressService;
use App\Services\TechnicianWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TechnicianMobileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function assignedProject(string $program = 'recovery'): array
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $lop = QeLop::create([
            'incident' => 'LOP-MOBILE-01',
            'nama_lop' => 'Project Mobile Teknisi',
            'program_type' => $program,
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
                'items' => [['designator_id' => $designator->id_designator, 'qty' => 12]],
            ])->assertRedirect();

        $this->assertSame('survey', $lop->fresh()->status_lop->value);
        $this->assertDatabaseHas('qe_material_reservation_items', [
            'designator_id' => $designator->id_designator,
            'qty' => 12,
        ]);
        $this->actingAs($technician)
            ->get(route('technician.projects.show', [$lop, 'step' => 2]))
            ->assertOk()
            ->assertSee('Evidence Material Tiba')
            ->assertSee('Pilih, review, hapus jika perlu, lalu upload.')
            ->assertSee('Ambil foto')
            ->assertSee('Galeri / file')
            ->assertSee('@click="startUploads()"', false);

        // Step 3 masih terkunci selama step 2 (Material Tiba) belum lengkap.
        $this->actingAs($technician)
            ->get(route('technician.projects.show', [$lop, 'step' => 3]))
            ->assertRedirect(route('technician.projects.show', [$lop, 'step' => 2]));
    }

    public function test_material_reservation_quantity_must_be_a_whole_number(): void
    {
        [, $technician, $lop] = $this->assignedProject();
        $designator = Designator::create([
            'code' => 'M-WHOLE-NUMBER',
            'item_name' => 'Kabel Fiber Optik',
            'unit' => 'meter',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);

        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));

        $this->actingAs($technician)
            ->put(route('technician.projects.materials', $lop), [
                'items' => [['designator_id' => $designator->id_designator, 'qty' => 12.5]],
            ])
            ->assertSessionHasErrors('items.0.qty');

        $this->assertDatabaseMissing('qe_material_reservation_items', [
            'designator_id' => $designator->id_designator,
        ]);
    }

    public function test_material_reservation_package_is_selected_automatically_from_lop_region(): void
    {
        $package5 = Package::create(['code' => 'Paket-5', 'name' => 'Paket 5']);
        $package10 = Package::create(['code' => 'Paket-10', 'name' => 'Paket 10']);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $technician = User::factory()->role(UserRole::TEKNISI->value)->create();
        $designator = Designator::create([
            'code' => 'M-REGIONAL-PACKAGE',
            'item_name' => 'Material Paket Regional',
            'unit' => 'unit',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);

        foreach ([
            ['JATIM', 'TECH-JATIM', $package5],
            ['JATENGDIY', 'TECH-JATENG', $package5],
            ['BALNUS', 'TECH-BALNUS', $package10],
        ] as [$regionCode, $branchCode, $expectedPackage]) {
            $region = Region::query()->where('code', $regionCode)->firstOrFail();
            $branch = Branch::create([
                'code' => $branchCode,
                'name' => $branchCode,
                'region' => $region->name,
                'region_id' => $region->id_region,
                'is_active' => true,
            ]);
            $serviceArea = ServiceArea::create([
                'workzone' => $branchCode,
                'name' => $branchCode,
                'branch_id' => $branch->id_branch,
                'region_id' => $region->id_region,
                'is_active' => true,
            ]);
            $lop = QeLop::create([
                'incident' => 'LOP-'.$branchCode,
                'nama_lop' => 'Reservasi '.$branchCode,
                'program_type' => 'recovery',
                'status_lop' => 'picked_up',
                'branch' => $branch->name,
                'branch_id' => $branch->id_branch,
                'sto' => $serviceArea->workzone,
                'service_area_id' => $serviceArea->id_service_area,
                'created_by' => $admin->id_user,
            ]);
            $lop->assignments()->create([
                'technician_id' => $technician->id_user,
                'assigned_by' => $admin->id_user,
                'assigned_at' => now(),
                'status' => 'active',
            ]);

            if ($regionCode === 'JATIM') {
                $this->actingAs($technician)
                    ->get(route('technician.projects.show', [$lop, 'step' => 1]))
                    ->assertOk()
                    ->assertSee('Paket 5')
                    ->assertSee('Otomatis sesuai region');
            }

            $this->actingAs($technician)
                ->put(route('technician.projects.materials', $lop), [
                    'items' => [['designator_id' => $designator->id_designator, 'qty' => 2]],
                ])
                ->assertRedirect();

            $this->assertSame($expectedPackage->id_package, $lop->fresh()->package_id);
        }
    }

    public function test_steps_ahead_of_the_first_incomplete_step_are_locked(): void
    {
        [, $technician, $lop] = $this->assignedProject();
        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));

        // Belum reservasi -> hanya step 1 yang terbuka; step 2-5 dikunci.
        foreach ([2, 3, 4, 5] as $ahead) {
            $this->actingAs($technician)
                ->get(route('technician.projects.show', [$lop, 'step' => $ahead]))
                ->assertRedirect(route('technician.projects.show', [$lop, 'step' => 1]));
        }

        // Tanpa param step -> jatuh ke step pertama yang belum lengkap (step 1).
        $this->actingAs($technician)
            ->get(route('technician.projects.show', $lop))
            ->assertOk()
            ->assertSee('Reservasi material')
            ->assertSee('aria-disabled="true"', false); // stepper step berikutnya terkunci
    }

    public function test_step_pra_requires_pre_but_location_and_insera_are_optional(): void
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
        $progress = app(ProjectProgressService::class);

        // Lokasi boleh kosong, tetapi foto pra tetap wajib.
        $this->assertFalse($progress->summary($lop->fresh())['steps'][3]);
        $this->actingAs($technician)->post(route('technician.projects.survey-complete', $lop))->assertSessionHasErrors('workflow');

        // Foto pra cukup, tanpa lokasi atau capture Insera.
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'pre', 'type' => 'PHOTO',
            'files' => [UploadedFile::fake()->image('pra.jpg')],
        ]);
        $this->assertTrue($progress->summary($lop->fresh())['steps'][3]);
        $this->assertTrue(app(TechnicianWorkflowService::class)->state($lop->fresh())['step3Complete']);
        $this->assertDatabaseMissing('qe_surveys', ['qe_lop_id' => $lop->getKey()]);
        $this->actingAs($technician)->get(route('technician.projects.show', [$lop, 'step' => 3]))
            ->assertOk()->assertSee('Capture Ticket Insera')->assertSee('Opsional')->assertSee('Tidak Ada')
            ->assertSee('Tempel koordinat (Latitude, Longitude)')->assertSee('Boleh langsung lanjut tanpa lokasi.');
        $this->actingAs($technician)->post(route('technician.projects.survey-complete', $lop))->assertSessionHasNoErrors();
        $this->assertSame('progress', $lop->fresh()->status_lop->value);

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

        // Membuat lokasi opsional tidak boleh menjadikan foto Pra rejected lengkap.
        $lop->evidences()->where('category', 'pre')->update(['status' => 'rejected']);
        $this->assertFalse($progress->summary($lop->fresh())['steps'][3]);
        $this->assertFalse(app(TechnicianWorkflowService::class)->state($lop->fresh())['step3Complete']);
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

    public function test_optional_location_can_be_saved_and_updated_but_invalid_pairs_and_other_technicians_are_rejected(): void
    {
        [, $technician, $lop] = $this->assignedProject();
        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));
        $this->put(route('technician.projects.location', $lop), [
            'latitude' => -7.257472, 'longitude' => 112.752090, 'location_source' => 'manual',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('qe_surveys', ['qe_lop_id' => $lop->getKey(), 'captured_by' => $technician->getKey(), 'location_source' => 'manual']);
        $this->put(route('technician.projects.location', $lop), [
            'latitude' => 0, 'longitude' => 0, 'location_source' => 'manual',
        ])->assertSessionHasNoErrors();
        $this->assertSame('0.0000000', $lop->fresh()->survey->latitude);
        $this->assertSame('0.0000000', $lop->fresh()->survey->longitude);
        foreach ([['latitude' => ''], ['longitude' => ''], ['latitude' => 91], ['longitude' => 181]] as $invalid) {
            $this->put(route('technician.projects.location', $lop), [...[
                'latitude' => 0, 'longitude' => 0, 'location_source' => 'manual',
            ], ...$invalid])->assertSessionHasErrors(array_key_first($invalid));
        }
        $other = User::factory()->role('TEKNISI')->create();
        $this->actingAs($other)->put(route('technician.projects.location', $lop), [
            'latitude' => -8, 'longitude' => 115, 'location_source' => 'manual',
        ])->assertForbidden();
        $this->assertSame('0.0000000', $lop->fresh()->survey->latitude);
        $this->assertDatabaseCount('qe_surveys', 1);
    }

    public function test_empty_location_is_a_noop_and_pasted_pair_works_without_javascript(): void
    {
        [, $technician, $lop] = $this->assignedProject();
        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));
        $this->put(route('technician.projects.location', $lop), [])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('qe_surveys', ['qe_lop_id' => $lop->getKey()]);
        $this->put(route('technician.projects.location', $lop), ['coordinates' => '112.752090, -7.257472'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('-7.2574720', $lop->fresh()->survey->latitude);
        $this->assertSame('112.7520900', $lop->fresh()->survey->longitude);
        $this->assertSame('manual', $lop->fresh()->survey->location_source);
        $before = $lop->fresh()->survey->getRawOriginal();
        $this->put(route('technician.projects.location', $lop), ['latitude' => '', 'longitude' => '', 'coordinates' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame($before, $lop->fresh()->survey->getRawOriginal());
        foreach (['-7', '-7,181', 'abc', 'https://maps.google.com/?q=-7,112'] as $invalid) {
            $this->put(route('technician.projects.location', $lop), ['coordinates' => $invalid])
                ->assertSessionHasErrors('coordinates');
        }
        $this->assertSame($before, $lop->fresh()->survey->getRawOriginal());
    }

    public function test_continue_form_has_no_location_validation_or_javascript_gate_even_when_pra_missing(): void
    {
        [, $technician, $lop] = $this->assignedProject();
        $lop->update(['status_lop' => 'survey']);
        $d = Designator::create(['code' => 'M-LOCATION-SKIP', 'item_name' => 'Skip GPS', 'unit' => 'pcs', 'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type')]);
        $lop->materialReservation()->create(['technician_id' => $technician->getKey()])->items()->create(['designator_id' => $d->getKey(), 'qty' => 1]);
        $lop->evidences()->create(['category' => 'material_arrival', 'step' => 'BEFORE', 'type' => 'PHOTO', 'uploaded_by' => $technician->getKey(), 'file_path' => 'arrival.jpg', 'status' => 'pending']);
        $response = $this->actingAs($technician)->get(route('technician.projects.show', [$lop, 'step' => 3]))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form[@id="technician-pra-complete"]')->item(0);
        $this->assertNotNull($form);
        $this->assertTrue($form->hasAttribute('novalidate'));
        $button = $xpath->query('.//button', $form)->item(0);
        $this->assertFalse($button->hasAttribute('disabled'));
        $this->assertFalse($button->hasAttribute(':disabled'));
        $this->assertSame('technician-pra-complete', $button->getAttribute('form'));
        $this->assertSame(0, $xpath->query('//input[@name="latitude" or @name="longitude"][@required]')->length);
        $this->post(route('technician.projects.survey-complete', $lop))->assertSessionHasErrors('workflow');
        $lop->evidences()->create(['category' => 'pre', 'step' => 'SURVEY', 'type' => 'PHOTO', 'uploaded_by' => $technician->getKey(), 'file_path' => 'pre.jpg', 'status' => 'pending']);
        $this->post(route('technician.projects.survey-complete', $lop))->assertSessionHasNoErrors();
        $this->get(route('technician.projects.show', [$lop, 'step' => 4]))->assertOk();
        $this->assertDatabaseMissing('qe_surveys', ['qe_lop_id' => $lop->getKey()]);
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

    public function test_step_after_requires_item_evidence_and_actual_but_slot_port_is_optional(): void
    {
        Storage::fake('public');
        [, $technician, $lop] = $this->assignedProject();
        $type = DesignatorType::where('code', 'MATERIAL')->value('id_designator_type');
        $d = Designator::create(['code' => 'M-SLOT', 'item_name' => 'ODP', 'unit' => 'pcs', 'designator_type_id' => $type]);

        $this->actingAs($technician)->post(route('technician.projects.pickup', $lop));
        $this->actingAs($technician)->put(route('technician.projects.materials', $lop), [
            'items' => [['designator_id' => $d->id_designator, 'qty' => 1]],
        ]);
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'after', 'type' => 'PHOTO', 'designator_id' => $d->id_designator,
            'files' => [UploadedFile::fake()->image('after.jpg')],
        ]);
        $this->assertFalse(app(ProjectProgressService::class)->summary($lop->fresh())['steps'][5]);
        $this->actingAs($technician)->put(route('technician.projects.material-usage', $lop), [
            'usage' => [['designator_id' => $d->id_designator, 'qty_actual' => 1]],
        ]);

        // After + rekap lengkap cukup, meskipun capture slot port tidak tersedia.
        $this->assertTrue(app(ProjectProgressService::class)->summary($lop->fresh())['steps'][5]);
        $this->assertTrue(app(TechnicianWorkflowService::class)->state($lop->fresh())['step5Complete']);

        // slot_port bersifat global (tanpa designator_id) -> step 5 lengkap.
        $this->actingAs($technician)->post(route('technician.projects.evidence', $lop), [
            'category' => 'slot_port', 'type' => 'PHOTO',
            'files' => [UploadedFile::fake()->image('slot-port.jpg')],
        ])->assertRedirect(route('technician.projects.show', [$lop, 'step' => 5]));
        $this->assertTrue(app(ProjectProgressService::class)->summary($lop->fresh())['steps'][5]);
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

    public function test_technician_can_resume_a_rejected_lop_back_to_progress(): void
    {
        [, $technician, $lop] = $this->assignedProject();
        $lop->update(['status_lop' => 'rejected']);

        $this->actingAs($technician)
            ->post(route('technician.projects.resume', $lop))
            ->assertRedirect(route('technician.projects.show', $lop));

        $this->assertSame('progress', $lop->fresh()->status_lop->value);
        $this->assertDatabaseHas('qe_lop_histories', [
            'qe_lop_id' => $lop->id_qe_lops,
            'status_before' => 'rejected',
            'status_after' => 'progress',
        ]);
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

    public static function optionalCapturePrograms(): array
    {
        return array_map(fn (ProgramType $program) => [$program->value], ProgramType::cases());
    }

    #[DataProvider('optionalCapturePrograms')]
    public function test_complete_five_step_workflow_can_be_submitted_for_approval_without_optional_location_or_captures(string $program): void
    {
        Storage::fake('public');
        [$admin, $technician, $lop] = $this->assignedProject($program);
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
        if ($program === ProgramType::RELOK_UTILITAS->value) {
            $boq = $lop->boq()->create(['source' => 'import', 'status' => 'ready', 'created_by' => $admin->getKey()]);
            $boq->items()->create([
                'designator_id' => $designator->getKey(), 'designator_code' => $designator->code,
                'item_name' => $designator->item_name, 'type' => 'MATERIAL', 'unit' => 'meter',
                'qty' => 5, 'unit_price' => 1000, 'total_price' => 5000,
            ]);
        }

        foreach ([
            ['pre', null],
            ['material_arrival', $program === ProgramType::RELOK_UTILITAS->value ? $designator->getKey() : null],
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
            ->assertSee('Ajukan Approval')->assertSee('Capture Logic Slot Port')
            ->assertSee('Screenshot Detail Slot Port OLT')->assertSee('Opsional')->assertSee('Tidak Ada');

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
        app(EvidenceApprovalService::class)->completeReview($lop->fresh(), $admin);
        $this->assertSame('completed', $lop->fresh()->status_lop->value);
        $this->assertDatabaseMissing('qe_surveys', ['qe_lop_id' => $lop->getKey()]);
        $this->actingAs($technician)->get(route('technician.projects.show', [$lop, 'step' => 3]))
            ->assertOk()->assertSee('Lokasi tidak diisi (opsional).')->assertDontSee('Simpan lokasi');
        $this->assertFalse($lop->evidences()->whereIn('category', ['insera', 'slot_port'])->exists());
    }
}
