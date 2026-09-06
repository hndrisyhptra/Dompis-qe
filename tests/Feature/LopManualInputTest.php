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
            'wbs_type' => 'recovery',
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
            'area' => '3', 'segment' => 'feeder', 'wbs_type' => 'relok_utilitas',
            'job_description' => 'Relokasi utilitas',
        ])->assertRedirect(route('lop.create'))->assertSessionHasErrors('budget_type');
    }

    public function test_ihld_id_can_be_added_later_through_update(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $lop = QeLop::create([
            'incident' => 'INC901', 'nama_lop' => '3SDA_QEREC_INC901_Test',
            'wbs_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'status_lop' => 'draft', 'created_by' => $admin->id_user,
        ]);

        $this->actingAs($admin)->put(route('lop.update', $lop), [
            'incident' => 'INC901', 'nama_lop' => $lop->nama_lop,
            'wbs_type' => 'recovery', 'sto' => 'SDA', 'branch' => 'SIDOARJO',
            'area' => '3', 'segment' => 'odp', 'job_description' => 'Test',
            'ihld_id' => 'IHLD-7788',
        ])->assertRedirect(route('lop.index'));

        $this->assertDatabaseHas('qe_lops', ['incident' => 'INC901', 'ihld_id' => 'IHLD-7788']);
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
                'job_description' => 'Ganti ODP', 'wbs_type' => 'recovery',
            ])
        );
    }
}
