<?php

namespace Tests\Feature;

use App\Enums\ProgramType;
use App\Enums\UserRole;
use App\Jobs\ProcessBulkLopImport;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\BulkLopImportService;
use App\Services\BulkLopTemplateService;
use App\Services\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

class IncidentProgramValidationTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $area = Area::create(['code' => 'INC-A', 'name' => 'Incident Area']);
        $region = Region::create(['code' => 'INC-R', 'name' => 'Incident Region', 'area_id' => $area->getKey()]);
        $this->branch = Branch::create(['code' => 'INC-B', 'name' => 'Incident Branch', 'region' => $region->name, 'region_id' => $region->getKey(), 'is_active' => true]);
        ServiceArea::create(['workzone' => 'ICX', 'name' => 'Incident STO', 'branch_id' => $this->branch->getKey(), 'region_id' => $region->getKey(), 'is_active' => true]);
        $this->admin = User::factory()->role(UserRole::ADMIN->value)->create(['admin_scope_type' => 'branch', 'branch_id' => $this->branch->getKey()]);
        $this->fakeIncidentTickets([
            'INC-GAMAS' => '  SQM gaMas distribusi  ',
            'INC-NON' => 'Non Numbering',
            'INC-VARIANT' => 'NON-NUMBERING TIANG',
            'INC-OTHER' => 'Gangguan pelanggan',
            'INC-EMPTY' => null,
            'INC-SIMILAR' => 'GAMBAS',
            'INC47206386' => 'REG',
            'INC-UNKNOWN-REC' => '  uNkNoWn  ',
            'INC-UNKNOWN-PREV' => 'UNKNOWN',
            'INC-UNKNOWN-TIANG' => 'UNKNOWN',
            'INC-UNKNOWN-OTHER' => 'UNKNOWN CUSTOMER',
        ]);
    }

    public function test_manual_input_accepts_gamas_for_recovery_and_preventive_and_non_numbering_for_tiang(): void
    {
        foreach (['recovery' => 'INC-GAMAS', 'preventive' => 'INC-GAMAS-PREV', 'relok_tiang' => 'INC-NON'] as $program => $incident) {
            if ($program === 'preventive') {
                DB::connection('mysql_dompis')->table('ticket')->insert(['incident' => $incident, 'jenis_tiket_2' => 'GAMAS ODP']);
            }
            $this->actingAs($this->admin)->post(route('lop.store'), $this->payload($incident, $program))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('qe_lops', ['incident' => $incident, 'program_type' => $program]);
        }
        $this->post(route('lop.store'), $this->payload('INC-VARIANT', 'relok_tiang'))->assertSessionHasNoErrors();
    }

    public function test_manual_input_rejects_wrong_missing_unrecognized_type_and_spoofed_client_summary(): void
    {
        foreach ([['INC-NON', 'recovery'], ['INC-NON', 'preventive'], ['INC-GAMAS', 'relok_tiang'], ['INC-OTHER', 'recovery'], ['INC-EMPTY', 'preventive'], ['INC-MISSING', 'relok_tiang'], ['INC-SIMILAR', 'recovery']] as [$incident, $program]) {
            $this->actingAs($this->admin)->post(route('lop.store'), [
                ...$this->payload($incident, $program), 'jenis_tiket_2' => 'GAMAS', 'ticket_summary' => 'Jenis Tiket: GAMAS NON NUMBERING',
            ])->assertSessionHasErrors('incident');
            $this->assertDatabaseMissing('qe_lops', ['incident' => $incident]);
        }
        $this->assertDatabaseCount('qe_lop_histories', 0);
    }

    public function test_unknown_ticket_is_accepted_for_recovery_and_preventive_after_lookup_and_on_save(): void
    {
        foreach (['recovery' => 'INC-UNKNOWN-REC', 'preventive' => 'INC-UNKNOWN-PREV'] as $program => $incident) {
            $this->actingAs($this->admin)->getJson(route('lop.ticket-lookup', ['incident' => $incident, 'program_type' => $program]))
                ->assertOk()->assertJsonPath('found', true)->assertJsonPath('incident_validation.valid', true)
                ->assertJsonPath('incident_validation.required_type', $program === 'preventive' ? 'GAMAS, UNKNOWN, INFRACARE, atau PREMAN' : 'GAMAS, UNKNOWN, TSEL, atau PREMAN');
            $this->post(route('lop.store'), $this->payload($incident, $program))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('qe_lops', ['incident' => $incident, 'program_type' => $program]);
        }
        $this->post(route('lop.store'), $this->payload('INC-UNKNOWN-TIANG', 'relok_tiang'))->assertSessionHasErrors('incident');
        $this->post(route('lop.store'), $this->payload('INC-UNKNOWN-OTHER', 'recovery'))->assertSessionHasErrors('incident');
    }

    public function test_bulk_import_accepts_unknown_for_recovery_and_preventive_but_not_relok_tiang(): void
    {
        $batch = $this->import([['INC-UNKNOWN-REC', 'recovery'], ['INC-UNKNOWN-PREV', 'preventive'], ['INC-UNKNOWN-TIANG', 'relok_tiang']]);
        $this->assertSame('partial', $batch->status);
        $this->assertSame(2, $batch->success_rows);
        $this->assertSame(1, $batch->failed_rows);
        $this->assertSame(100, $batch->progressPercentage());
        $this->assertStringContainsString('NON NUMBERING', $batch->rows()->where('status', 'failed')->value('message'));
    }

    public function test_infracare_is_accepted_for_preventive_after_lookup_and_on_save(): void
    {
        $this->fakeIncidentTickets(['INC-INFRA' => 'INFRACARE', 'INC-INFRA-CASE' => '  iNfRaCaRe  ']);
        foreach (['INC-INFRA', 'INC-INFRA-CASE'] as $incident) {
            $this->actingAs($this->admin)->getJson(route('lop.ticket-lookup', ['incident' => $incident, 'program_type' => 'preventive']))
                ->assertOk()->assertJsonPath('found', true)->assertJsonPath('incident_validation.valid', true)
                ->assertJsonPath('incident_validation.required_type', 'GAMAS, UNKNOWN, INFRACARE, atau PREMAN');
            $this->post(route('lop.store'), $this->payload($incident, 'preventive'))
                ->assertRedirect(route('lop.create'))->assertSessionHasNoErrors();
            $lop = QeLop::where('incident', $incident)->firstOrFail();
            $this->assertSame(ProgramType::PREVENTIVE, $lop->program_type);
            $this->assertSame('draft', $lop->status_lop->value);
            $this->assertSame('usulan', $lop->status_project->value);
            $this->assertSame(1, $lop->histories()->count());
        }
    }

    public function test_tsel_is_accepted_for_recovery_after_lookup_and_on_save(): void
    {
        $this->fakeIncidentTickets(['INC-TSEL' => 'TSEL', 'INC-TSEL-CASE' => '  tsel odc  ']);
        foreach (['INC-TSEL', 'INC-TSEL-CASE'] as $incident) {
            $this->actingAs($this->admin)->getJson(route('lop.ticket-lookup', ['incident' => $incident, 'program_type' => 'recovery']))
                ->assertOk()->assertJsonPath('found', true)->assertJsonPath('incident_validation.valid', true)
                ->assertJsonPath('incident_validation.required_type', 'GAMAS, UNKNOWN, TSEL, atau PREMAN');
            $this->post(route('lop.store'), $this->payload($incident, 'recovery'))
                ->assertRedirect(route('lop.create'))->assertSessionHasNoErrors();
            $lop = QeLop::where('incident', $incident)->firstOrFail();
            $this->assertSame(ProgramType::RECOVERY, $lop->program_type);
            $this->assertSame('draft', $lop->status_lop->value);
            $this->assertNull($lop->status_project);
        }
    }

    public function test_preman_is_accepted_for_recovery_and_preventive_after_lookup_and_on_save(): void
    {
        $this->fakeIncidentTickets(['INC-PREMAN-REC' => 'PREMAN', 'INC-PREMAN-PREV' => '  pReMaN  ']);
        foreach (['recovery' => 'INC-PREMAN-REC', 'preventive' => 'INC-PREMAN-PREV'] as $program => $incident) {
            $this->actingAs($this->admin)->getJson(route('lop.ticket-lookup', ['incident' => $incident, 'program_type' => $program]))
                ->assertOk()->assertJsonPath('found', true)->assertJsonPath('incident_validation.valid', true)
                ->assertJsonPath('incident_validation.required_type', $program === 'preventive' ? 'GAMAS, UNKNOWN, INFRACARE, atau PREMAN' : 'GAMAS, UNKNOWN, TSEL, atau PREMAN');
            $this->post(route('lop.store'), $this->payload($incident, $program))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('qe_lops', ['incident' => $incident, 'program_type' => $program]);
        }
        $this->post(route('lop.store'), $this->payload('INC-PREMAN-REC', 'relok_tiang'))->assertSessionHasErrors('incident');
    }

    public function test_bulk_import_accepts_preman_only_for_recovery_and_preventive(): void
    {
        $this->fakeIncidentTickets(['INC-PREMAN-REC' => 'PREMAN', 'INC-PREMAN-PREV' => 'PREMAN', 'INC-PREMAN-TIANG' => 'PREMAN']);
        $batch = $this->import([['INC-PREMAN-REC', 'recovery'], ['INC-PREMAN-PREV', 'preventive'], ['INC-PREMAN-TIANG', 'relok_tiang']]);
        $this->assertSame('partial', $batch->status);
        $this->assertSame(2, $batch->success_rows);
        $this->assertSame(1, $batch->failed_rows);
        $this->assertDatabaseHas('qe_lops', ['incident' => 'INC-PREMAN-REC', 'program_type' => 'recovery']);
        $this->assertDatabaseHas('qe_lops', ['incident' => 'INC-PREMAN-PREV', 'program_type' => 'preventive']);
        $this->assertDatabaseMissing('qe_lops', ['incident' => 'INC-PREMAN-TIANG']);
    }

    public function test_infracare_is_rejected_for_recovery_and_tiang_and_similar_labels_are_rejected_for_preventive(): void
    {
        $this->fakeIncidentTickets(['INC-INFRA' => 'INFRACARE', 'INC-INFRA-OTHER' => 'INFRACARE CUSTOMER']);
        foreach ([['INC-INFRA', 'recovery'], ['INC-INFRA', 'relok_tiang'], ['INC-INFRA-OTHER', 'preventive']] as [$incident, $program]) {
            $this->actingAs($this->admin)->getJson(route('lop.ticket-lookup', ['incident' => $incident, 'program_type' => $program]))
                ->assertOk()->assertJsonPath('incident_validation.valid', false);
            $this->post(route('lop.store'), [...$this->payload($incident, $program), 'jenis_tiket_2' => 'GAMAS'])
                ->assertSessionHasErrors('incident');
            $this->assertDatabaseMissing('qe_lops', ['incident' => $incident]);
        }
        $this->assertDatabaseCount('qe_lop_histories', 0);
    }

    public function test_bulk_import_accepts_infracare_only_for_preventive_with_progress(): void
    {
        $this->fakeIncidentTickets(['INC-INFRA-PREV' => '  infracare  ', 'INC-INFRA-REC' => 'INFRACARE', 'INC-INFRA-TIANG' => 'INFRACARE']);
        $batch = $this->import([['INC-INFRA-PREV', 'preventive'], ['INC-INFRA-REC', 'recovery'], ['INC-INFRA-TIANG', 'relok_tiang']]);
        $this->assertSame('partial', $batch->status);
        $this->assertSame(1, $batch->success_rows);
        $this->assertSame(2, $batch->failed_rows);
        $this->assertSame(100, $batch->progressPercentage());
        $this->assertDatabaseHas('qe_lops', ['incident' => 'INC-INFRA-PREV', 'program_type' => 'preventive']);
        $this->assertDatabaseMissing('qe_lops', ['incident' => 'INC-INFRA-REC']);
        $this->assertDatabaseMissing('qe_lops', ['incident' => 'INC-INFRA-TIANG']);
    }

    public static function bulkUploadFormats(): array
    {
        return ['CSV' => ['csv'], 'Excel' => ['xlsx']];
    }

    #[DataProvider('bulkUploadFormats')]
    public function test_bulk_upload_job_validates_tickets_from_the_server_and_keeps_valid_rows(string $format): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->fakeIncidentTickets([
            'INC-GAMAS' => 'GAMAS ODP', 'INC-UNKNOWN' => ' unknown ',
            'INC-INFRA' => ' infracare ', 'INC-NON' => 'NON-NUMBERING TIANG',
            'INC-INFRA-REC' => 'INFRACARE', 'INC-CHANGED' => 'GAMAS', 'INC-EMPTY' => null,
        ]);
        $rows = [
            ['INC-GAMAS', 'QE Recovery'], ['INC-UNKNOWN', 'qe_recovery'],
            ['INC-INFRA', 'QE Preventive'], ['INC-NON', 'QE RELOK TIANG'],
            ['INC-INFRA-REC', 'recovery'], ['INC-CHANGED', 'preventive'],
            ['INC-MISSING', 'relok_tiang'], ['INC-EMPTY', 'preventive'],
            ['', 'preventive'], ['INP99999', 'relok_tiang'], ['INC-UTILITY', 'relok_utilitas'],
        ];
        $data = [BulkLopTemplateService::HEADERS];
        foreach ($rows as [$incident, $program]) {
            $data[] = [$incident, 'ICX', '', '', $program, 'odp', '', 'Pekerjaan incident', '', '', ''];
        }
        $path = tempnam(sys_get_temp_dir(), 'incident-bulk-');
        try {
            if ($format === 'xlsx') {
                $book = new Spreadsheet;
                try {
                    $book->getActiveSheet()->setTitle('LOP')->fromArray($data);
                    $book->createSheet()->setTitle('Panduan')->setCellValue('A1', 'Isi sheet LOP');
                    $book->setActiveSheetIndex(1);
                    (new Xlsx($book))->save($path);
                } finally {
                    $book->disconnectWorksheets();
                }
            } else {
                $out = fopen($path, 'w');
                foreach ($data as $row) {
                    fputcsv($out, $row, ',', '"', '');
                }
                fclose($out);
            }
            $file = UploadedFile::fake()->createWithContent("incidents.{$format}", file_get_contents($path));
        } finally {
            unlink($path);
        }

        $this->actingAs($this->admin)->postJson(route('bulk-import.lop.store'), [
            'file' => $file, 'jenis_tiket_2' => 'GAMAS', 'ticket_summary' => 'Jenis Tiket: GAMAS',
        ])->assertStatus(202);
        $batch = QeImportBatch::firstOrFail();
        $this->assertSame('queued', $batch->status);
        $this->assertDatabaseCount('qe_lops', 0);
        DB::connection('mysql_dompis')->table('ticket')->where('incident', 'INC-CHANGED')
            ->update(['jenis_tiket_2' => 'REG']);
        Queue::assertPushed(ProcessBulkLopImport::class, fn (ProcessBulkLopImport $job): bool => $job->batchId === $batch->getKey());
        $job = Queue::pushed(ProcessBulkLopImport::class)->sole();
        app()->call([$job, 'handle']);

        $batch->refresh();
        $this->assertSame('partial', $batch->status);
        $this->assertSame(11, $batch->total_rows);
        $this->assertSame(7, $batch->success_rows);
        $this->assertSame(4, $batch->failed_rows);
        $this->assertSame(100, $batch->progressPercentage());
        $this->assertDatabaseCount('qe_lops', 7);
        $this->assertDatabaseCount('qe_lop_histories', 7);
        foreach (['INC-INFRA-REC' => 'INFRACARE', 'INC-CHANGED' => 'REG', 'INC-MISSING' => 'tidak ditemukan', 'INC-EMPTY' => 'belum terisi'] as $incident => $message) {
            $result = $batch->rows()->where('reference', $incident)->firstOrFail();
            $this->assertSame('failed', $result->status);
            $this->assertStringContainsString($message, $result->message);
            $this->assertDatabaseMissing('qe_lops', ['incident' => $incident]);
        }
        $this->assertDatabaseHas('qe_lops', ['incident' => 'INC-INFRA', 'program_type' => 'preventive', 'status_lop' => 'draft', 'status_project' => 'usulan']);
        $this->assertSame(1, QeLop::where('incident', 'like', 'INP%')->where('program_type', 'preventive')->count());
    }

    public function test_blank_incident_generates_inp_for_every_program_and_existing_inp_still_works(): void
    {
        DB::connection('mysql_dompis')->getSchemaBuilder()->drop('ticket');
        foreach (ProgramType::cases() as $program) {
            $this->actingAs($this->admin)->post(route('lop.store'), $this->payload('', $program->value))->assertSessionHasNoErrors();
            $lop = QeLop::where('program_type', $program->value)->firstOrFail();
            $this->assertStringStartsWith('INP'.$this->branch->getKey().$program->order(), $lop->incident);
            $this->assertStringContainsString($lop->incident, $lop->nama_lop);
            $this->assertSame('draft', $lop->status_lop->value);
            $this->assertSame(1, $lop->histories()->count());
        }
        $this->post(route('lop.store'), $this->payload('INP123456', 'recovery'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('qe_lops', ['incident' => 'INP123456']);
        $this->post(route('lop.store'), $this->payload('INP123456', 'recovery'))->assertSessionHasErrors('incident');
        $this->post(route('lop.store'), $this->payload('INC-UTILITY', 'relok_utilitas'))->assertSessionHasNoErrors();
    }

    public function test_lookup_returns_program_validation_and_rechecks_when_program_changes(): void
    {
        $this->actingAs($this->admin)->getJson(route('lop.ticket-lookup', ['incident' => 'inc-gamas', 'program_type' => 'recovery']))
            ->assertOk()->assertJsonPath('incident_validation.valid', true)->assertJsonPath('incident_validation.required_type', 'GAMAS, UNKNOWN, TSEL, atau PREMAN');
        $this->getJson(route('lop.ticket-lookup', ['incident' => 'INC-GAMAS', 'program_type' => 'relok_tiang']))
            ->assertOk()->assertJsonPath('incident_validation.valid', false)->assertJsonPath('incident_validation.required_type', 'NON NUMBERING');
        $this->getJson(route('lop.ticket-lookup', ['incident' => 'INP123456', 'program_type' => 'preventive']))
            ->assertOk()->assertJsonPath('incident_validation.valid', true);
        $this->getJson(route('lop.ticket-lookup', ['incident' => 'INC-GAMAS', 'program_type' => 'invalid']))->assertUnprocessable();
    }

    public function test_reg_ticket_is_rejected_for_recovery_even_after_successful_lookup_without_program(): void
    {
        $this->actingAs($this->admin)->getJson(route('lop.ticket-lookup', ['incident' => 'INC47206386']))
            ->assertOk()->assertJsonPath('found', true)->assertJsonPath('jenis_tiket_2', 'REG');
        $this->getJson(route('lop.ticket-lookup', ['incident' => 'INC47206386', 'program_type' => 'recovery']))
            ->assertOk()->assertJsonPath('incident_validation.valid', false)
            ->assertJsonPath('incident_validation.message', 'Gunakan tiket GAMAS, UNKNOWN, TSEL, atau PREMAN untuk QE Recovery. Tiket ini berjenis REG.')
            ->assertJsonPath('incident_validation.ticket_type', 'REG');
        $this->post(route('lop.store'), $this->payload('INC47206386', 'recovery'))->assertSessionHasErrors('incident');
        $this->assertDatabaseMissing('qe_lops', ['incident' => 'INC47206386']);
        $this->get(route('lop.create'))->assertOk()->assertSee(':disabled="!canSubmitLop"', false);
    }

    public function test_bulk_import_checks_each_program_and_generates_blank_incidents_with_progress(): void
    {
        $rows = [
            ['INC-GAMAS', 'recovery'], ['INC-NON', 'relok_tiang'], ['INC-OTHER', 'preventive'],
            ['INC-EMPTY', 'recovery'], ['INC-MISSING', 'relok_tiang'], ['INC-UTILITY', 'relok_utilitas'],
            ['', 'recovery'], ['', 'preventive'], ['', 'relok_tiang'], ['', 'relok_utilitas'],
        ];
        $batch = $this->import($rows);
        $this->assertSame('partial', $batch->status);
        $this->assertSame(7, $batch->success_rows);
        $this->assertSame(3, $batch->failed_rows);
        $this->assertSame(100, $batch->progressPercentage());
        $this->assertCount(10, $batch->rows);
        $this->assertStringContainsString('GAMAS', $batch->rows()->where('reference', 'INC-OTHER')->value('message'));
        $this->assertStringContainsString('tidak ditemukan', $batch->rows()->where('reference', 'INC-MISSING')->value('message'));
        $this->assertSame(4, QeLop::where('incident', 'like', 'INP%')->count());
    }

    public function test_unavailable_ticket_database_blocks_real_incidents_but_allows_blank_inp_and_utilitas(): void
    {
        DB::connection('mysql_dompis')->getSchemaBuilder()->drop('ticket');
        $this->actingAs($this->admin)->post(route('lop.store'), $this->payload('INC-GAMAS', 'recovery'))
            ->assertSessionHasErrors('incident');
        $batch = $this->import([['INC-GAMAS', 'recovery'], ['', 'preventive'], ['INC-UTILITY', 'relok_utilitas'], ['INP99999', 'relok_tiang']]);
        $this->assertSame(3, $batch->success_rows);
        $this->assertSame(1, $batch->failed_rows);
        $message = $batch->rows()->where('status', 'failed')->value('message');
        $this->assertStringContainsString('pengecekan', $message);
        $this->assertStringNotContainsString('SQL', $message);
    }

    public function test_bulk_ticket_types_are_prefetched_and_duplicates_keep_the_existing_error(): void
    {
        $connection = DB::connection('mysql_dompis');
        $connection->enableQueryLog();
        $batch = $this->import([['INC-GAMAS', 'recovery'], ['INC-GAMAS', 'recovery'], ['INC-NON', 'relok_tiang'], ['INC-NON', 'preventive']]);
        $this->assertSame(2, $batch->success_rows);
        $this->assertSame(2, $batch->failed_rows);
        $this->assertCount(1, $connection->getQueryLog());
        $this->assertStringContainsString('baris', $batch->rows()->where('status', 'failed')->first()->message);
        $connection->disableQueryLog();
    }

    private function payload(string $incident, string $program): array
    {
        return ['incident' => $incident, 'nama_lop' => '', 'sto' => 'ICX', 'branch' => $this->branch->name, 'area' => 'INC-A', 'program_type' => $program, 'segment' => ['odp'], 'job_description' => 'Pekerjaan incident'];
    }

    private function import(array $rows): QeImportBatch
    {
        Storage::fake('local');
        Queue::fake();
        $out = fopen('php://temp', 'r+');
        fputcsv($out, BulkLopTemplateService::HEADERS, ',', '"', '');
        foreach ($rows as [$incident, $program]) {
            fputcsv($out, [$incident, 'ICX', '', '', $program, 'odp', '', 'Pekerjaan incident', '', '', ''], ',', '"', '');
        }
        rewind($out);
        $file = UploadedFile::fake()->createWithContent('incidents.csv', stream_get_contents($out));
        fclose($out);
        $batch = app(ImportBatchService::class)->create('bulk_lop', $file, $this->admin);
        app(BulkLopImportService::class)->process($batch, $this->admin);

        return $batch->refresh();
    }
}
