<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\User;
use App\Services\LopNamingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LopManualInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_lop_with_manual_input_flow_and_generated_name(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);

        $this->actingAs($admin)->get(route('lop.create'))
            ->assertOk()
            ->assertSee('Incident')
            ->assertSee('Deskripsi Pekerjaan')
            ->assertSee('ID IHLD');

        $response = $this->actingAs($admin)->post(route('lop.store'), [
            'incident' => 'inc123456',
            'sto' => 'sda',
            'branch' => 'SIDOARJO',
            'area' => '3',
            'segment' => 'odp',
            'program_type' => 'recovery',
            'job_description' => 'Penggantian BOX ODP',
            'ticket_summary' => "Incident: INC123456\nWorkzone: SDA\nStatus: OPEN",
            'datek' => json_encode([
                'kategori' => 'distribusi',
                'odc' => ['ODC-SDA-XYZ'],
                'odp' => ['ODP-SDA-XYZ/12'],
                'gpon' => [['name' => 'GPON01-D5-SDA-2', 'ip' => '10.1.2.3', 'ports' => ['1/4']]],
                'kabel' => [],
                'ip' => [],
                'olt' => false,
                'rca' => '',
                'est' => '',
                'pic' => ['nama' => '', 'telp' => ''],
            ]),
            'ihld_id' => '',
            'nama_lop' => '',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qe_lops', [
            'incident' => 'INC123456',
            'nama_lop' => '3SDA_QEREC_INC123456_ODP',
            'segment' => 'odp',
            'budget_type' => null,
            'ticket_summary' => "Incident: INC123456\nWorkzone: SDA\nStatus: OPEN",
            'ihld_id' => null,
        ]);

        $lop = QeLop::where('incident', 'INC123456')->firstOrFail();
        $this->assertSame(['ODC-SDA-XYZ'], $lop->datek['odc']);
        $this->assertSame('GPON01-D5-SDA-2', $lop->datek['gpon'][0]['name']);
        $this->assertSame(['1/4'], $lop->datek['gpon'][0]['ports']);
    }

    public function test_relok_utilitas_requires_budget_type(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);

        $this->actingAs($admin)->from(route('lop.create'))->post(route('lop.store'), [
            'incident' => 'INC900', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'feeder', 'program_type' => 'relok_utilitas',
            'job_description' => 'Relokasi utilitas',
        ])->assertRedirect(route('lop.create'))->assertSessionHasErrors('budget_type');
    }

    public function test_ihld_id_can_be_added_later_through_update(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $lop = QeLop::create([
            'incident' => 'INC901', 'nama_lop' => '3SDA_QEREC_INC901_Test',
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        $this->actingAs($admin)->put(route('lop.update', $lop), [
            'incident' => 'INC901', 'nama_lop' => $lop->nama_lop,
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'ihld_id' => 'IHLD-7788',
        ])->assertRedirect(route('lop.index'));

        $this->assertDatabaseHas('qe_lops', ['incident' => 'INC901', 'ihld_id' => 'IHLD-7788']);
    }

    public function test_manual_incident_number_can_be_changed_through_update(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $lop = QeLop::create([
            'incident' => 'INP3102092601', 'nama_lop' => '3SDA_QEREC_INP3102092601_Test',
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        $this->actingAs($admin)->put(route('lop.update', $lop), [
            'incident' => 'INP3102092699', 'nama_lop' => $lop->nama_lop,
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
        ])->assertRedirect(route('lop.index'));

        $this->assertDatabaseHas('qe_lops', ['id_qe_lops' => $lop->id_qe_lops, 'incident' => 'INP3102092699']);
    }

    public function test_manual_incident_number_must_keep_inp_format(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $lop = QeLop::create([
            'incident' => 'INP3102092601', 'nama_lop' => '3SDA_QEREC_INP3102092601_Test',
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        $this->actingAs($admin)->from(route('lop.edit', $lop))->put(route('lop.update', $lop), [
            'incident' => 'INC777', 'nama_lop' => $lop->nama_lop,
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
        ])->assertRedirect(route('lop.edit', $lop))->assertSessionHasErrors('incident');

        $this->assertSame('INP3102092601', $lop->fresh()->incident);
    }

    public function test_real_incident_number_cannot_be_changed_through_update(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $lop = QeLop::create([
            'incident' => 'INC50390302', 'nama_lop' => '3SDA_QEREC_INC50390302_Test',
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        // Kirim incident berbeda -> diabaikan, nilai lama dipertahankan (tanpa error).
        $this->actingAs($admin)->put(route('lop.update', $lop), [
            'incident' => 'INC99999999', 'nama_lop' => $lop->nama_lop,
            'program_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'ihld_id' => 'IHLD-1',
        ])->assertRedirect(route('lop.index'));

        $fresh = $lop->fresh();
        $this->assertSame('INC50390302', $fresh->incident);
        $this->assertSame('IHLD-1', $fresh->ihld_id);
        $this->assertDatabaseMissing('qe_lops', ['incident' => 'INC99999999']);
    }

    public function test_edit_form_locks_real_incident_and_allows_manual_incident(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);

        $real = QeLop::create([
            'incident' => 'INC50390302', 'nama_lop' => 'A', 'program_type' => 'recovery',
            'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'segment' => 'odp',
            'job_description' => 'Test', 'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);
        $manual = QeLop::create([
            'incident' => 'INP3102092601', 'nama_lop' => 'B', 'program_type' => 'recovery',
            'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'segment' => 'odp',
            'job_description' => 'Test', 'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        $this->actingAs($admin)->get(route('lop.edit', $real))
            ->assertOk()->assertSee('tidak dapat diubah');
        $this->actingAs($admin)->get(route('lop.edit', $manual))
            ->assertOk()->assertSee('boleh diubah');
    }

    public function test_only_super_admin_can_manage_lop_name_format(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->actingAs($admin)->get(route('lop-name-format.edit'))->assertForbidden();

        $this->actingAs($superAdmin)->get(route('lop-name-format.edit'))
            ->assertOk()
            ->assertSee('Format Nama LOP')
            ->assertSee('{incident}');

        $this->actingAs($superAdmin)->put(route('lop-name-format.update'), [
            'template' => '{incident}_{branch}_{description}',
        ])->assertRedirect(route('lop-name-format.edit'));

        $this->assertSame(
            'INC123_SIDOARJO_Ganti_ODP',
            app(LopNamingService::class)->generate([
                'incident' => 'INC123', 'branch' => 'SIDOARJO',
                'job_description' => 'Ganti ODP', 'program_type' => 'recovery',
            ])
        );
    }
}
