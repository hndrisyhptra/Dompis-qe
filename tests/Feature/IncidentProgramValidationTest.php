<?php

namespace Tests\Feature;

use App\Enums\ProgramType;
use App\Enums\UserRole;
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

    public function test_manual_input_rejects_wrong_missing_unknown_type_and_spoofed_client_summary(): void
    {
        foreach ([['INC-NON', 'recovery'], ['INC-NON', 'preventive'], ['INC-GAMAS', 'relok_tiang'], ['INC-OTHER', 'recovery'], ['INC-EMPTY', 'preventive'], ['INC-MISSING', 'relok_tiang'], ['INC-SIMILAR', 'recovery']] as [$incident, $program]) {
            $this->actingAs($this->admin)->post(route('lop.store'), [
                ...$this->payload($incident, $program), 'jenis_tiket_2' => 'GAMAS', 'ticket_summary' => 'Jenis Tiket: GAMAS NON NUMBERING',
            ])->assertSessionHasErrors('incident');
            $this->assertDatabaseMissing('qe_lops', ['incident' => $incident]);
        }
        $this->assertDatabaseCount('qe_lop_histories', 0);
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
            ->assertOk()->assertJsonPath('incident_validation.valid', true)->assertJsonPath('incident_validation.required_type', 'GAMAS');
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
            ->assertJsonPath('incident_validation.message', 'Gunakan tiket GAMAS untuk QE Recovery. Tiket ini berjenis REG.')
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
