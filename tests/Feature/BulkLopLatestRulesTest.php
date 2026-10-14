<?php

namespace Tests\Feature;

use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Package;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\BulkLopImportService;
use App\Services\BulkLopTemplateService;
use App\Services\ImportBatchService;
use App\Services\LopService;
use App\Services\SpreadsheetReader;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

class BulkLopLatestRulesTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    public function test_all_programs_create_and_edit_five_segments_and_reject_six_without_silent_truncation(): void
    {
        $this->fakeIncidentTickets(['INC-FIVE-0' => 'GAMAS FEEDER', 'INC-FIVE-1' => 'GAMAS FEEDER', 'INC-FIVE-3' => 'NON NUMBERING']);
        [$admin] = $this->actors();
        $this->actingAs($admin)->get(route('lop.create'))->assertOk()->assertSee('maks 5')->assertSee('maxSegments: 5', false);
        $segments = ['feeder', 'distribusi', 'odc', 'odp', 'tiang'];
        foreach (ProgramType::cases() as $index => $program) {
            $data = $this->payload('INC-FIVE-'.$index, $program, $segments);
            $this->post(route('lop.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
            $lop = QeLop::where('incident', $data['incident'])->firstOrFail();
            $this->assertSame($segments, $lop->segments());
            $this->assertSame($program->budgetType(), $lop->budget_type);
            $this->assertStringContainsString('FEEDER_DISTRIBUSI_ODC_ODP_TIANG', $lop->nama_lop);
            $this->put(route('lop.update', $lop), [...$data, 'segment' => ['OTB', 'ODP', 'JC', 'GPON', 'Mini OLT']])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(['otb', 'odp', 'jc', 'gpon', 'mini_olt'], $lop->refresh()->segments());
            $this->put(route('lop.update', $lop), [...$data, 'segment' => [...$segments, 'otb']])->assertSessionHasErrors('segment');
            $this->assertSame(['otb', 'odp', 'jc', 'gpon', 'mini_olt'], $lop->refresh()->segments());
            $this->post(route('lop.store'), [...$data, 'incident' => 'INC-SIX-'.$index, 'segment' => [...$segments, 'otb']])->assertSessionHasErrors('segment');
            $this->assertDatabaseMissing('qe_lops', ['incident' => 'INC-SIX-'.$index]);
        }
    }

    public function test_bulk_csv_all_programs_accept_five_segments_and_keep_per_row_failure_and_auto_fields(): void
    {
        [$admin] = $this->actors();
        $rows = [];
        foreach (ProgramType::cases() as $index => $program) {
            $rows[] = ['INC-CSV-'.$index, 'SDA', '', '', str_replace(' ', '_', strtoupper($program->label())), 'Feeder|Distribusi|ODC|ODP|Tiang', 'WRONG', 'Pekerjaan', '', '', ''];
        }
        $rows[] = ['INC-SIX', 'SDA', '', '', 'recovery', 'feeder|distribusi|odc|odp|tiang|otb', '', 'Pekerjaan', '', '', ''];
        $rows[] = ['INC-BAD-AREA', 'SDA', '', '999', 'preventive', 'odp', '', 'Pekerjaan', '', '', ''];
        $rows[] = ['INC-ALIASES', 'SDA', '', '', 'QE Relok Tiang', 'ODP;odp;Mini OLT', '', 'Pekerjaan', '', 'Nama manual tetap', ''];
        $batch = $this->processRows($admin, $rows);
        $this->assertSame('partial', $batch->status);
        $this->assertSame(5, $batch->success_rows);
        $this->assertSame(2, $batch->failed_rows);
        $this->assertSame(100, $batch->progressPercentage());
        foreach (ProgramType::cases() as $index => $program) {
            $lop = QeLop::where('incident', 'INC-CSV-'.$index)->firstOrFail();
            $this->assertSame(['feeder', 'distribusi', 'odc', 'odp', 'tiang'], $lop->segments());
            $this->assertSame($program, $lop->program_type);
            $this->assertSame($program->budgetType(), $lop->budget_type);
            $this->assertSame('3', $lop->area);
            $this->assertSame('SIDOARJO', $lop->branch);
            $this->assertNotEmpty($lop->nama_lop);
            $this->assertSame($program->usesProjectStatus() ? 'usulan' : null, $lop->status_project?->value);
        }
        $this->assertStringContainsString('Maksimal 5 segmen', $batch->rows()->where('reference', 'INC-SIX')->value('message'));
        $this->assertSame(['odp', 'mini_olt'], QeLop::where('incident', 'INC-ALIASES')->firstOrFail()->segments());
        $this->assertDatabaseHas('qe_lops', ['incident' => 'INC-ALIASES', 'nama_lop' => 'Nama manual tetap']);
    }

    public function test_duplicate_incidents_identify_active_archived_and_same_file_lops_without_revealing_other_scope(): void
    {
        [$admin, $otherAdmin] = $this->actors();
        $service = app(LopService::class);
        $service->create([...$this->payload('INC-USED', ProgramType::RECOVERY, ['odp']), 'nama_lop' => 'LOP Existing Sidoarjo'], $admin);
        $archived = $service->create([...$this->payload('INC-ARCHIVE', ProgramType::PREVENTIVE, ['odp']), 'nama_lop' => 'LOP Arsip Sidoarjo'], $admin);
        $archived->delete();
        $service->create([...$this->payload('INC-PRIVATE', ProgramType::RELOK_TIANG, ['tiang']), 'sto' => 'JBR', 'branch' => 'JEMBER', 'nama_lop' => 'Nama rahasia luar scope'], $otherAdmin);
        $rows = [];
        foreach (['inc-used', 'INC-ARCHIVE', 'INC-PRIVATE', 'INC-SAME-FILE', 'INC-SAME-FILE'] as $incident) {
            $rows[] = [$incident, 'SDA', '', '', 'recovery', 'odp', '', 'Pekerjaan baru', '', '', ''];
        }
        $batch = $this->processRows($admin, $rows);
        $this->assertSame(1, $batch->success_rows);
        $this->assertSame(4, $batch->failed_rows);
        $messages = $batch->rows()->where('status', 'failed')->pluck('message')->all();
        $this->assertStringContainsString('LOP Existing Sidoarjo', $messages[0]);
        $this->assertStringContainsString('LOP Arsip Sidoarjo', $messages[1]);
        $this->assertStringContainsString('dihapus/arsip', $messages[1]);
        $this->assertStringContainsString('di luar scope Anda', $messages[2]);
        $this->assertStringNotContainsString('Nama rahasia', $messages[2]);
        $created = QeLop::where('incident', 'INC-SAME-FILE')->firstOrFail();
        $this->assertStringContainsString($created->nama_lop, $messages[3]);
        $this->assertStringContainsString('baris 5 file ini', $messages[3]);
        $this->assertSame(1, QeLop::where('incident', 'INC-SAME-FILE')->count());
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $superBatch = $this->processRows($super, [['INC-PRIVATE', 'SDA', '', '', 'recovery', 'odp', '', 'Pekerjaan', '', '', '']]);
        $this->assertStringContainsString('Nama rahasia luar scope', $superBatch->rows()->value('message'));
        $this->actingAs($admin)->get(route('imports.show', $batch))->assertOk()->assertSee('LOP Existing Sidoarjo')->assertDontSee('Nama rahasia');
    }

    public function test_excel_and_csv_templates_include_latest_rules_and_scoped_references_and_round_trip_active_guide(): void
    {
        [$admin] = $this->actors();
        Package::create(['code' => '5', 'name' => 'Paket 5']);
        $this->actingAs($admin)->get(route('bulk-import.lop.index'))->assertOk()->assertSee('Unduh Template LOP')->assertSee('1–5 segmen')->assertSee('Referensi Program, Segmen, Lokasi, Paket');
        $response = $this->get(route('bulk-import.lop.template'))->assertOk()->assertDownload('template-bulk-lop.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'lop-template-');
        file_put_contents($path, $response->streamedContent());
        try {
            $book = IOFactory::load($path);
            $this->assertSame(['LOP', 'Panduan', 'Referensi Program', 'Referensi Segmen', 'Referensi Lokasi', 'Referensi Paket'], $book->getSheetNames());
            $this->assertSame('LOP', $book->getActiveSheet()->getTitle());
            $this->assertSame('5', $book->getSheetByName('Referensi Paket')->getCell('A2')->getValue());
            $this->assertSame(BulkLopTemplateService::HEADERS, $book->getSheetByName('LOP')->rangeToArray('A1:K1', null, false, false)[0]);
            $references = json_encode($book->getSheetByName('Referensi Lokasi')->toArray());
            $this->assertStringContainsString('SDA', $references);
            $this->assertStringNotContainsString('JBR', $references);
            $this->assertStringContainsString('1–5 segmen', json_encode($book->getSheetByName('Panduan')->toArray(), JSON_UNESCAPED_UNICODE));
            $this->assertSame('s', $book->getSheetByName('LOP')->getCell('A2')->getDataType());
            $book->setActiveSheetIndex(1);
            (new Xlsx($book))->save($path);
            $book->disconnectWorksheets();
            $table = app(SpreadsheetReader::class)->table($path, ['sto', 'program_type', 'segment', 'job_description'], sheetName: 'LOP');
            $this->assertCount(4, $table['rows']);
            $this->assertSame('QE Relok Tiang', $table['rows'][3]['data']['program_type']);
            Storage::fake('local');
            $batch = app(ImportBatchService::class)->create('bulk_lop', new UploadedFile($path, 'latest.xlsx', null, null, true), $admin);
            $this->fakeIncidentTickets(['INC-CONTOH-1' => 'GAMAS FEEDER', 'INC-CONTOH-2' => 'GAMAS FEEDER', 'INC-CONTOH-4' => 'NON NUMBERING']);
            app(BulkLopImportService::class)->process($batch, $admin);
            $this->assertSame(4, $batch->refresh()->success_rows, json_encode($batch->rows()->pluck('message')));
            $this->assertSame(0, $batch->failed_rows);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $csv = $this->get(route('bulk-import.lop.template', ['format' => 'csv']))->assertOk()->assertDownload('template-bulk-lop.csv')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('feeder|distribusi|odc|odp|otb', $csv);
        $this->assertStringContainsString('QE Relok Tiang', $csv);
        $this->get(route('bulk-import.lop.template', ['format' => 'pdf']))->assertSessionHasErrors('format');
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create();
        $this->actingAs($tech)->get(route('bulk-import.lop.template'))->assertForbidden();
    }

    public function test_incident_created_by_another_batch_after_prefetch_reports_lop_instead_of_sql(): void
    {
        [$admin] = $this->actors();
        $service = app(LopService::class);
        $this->mock(LopService::class, function ($mock) use ($service, $admin) {
            $mock->shouldReceive('create')->once()->andReturnUsing(function ($data) use ($service, $admin) {
                $service->create([...$data, 'nama_lop' => 'LOP dibuat batch lain'], $admin);
                throw new UniqueConstraintViolationException('sqlite', 'insert into qe_lops SECRET_SQL', [], new \PDOException('duplicate incident'));
            });
        });
        $batch = $this->processRows($admin, [['INC-RACE', 'SDA', '', '', 'recovery', 'odp', '', 'Pekerjaan', '', '', '']]);
        $this->assertSame(1, $batch->failed_rows);
        $this->assertStringContainsString('LOP dibuat batch lain', $batch->rows()->value('message'));
        $this->assertStringNotContainsString('SECRET_SQL', $batch->rows()->value('message'));
        $this->assertSame(1, QeLop::where('incident', 'INC-RACE')->count());
    }

    public function test_relok_tiang_menu_only_lists_its_program_in_admin_scope_and_superadmin_can_see_all(): void
    {
        [$admin, $otherAdmin] = $this->actors();
        $service = app(LopService::class);
        $mine = $service->create($this->payload('INC-TIANG-MINE', ProgramType::RELOK_TIANG, ['odp']), $admin);
        $other = $service->create([...$this->payload('INC-TIANG-OTHER', ProgramType::RELOK_TIANG, ['tiang']), 'sto' => 'JBR', 'branch' => 'JEMBER'], $otherAdmin);
        $recovery = $service->create($this->payload('INC-NOT-TIANG', ProgramType::RECOVERY, ['odp']), $admin);
        $this->actingAs($admin)->get(route('program.show', 'relok_tiang'))->assertOk()
            ->assertSee(route('program.show', 'relok_tiang'), false)->assertSee($mine->incident)->assertDontSee($other->incident)->assertDontSee($recovery->incident);
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $this->actingAs($super)->get(route('program.show', 'relok_tiang'))->assertOk()->assertSee($mine->incident)->assertSee($other->incident)->assertDontSee($recovery->incident);
    }

    private function processRows(User $actor, array $rows): QeImportBatch
    {
        $this->fakeIncidentTickets(collect($rows)->filter(fn ($row) => ProgramType::fromInput($row[4]) !== null)
            ->mapWithKeys(fn ($row) => [mb_strtoupper($row[0]) => ProgramType::fromInput($row[4])->incidentTicketType() ?? 'GAMAS'])->all());
        Storage::fake('local');
        Queue::fake();
        $out = fopen('php://temp', 'r+');
        fputcsv($out, BulkLopTemplateService::HEADERS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        rewind($out);
        $contents = stream_get_contents($out);
        fclose($out);
        $batch = app(ImportBatchService::class)->create('bulk_lop', UploadedFile::fake()->createWithContent('latest.csv', $contents), $actor);
        app(BulkLopImportService::class)->process($batch, $actor);

        return $batch->refresh();
    }

    private function payload(string $incident, ProgramType $program, array $segments): array
    {
        return ['incident' => $incident, 'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'program_type' => $program->value, 'segment' => $segments, 'budget_type' => 'INVALID', 'job_description' => 'Pekerjaan'];
    }

    private function actors(): array
    {
        $area = Area::updateOrCreate(['code' => '3'], ['name' => 'Area 3', 'is_active' => true]);
        $region = Region::where('code', 'JATIM')->firstOrFail();
        $region->update(['area_id' => $area->getKey()]);
        $actors = [];
        foreach ([['SDA', 'SIDOARJO'], ['JBR', 'JEMBER']] as [$code, $name]) {
            $branch = Branch::create(['code' => $code, 'name' => $name, 'region' => 'REGION JATIM', 'region_id' => $region->getKey(), 'is_active' => true]);
            ServiceArea::create(['workzone' => $code, 'name' => $name, 'branch_id' => $branch->getKey(), 'region_id' => $region->getKey(), 'is_active' => true]);
            $actors[] = User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->getKey(), 'admin_scope_type' => 'branch']);
        }

        return $actors;
    }
}
