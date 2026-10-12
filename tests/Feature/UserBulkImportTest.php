<?php

namespace Tests\Feature;

use App\Enums\AdminScopeType;
use App\Enums\UserRole;
use App\Jobs\ProcessUserImport;
use App\Models\Area;
use App\Models\Branch;
use App\Models\QeImportBatch;
use App\Models\Region;
use App\Models\Role;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\SpreadsheetReader;
use App\Services\UserImportService;
use App\Services\UserImportTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class UserBulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_queued_private_encrypted_and_buttons_are_next_to_add_user(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = $this->actor();
        $csv = $this->csv([['username' => '000123']]);
        $this->actingAs($actor)->get(route('users.index'))->assertOk()->assertSee('Bulk Import User')->assertSee('Download Template')->assertSee('Tambah User');
        $this->post(route('users.imports.store'), ['file' => UploadedFile::fake()->createWithContent('users.csv', $csv)])->assertRedirect();
        $batch = QeImportBatch::firstOrFail();
        Queue::assertPushed(ProcessUserImport::class, fn ($job) => $job->batchId === $batch->getKey());
        $encrypted = Storage::disk('local')->get($batch->file_path);
        $this->assertStringNotContainsString('TestSecret123!', $encrypted);
        $this->assertSame($csv, Crypt::decryptString($encrypted));
        $this->assertDatabaseMissing('users', ['username' => '000123']);
        $this->get(route('users.imports.show', $batch))->assertOk()->assertSee(route('users.index'))
            ->assertViewHas('statusUrl', route('users.imports.status', $batch, false))->assertDontSee('TestSecret123!');
        $this->getJson(route('users.imports.status', $batch))->assertOk()->assertJsonPath('percentage', 0);
    }

    public function test_csv_creates_each_admin_scope_and_non_admin_roles_with_hash_history_and_leading_zeroes(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $locations = $this->locations();
        $batch = $this->batch($actor, [
            ['username' => '000001', 'nik' => '000001', 'phone' => '08123456789', 'role' => 'ADMIN', 'admin_scope_type' => 'area', 'area' => '3'],
            ['username' => '000002', 'role' => 'Admin', 'admin_scope_type' => 'Admin Region', 'region' => 'REGION JATIM'],
            ['username' => '000003', 'role' => 'admin', 'admin_scope_type' => 'branch', 'branch' => 'SIDOARJO'],
            ['username' => '000004', 'role' => 'ADMIN', 'admin_scope_type' => 'service_area', 'service_areas' => 'SDA|DPS'],
            ['username' => '000005', 'role' => 'teknisi', 'branch' => 'sda'],
            ['username' => '000006', 'role' => 'MANAGER', 'status' => 'inactive'],
            ['username' => '000007', 'role' => 'APPROVER'],
            ['username' => '000008', 'role' => 'SUPER_ADMIN'],
        ]);
        (new ProcessUserImport($batch->getKey()))->handle(app(UserImportService::class));
        $this->assertSame('completed', $batch->refresh()->status);
        $this->assertSame(8, $batch->success_rows);
        $areaUser = User::where('username', '000001')->firstOrFail();
        $this->assertSame(AdminScopeType::AREA, $areaUser->admin_scope_type);
        $this->assertSame('000001', $areaUser->nik);
        $this->assertSame('08123456789', $areaUser->phone);
        $this->assertSame($locations['area']->getKey(), $areaUser->area_id);
        $this->assertNull($areaUser->branch_id);
        $this->assertTrue(Hash::check('TestSecret123!', $areaUser->password));
        $this->assertDatabaseHas('user_histories', ['target_user_id' => $areaUser->getKey(), 'actor_id' => $actor->getKey(), 'event_type' => 'created']);
        $regionUser = User::where('username', '000002')->firstOrFail();
        $this->assertSame($locations['jatim']->getKey(), $regionUser->region_id);
        $this->assertSame($locations['area']->getKey(), $regionUser->area_id);
        $this->assertSame($locations['sda']->getKey(), User::where('username', '000003')->firstOrFail()->branch_id);
        $serviceUser = User::where('username', '000004')->firstOrFail();
        $this->assertSame(AdminScopeType::SERVICE_AREA, $serviceUser->admin_scope_type);
        $this->assertEqualsCanonicalizing([$locations['sto_sda']->getKey(), $locations['sto_dps']->getKey()], $serviceUser->serviceAreas->modelKeys());
        $this->assertNull($serviceUser->branch_id);
        $tech = User::where('username', '000005')->firstOrFail();
        $this->assertSame($locations['sda']->getKey(), $tech->branch_id);
        $this->assertNull($tech->admin_scope_type);
        $this->assertSame('inactive', User::where('username', '000006')->firstOrFail()->status);
        $this->assertSame(100, $batch->progressPercentage());
        $this->assertStringNotContainsString('TestSecret123!', $batch->rows->toJson());
        $this->assertStringNotContainsString('password', $batch->rows->toJson());
        Storage::disk('local')->assertMissing($batch->file_path);
        Storage::disk('local')->assertMissing($batch->file_path.'.working.csv');
    }

    public function test_invalid_scopes_duplicate_accounts_and_wrong_hierarchy_fail_per_row_without_overwriting(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $this->locations();
        $existing = User::factory()->create(['username' => 'EXISTING', 'nik' => 'NIK-EXISTING', 'email' => 'existing@example.test']);
        $deleted = User::factory()->create(['username' => 'DELETED']);
        $deleted->delete();
        $batch = $this->batch($actor, [
            ['username' => 'VALID'], ['username' => 'VALID'], ['username' => 'EXISTING'], ['username' => 'DELETED'],
            ['username' => 'DUP-NIK', 'nik' => $existing->nik], ['username' => 'DUP-EMAIL', 'email' => $existing->email],
            ['username' => 'NO-SCOPE', 'role' => 'ADMIN'],
            ['username' => 'NO-BRANCH', 'role' => 'ADMIN', 'admin_scope_type' => 'branch'],
            ['username' => 'BAD-HIERARCHY', 'role' => 'ADMIN', 'admin_scope_type' => 'branch', 'branch' => 'SDA', 'region' => 'BALNUS'],
            ['username' => 'BAD-STO', 'role' => 'ADMIN', 'admin_scope_type' => 'service_area', 'service_areas' => 'NOT-A-STO'],
            ['username' => 'TECH-WRONG-SCOPE', 'admin_scope_type' => 'area', 'area' => '3'],
            ['username' => 'SHORT-PASSWORD', 'password' => 'short'],
            ['username' => 'BAD-ROLE', 'role' => 'UNKNOWN'], ['username' => 'BAD-STATUS', 'status' => 'WRONG'],
        ]);
        app(UserImportService::class)->process($batch, $actor);
        $this->assertSame('partial', $batch->refresh()->status);
        $this->assertSame(1, $batch->success_rows);
        $this->assertSame(13, $batch->failed_rows);
        $this->assertSame(14, $batch->rows()->count());
        $this->assertSame($existing->name, $existing->refresh()->name);
        $this->assertNotNull($deleted->fresh()->deleted_at);
        $this->assertStringNotContainsString('TestSecret123!', $batch->rows->toJson());
        $this->actingAs($actor)->get(route('users.imports.show', $batch))->assertOk()->assertDontSee('TestSecret123!')->assertSee('EXISTING');
        app(UserImportService::class)->process($batch, $actor);
        $this->assertSame(14, $batch->rows()->count());
        $this->assertSame(1, User::where('username', 'VALID')->count());
    }

    public function test_excel_template_has_scope_examples_text_identifiers_and_reads_users_even_if_guide_is_active(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $this->locations();
        $download = $this->actingAs($actor)->get(route('users.imports.template', ['format' => 'xlsx', 'scope' => 'service_area']))
            ->assertOk()->assertDownload('template-users-service_area.xlsx');
        $downloadedFile = UploadedFile::fake()->createWithContent('download.xlsx', $download->streamedContent());
        $downloadedBook = IOFactory::load($downloadedFile->getRealPath());
        $this->assertCount(3, $downloadedBook->getAllSheets());
        $this->assertSame('service_area', $downloadedBook->getSheetByName('Users')->getCell('I2')->getValue());
        $this->assertSame('', (string) $downloadedBook->getSheetByName('Users')->getCell('F2')->getValue());
        $downloadedBook->disconnectWorksheets();
        $book = app(UserImportTemplateService::class)->workbook('branch');
        $sheet = $book->getSheetByName('Users');
        $sheet->setCellValueExplicit('B2', '000987654321', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C2', '000987654321', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F2', 'TestSecret123!', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('H2', 'active', DataType::TYPE_STRING);
        $book->setActiveSheetIndex(1);
        ob_start();
        (new Xlsx($book))->save('php://output');
        $file = UploadedFile::fake()->createWithContent('users.xlsx', ob_get_clean());
        $book->disconnectWorksheets();
        $importedBook = IOFactory::load($file->getRealPath());
        $this->assertSame('s', $importedBook->getSheetByName('Users')->getCell('B2')->getDataType());
        $this->assertSame('000987654321', $importedBook->getSheetByName('Users')->getCell('B2')->getValue());
        $importedBook->disconnectWorksheets();
        $batch = app(UserImportService::class)->enqueue($file, $actor);
        app(UserImportService::class)->process($batch, $actor);
        $this->assertSame('completed', $batch->refresh()->status);
        $this->assertSame(1, $batch->success_rows);
        $this->assertDatabaseHas('users', ['username' => '000987654321', 'nik' => '000987654321', 'admin_scope_type' => 'branch']);
        foreach (['area', 'region', 'branch', 'service_area', 'non_admin', 'all'] as $scope) {
            $response = $this->get(route('users.imports.template', ['format' => 'csv', 'scope' => $scope]))->assertOk()->assertDownload("template-users-{$scope}.csv");
            $csv = $response->streamedContent();
            $this->assertStringContainsString('admin_scope_type', $csv);
            $this->assertStringContainsString($scope === 'non_admin' ? 'TEKNISI' : 'ADMIN', $csv);
            $this->assertStringNotContainsString('TestSecret123!', $csv);
        }
    }

    public function test_permissions_are_enforced_for_upload_templates_results_and_generic_import_endpoints(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $batch = $this->batch($actor, [['username' => 'PRIVATE']]);
        $this->get(route('users.imports.template'))->assertRedirect(route('login'));
        foreach ([UserRole::ADMIN, UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $user = User::factory()->role($role->value)->create();
            $this->actingAs($user)->post(route('users.imports.store'), ['file' => UploadedFile::fake()->createWithContent('users.csv', $this->csv([]))])->assertForbidden();
            $this->get(route('users.imports.template'))->assertForbidden();
            $this->get(route('users.imports.show', $batch))->assertForbidden();
            $this->getJson(route('users.imports.status', $batch))->assertForbidden();
            $this->get(route('imports.show', $batch))->assertForbidden();
            $this->getJson(route('imports.status', $batch))->assertForbidden();
        }
    }

    public function test_bad_files_empty_and_too_many_rows_fail_safely_and_cleanup_secrets(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = $this->actor();
        $this->actingAs($actor)->from(route('users.index'))->post(route('users.imports.store'), [])->assertSessionHasErrorsIn('userImport', 'file');
        $this->post(route('users.imports.store'), ['file' => UploadedFile::fake()->create('bad.pdf', 5, 'application/pdf')])->assertSessionHasErrorsIn('userImport', 'file');
        $this->post(route('users.imports.store'), ['file' => UploadedFile::fake()->create('big.csv', 5121, 'text/csv')])->assertSessionHasErrorsIn('userImport', 'file');
        foreach (["name,username\nBad,NAME\n", $this->csv([]), $this->csv(array_fill(0, 1001, ['username' => 'TOO-MANY']))] as $csv) {
            $batch = app(UserImportService::class)->enqueue(UploadedFile::fake()->createWithContent('bad.csv', $csv), $actor);
            app(UserImportService::class)->process($batch, $actor);
            $this->assertSame('failed', $batch->refresh()->status);
            $this->assertSame(0, $batch->success_rows);
            $this->assertNotEmpty($batch->error_message);
            Storage::disk('local')->assertMissing($batch->file_path);
            Storage::disk('local')->assertMissing($batch->file_path.'.working.csv');
        }
        $this->assertDatabaseMissing('users', ['username' => 'TOO-MANY']);
    }

    public function test_worker_rechecks_permission_and_failure_callback_cleans_files_without_leaking_exception(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $batch = $this->batch($actor, [['username' => 'DENIED-LATER']]);
        $actor->update(['role_id' => Role::where('code', 'TEKNISI')->value('id')]);
        (new ProcessUserImport($batch->getKey()))->handle(app(UserImportService::class));
        $this->assertSame('failed', $batch->refresh()->status);
        $this->assertDatabaseMissing('users', ['username' => 'DENIED-LATER']);
        Storage::disk('local')->assertMissing($batch->file_path);
        $actor = $this->actor();
        $batch = $this->batch($actor, [['username' => 'CALLBACK']]);
        Storage::disk('local')->put($batch->file_path.'.working.csv', 'Temporary secret');
        (new ProcessUserImport($batch->getKey()))->failed(new \RuntimeException('SQL password=TestSecret123!'));
        $this->assertSame('failed', $batch->refresh()->status);
        $this->assertStringNotContainsString('TestSecret123!', $batch->error_message);
        Storage::disk('local')->assertMissing($batch->file_path);
        Storage::disk('local')->assertMissing($batch->file_path.'.working.csv');
    }

    public function test_outdated_worker_signature_is_reported_as_worker_not_as_wrong_template(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $batch = $this->batch($actor, [['username' => 'OLD-WORKER']]);
        $this->mock(SpreadsheetReader::class)->shouldReceive('table')->once()
            ->andThrow(new \Error('Unknown named parameter $sheetName'));

        app(UserImportService::class)->process($batch, $actor);

        $this->assertSame('failed', $batch->refresh()->status);
        $this->assertSame('worker_outdated', data_get($batch->metadata, 'error_code'));
        $this->assertStringContainsString('worker', $batch->error_message);
        $this->assertStringNotContainsString('Wajib ada header', $batch->error_message);
        $this->assertDatabaseMissing('users', ['username' => 'OLD-WORKER']);
        Storage::disk('local')->assertMissing($batch->file_path);
    }

    public function test_parser_errors_are_specific_and_unexpected_errors_do_not_expose_passwords(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $batch = app(UserImportService::class)->enqueue(UploadedFile::fake()->createWithContent('bad.csv', "name,username\nExample,EXAMPLE\n"), $actor);
        app(UserImportService::class)->process($batch, $actor);
        $this->assertStringContainsString('Header wajib tidak ditemukan', $batch->refresh()->error_message);
        $this->assertSame('headers_missing', data_get($batch->metadata, 'error_code'));

        $batch = $this->batch($actor, [['username' => 'SECRET-ERROR']]);
        $this->mock(SpreadsheetReader::class)->shouldReceive('table')->once()
            ->andThrow(new \RuntimeException('Failure password=TestSecret123! SQL password_confirmation=TestSecret123!'));
        app(UserImportService::class)->process($batch, $actor);
        $this->assertStringNotContainsString('TestSecret123!', $batch->refresh()->error_message);
        $this->assertStringNotContainsString('TestSecret123!', json_encode($batch->metadata));
        $this->assertSame('read_file', data_get($batch->metadata, 'error_stage'));
        $this->assertSame('read_failed', data_get($batch->metadata, 'error_code'));
        $this->assertDatabaseMissing('users', ['username' => 'SECRET-ERROR']);
    }

    private function actor(): User
    {
        return User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
    }

    private function batch(User $actor, array $rows): QeImportBatch
    {
        return app(UserImportService::class)->enqueue(UploadedFile::fake()->createWithContent('users.csv', $this->csv($rows)), $actor);
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, UserImportService::HEADERS, ',', '"', '');
        foreach ($rows as $row) {
            $data = [...array_fill_keys(UserImportService::HEADERS, ''), 'name' => 'User Bulk', 'role' => 'TEKNISI', 'password' => 'TestSecret123!', ...$row];
            fputcsv($stream, array_map(fn ($header) => $data[$header], UserImportService::HEADERS), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function locations(): array
    {
        $area = Area::updateOrCreate(['code' => '3'], ['name' => 'Area 3', 'is_active' => true]);
        $jatim = Region::where('code', 'JATIM')->firstOrFail();
        $jatim->update(['area_id' => $area->getKey()]);
        $balnus = Region::where('code', 'BALNUS')->firstOrFail();
        $balnus->update(['area_id' => $area->getKey()]);
        $sda = Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region_id' => $jatim->getKey(), 'is_active' => true]);
        $dps = Branch::create(['code' => 'DPS', 'name' => 'DENPASAR', 'region_id' => $balnus->getKey(), 'is_active' => true]);
        $sto_sda = ServiceArea::create(['workzone' => 'SDA', 'name' => 'SIDOARJO', 'branch_id' => $sda->getKey(), 'region_id' => $jatim->getKey(), 'is_active' => true]);
        ServiceArea::create(['workzone' => 'SDB', 'name' => 'SIDOARJO B', 'branch_id' => $sda->getKey(), 'region_id' => $jatim->getKey(), 'is_active' => true]);
        $sto_dps = ServiceArea::create(['workzone' => 'DPS', 'name' => 'DENPASAR', 'branch_id' => $dps->getKey(), 'region_id' => $balnus->getKey(), 'is_active' => true]);

        return compact('area', 'jatim', 'balnus', 'sda', 'dps', 'sto_sda', 'sto_dps');
    }
}
