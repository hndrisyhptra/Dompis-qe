<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRole;
use App\Jobs\ProcessAissImport;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeImportBatch;
use App\Models\QeImportRow;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\AissImportService;
use App\Services\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class AissImportTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->super = User::factory()->role('SUPER_ADMIN')->create();
    }

    private function file(array $rows, bool $wrongHeader = false): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('AISS');
        foreach (['B1' => 'ID IHLD', 'H1' => 'NAMA PROGRAM', 'L1' => $wrongHeader ? 'SALAH' : 'STATUS', 'O1' => 'STATUS PROGRAM', 'W1' => 'INCIDENT'] as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        foreach ($rows as $i => $row) {
            foreach (['B' => 0, 'H' => 1, 'W' => 2, 'L' => 3] as $column => $index) {
                $sheet->setCellValue($column.($i + 2), $row[$index] ?? '');
            }
            $sheet->setCellValue('O'.($i + 2), 'BUKAN STATUS AISS');
        }
        $book->createSheet()->setTitle('Panduan');
        $book->setActiveSheetIndex(1);
        $path = Storage::disk('local')->path('fixture.xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'aiss.xlsx', null, null, true);
    }

    private function lop(string $incident, array $attributes = []): QeLop
    {
        return QeLop::create(array_merge(['incident' => $incident, 'nama_lop' => 'LOP '.$incident, 'program_type' => 'recovery', 'status_lop' => 'progress', 'created_by' => $this->super->getKey()], $attributes));
    }

    private function batch(array $rows, ?User $actor = null, bool $wrongHeader = false): QeImportBatch
    {
        return app(ImportBatchService::class)->create('aiss', $this->file($rows, $wrongHeader), $actor ?? $this->super);
    }

    private function processBatch(QeImportBatch $batch): QeImportBatch
    {
        (new ProcessAissImport($batch->getKey()))->handle(app(AissImportService::class));

        return $batch->refresh();
    }

    public function test_excel_is_read_from_correct_sheet_and_column_l_not_status_program(): void
    {
        $lop = $this->lop('INC001', ['ihld_id' => 'D-001']);
        $batch = $this->processBatch($this->batch([['D-001', '', '', 'REVIEW RAM RETURN']]));
        $this->assertSame('completed', $batch->status);
        $this->assertSame('REVIEW RAM RETURN', $lop->fresh()->status_aiss);
        $this->assertSame('AISS', $batch->metadata['sheet']);
        $this->assertSame(100, $batch->progressPercentage());
    }

    public function test_matching_follows_priority_and_normalizes_case_and_whitespace_without_fuzzy_matching(): void
    {
        $first = $this->lop('INC001', ['ihld_id' => 'D-001', 'nama_lop' => 'Nama Pertama']);
        $second = $this->lop('INC002', ['nama_lop' => 'Nama Kedua']);
        $third = $this->lop('INC003');
        $batch = $this->processBatch($this->batch([
            [' d-001 ', 'Nama Kedua', 'INC003', 'SUBMIT'],
            ['TIDAK ADA', ' nama   kedua ', 'INC003', 'FINISH'],
            ['', 'Tidak ada juga', ' inc003 ', 'IN PROGRESS'],
            ['', 'Nama Pertam', 'INC000', 'CLOSE'],
        ]));
        $this->assertSame('SUBMIT', $first->fresh()->status_aiss);
        $this->assertSame('FINISH', $second->fresh()->status_aiss);
        $this->assertSame('IN PROGRESS', $third->fresh()->status_aiss);
        $this->assertSame(['ihld_id', 'nama_lop', 'incident'], $batch->rows()->where('status', 'success')->get()->pluck('result.match_stage')->all());
        $this->assertSame(3, $batch->success_rows);
        $this->assertSame(1, $batch->metadata['skipped_rows']);
        $this->assertSame('partial', $batch->status);
    }

    public function test_ambiguous_higher_priority_key_is_skipped_instead_of_falling_through(): void
    {
        $one = $this->lop('INC1', ['ihld_id' => 'SAME']);
        $two = $this->lop('INC2', ['ihld_id' => 'same']);
        $batch = $this->processBatch($this->batch([['SAME', $one->nama_lop, 'INC1', 'FINISH']]));
        $this->assertNull($one->fresh()->status_aiss);
        $this->assertNull($two->fresh()->status_aiss);
        $this->assertStringContainsString('ambigu', $batch->rows->first()->message);
    }

    public function test_empty_long_or_formula_status_and_unmatched_rows_preserve_existing_data(): void
    {
        $lop = $this->lop('INC1', ['status_aiss' => 'SUBMIT']);
        $batch = $this->processBatch($this->batch([
            ['', '', 'INC1', ''], ['', '', 'INC1', str_repeat('X', 256)], ['', '', 'INC1', '=1+1'], ['', '', 'NOTFOUND', 'FINISH'],
        ]));
        $this->assertSame('SUBMIT', $lop->fresh()->status_aiss);
        $this->assertSame(4, $batch->metadata['skipped_rows']);
        $this->assertSame(0, $batch->failed_rows);
        $this->assertDatabaseCount('qe_lops', 1);
        $this->assertDatabaseCount('qe_lop_histories', 0);
    }

    public function test_duplicate_target_first_valid_row_wins_and_finished_job_is_idempotent(): void
    {
        $lop = $this->lop('INC1', ['ihld_id' => '00123']);
        $batch = $this->processBatch($this->batch([['00123', '', '', 'SUBMIT'], ['', '', 'INC1', 'FINISH']]));
        $this->assertSame('SUBMIT', $lop->fresh()->status_aiss);
        $this->assertSame(1, $batch->success_rows);
        $this->assertStringContainsString('baris 2', $batch->rows->last()->message);
        $this->processBatch($batch);
        $this->assertDatabaseCount('qe_lop_histories', 1);
        $this->assertDatabaseCount('qe_import_rows', 2);
        $again = $this->processBatch($this->batch([['00123', '', '', 'SUBMIT']]));
        $this->assertFalse($again->rows->first()->result['changed']);
        $this->assertDatabaseCount('qe_lop_histories', 1);
    }

    public function test_only_aiss_status_and_history_change_even_when_completed(): void
    {
        $lop = $this->lop('INC1', ['status_lop' => 'completed', 'ihld_id' => null, 'boq_snapshot' => ['grand_total' => 1234]]);
        $before = $lop->fresh()->getRawOriginal();
        $batch = $this->processBatch($this->batch([['NEW-IHLD', $lop->nama_lop, 'INC1', 'PREPARING']]));
        $after = $lop->fresh()->getRawOriginal();
        unset($before['status_aiss'], $before['updated_at'], $after['status_aiss'], $after['updated_at']);
        $this->assertSame($before, $after);
        $history = $lop->histories()->first();
        $this->assertSame('completed', $history->status_before);
        $this->assertSame('completed', $history->status_after);
        $this->assertSame('aiss_status_updated', $history->event_type);
        $this->assertSame($batch->uuid, $history->meta['batch_uuid']);
        $this->assertSame('PREPARING', $history->meta['after']);
        $this->assertNull($lop->fresh()->ihld_id);
    }

    private function location(): array
    {
        $area = Area::create(['code' => 'AISS', 'name' => 'Area AISS']);
        $region = Region::create(['code' => 'AISS', 'name' => 'Region AISS', 'area_id' => $area->getKey()]);
        $branch = Branch::create(['code' => 'AISS', 'name' => 'Branch AISS', 'region_id' => $region->getKey(), 'region' => $region->name]);
        $sa = ServiceArea::create(['workzone' => 'AISS', 'name' => 'STO AISS', 'branch_id' => $branch->getKey(), 'region_id' => $region->getKey()]);

        return compact('area', 'region', 'branch', 'sa');
    }

    public function test_all_admin_scopes_and_legacy_location_are_enforced_and_do_not_leak_outside_lop_names(): void
    {
        $f = $this->location();
        $inside = $this->lop('INC-IN', ['branch_id' => $f['branch']->getKey(), 'service_area_id' => $f['sa']->getKey(), 'branch' => $f['branch']->name, 'sto' => $f['sa']->workzone]);
        $legacy = $this->lop('INC-LEGACY', ['branch' => $f['branch']->name, 'sto' => $f['sa']->workzone]);
        $outside = $this->lop('INC-OUT', ['ihld_id' => 'OUTSIDE-IHLD', 'nama_lop' => 'SECRET OUTSIDE', 'branch' => 'Other', 'sto' => 'OTHER']);
        foreach (['area', 'region', 'branch', 'service_area'] as $scope) {
            $admin = User::factory()->role('ADMIN')->create(['admin_scope_type' => $scope, 'area_id' => $f['area']->getKey(), 'region_id' => $f['region']->getKey(), 'branch_id' => $f['branch']->getKey(), 'service_area_id' => $f['sa']->getKey()]);
            if ($scope === 'service_area') {
                $admin->serviceAreas()->sync([$f['sa']->getKey()]);
            }
            $batch = $this->processBatch($this->batch([
                ['', '', 'INC-IN', $scope], ['', '', 'INC-LEGACY', $scope], ['OUTSIDE-IHLD', $inside->nama_lop, 'INC-IN', 'WRONG'],
            ], $admin));
            $this->assertSame($scope, $inside->fresh()->status_aiss);
            $this->assertSame($scope, $legacy->fresh()->status_aiss);
            $this->assertNull($outside->fresh()->status_aiss);
            $this->assertSame(2, $batch->success_rows);
            $this->assertStringNotContainsString('SECRET OUTSIDE', $batch->rows->last()->message);
        }
    }

    public function test_archived_match_is_not_updated_and_does_not_fall_through_to_other_lop(): void
    {
        $archived = $this->lop('INC-OLD', ['ihld_id' => 'D-OLD']);
        $archived->delete();
        $live = $this->lop('INC-LIVE');
        $batch = $this->processBatch($this->batch([['D-OLD', $live->nama_lop, 'INC-LIVE', 'FINISH']]));
        $this->assertNull($archived->fresh()->status_aiss);
        $this->assertNull($live->fresh()->status_aiss);
        $this->assertSame(1, $batch->metadata['skipped_rows']);
    }

    public function test_multi_service_area_admin_can_import_across_assigned_branches_only(): void
    {
        $f = $this->location();
        $otherBranch = Branch::create(['code' => 'AISS-2', 'name' => 'Branch 2', 'region_id' => $f['region']->getKey(), 'region' => $f['region']->name]);
        $otherSa = ServiceArea::create(['workzone' => 'AISS-2', 'name' => 'STO 2', 'branch_id' => $otherBranch->getKey(), 'region_id' => $f['region']->getKey()]);
        $one = $this->lop('IN-1', ['branch_id' => $f['branch']->getKey(), 'service_area_id' => $f['sa']->getKey()]);
        $two = $this->lop('IN-2', ['branch_id' => $otherBranch->getKey(), 'service_area_id' => $otherSa->getKey()]);
        $outside = $this->lop('OUT-1');
        $admin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'service_area']);
        $admin->serviceAreas()->sync([$f['sa']->getKey(), $otherSa->getKey()]);
        $batch = $this->processBatch($this->batch([['', '', 'IN-1', 'FINISH'], ['', '', 'IN-2', 'FINISH'], ['', '', 'OUT-1', 'FINISH']], $admin));
        $this->assertSame(2, $batch->success_rows);
        $this->assertSame('FINISH', $one->fresh()->status_aiss);
        $this->assertSame('FINISH', $two->fresh()->status_aiss);
        $this->assertNull($outside->fresh()->status_aiss);
    }

    public function test_timeout_failure_preserves_durable_results_and_cannot_fail_a_finished_batch(): void
    {
        $batch = $this->batch([['', '', 'INC1', 'SUBMIT']]);
        $batch->update(['status' => 'processing', 'total_rows' => 2]);
        $batch->rows()->create(['row_number' => 2, 'status' => 'success']);
        $batch->rows()->create(['row_number' => 3, 'status' => 'skipped', 'message' => 'Tidak ada pasangan']);
        $job = new ProcessAissImport($batch->getKey());
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame(1, $job->tries);
        $job->failed(new RuntimeException('private worker detail'));
        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertSame(1, $batch->fresh()->success_rows);
        $this->assertSame(1, $batch->fresh()->metadata['skipped_rows']);
        $this->assertStringNotContainsString('private worker detail', $batch->fresh()->error_message);
        $batch->update(['status' => 'completed']);
        $job->failed(new RuntimeException('late callback'));
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_corrupt_and_over_limit_workbooks_fail_with_safe_messages(): void
    {
        $batch = app(ImportBatchService::class)->create('aiss', UploadedFile::fake()->createWithContent('broken.xlsx', 'not an Excel archive'), $this->super);
        $this->assertSame('failed', $this->processBatch($batch)->status);
        $this->assertStringContainsString('tidak dapat dibaca', $batch->error_message);
        $file = $this->file([['', '', 'INC1', 'SUBMIT']]);
        $book = IOFactory::load($file->getPathname());
        $book->getSheetByName('AISS')->setCellValue('L10002', 'SUBMIT');
        (new Xlsx($book))->save($file->getPathname());
        $book->disconnectWorksheets();
        $batch = app(ImportBatchService::class)->create('aiss', $file, $this->super);
        $this->assertSame('failed', $this->processBatch($batch)->status);
        $this->assertStringContainsString('10.000', $batch->error_message);
        $this->assertDatabaseCount('qe_lop_histories', 0);
    }

    public function test_migration_rollback_refuses_to_drop_populated_aiss_status(): void
    {
        $this->lop('INC1', ['status_aiss' => 'FINISH']);
        $migration = require database_path('migrations/2026_10_15_000001_add_status_aiss_to_qe_lops_table.php');
        try {
            $migration->down();
            $this->fail('Populated Status AISS must not be dropped.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Rollback ditolak', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('qe_lops', 'status_aiss'));
    }

    public function test_import_does_not_normalize_legacy_project_state_and_duplicate_names_do_not_match(): void
    {
        $lop = $this->lop('INC1', ['program_type' => 'preventive']);
        DB::table('qe_lops')->where('id_qe_lops', $lop->getKey())->update(['status_project' => null]);
        $this->processBatch($this->batch([['', '', 'INC1', 'SUBMIT']]));
        $this->assertNull($lop->fresh()->status_project);
        $this->lop('INC2', ['nama_lop' => $lop->nama_lop]);
        $batch = $this->processBatch($this->batch([['UNKNOWN', $lop->nama_lop, 'INC1', 'FINISH']]));
        $this->assertSame('SUBMIT', $lop->fresh()->status_aiss);
        $this->assertSame(1, $batch->metadata['skipped_rows']);
    }

    public function test_progress_includes_skips_and_result_page_shows_reasons_and_correct_back_link(): void
    {
        $rows = [];
        for ($i = 1; $i <= 31; $i++) {
            $this->lop('INC'.$i);
            $rows[] = ['', '', $i % 2 ? 'INC'.$i : 'MISSING'.$i, 'FINISH'];
        }
        $batch = $this->processBatch($this->batch($rows));
        $this->assertSame(31, $batch->processedRows());
        $this->assertSame(16, $batch->success_rows);
        $this->assertSame(15, $batch->metadata['skipped_rows']);
        $this->actingAs($this->super)->get(route('imports.show', $batch))->assertOk()->assertSee('Dilewati')->assertSee('Tidak ada LOP yang cocok')->assertSee(route('bulk-import.aiss.index'), false);
        $this->getJson(route('imports.status', $batch))->assertOk()->assertJsonPath('percentage', 100)->assertJsonPath('skipped_rows', 15);
    }

    public function test_upload_dispatches_queue_and_access_requires_admin_gate_including_result_ownership(): void
    {
        Queue::fake();
        $this->actingAs($this->super)->postJson(route('bulk-import.aiss.store'), ['file' => $this->file([['', '', 'INC1', 'SUBMIT']])])->assertStatus(202)->assertJsonStructure(['result_url']);
        Queue::assertPushed(ProcessAissImport::class);
        $batch = QeImportBatch::first();
        $admin = User::factory()->role('ADMIN')->create();
        $this->actingAs($admin)->get(route('imports.show', $batch))->assertForbidden();
        $this->withoutMiddleware(EnsureRole::class);
        foreach (['TEKNISI', 'MANAGER', 'APPROVER'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('bulk-import.aiss.index'))->assertForbidden();
            $this->post(route('bulk-import.aiss.store'), ['file' => $this->file([['', '', 'INC1', 'SUBMIT']])])->assertForbidden();
            $this->get(route('imports.status', $batch))->assertForbidden();
        }
    }

    public function test_worker_rechecks_uploader_role_and_inactive_state(): void
    {
        $lop = $this->lop('INC1');
        $admin = User::factory()->role('ADMIN')->create();
        $batch = $this->batch([['', '', 'INC1', 'SUBMIT']], $admin);
        $admin->update(['status' => 'inactive']);
        $this->assertSame('failed', $this->processBatch($batch)->status);
        $this->assertNull($lop->fresh()->status_aiss);
    }

    public function test_wrong_headers_empty_and_unreadable_files_fail_without_writes(): void
    {
        foreach ([$this->batch([['', '', 'INC1', 'SUBMIT']], wrongHeader: true), $this->batch([])] as $batch) {
            $this->assertSame('failed', $this->processBatch($batch)->status);
            $this->assertNotEmpty($batch->error_message);
        }
        $this->assertDatabaseCount('qe_lop_histories', 0);
        $this->actingAs($this->super)->postJson(route('bulk-import.aiss.store'), ['file' => UploadedFile::fake()->create('bad.csv', 1, 'text/csv')])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_missing_migration_is_reported_without_creating_a_batch(): void
    {
        Schema::shouldReceive('hasColumn')->with('qe_lops', 'status_aiss')->andReturn(false);
        $this->actingAs($this->super)->postJson(route('bulk-import.aiss.store'), ['file' => $this->file([['', '', 'INC1', 'SUBMIT']])])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('qe_import_batches', 0);
    }

    public function test_row_result_failure_rolls_back_aiss_change_and_history(): void
    {
        $lop = $this->lop('INC1');
        $batch = $this->batch([['', '', 'INC1', 'SUBMIT']]);
        Event::listen('eloquent.creating: '.QeImportRow::class, fn () => throw new RuntimeException('simulated result failure'));
        try {
            $this->assertSame('failed', $this->processBatch($batch)->status);
        } finally {
            Event::forget('eloquent.creating: '.QeImportRow::class);
        }
        $this->assertNull($lop->fresh()->status_aiss);
        $this->assertDatabaseCount('qe_lop_histories', 0);
    }

    public function test_data_lop_and_all_program_tables_display_aiss_after_dompis_with_escaped_values(): void
    {
        foreach (['recovery', 'preventive', 'relok_utilitas', 'relok_tiang'] as $program) {
            $lop = $this->lop('INC-'.$program, ['program_type' => $program, 'status_lop' => 'draft', 'status_aiss' => '<script>bad</script>']);
            $this->actingAs($this->super)->get(route('program.show', $program))->assertOk()
                ->assertSeeInOrder(['Status Dompis', 'Status AISS'])->assertSee('&lt;script&gt;bad&lt;/script&gt;', false)->assertDontSee('<script>bad</script>', false);
        }
        $this->get(route('data-lops.index'))->assertOk()->assertSeeInOrder(['Status Dompis', 'Status AISS'])->assertSee('Import Data AISS');
        $this->get(route('bulk-import.aiss.index'))->assertOk()->assertSee('H · NAMA PROGRAM')->assertSee('History Upload');
    }
}
