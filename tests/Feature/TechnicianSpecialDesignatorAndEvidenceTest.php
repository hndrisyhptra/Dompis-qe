<?php

namespace Tests\Feature;

use App\Enums\EvidenceCategory;
use App\Enums\ProgramType;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeBoq;
use App\Models\QeEvidence;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\BoqImportService;
use App\Services\BoqService;
use App\Services\DataBoqExportService;
use App\Services\EvidenceService;
use App\Services\ImportBatchService;
use App\Services\LopBoqProjectionService;
use App\Services\ReservationBoqService;
use App\Services\TechnicianWorkflowService;
use App\Support\ReservedDesignatorRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TechnicianSpecialDesignatorAndEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tech;

    private Area $area;

    private Region $region;

    private Branch $branch;

    private ServiceArea $sa;

    private Package $package;

    private array $designators = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('evidence.disk', 'public'));
        $this->admin = User::factory()->role('SUPER_ADMIN')->create();
        $this->tech = User::factory()->role('TEKNISI')->create();
        $this->area = Area::create(['code' => 'SPECIAL-A', 'name' => 'Special Area']);
        $this->region = Region::create(['code' => 'SPECIAL-R', 'name' => 'SPECIAL JATIM', 'area_id' => $this->area->getKey()]);
        $this->branch = Branch::create(['code' => 'SPECIAL-B', 'name' => 'Special Branch', 'region_id' => $this->region->getKey(), 'region' => $this->region->name]);
        $this->sa = ServiceArea::create(['workzone' => 'SPECIAL-SA', 'name' => 'Special SA', 'branch_id' => $this->branch->getKey(), 'region_id' => $this->region->getKey()]);
        $this->package = Package::create(['code' => 'Paket-5', 'name' => 'Paket 5 Special']);
        foreach (['M-CO-OF', 'J-CO-OF', 'J-Coring', 'J-OS-SM-1', 'J-OTHER'] as $code) {
            $this->designators[$code] = Designator::create([
                'code' => $code, 'item_name' => $code.' pekerjaan', 'unit' => 'pcs', 'created_by' => $this->admin->getKey(),
                'designator_type_id' => DesignatorType::where('code', str_starts_with($code, 'M-') ? 'MATERIAL' : 'JASA')->value('id_designator_type'),
            ]);
            DesignatorPackagePrice::create(['designator_id' => $this->designators[$code]->getKey(), 'package_id' => $this->package->getKey(), 'price' => 1000]);
        }
    }

    private function lop(string $status = 'progress', string $program = 'preventive'): QeLop
    {
        $lop = QeLop::create(['ba_bongkar_required' => false,
            'incident' => 'SPECIAL-'.QeLop::count(), 'nama_lop' => 'Special Designator Test', 'program_type' => $program,
            'status_lop' => $status, 'budget_type' => ProgramType::from($program)->budgetType()->value, 'branch_id' => $this->branch->getKey(), 'service_area_id' => $this->sa->getKey(),
            'branch' => $this->branch->name, 'sto' => $this->sa->workzone, 'created_by' => $this->admin->getKey(), 'package_id' => $this->package->getKey(),
        ]);
        $lop->assignments()->create(['technician_id' => $this->tech->getKey(), 'assigned_by' => $this->admin->getKey(), 'assigned_at' => now(), 'status' => 'active']);

        return $lop;
    }

    private function plan(QeLop $lop): void
    {
        $boq = QeBoq::create(['qe_lop_id' => $lop->getKey(), 'package_id' => $this->package->getKey(), 'source' => 'import', 'status' => 'ready', 'grand_total' => 65, 'item_count' => 4, 'created_by' => $this->admin->getKey()]);
        foreach (array_diff(array_keys($this->designators), ['J-OTHER']) as $code) {
            $material = str_starts_with($code, 'M-');
            $boq->items()->create([
                'designator_id' => $this->designators[$code]->getKey(), 'designator_code' => $code,
                'item_name' => $code.' snapshot', 'unit' => 'pcs', 'type' => $material ? 'MATERIAL' : 'JASA',
                'qty' => $material ? 10 : 3, 'unit_price' => $material ? 2 : 5, 'total_price' => $material ? 20 : 15,
            ]);
        }
    }

    private function evidence(QeLop $lop, string $category, ?Designator $designator = null, string $status = 'pending', ?User $uploader = null): QeEvidence
    {
        $path = 'special/'.uniqid().'.jpg';
        Storage::disk(config('evidence.disk', 'public'))->put($path, 'photo');

        return QeEvidence::create([
            'qe_lop_id' => $lop->getKey(), 'uploaded_by' => ($uploader ?? $this->tech)->getKey(),
            'category' => $category, 'step' => match ($category) {
                'progress' => 'PROGRESS', 'after', 'slot_port' => 'AFTER', default => 'SURVEY'
            },
            'type' => 'PHOTO', 'designator_id' => $designator?->getKey(), 'status' => $status, 'file_path' => $path, 'metadata' => ['mime' => 'image/jpeg'],
        ]);
    }

    public static function programs(): array
    {
        return array_map(fn ($p) => [$p->value], ProgramType::cases());
    }

    #[DataProvider('programs')]
    public function test_services_are_reserved_and_counted_only_for_capex_programs(string $program): void
    {
        $lop = $this->lop('completed', $program);
        $this->plan($lop);
        $reservation = app(BoqService::class)->syncReservation($lop, $this->tech);
        $hasServices = $lop->budget_type->value === 'CAPEX';
        $this->assertCount($hasServices ? 4 : 1, $reservation->items);
        foreach ($reservation->items as $item) {
            $item->update(['qty_actual' => 1]);
        }
        $actual = app(LopBoqProjectionService::class)->report($lop->fresh(), 'actual');
        $this->assertCount($hasServices ? 4 : 1, $actual['lines']);
        $expectedActual = $hasServices ? 17.0 : 2.0;
        $this->assertSame($expectedActual, $actual['grand']['total_actual']);
        foreach ($actual['lines'] as $line) {
            $this->assertSame(1.0, $line['qty_actual']);
        }
        $rows = iterator_to_array(app(DataBoqExportService::class)->rows($this->admin));
        $this->assertCount($hasServices ? 4 : 1, $rows);
        $this->assertSame($expectedActual, (float) array_sum(array_column($rows, 10)));
        $expectedPlan = $program === 'recovery' ? 0.0 : ($hasServices ? 65.0 : 20.0);
        foreach ([
            $this->admin,
            User::factory()->role('ADMIN')->create(['admin_scope_type' => 'area', 'area_id' => $this->area->getKey()]),
            User::factory()->role('ADMIN')->create(['admin_scope_type' => 'region', 'region_id' => $this->region->getKey()]),
            User::factory()->role('ADMIN')->create(['branch_id' => $this->branch->getKey()]),
            User::factory()->role('ADMIN')->create(['admin_scope_type' => 'service_area', 'service_area_id' => $this->sa->getKey()]),
        ] as $actor) {
            $this->actingAs($actor)->get(route('revenue.index'))->assertOk()
                ->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === $expectedActual && $stats['plan_value'] === $expectedPlan);
        }
    }

    public function test_opex_plan_services_do_not_enter_technician_reservation_or_workflow(): void
    {
        $lop = $this->lop();
        $this->plan($lop);
        $reservation = $lop->materialReservation()->create(['technician_id' => $this->tech->getKey(), 'status' => 'draft']);
        $reservation->items()->create(['designator_id' => $this->designators['M-CO-OF']->getKey(), 'qty' => 10, 'qty_actual' => 2]);
        $this->assertSame(['M-CO-OF'], array_column(app(LopBoqProjectionService::class)->report($lop, 'plan')['lines'], 'designator_code'));
        $this->actingAs($this->admin)->get(route('reports.lop.boq-plan', $lop), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonMissing(['designator_code' => 'J-CO-OF']);
        $this->actingAs($this->admin)->get(route('data-boqs.index'))->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => $boqs->first()->program_items->count() === 1);
        $this->actingAs($this->tech)->get(route('technician.projects.show', $lop))->assertOk()
            ->assertViewHas('state', fn ($state) => $state['step1Complete'] && $state['currentStep'] === 2 && $state['items']->count() === 1);
        $this->assertDatabaseCount('qe_material_reservation_items', 1);
        $this->put(route('technician.projects.materials', $lop), ['items' => [['designator_id' => $this->designators['M-CO-OF']->getKey(), 'qty' => 10]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('qe_material_reservation_items', 1);
        $this->assertDatabaseHas('qe_material_reservation_items', ['designator_id' => $this->designators['M-CO-OF']->getKey(), 'qty_actual' => 2]);
    }

    #[DataProvider('programs')]
    public function test_reservation_only_special_services_are_valued_without_auto_pair_duplication(string $program): void
    {
        $lop = $this->lop('completed', $program);
        $reservation = $lop->materialReservation()->create(['technician_id' => $this->tech->getKey(), 'status' => 'submitted']);
        foreach (collect($this->designators)->except('J-OTHER') as $code => $designator) {
            $special = ReservedDesignatorRules::fixedActual($code);
            $reservation->items()->create(['designator_id' => $designator->getKey(), 'qty' => $special ? 3 : 10, 'qty_actual' => 1]);
        }
        $actual = app(LopBoqProjectionService::class)->report($lop, 'actual');
        $hasServices = $lop->budget_type->value === 'CAPEX';
        $this->assertCount($hasServices ? 4 : 1, $actual['lines']);
        $expected = $hasServices ? 4000.0 : 1000.0;
        $this->assertSame($expected, $actual['grand']['total_actual']);
        $this->actingAs($this->admin)->get(route('revenue.index'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['plan_value'] === 0.0 && $stats['actual_value'] === $expected);
        $this->assertDatabaseCount('qe_boqs', 0);
    }

    public function test_boq_import_rejects_opex_file_containing_jasa(): void
    {
        Storage::fake('local');
        $lop = $this->lop('assigned');
        $file = UploadedFile::fake()->createWithContent('special.csv', "PROJECT : Special Designator Test,,,,,,\nNO,DESIGNATOR,URAIAN PEKERJAAN,SATUAN,HARGA SATUAN (PAKET-5),,VOL\n,,,,MATERIAL,JASA,\n1,M-CO-OF,Material,pcs,2,0,10\n2,J-CO-OF,Jasa CO,pcs,0,5,3\n3,J-Coring,Coring,pcs,0,5,3\n4,J-OS-SM-1,Splicing,pcs,0,5,3\n5,J-OTHER,Jasa lainnya,pcs,0,999,3\n");
        $batch = app(ImportBatchService::class)->create('boq', $file, $this->admin);
        app(BoqImportService::class)->process($batch, $this->admin);
        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertSame(4, $batch->fresh()->failed_rows);
        $this->assertSame(1, $batch->fresh()->metadata['cancelled_count']);
        $this->assertSame(4, $batch->fresh()->metadata['rejected_service_count']);
        $this->assertSame(3042, $batch->fresh()->metadata['rejected_service_total']);
        $this->assertSame(3062, $batch->fresh()->metadata['file_total']);
        $this->assertDatabaseMissing('qe_boqs', ['qe_lop_id' => $lop->getKey()]);
        $this->assertDatabaseHas('qe_import_rows', [
            'import_batch_id' => $batch->getKey(), 'reference' => 'J-CO-OF', 'status' => 'failed',
        ]);
        $this->assertStringContainsString('CAPEX', DB::table('qe_import_rows')
            ->where('import_batch_id', $batch->getKey())->where('reference', 'J-CO-OF')->value('message'));
        $this->assertDatabaseHas('qe_import_rows', [
            'import_batch_id' => $batch->getKey(), 'reference' => 'M-CO-OF', 'status' => 'cancelled',
        ]);
        $this->assertStringContainsString('seluruh import dibatalkan', DB::table('qe_import_rows')
            ->where('import_batch_id', $batch->getKey())->where('reference', 'M-CO-OF')->value('message'));
        $this->assertDatabaseMissing('qe_material_reservation_items', ['designator_id' => $this->designators['J-Coring']->getKey()]);
    }

    public function test_approval_requires_each_service_actual_and_approved_progress_after(): void
    {
        $lop = $this->lop('waiting_approval', 'kuratif');
        $this->plan($lop);
        $this->evidence($lop, 'pre', status: 'approved');
        $this->actingAs($this->admin)->post(route('evidence-approval.lop.complete', $lop))->assertSessionHasErrors('review');
        $this->assertSame('waiting_approval', $lop->fresh()->status_lop->value);
        $reservation = app(BoqService::class)->syncReservation($lop->fresh(), $this->tech);
        foreach ($reservation->items as $item) {
            $item->update(['qty_actual' => 1]);
            $this->evidence($lop, 'progress', $item->designator, 'approved');
            $this->evidence($lop, 'after', $item->designator, 'approved');
        }
        $this->post(route('evidence-approval.lop.complete', $lop))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $lop->fresh()->status_lop->value);
    }

    public function test_service_reservations_allow_partial_actual_and_require_their_own_progress_after(): void
    {
        $lop = $this->lop('progress', 'kuratif');
        $workflow = app(TechnicianWorkflowService::class);
        $items = collect($this->designators)->except('J-OTHER')->map(fn ($d) => ['designator_id' => $d->getKey(), 'qty' => 3])->values()->all();
        $this->actingAs($this->tech)->put(route('technician.projects.materials', $lop), ['items' => $items])->assertRedirect()->assertSessionHasNoErrors();
        $lop->survey()->create(['latitude' => -7.1, 'longitude' => 112.1, 'captured_by' => $this->tech->getKey(), 'captured_at' => now()]);
        $this->evidence($lop, 'material_arrival');
        $this->evidence($lop, 'pre');
        $this->evidence($lop, 'progress', $this->designators['M-CO-OF']);
        $this->assertCount(3, $workflow->state($lop->fresh())['missingProgress']);
        $this->get(route('technician.projects.show', [$lop, 'step' => 3]))->assertOk();
        foreach (['J-CO-OF', 'J-Coring', 'J-OS-SM-1'] as $code) {
            $this->evidence($lop, 'progress', $this->designators[$code]);
        }
        $this->get(route('technician.projects.show', [$lop, 'step' => 4]))->assertOk()->assertSee('J-CO-OF')->assertSee('J-Coring')->assertSee('J-OS-SM-1');
        $this->get(route('technician.projects.show', [$lop, 'step' => 5]))->assertOk()->assertDontSee(':readonly="u.fixed_actual"', false)->assertSee('Rekap material dan jasa terpakai');
        $usage = array_map(fn ($item) => ['designator_id' => $item['designator_id'], 'qty_actual' => 3], $items);
        $usage[1]['qty_actual'] = 1.5;
        $this->put(route('technician.projects.material-usage', $lop), ['usage' => $usage])->assertSessionHasErrors('usage.1.qty_actual');
        $usage[1]['qty_actual'] = 1;
        $this->put(route('technician.projects.material-usage', $lop), ['usage' => $usage])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('qe_material_reservation_items', ['designator_id' => $items[1]['designator_id'], 'qty_actual' => 1]);
        $usage[1]['qty_actual'] = 4;
        $this->put(route('technician.projects.material-usage', $lop), ['usage' => $usage])->assertSessionHasErrors('usage.1.qty_actual');
        $usage[1]['qty_actual'] = 1;
        $this->put(route('technician.projects.material-usage', $lop), ['usage' => $usage])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(4, $workflow->state($lop->fresh())['missingAfter']);
        foreach (collect($this->designators)->except('J-OTHER') as $designator) {
            $this->evidence($lop, 'after', $designator);
        }
        $this->assertTrue($workflow->state($lop->fresh())['step5Complete']);
        $this->post(route('technician.projects.submit', $lop))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('waiting_approval', $lop->fresh()->status_lop->value);
    }

    public function test_admin_reservation_rejects_new_opex_service_and_accepts_capex_service(): void
    {
        $opex = $this->lop('progress', 'preventive');
        $opexReservation = $opex->materialReservation()->create(['technician_id' => $this->tech->getKey(), 'status' => 'draft']);
        $opexReservation->items()->create(['designator_id' => $this->designators['M-CO-OF']->getKey(), 'qty' => 3]);
        $service = app(ReservationBoqService::class);
        try {
            $service->update($opex, [
                ['designator_id' => $this->designators['M-CO-OF']->getKey(), 'qty' => 3],
                ['designator_id' => $this->designators['J-OTHER']->getKey(), 'qty' => 1],
            ], ReservationBoqService::version($opexReservation), $this->admin);
            $this->fail('Admin reservation editor must reject new OPEX jasa.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('qe_material_reservation_items', ['reservation_id' => $opexReservation->getKey(), 'designator_id' => $this->designators['J-OTHER']->getKey()]);
        }

        $capex = $this->lop('progress', 'kuratif');
        $capexReservation = $capex->materialReservation()->create(['technician_id' => $this->tech->getKey(), 'status' => 'draft']);
        $capexReservation->items()->create(['designator_id' => $this->designators['M-CO-OF']->getKey(), 'qty' => 3]);
        $updated = $service->update($capex, [
            ['designator_id' => $this->designators['M-CO-OF']->getKey(), 'qty' => 3],
            ['designator_id' => $this->designators['J-OTHER']->getKey(), 'qty' => 1],
        ], ReservationBoqService::version($capexReservation), $this->admin);
        $this->assertTrue($updated->items->contains('designator_id', $this->designators['J-OTHER']->getKey()));
    }

    public function test_kuratif_with_stored_opex_budget_cannot_add_services_until_backfill(): void
    {
        $lop = $this->lop('progress', 'kuratif');
        $lop->update(['budget_type' => 'OPEX']);
        try {
            app(BoqService::class)->save($lop, [[
                'designator_id' => $this->designators['J-OTHER']->getKey(), 'qty' => 1, 'unit_price' => 5,
            ]], $this->package, $this->admin);
            $this->fail('Stored OPEX budget must close jasa even for the Kuratif program.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('qe_boqs', 0);
        }
        $this->actingAs($this->tech)->get(route('technician.projects.show', [$lop, 'step' => 1]))
            ->assertOk()->assertDontSee('J-OTHER');
    }

    #[DataProvider('programs')]
    public function test_service_designators_are_available_only_for_capex_programs(string $program): void
    {
        $lop = $this->lop('progress', $program);
        $id = $this->designators['J-Coring']->getKey();
        if (ProgramType::from($program)->includesBoqServices()) {
            $boq = app(BoqService::class)->save($lop, [['designator_id' => $id, 'qty' => 2, 'unit_price' => 5]], $this->package, $this->admin);
            $this->assertSame('J-Coring', $boq->items->first()->designator_code);
            $this->assertDatabaseHas('qe_material_reservation_items', ['designator_id' => $id, 'qty' => 2]);
            $this->actingAs($this->tech)->get(route('technician.projects.show', [$lop, 'step' => 1]))->assertOk()->assertSee('J-OTHER');
            $this->actingAs($this->tech)->put(route('technician.projects.materials', $lop), ['items' => [
                ['designator_id' => $this->designators['J-OTHER']->getKey(), 'qty' => 2],
            ]])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('qe_material_reservation_items', ['designator_id' => $this->designators['J-OTHER']->getKey(), 'qty' => 2]);
            $this->assertContains('J-OTHER', collect(app(LopBoqProjectionService::class)->report($lop->fresh(), 'actual')['lines'])->pluck('designator_code')->all());
        } else {
            try {
                app(BoqService::class)->save($lop, [['designator_id' => $id, 'qty' => 2, 'unit_price' => 5]], $this->package, $this->admin);
                $this->fail('OPEX must reject newly added jasa.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('qe_boqs', 0);
            }
            $this->actingAs($this->tech)->get(route('technician.projects.show', [$lop, 'step' => 1]))->assertOk()->assertDontSee('J-OTHER');
            $this->actingAs($this->tech)->put(route('technician.projects.materials', $lop), ['items' => [
                ['designator_id' => $this->designators['J-OTHER']->getKey(), 'qty' => 2],
            ]])->assertSessionHasErrors('items');
        }
        $this->assertFalse(ReservedDesignatorRules::fixedActual('J-Coring-other'));
        $this->assertTrue(ReservedDesignatorRules::fixedActual(' j-coring '));
    }

    public function test_explicit_service_reservation_does_not_duplicate_relok_utilitas_service_pair(): void
    {
        $lop = $this->lop('completed', 'relok_utilitas');
        $reservation = $lop->materialReservation()->create(['technician_id' => $this->tech->getKey(), 'status' => 'submitted']);
        $reservation->items()->create(['designator_id' => $this->designators['M-CO-OF']->getKey(), 'qty' => 3, 'qty_actual' => 2]);
        $reservation->items()->create(['designator_id' => $this->designators['J-CO-OF']->getKey(), 'qty' => 3, 'qty_actual' => 1]);

        $actual = app(LopBoqProjectionService::class)->report($lop, 'actual');
        $this->assertCount(2, $actual['lines']);
        $this->assertSame(3000.0, $actual['grand']['total_actual']);
        $this->actingAs($this->admin)->get(route('revenue.index'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === 3000.0);
    }

    public function test_general_opex_jasa_is_excluded_from_revenue(): void
    {
        $lop = $this->lop('completed', 'recovery');
        $reservation = $lop->materialReservation()->create(['technician_id' => $this->tech->getKey(), 'status' => 'submitted']);
        $reservation->items()->create(['designator_id' => $this->designators['J-OTHER']->getKey(), 'qty' => 2, 'qty_actual' => 1]);

        $this->actingAs($this->admin)->get(route('revenue.index'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['actual_value'] === 0.0);
    }

    public function test_optional_choices_persist_for_both_categories_with_actor_history_and_no_dummy_evidence(): void
    {
        $lop = $this->lop();
        foreach (['insera', 'slot_port'] as $category) {
            $this->actingAs($this->tech)->putJson(route('technician.projects.optional-capture', $lop), ['category' => $category, 'unavailable' => true])->assertOk()->assertJsonPath('unavailable', true);
            $this->assertSame($this->tech->getKey(), $lop->fresh()->optional_capture_choices[$category]['user_id']);
            $this->assertNotEmpty($lop->fresh()->optional_capture_choices[$category]['recorded_at']);
            $this->putJson(route('technician.projects.optional-capture', $lop), ['category' => $category, 'unavailable' => true])->assertOk();
        }
        $this->assertSame(2, $lop->histories()->where('event_type', 'optional_capture_choice')->count());
        $this->assertDatabaseCount('qe_evidences', 0);
        $this->assertSame('progress', $lop->fresh()->status_lop->value);
        $lop->update(['status_lop' => 'waiting_approval']);
        $this->get(route('technician.projects.show', [$lop, 'step' => 3]))->assertOk()->assertSee('pilihan teknisi sudah tersimpan');
    }

    public function test_optional_upload_clears_previous_choice_and_failed_choice_cannot_hide_existing_evidence(): void
    {
        $lop = $this->lop();
        $workflow = app(TechnicianWorkflowService::class);
        $workflow->optionalCaptureChoice($lop, $this->tech, EvidenceCategory::INSERA, true);
        $workflow->uploadEvidenceFile($lop, $this->tech, ['category' => 'insera', 'type' => 'PHOTO'], UploadedFile::fake()->image('insera.jpg'));
        $this->assertNull($lop->fresh()->optional_capture_choices);
        $this->assertDatabaseCount('qe_evidences', 1);
        $this->actingAs($this->tech)->putJson(route('technician.projects.optional-capture', $lop), ['category' => 'insera', 'unavailable' => true])->assertUnprocessable();
        $this->assertNull($lop->fresh()->optional_capture_choices);
        $this->assertSame(2, $lop->histories()->where('event_type', 'optional_capture_choice')->count());
    }

    public function test_optional_choices_validate_category_owner_state_and_cannot_be_set_on_another_lop(): void
    {
        $lop = $this->lop();
        $payload = ['category' => 'slot_port', 'unavailable' => true];
        $route = route('technician.projects.optional-capture', $lop);
        $this->actingAs(User::factory()->role('TEKNISI')->create())->putJson($route, $payload)->assertForbidden();
        $this->actingAs($this->admin)->putJson($route, $payload)->assertForbidden();
        $this->actingAs($this->tech)->putJson($route, ['category' => 'progress', 'unavailable' => true])->assertUnprocessable();
        $this->putJson($route, ['category' => 'slot_port'])->assertUnprocessable();
        foreach (['assigned', 'waiting_approval', 'completed', 'rejected'] as $status) {
            $lop->update(['status_lop' => $status]);
            $this->putJson($route, $payload)->assertForbidden();
        }
        $this->assertDatabaseCount('qe_lop_histories', 0);
    }

    public function test_technician_can_delete_own_pending_or_rejected_with_confirmation_but_not_approved_or_others(): void
    {
        $lop = $this->lop('waiting_approval');
        $pending = $this->evidence($lop, 'progress');
        $approved = $this->evidence($lop, 'progress', status: 'approved');
        $other = $this->evidence($lop, 'progress', uploader: $this->admin);
        $this->actingAs($this->tech)->get(route('technician.projects.show', [$lop, 'step' => 4]))->assertOk()->assertSee('Ya, hapus')->assertSee('Tidak, batal')->assertSee('Terkunci · approved');
        $this->delete(route('technician.projects.evidence.destroy', [$lop, $approved]))->assertForbidden();
        $this->delete(route('technician.projects.evidence.destroy', [$lop, $other]))->assertForbidden();
        $this->delete(route('technician.projects.evidence.destroy', [$lop, $pending]))->assertRedirect();
        $this->assertSoftDeleted($pending);
        Storage::disk(config('evidence.disk', 'public'))->assertMissing($pending->file_path);
        $this->assertSame('progress', $lop->fresh()->status_lop->value);
        $rejected = $this->evidence($lop, 'pre', status: 'rejected');
        $this->delete(route('technician.projects.evidence.destroy', [$lop, $rejected]))->assertRedirect();
        $this->assertSoftDeleted($rejected);
        $this->assertSame(2, $lop->histories()->where('event_type', 'evidence_deleted')->count());
    }

    public function test_completed_approved_requires_admin_reset_before_technician_deletion_and_reopens_progress(): void
    {
        $lop = $this->lop('completed');
        $evidence = $this->evidence($lop, 'pre', status: 'approved');
        $route = route('technician.projects.evidence.destroy', [$lop, $evidence]);
        $this->actingAs($this->tech)->delete($route)->assertForbidden();
        $this->actingAs($this->admin)->post(route('evidence-approval.reset', $evidence))->assertRedirect();
        $this->assertSame('progress', $lop->fresh()->status_lop->value);
        $this->assertSame('pending', $evidence->fresh()->status->value);
        $this->assertCount(1, $evidence->fresh()->metadata['review_resets']);
        $this->actingAs($this->tech)->delete($route)->assertRedirect();
        $this->assertSoftDeleted($evidence);
    }

    public function test_delete_rechecks_fresh_approval_active_assignment_parent_and_file_integrity(): void
    {
        $lop = $this->lop();
        $evidence = $this->evidence($lop, 'pre');
        $evidence->load('lop');
        QeEvidence::whereKey($evidence->getKey())->update(['status' => 'approved']);
        try {
            app(EvidenceService::class)->delete($evidence, $this->tech);
            $this->fail('Approved evidence must remain protected even with a stale model.');
        } catch (AuthorizationException) {
            Storage::disk(config('evidence.disk', 'public'))->assertExists($evidence->file_path);
        }
        $otherLop = $this->lop();
        $this->actingAs($this->tech)->delete(route('technician.projects.evidence.destroy', [$otherLop, $evidence]))->assertNotFound();
        QeEvidence::whereKey($evidence->getKey())->update(['status' => 'pending']);
        $lop->assignments()->update(['status' => 'replaced']);
        $this->delete(route('technician.projects.evidence.destroy', [$lop, $evidence]))->assertForbidden();
        $this->assertNotNull($evidence->fresh());
    }

    public function test_failed_outer_transaction_keeps_original_file_and_evidence(): void
    {
        $lop = $this->lop();
        $evidence = $this->evidence($lop, 'pre');
        try {
            DB::transaction(function () use ($evidence) {
                app(EvidenceService::class)->delete($evidence, $this->tech);
                throw new \RuntimeException('Rollback fixture');
            });
        } catch (\RuntimeException) {
            $this->assertNotNull($evidence->fresh());
            Storage::disk(config('evidence.disk', 'public'))->assertExists($evidence->file_path);
        }
    }

    public function test_optional_capture_migration_rollback_refuses_to_discard_recorded_choices(): void
    {
        $lop = $this->lop();
        app(TechnicianWorkflowService::class)->optionalCaptureChoice($lop, $this->tech, EvidenceCategory::SLOT_PORT, true);
        $migration = require database_path('migrations/2026_10_16_000001_add_optional_capture_choices_to_qe_lops_table.php');
        try {
            $migration->down();
            $this->fail('Recorded choices must not be discarded by rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Backup', $exception->getMessage());
            $this->assertTrue($lop->fresh()->optional_capture_choices['slot_port']['unavailable']);
        }
    }
}
