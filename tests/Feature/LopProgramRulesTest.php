<?php

namespace Tests\Feature;

use App\Enums\LopBudgetType;
use App\Enums\ProgramType;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\BoqImportService;
use App\Services\BulkLopImportService;
use App\Services\ImportBatchService;
use App\Services\LopService;
use App\Services\ManualIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LopProgramRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_labels_accept_case_separators_codes_and_containing_text_but_reject_ambiguity(): void
    {
        foreach (ProgramType::cases() as $program) {
            $variants = [
                $program->value, $program->label(), mb_strtolower($program->label()), mb_strtoupper($program->label()),
                str_replace(' ', '_', mb_strtolower($program->label())), str_replace(' ', '-', $program->label()),
                str_replace(' ', '   ', $program->label()), $program->code(), 'Program: '.$program->label().' (pekerjaan)',
            ];
            foreach ($variants as $value) {
                $this->assertSame($program, ProgramType::fromInput($value), $value);
            }
        }
        foreach (['', 'Program baru', 'QE Recovery Preventive', 'QE Relok Tiang / Relok Utilitas', 'unrecovery'] as $invalid) {
            $this->assertNull(ProgramType::fromInput($invalid));
        }
    }

    public function test_manual_create_and_edit_force_budget_and_preserve_multiple_tiang_segments(): void
    {
        [$admin] = $this->actors();
        $this->actingAs($admin)->get(route('lop.create'))->assertOk()->assertSee('QE Relok Tiang')->assertDontSee('type="radio" name="budget_type"', false);
        foreach (ProgramType::cases() as $index => $program) {
            $payload = $this->payload('INC-PROGRAM-'.$index, $program);
            $this->post(route('lop.store'), [...$payload, 'budget_type' => 'INVALID'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $lop = QeLop::where('incident', $payload['incident'])->firstOrFail();
            $this->assertSame($program->budgetType(), $lop->budget_type);
            $this->assertSame($program->usesProjectStatus() ? ProjectStatus::USULAN : null, $lop->status_project);
            if ($program === ProgramType::RELOK_TIANG) {
                $this->assertSame(['tiang', 'odp'], $lop->segments());
                $this->assertStringContainsString('QERELTIANG', $lop->nama_lop);
                $this->assertStringContainsString('TIANG_ODP', $lop->nama_lop);
                $this->put(route('lop.update', $lop), [...$payload, 'program_type' => 'relok_utilitas', 'budget_type' => 'OPEX'])
                    ->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame(LopBudgetType::CAPEX, $lop->refresh()->budget_type);
                $this->put(route('lop.update', $lop), [...$payload, 'budget_type' => 'CAPEX'])
                    ->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame(LopBudgetType::OPEX, $lop->refresh()->budget_type);
            }
        }
        $this->post(route('lop.store'), [...$this->payload('INC-TOO-MANY', ProgramType::RELOK_TIANG), 'segment' => ['tiang', 'odp', 'odc', 'feeder', 'otb', 'gpon']])
            ->assertSessionHasErrors('segment');
    }

    public function test_bulk_program_variants_auto_budget_allow_tiang_odp_and_report_invalid_programs(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$admin] = $this->actors();
        $csv = "incident,sto,branch,area,program_type,segment,budget_type,job_description\n";
        $expected = [];
        foreach (ProgramType::cases() as $program) {
            foreach ([$program->label(), str_replace(' ', '_', strtolower($program->label())), strtoupper($program->label()), 'Program '.$program->label()] as $label) {
                $incident = 'INC-BULK-'.count($expected);
                $csv .= implode(',', [$incident, 'SDA', 'SIDOARJO', 3, $label, $program->supportsMultipleSegments() ? 'tiang|odp' : 'odp', 'INVALID', 'Pekerjaan'])."\n";
                $expected[$incident] = $program;
            }
        }
        $csv .= "INC-INVALID,SDA,SIDOARJO,3,QE Lainnya,odp,,Pekerjaan\n";
        $csv .= "INC-AMBIGUOUS,SDA,SIDOARJO,3,QE Recovery Preventive,odp,,Pekerjaan\n";
        $this->actingAs($admin)->post(route('bulk-import.lop.store'), ['file' => UploadedFile::fake()->createWithContent('program.csv', $csv)])
            ->assertRedirect();
        $batch = QeImportBatch::firstOrFail();
        app(BulkLopImportService::class)->process($batch, $admin);
        $this->assertSame(16, $batch->refresh()->success_rows);
        $this->assertSame(2, $batch->failed_rows);
        foreach ($expected as $incident => $program) {
            $lop = QeLop::where('incident', $incident)->firstOrFail();
            $this->assertSame($program, $lop->program_type);
            $this->assertSame($program->budgetType(), $lop->budget_type);
            $this->assertSame($program->supportsMultipleSegments() ? ['tiang', 'odp'] : ['odp'], $lop->segments());
        }
        $this->assertDatabaseMissing('qe_lops', ['incident' => 'INC-INVALID']);
        $this->assertDatabaseMissing('qe_lops', ['incident' => 'INC-AMBIGUOUS']);
        $this->get(route('bulk-import.lop.template', ['format' => 'csv']))->assertOk()->assertDownload('template-bulk-lop.csv');
        $template = $this->get(route('bulk-import.lop.template', ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('QE Relok Tiang', $template);
        $this->assertStringContainsString('tiang|odp', $template);
        $this->assertStringContainsString('CAPEX', $template);
        $this->assertStringContainsString('OPEX', $template);
    }

    public function test_new_program_supports_assignment_dashboard_and_revenue_without_scope_changes(): void
    {
        [$admin, $technician, $branch] = $this->actors();
        $lop = app(LopService::class)->create($this->payload('INC-TIANG-FLOW', ProgramType::RELOK_TIANG), $admin);
        $this->assertSame(LopBudgetType::OPEX, $lop->budget_type);
        $incident = app(ManualIncidentService::class)->generate($branch, ProgramType::RELOK_TIANG);
        $this->assertStringStartsWith('INP'.$branch->getKey().'4', $incident);
        $this->actingAs($admin)->get(route('program.show', 'relok_tiang'))->assertOk()->assertSee($lop->incident)->assertSee('Usulan');
        $this->post(route('lop.assign', $lop), ['technician_id' => $technician->getKey(), 'return_to' => 'program:relok_tiang'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(ProjectStatus::ON_GOING, $lop->refresh()->status_project);
        $this->get(route('program.show', ['relok_tiang', 'project_status' => 'on_going']))->assertOk()->assertSee($lop->incident);
        $this->get(route('dashboard', ['program' => 'relok_tiang']))->assertOk()->assertSee('QE Relok Tiang');
        $this->get(route('revenue.index'))->assertOk()->assertViewHas('programRows', fn ($rows) => $rows->count() === 4 && $rows->contains('program', 'relok_tiang'));
        $this->actingAs($technician)->get(route('technician.projects.show', $lop))->assertOk()->assertSee('Pickup Order');
    }

    public function test_boq_project_name_detects_relok_tiang_and_uses_opex_instead_of_relok_utilitas(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$admin] = $this->actors();
        Package::create(['code' => '5', 'name' => 'Paket 5', 'is_active' => true]);
        $type = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material', 'is_active' => true]);
        Designator::create(['code' => 'M-TIANG', 'item_name' => 'Tiang', 'unit' => 'buah', 'designator_type_id' => $type->getKey()]);
        $csv = "PROJECT : 3SDA_QERELTIANG_INC123459_TIANG_ODP_Relokasi,,,,,,\nSTO : SDA,,,,,,\nNO,DESIGNATOR,URAIAN PEKERJAAN,SATUAN,HARGA SATUAN (PAKET-5),,VOL\n,,,,MATERIAL,JASA,\n1,M-TIANG,Tiang,buah,125000,0,4\n";
        $batch = app(ImportBatchService::class)->create('boq', UploadedFile::fake()->createWithContent('tiang.csv', $csv), $admin);

        app(BoqImportService::class)->process($batch, $admin);

        $this->assertSame('completed', $batch->refresh()->status, json_encode($batch->errors));
        $lop = QeLop::where('incident', 'INC123459')->firstOrFail();
        $this->assertSame(ProgramType::RELOK_TIANG, $lop->program_type);
        $this->assertSame(LopBudgetType::OPEX, $lop->budget_type);
        $this->assertSame(['tiang', 'odp'], $lop->segments());
        $this->assertSame(ProjectStatus::USULAN, $lop->status_project);
        $this->assertDatabaseHas('qe_boqs', ['qe_lop_id' => $lop->getKey(), 'grand_total' => 500000]);
    }

    public function test_migration_rollback_guards_existing_relok_tiang_including_soft_deleted_lops(): void
    {
        [$admin] = $this->actors();
        $lop = app(LopService::class)->create($this->payload('INC-TIANG-ROLLBACK', ProgramType::RELOK_TIANG), $admin);
        $lop->delete();
        $migration = require database_path('migrations/2026_10_08_000001_add_relok_tiang_program_to_qe_lops.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rollback dibatalkan');
        $migration->down();
    }

    private function payload(string $incident, ProgramType $program): array
    {
        return ['incident' => $incident, 'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'program_type' => $program->value,
            'segment' => $program->supportsMultipleSegments() ? ['tiang', 'odp'] : 'odp', 'job_description' => 'Pekerjaan tiang dan ODP'];
    }

    private function actors(): array
    {
        $area = Area::updateOrCreate(['code' => '3'], ['name' => 'Area 3', 'is_active' => true]);
        $region = Region::where('code', 'JATIM')->firstOrFail();
        $region->update(['area_id' => $area->getKey()]);
        $branch = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM', 'region_id' => $region->getKey(), 'is_active' => true]);
        ServiceArea::create(['workzone' => 'SDA', 'name' => 'SIDOARJO', 'branch_id' => $branch->getKey(), 'region_id' => $region->getKey(), 'is_active' => true]);
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->getKey(), 'admin_scope_type' => 'branch']);
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create(['branch_id' => $branch->getKey()]);

        return [$admin, $tech, $branch];
    }
}
