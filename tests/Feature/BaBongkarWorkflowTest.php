<?php

namespace Tests\Feature;

use App\Enums\EvidenceStatus;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\QeEvidence;
use App\Models\QeImportBatch;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\Role;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\BaBongkarPdfService;
use App\Services\EvidenceArchiveService;
use App\Services\ProjectProgressService;
use App\Services\UserImportService;
use App\Services\UserService;
use App\Support\BaBongkarRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\PngWithExtraIccData;
use Tests\TestCase;

class BaBongkarWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tech;

    private QeLop $lop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $branch = Branch::create(['code' => 'BA-JBR', 'name' => 'JEMBER BA', 'region_id' => Region::where('code', 'JATIM')->value('id_region')]);
        $this->admin = User::factory()->role('SUPER_ADMIN')->create();
        $this->tech = User::factory()->role('TEKNISI')->create();
        $this->lop = QeLop::create(['incident' => 'INC-BA-001', 'nama_lop' => 'Bongkar Jember', 'program_type' => 'recovery', 'status_lop' => 'progress', 'branch_id' => $branch->getKey(), 'branch' => $branch->name, 'sto' => 'JGW', 'created_by' => $this->admin->getKey()]);
        $this->lop->assignments()->create(['technician_id' => $this->tech->getKey(), 'assigned_by' => $this->admin->getKey(), 'assigned_at' => now(), 'status' => 'active']);
        $material = Designator::create(['code' => 'M-BA', 'item_name' => 'Material baru', 'unit' => 'meter', 'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type')]);
        $reservation = $this->lop->materialReservation()->create(['technician_id' => $this->tech->getKey(), 'status' => 'draft']);
        $reservation->items()->create(['designator_id' => $material->getKey(), 'qty' => 5, 'qty_actual' => 5]);
        foreach (['material_arrival' => 'BEFORE', 'pre' => 'SURVEY', 'progress' => 'PROGRESS', 'after' => 'AFTER'] as $category => $step) {
            $this->lop->evidences()->create(['uploaded_by' => $this->tech->getKey(), 'step' => $step, 'category' => $category, 'designator_id' => in_array($category, ['progress', 'after']) ? $material->getKey() : null, 'type' => 'PHOTO', 'file_path' => 'existing.jpg', 'status' => 'approved']);
        }
    }

    private function signature(): string
    {
        $image = imagecreatetruecolor(250, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 10, 50, 200, 20, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($data);
    }

    private function payload(array $overrides = []): array
    {
        $payload = array_replace_recursive([
            'required' => '1', 'action' => 'generate', 'revision' => data_get($this->lop->fresh()->ba_bongkar_data, 'revision'),
            'form' => ['date' => '2026-10-23', 'witel' => 'JEMBER', 'sto_asal' => 'JGW', 'sto_tujuan' => 'WITEL', 'lokasi' => 'Lokasi bongkaran', 'materials' => [['group' => 'distribusi', 'type' => '12 Core', 'length' => 190], ['group' => 'tiang', 'type' => 'T7', 'quantity' => 1, 'segments' => '2 Segmen', 'condition' => 'Bengkok']]],
            'signature_data' => $this->signature(), 'photos' => [UploadedFile::fake()->image('bongkar.jpg', 120, 160)],
        ], $overrides);

        return array_replace($payload, array_intersect_key($overrides, array_flip(['photos'])));
    }

    private function generate(): QeEvidence
    {
        $this->actingAs($this->tech)->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload())->assertSessionHasNoErrors()->assertRedirect();

        return BaBongkarRules::document($this->lop->fresh());
    }

    private function submit(): void
    {
        $this->actingAs($this->tech)->post(route('technician.projects.submit', $this->lop))->assertSessionHasNoErrors();
    }

    private function approveBa(): void
    {
        $this->actingAs($this->admin)->post(route('evidence-approval.ba-bongkar.review', $this->lop), ['decision' => 'approve', 'revision' => data_get($this->lop->fresh()->ba_bongkar_data, 'revision')])->assertSessionHasNoErrors()->assertRedirect(route('evidence-approval.lop.review', [$this->lop, 'step' => 6]));
    }

    public function test_explicit_no_finishes_sixth_step_and_submits_only_to_admin(): void
    {
        $this->actingAs($this->tech)->get(route('technician.projects.show', $this->lop))->assertOk()->assertViewHas('step', 6)->assertSee('Apakah pekerjaan ini memerlukan BA Bongkar?');
        $this->post(route('technician.projects.submit', $this->lop))->assertSessionHasErrors('workflow');
        $this->put(route('technician.projects.ba-bongkar', $this->lop), ['required' => '0', 'action' => 'draft'])->assertSessionHasNoErrors();
        $summary = app(ProjectProgressService::class)->summary($this->lop->fresh());
        $this->assertSame(6, $summary['total_steps']);
        $this->assertSame(100, $summary['percentage']);
        $this->submit();
        $this->actingAs($this->admin)->get(route('evidence-approval.lop.review', [$this->lop, 'step' => 6]))->assertOk()->assertDontSee('Setujui BA Bongkar');
        $this->post(route('evidence-approval.lop.complete', $this->lop))->assertSessionHasNoErrors();
        $this->assertSame('completed', $this->lop->fresh()->status_lop->value);
    }

    public function test_yes_builds_private_pdf_and_only_admin_reviews_ba_without_extra_signatures(): void
    {
        $doc = $this->generate();
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($doc->file_path));
        $this->assertSame($this->tech->getKey(), data_get($doc->metadata, 'signatures.technician.user_id'));
        $other = User::factory()->role('TEKNISI')->create();
        $this->actingAs($other)->get($doc->url())->assertForbidden();
        $this->submit();
        $this->actingAs($this->admin)->get(route('evidence-approval.lop.review', [$this->lop, 'step' => 6]))->assertOk()->assertSee('Setujui BA Bongkar')->assertDontSee('Warehouse')->assertDontSee('TTD Admin');
        $this->get($doc->url())->assertOk();
        $this->post(route('evidence-approval.approve', $doc))->assertForbidden();
        $this->post(route('evidence-approval.lop.complete', $this->lop))->assertSessionHasErrors('review');
        $this->approveBa();
        $doc = BaBongkarRules::document($this->lop->fresh());
        $this->assertSame(EvidenceStatus::APPROVED, $doc->status);
        $this->assertSame($this->admin->getKey(), $doc->reviewed_by);
        $this->post(route('evidence-approval.lop.complete', $this->lop))->assertSessionHasNoErrors();
        $this->assertSame('completed', $this->lop->fresh()->status_lop->value);
        $this->assertSame(['technician'], array_keys($doc->metadata['signatures']));
        $this->assertSame(4, $this->lop->evidences()->whereNotIn('category', ['ba_bongkar', 'ba_bongkar_photo'])->where('status', 'approved')->count());
        $this->assertSame(2, $this->lop->evidences()->whereIn('category', ['ba_bongkar', 'ba_bongkar_photo'])->where('status', 'approved')->count());
    }

    public function test_png_photo_with_icc_warning_can_complete_ba_through_admin(): void
    {
        $png = PngWithExtraIccData::make(base64_decode(substr($this->signature(), strlen('data:image/png;base64,'))));
        $this->actingAs($this->tech)->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload(['photos' => [UploadedFile::fake()->createWithContent('bongkar.png', $png)]]))->assertSessionHasNoErrors()->assertRedirect();
        $photo = $this->lop->evidences()->where('category', 'ba_bongkar_photo')->firstOrFail();
        $this->assertSame($png, Storage::disk('local')->get($photo->file_path));
        $this->submit();
        $this->approveBa();
        $this->post(route('evidence-approval.lop.complete', $this->lop))->assertSessionHasNoErrors()->assertRedirect();
        $doc = BaBongkarRules::document($this->lop->fresh());
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($doc->file_path));
        $this->assertSame('completed', $this->lop->fresh()->status_lop->value);
    }

    public function test_drawn_signature_with_icc_warning_can_generate_ba_pdf(): void
    {
        $png = PngWithExtraIccData::make(base64_decode(substr($this->signature(), strlen('data:image/png;base64,'))));
        $payload = $this->payload(['signature_data' => 'data:image/png;base64,'.base64_encode($png)]);
        $this->actingAs($this->tech)->put(route('technician.projects.ba-bongkar', $this->lop), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $doc = BaBongkarRules::document($this->lop->fresh());
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($doc->file_path));
    }

    public function test_rejection_revises_bundle_and_rejects_stale_review_and_no_bypass(): void
    {
        $doc = $this->generate();
        $revision = data_get($doc->metadata, 'revision');
        $this->submit();
        $this->actingAs($this->admin)->post(route('evidence-approval.ba-bongkar.review', $this->lop), ['decision' => 'reject', 'revision' => $revision, 'review_note' => 'Panjang kabel belum sesuai'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $this->lop->fresh()->status_lop->value);
        $this->actingAs($this->tech)->get(route('technician.projects.show', $this->lop))->assertOk()->assertViewHas('step', 6)->assertSee('Panjang kabel belum sesuai');
        $this->post(route('technician.projects.resume', $this->lop))->assertSessionHasNoErrors();
        $this->put(route('technician.projects.ba-bongkar', $this->lop), ['required' => 0, 'action' => 'draft', 'revision' => $revision])->assertSessionHasErrors('required');
        $this->generate();
        Storage::disk('local')->assertExists($doc->file_path);
        $this->submit();
        $this->actingAs($this->admin)->post(route('evidence-approval.ba-bongkar.review', $this->lop), ['decision' => 'approve', 'revision' => $revision, 'signature_data' => $this->signature()])->assertSessionHasErrors('revision');
        $this->approveBa();
    }

    public function test_quantity_input_uses_whole_units_and_rejects_fractional_counts(): void
    {
        $response = $this->actingAs($this->tech)->get(route('technician.projects.show', $this->lop));
        $response->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $input = (new \DOMXPath($dom))->query('//input[@x-model="row.quantity"]')->item(0);
        $this->assertNotNull($input);
        $this->assertSame('1', $input->getAttribute('min'));
        $this->assertSame('1', $input->getAttribute('step'));

        foreach (['1.5', '1.001'] as $quantity) {
            $this->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload(['form' => ['materials' => [1 => ['quantity' => $quantity]]]]))->assertSessionHasErrors('form.materials.1.quantity');
        }
        $this->assertNull($this->lop->fresh()->ba_bongkar_data);

        $this->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload(['form' => ['materials' => [0 => ['length' => '190.5'], 1 => ['quantity' => '2']]]]))->assertSessionHasNoErrors()->assertRedirect();
        $doc = BaBongkarRules::document($this->lop->fresh());
        $html = view('ba-bongkar.pdf', ['lop' => $this->lop, 'form' => $doc->metadata['form'], 'signatures' => [], 'images' => [], 'groups' => BaBongkarRules::MATERIALS])->render();
        $this->assertStringContainsString('Jumlah Tiang : 2 Batang', $html);
        $this->assertStringContainsString('Panjang : 190.5 Meter', $html);
    }

    public function test_created_ba_shows_summary_until_edit_is_requested_and_cancel_keeps_saved_ba(): void
    {
        $doc = $this->generate();
        $url = route('technician.projects.show', [$this->lop, 'step' => 6]);
        $editUrl = route('technician.projects.show', [$this->lop, 'step' => 6, 'edit_ba' => 1]);
        $this->actingAs($this->tech)->get($url)->assertOk()->assertSee('Lihat / unduh PDF BA Bongkar')->assertSee('Edit BA Bongkar')->assertSee($editUrl)->assertSee('Ajukan Approval')->assertDontSee('name="form[date]"', false)->assertDontSee('Pertahankan foto');
        $this->get($editUrl)->assertOk()->assertSee('name="form[date]"', false)->assertSee('Lokasi bongkaran')->assertSee('Pertahankan foto')->assertSee('TTD teknisi tersimpan.')->assertSee('Batal')->assertSee($url, false)->assertDontSee('Checklist akhir');
        $this->get($url)->assertOk()->assertDontSee('name="form[date]"', false);
        $this->assertSame($doc->file_path, BaBongkarRules::document($this->lop->fresh())->file_path);
        $this->assertSame(data_get($doc->metadata, 'revision'), data_get($this->lop->fresh()->ba_bongkar_data, 'revision'));

        $this->submit();
        $this->get($editUrl)->assertOk()->assertDontSee('name="form[date]"', false)->assertDontSee('Edit BA Bongkar');
    }

    public function test_ba_validation_reopens_editor_with_input_and_saved_no_can_be_changed(): void
    {
        $this->generate();
        $url = route('technician.projects.show', [$this->lop, 'step' => 6]);
        $this->actingAs($this->tech)->from($url)->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload(['form' => ['witel' => '', 'lokasi' => 'Lokasi yang sedang diperbaiki']]))->assertSessionHasErrors('form.witel');
        $this->get($url)->assertOk()->assertSee('name="form[date]"', false)->assertSee('Lokasi yang sedang diperbaiki')->assertSee('Batal')->assertDontSee('Checklist akhir');
        $this->get($url)->assertOk()->assertDontSee('name="form[date]"', false);

        $this->put(route('technician.projects.ba-bongkar', $this->lop), ['required' => '0', 'action' => 'draft', 'revision' => data_get($this->lop->fresh()->ba_bongkar_data, 'revision')])->assertSessionHasNoErrors();
        $this->get($url)->assertOk()->assertSee('Ubah pilihan BA Bongkar')->assertSee('Ajukan Approval')->assertDontSee('name="form[date]"', false);
        $this->get(route('technician.projects.show', [$this->lop, 'step' => 6, 'edit_ba' => 1]))->assertOk()->assertSee('Apakah pekerjaan ini memerlukan BA Bongkar?');
    }

    public function test_validation_ownership_and_transaction_cleanup(): void
    {
        $other = User::factory()->role('TEKNISI')->create();
        $this->actingAs($other)->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload())->assertForbidden();
        $this->actingAs($this->tech)->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload(['form' => ['materials' => [['type' => '288 Core']]]]))->assertSessionHasErrors('form.materials.0.type');
        $this->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload(['signature_data' => '']))->assertSessionHasErrors('workflow');
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $mock = Mockery::mock(BaBongkarPdfService::class);
        $mock->shouldReceive('render')->andThrow(new \RuntimeException('render failed'));
        $this->app->instance(BaBongkarPdfService::class, $mock);
        $this->withoutExceptionHandling();
        try {
            $this->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload());
            $this->fail('Render harus gagal.');
        } catch (\RuntimeException $error) {
            $this->assertSame('render failed', $error->getMessage());
        }
        $this->assertNull($this->lop->fresh()->ba_bongkar_required);
        $this->assertSame(0, $this->lop->evidences()->whereIn('category', ['ba_bongkar', 'ba_bongkar_photo'])->count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_admin_ba_scope_blocks_other_branch_and_accepts_legacy_location(): void
    {
        $doc = $this->generate();
        $this->submit();
        $other = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'branch', 'branch_id' => Branch::create(['code' => 'BA-OTHER', 'name' => 'Other BA'])->getKey()]);
        $this->actingAs($other)->get($doc->url())->assertForbidden();
        $this->post(route('evidence-approval.ba-bongkar.review', $this->lop), ['decision' => 'approve', 'revision' => data_get($doc->metadata, 'revision')])->assertForbidden();
        $scoped = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'branch', 'branch_id' => $this->lop->branch_id]);
        $this->lop->update(['branch_id' => null]);
        $this->actingAs($scoped)->get(route('evidence-approval.lop.review', [$this->lop, 'step' => 6]))->assertOk()->assertSee('Setujui BA Bongkar');
        $this->post(route('evidence-approval.ba-bongkar.review', $this->lop), ['decision' => 'approve', 'revision' => data_get($doc->metadata, 'revision')])->assertSessionHasNoErrors();
    }

    public function test_activation_is_dry_run_by_default_and_idempotent(): void
    {
        $this->lop->refresh()->update(['status_lop' => 'completed']);
        $cutoff = now()->addDay()->toIso8601String();
        $this->artisan('dompis:activate-ba-bongkar', ['--cutoff' => $cutoff])->assertSuccessful();
        $this->assertSame('completed', $this->lop->fresh()->status_lop->value);
        $this->artisan('dompis:activate-ba-bongkar', ['--cutoff' => $cutoff, '--actor' => $this->admin->getKey(), '--apply' => true])->assertSuccessful();
        $this->assertSame('progress', $this->lop->fresh()->status_lop->value);
        $this->assertSame(4, $this->lop->evidences()->where('status', 'approved')->count());
        $this->lop->refresh()->update(['status_lop' => 'completed']);
        $this->artisan('dompis:activate-ba-bongkar', ['--cutoff' => $cutoff, '--actor' => $this->admin->getKey(), '--apply' => true])->assertSuccessful();
        $this->assertSame('completed', $this->lop->fresh()->status_lop->value);
        $this->assertSame(1, $this->lop->histories()->where('event_type', 'ba_bongkar_activated')->count());
    }

    public function test_admin_ba_review_all_scope_levels_and_no_scope_are_enforced(): void
    {
        $this->generate();
        $this->submit();
        $branch = Branch::findOrFail($this->lop->branch_id);
        $region = Region::findOrFail($branch->region_id);
        $region->update(['area_id' => Area::firstOrCreate(['code' => '3'], ['name' => 'Area 3'])->getKey()]);
        $sto = ServiceArea::create(['workzone' => 'BA-JGW', 'name' => 'JGW', 'branch_id' => $branch->getKey(), 'region_id' => $region->getKey()]);
        $this->lop->update(['service_area_id' => $sto->getKey()]);
        foreach ([['admin_scope_type' => 'area', 'area_id' => $region->area_id], ['admin_scope_type' => 'region', 'region_id' => $region->getKey()], ['admin_scope_type' => 'branch', 'branch_id' => $branch->getKey()], ['admin_scope_type' => 'service_area']] as $scope) {
            $user = User::factory()->role('ADMIN')->create($scope);
            if ($scope['admin_scope_type'] === 'service_area') {
                $user->serviceAreas()->attach($sto);
            }
            $this->assertTrue($user->can('reviewBaBongkar', $this->lop->fresh()));
            $this->actingAs($user)->get(route('evidence-approval.lop.review', [$this->lop, 'step' => 6]))->assertOk()->assertSee('Setujui BA Bongkar');
        }
        foreach (['ADMIN', 'TEKNISI', 'APPROVER', 'MANAGER'] as $code) {
            $user = User::factory()->role($code)->create();
            $this->assertFalse($user->can('reviewBaBongkar', $this->lop->fresh()));
            $this->actingAs($user)->post(route('evidence-approval.ba-bongkar.review', $this->lop), ['decision' => 'approve', 'revision' => data_get($this->lop->fresh()->ba_bongkar_data, 'revision')])->assertForbidden();
        }
    }

    public function test_removed_warehouse_role_cannot_be_created_imported_or_opened(): void
    {
        $this->assertDatabaseMissing('roles', ['code' => 'WAREHOUSE']);
        $this->assertDatabaseMissing('permissions', ['code' => 'review_ba_bongkar']);
        $this->assertFalse(Route::has('warehouse.review'));
        $this->actingAs($this->admin)->get('/warehouse/dashboard')->assertNotFound();
        $this->get('/warehouse/inbox')->assertNotFound();
        $legacy = Role::create(['code' => 'WAREHOUSE', 'name' => 'Warehouse']);
        $payload = ['name' => 'Gudang Jember', 'username' => 'gudang-ba', 'password' => 'TestWarehouse123!', 'password_confirmation' => 'TestWarehouse123!', 'role_id' => $legacy->getKey(), 'status' => 'active'];
        $this->post(route('users.store'), $payload)->assertSessionHasErrors('role_id');
        $this->get(route('users.create'))->assertOk()->assertDontSee('Warehouse');
        $headers = UserImportService::HEADERS;
        $row = ['name' => 'Gudang Import', 'username' => 'gudang-import', 'password' => 'TestWarehouse123!', 'role' => 'WAREHOUSE', 'status' => 'active'];
        $csv = implode(',', $headers)."\n".implode(',', array_map(fn ($key) => $row[$key] ?? '', $headers));
        $path = 'imports/removed-role.enc';
        Storage::disk('local')->put($path, Crypt::encryptString($csv));
        $batch = QeImportBatch::create(['uuid' => (string) Str::uuid(), 'type' => 'users', 'status' => 'queued', 'uploaded_by' => $this->admin->getKey(), 'original_name' => 'warehouse.csv', 'file_path' => $path]);
        app(UserImportService::class)->process($batch, $this->admin);
        $this->assertSame(0, $batch->fresh()->success_rows);
        $this->assertDatabaseMissing('users', ['username' => 'gudang-import']);
    }

    public function test_admin_must_review_both_ba_and_work_evidence_before_completion(): void
    {
        $doc = $this->generate();
        $this->submit();
        $normal = $this->lop->evidences()->where('category', 'after')->firstOrFail();
        $normal->update(['status' => 'pending']);
        $this->actingAs($this->admin)->post(route('evidence-approval.ba-bongkar.review', $this->lop), ['decision' => 'approve', 'revision' => (string) Str::uuid()])->assertSessionHasErrors('revision');
        $this->approveBa();
        $this->post(route('evidence-approval.lop.complete', $this->lop))->assertSessionHasErrors('review');
        $this->assertSame('waiting_approval', $this->lop->fresh()->status_lop->value);
        $this->post(route('evidence-approval.approve', $normal))->assertSessionHasNoErrors();
        $this->post(route('evidence-approval.lop.complete', $this->lop))->assertSessionHasNoErrors();
        $this->assertSame('completed', $this->lop->fresh()->status_lop->value);
    }

    public function test_draft_is_not_reviewable_and_generation_requires_photo_and_real_signature(): void
    {
        $input = $this->payload(['action' => 'draft', 'signature_data' => '', 'photos' => []]);
        $this->actingAs($this->tech)->put(route('technician.projects.ba-bongkar', $this->lop), $input)->assertSessionHasNoErrors();
        $this->assertFalse(BaBongkarRules::complete($this->lop->fresh()));
        $this->post(route('technician.projects.submit', $this->lop))->assertSessionHasErrors('workflow');
        $this->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload(['photos' => []]))->assertSessionHasErrors('workflow');
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $white = imagecreatetruecolor(100, 100);
        imagefill($white, 0, 0, imagecolorallocate($white, 255, 255, 255));
        ob_start();
        imagepng($white);
        $bytes = ob_get_clean();
        imagedestroy($white);
        $this->put(route('technician.projects.ba-bongkar', $this->lop), $this->payload(['signature_data' => 'data:image/png;base64,'.base64_encode($bytes)]))->assertSessionHasErrors('signature_data');
        $this->actingAs($this->admin)->post(route('evidence-approval.ba-bongkar.review', $this->lop), ['decision' => 'approve', 'revision' => data_get($this->lop->fresh()->ba_bongkar_data, 'revision')])->assertForbidden();
    }

    public function test_activation_preserves_early_statuses_and_skips_archived_lops_and_missing_actor(): void
    {
        $lops = [];
        foreach (['draft', 'assigned', 'picked_up', 'survey', 'progress', 'waiting_approval', 'rejected', 'completed'] as $i => $status) {
            $lops[$status] = QeLop::create(['incident' => 'ACT-BA-'.$i, 'nama_lop' => $status, 'status_lop' => $status, 'program_type' => 'recovery', 'created_by' => $this->admin->getKey()]);
        }
        $archived = QeLop::create(['incident' => 'ACT-ARCHIVE', 'nama_lop' => 'Archive', 'status_lop' => 'completed', 'program_type' => 'recovery', 'created_by' => $this->admin->getKey()]);
        $archived->delete();
        $options = ['--cutoff' => now()->addDay()->toIso8601String(), '--apply' => true];
        $this->artisan('dompis:activate-ba-bongkar', $options)->assertFailed();
        $this->artisan('dompis:activate-ba-bongkar', [...$options, '--actor' => $this->admin->getKey()])->assertSuccessful();
        foreach ($lops as $before => $lop) {
            $this->assertSame(in_array($before, ['waiting_approval', 'rejected', 'completed']) ? 'progress' : $before, $lop->fresh()->status_lop->value);
        }
        $this->assertSame('completed', QeLop::withTrashed()->find($archived->getKey())->status_lop->value);
    }

    public function test_admin_review_keeps_original_ba_pdf_and_photos_in_evidence_archive(): void
    {
        $doc = $this->generate();
        $this->submit();
        $this->approveBa();
        $this->assertSame($doc->file_path, BaBongkarRules::document($this->lop->fresh())->file_path);
        [$path] = app(EvidenceArchiveService::class)->build($this->lop->fresh());
        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $this->assertSame(1, $zip->numFiles);
            $this->assertStringContainsString('/ba_bongkar_photo/', $zip->getNameIndex(0));
            $zip->close();
        } finally {
            unlink($path);
        }
    }

    public function test_schema_rollback_refuses_to_discard_saved_decisions(): void
    {
        $this->lop->update(['ba_bongkar_required' => false]);
        $migration = require database_path('migrations/2026_10_23_000001_add_ba_bongkar_to_qe_lops.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('keputusan atau draft');
        $migration->down();
    }

    public function test_role_removal_refuses_accounts_and_safe_retirement_preserves_account_history(): void
    {
        $migration = require database_path('migrations/2026_10_26_000001_remove_warehouse_role.php');
        $migration->down();
        $legacy = User::factory()->role('WAREHOUSE')->create();
        try {
            $migration->up();
            $this->fail('Role with assigned accounts must not be removed.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('akun Warehouse', $error->getMessage());
        }
        $this->assertDatabaseHas('users', ['id_user' => $legacy->getKey(), 'status' => 'active', 'role_id' => $legacy->role_id]);
        DB::table('sessions')->insert(['id' => 'removed-role-session', 'user_id' => $legacy->getKey(), 'payload' => base64_encode('test'), 'last_activity' => time()]);
        $retired = app(UserService::class)->retireRemovedRole($legacy, $this->admin);
        $this->assertSame('inactive', $retired->status);
        $this->assertNull($retired->role_id);
        $this->assertDatabaseMissing('sessions', ['id' => 'removed-role-session']);
        $this->assertDatabaseHas('user_histories', ['target_user_id' => $legacy->getKey(), 'event_type' => 'role_retired']);
        $migration->up();
        $this->assertDatabaseMissing('roles', ['code' => 'WAREHOUSE']);
        $this->assertDatabaseHas('users', ['id_user' => $legacy->getKey(), 'deleted_at' => null]);
    }

    public function test_pdf_matches_reference_fields_and_keeps_manual_signatures_blank(): void
    {
        $form = [...$this->payload()['form'], 'barcode_scmt' => 'SCMT-001', 'receiver_name' => 'Penerima Lapangan', 'receiver_position' => 'Security'];
        $html = view('ba-bongkar.pdf', [
            'lop' => $this->lop, 'form' => $form, 'groups' => BaBongkarRules::MATERIALS, 'images' => [],
            'signatures' => [
                'technician' => ['name' => 'Teknisi Penyerah', 'phone' => '081234567890'],
                'admin' => ['name' => 'Nama Admin Privat', 'nik' => '789789', 'image' => $this->signature()],
            ],
        ])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//div[@class="sheet"]')->length);
        $columns = $xpath->query('//div[@data-signature-role]');
        $this->assertCount(2, $columns);
        foreach ($columns as $column) {
            $this->assertSame('', trim($column->textContent));
            $this->assertSame(0, $xpath->query('.//img', $column)->length);
        }
        foreach (['Teknisi Penyerah', 'Penerima Lapangan', 'Security', 'No. Tlpn', 'Barcode', 'SCMT-001', 'BRANCH', 'MGR. NETWORK OPERATION OSP', 'DATA MANAGENT REGIONAL'] as $text) {
            $this->assertStringContainsStringIgnoringCase($text, $html);
        }
        $this->assertStringNotContainsString('NIK. _______________', $html);
        $this->assertStringNotContainsString('Nama Admin Privat', $html);
        $this->assertStringNotContainsString('789789', $html);
    }

    public function test_ba_pdf_download_action_only_appears_when_a_ba_exists(): void
    {
        $this->actingAs($this->admin)->get(route('program.show', 'recovery'))
            ->assertOk()
            ->assertDontSee(route('lop.ba-bongkar.download', $this->lop), false);
        $this->actingAs($this->admin)->get(route('lop.ba-bongkar.download', $this->lop))->assertNotFound();

        $doc = $this->generate();
        $downloadUrl = route('lop.ba-bongkar.download', $this->lop);

        $this->actingAs($this->admin)->get(route('program.show', 'recovery'))
            ->assertOk()
            ->assertSee('Download BA Bongkar')
            ->assertSee($downloadUrl, false);
        $this->actingAs($this->admin)->get(route('data-lops.index'))
            ->assertOk()
            ->assertSee('Download BA Bongkar')
            ->assertSee($downloadUrl, false);
        $this->actingAs($this->admin)->get($downloadUrl)
            ->assertOk()
            ->assertDownload(data_get($doc->metadata, 'original_name'));
    }

    public function test_pdf_preserves_extra_material_details_outside_reference_slots(): void
    {
        $form = $this->payload()['form'];
        $form['mitra'] = 'MITRA-TERCATAT';
        $form['materials'] = [
            ['group' => 'feeder', 'type' => '48 Core', 'length' => 10, 'year' => 2020, 'brand' => 'MEREK-FEEDER-TERCATAT'],
            ['group' => 'drop_core', 'length' => 2, 'quantity' => 1, 'year' => 2021],
            ['group' => 'drop_core', 'length' => 3, 'quantity' => 2, 'year' => 2022],
        ];
        $html = view('ba-bongkar.pdf', ['lop' => $this->lop, 'form' => $form, 'groups' => BaBongkarRules::MATERIALS, 'images' => [], 'signatures' => []])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $continuation = (new \DOMXPath($dom))->query('//div[@class="continuation"]')->item(0);
        $this->assertNotNull($continuation);
        $this->assertStringContainsString('MEREK-FEEDER-TERCATAT', $continuation->textContent);
        $this->assertStringContainsString('2022', $continuation->textContent);
        $this->assertStringContainsString('MITRA-TERCATAT', $continuation->textContent);
    }

    public function test_pdf_supports_repeated_materials_and_twelve_photos_across_pages(): void
    {
        $payload = $this->payload();
        $payload['form']['materials'] = array_fill(0, 25, ['group' => 'distribusi', 'type' => '12 Core', 'length' => 190]);
        $payload['photos'] = array_map(fn ($i) => UploadedFile::fake()->image("bongkar-$i.jpg", 1200, 1600), range(1, 12));
        $this->actingAs($this->tech)->put(route('technician.projects.ba-bongkar', $this->lop), $payload)->assertSessionHasNoErrors();
        $doc = BaBongkarRules::document($this->lop->fresh());
        $bytes = Storage::disk('local')->get($doc->file_path);
        $this->assertGreaterThanOrEqual(5, preg_match_all('/\/Type\s*\/Page\b/', $bytes));
        $this->assertCount(25, $doc->metadata['form']['materials']);
        $this->assertSame(12, $this->lop->evidences()->where('category', 'ba_bongkar_photo')->count());
    }
}
