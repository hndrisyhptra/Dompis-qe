<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\QeLop;
use App\Models\TicketSegmentMap;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketLookupTest extends TestCase
{
    use RefreshDatabase;

    private string $externalDb;

    protected function setUp(): void
    {
        parent::setUp();

        // Koneksi eksternal `mysql_dompis` diarahkan ke sqlite terpisah untuk tes,
        // lalu tabel-tabel fixture dibangun manual di koneksi itu.
        $this->externalDb = tempnam(sys_get_temp_dir(), 'dompis_ext_').'.sqlite';
        touch($this->externalDb);

        config()->set('database.connections.mysql_dompis', [
            'driver' => 'sqlite',
            'database' => $this->externalDb,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('mysql_dompis');

        $schema = DB::connection('mysql_dompis')->getSchemaBuilder();

        $schema->create('ticket', function (Blueprint $t) {
            $t->increments('id_ticket');
            $t->string('incident');
            $t->string('workzone')->nullable();
            $t->string('jenis_tiket_2')->nullable();
            $t->text('summary')->nullable();
        });
        $schema->create('service_area', function (Blueprint $t) {
            $t->increments('id_sa');
            $t->string('nama_sa')->nullable();
            $t->integer('area_id')->nullable();
        });
        $schema->create('area', function (Blueprint $t) {
            $t->increments('id_area');
            $t->integer('branch_id')->nullable();
        });
        $schema->create('branch', function (Blueprint $t) {
            $t->increments('id_branch');
            $t->string('nama_branch');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('mysql_dompis');

        if (is_file($this->externalDb)) {
            @unlink($this->externalDb);
        }

        parent::tearDown();
    }

    private function seedExternalTicket(string $incident, ?string $workzone, ?string $jenisTiket2, ?string $summary = null): void
    {
        $ext = DB::connection('mysql_dompis');
        $ext->table('ticket')->insert([
            'incident' => $incident,
            'workzone' => $workzone,
            'jenis_tiket_2' => $jenisTiket2,
            'summary' => $summary,
        ]);
    }

    private function seedExternalBranchChain(string $workzone, string $namaBranch): void
    {
        $ext = DB::connection('mysql_dompis');
        $branchId = $ext->table('branch')->insertGetId(['nama_branch' => $namaBranch]);
        $areaId = $ext->table('area')->insertGetId(['branch_id' => $branchId]);
        $ext->table('service_area')->insert(['nama_sa' => $workzone, 'area_id' => $areaId]);
    }

    private function admin(): User
    {
        return User::factory()->role(UserRole::ADMIN->value)->create();
    }

    public function test_lookup_resolves_sto_branch_and_segment(): void
    {
        Branch::create(['code' => 'JBR', 'name' => 'JEMBER', 'region' => 'REGION JATIM']);
        TicketSegmentMap::create(['source_value' => 'GAMAS DISTRIBUSI', 'segment' => 'distribusi']);

        $this->seedExternalTicket('INC999', 'KBS', 'GAMAS DISTRIBUSI');
        $this->seedExternalBranchChain('KBS', 'JEMBER');

        $this->actingAs($this->admin())
            ->getJson(route('lop.ticket-lookup', ['incident' => 'inc999']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'sto' => 'KBS',
                'branch' => 'JEMBER',
                'segment' => 'distribusi',
                'warnings' => [],
            ])
            ->assertJsonPath('summary', "Incident: INC999\nWorkzone: KBS\nJenis Tiket: GAMAS DISTRIBUSI");
    }

    public function test_lookup_flags_incident_already_used_by_a_lop(): void
    {
        $admin = $this->admin();
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);

        $lop = QeLop::create([
            'incident' => 'INC5000', 'nama_lop' => '3SDA_QEREC_INC5000_ODP', 'program_type' => 'recovery',
            'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3', 'segment' => 'odp',
            'job_description' => 'x', 'status_lop' => 'progress', 'created_by' => $admin->id_user,
        ]);

        $this->actingAs($admin)
            ->getJson(route('lop.ticket-lookup', ['incident' => 'inc5000']))
            ->assertOk()
            ->assertJsonPath('existing_lop.nama_lop', '3SDA_QEREC_INC5000_ODP')
            ->assertJsonPath('existing_lop.trashed', false);

        $lop->delete();

        $this->actingAs($admin)
            ->getJson(route('lop.ticket-lookup', ['incident' => 'INC5000']))
            ->assertOk()
            ->assertJsonPath('existing_lop.trashed', true);
    }

    public function test_lookup_has_no_existing_lop_key_for_fresh_incident(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('lop.ticket-lookup', ['incident' => 'INC-BRAND-NEW']))
            ->assertOk()
            ->assertJsonMissingPath('existing_lop');
    }

    public function test_lookup_resolves_gamas_gpon_to_gpon_segment(): void
    {
        Branch::create(['code' => 'BEA', 'name' => 'BANGKALAN', 'region' => 'REGION JATIM']);
        TicketSegmentMap::create(['source_value' => 'GAMAS GPON', 'segment' => 'gpon']);

        $this->seedExternalTicket('INC888', 'BEA', 'GAMAS GPON');
        $this->seedExternalBranchChain('BEA', 'BANGKALAN');

        $this->actingAs($this->admin())
            ->getJson(route('lop.ticket-lookup', ['incident' => 'INC888']))
            ->assertOk()
            ->assertJson(['found' => true, 'segment' => 'gpon', 'warnings' => []]);
    }

    public function test_lookup_extracts_datek_from_summary(): void
    {
        Branch::create(['code' => 'PME', 'name' => 'PAMEKASAN', 'region' => 'REGION JATIM']);
        TicketSegmentMap::create(['source_value' => 'GAMAS DISTRIBUSI', 'segment' => 'distribusi']);

        $this->seedExternalTicket(
            'INC777', 'PME', 'GAMAS DISTRIBUSI',
            '[SQM GAMAS] | AKSES | DISTRIBUSI | PME | [ODC-PME-FBK] | '
            .'Datek ODP Terdampak : [ODP-PME-FBK/29, ODP-PME-FBK/33] | 4 Jam',
        );
        $this->seedExternalBranchChain('PME', 'PAMEKASAN');

        $this->actingAs($this->admin())
            ->getJson(route('lop.ticket-lookup', ['incident' => 'INC777']))
            ->assertOk()
            ->assertJsonPath('datek.kategori', 'distribusi')
            ->assertJsonPath('datek.odc.0', 'ODC-PME-FBK')
            ->assertJsonPath('datek.odp.0', 'ODP-PME-FBK/29')
            ->assertJsonPath('datek.odp.1', 'ODP-PME-FBK/33');
    }

    public function test_parse_datek_endpoint_parses_raw_summary(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('lop.parse-datek', [
                'summary' => 'GPON01-D5-SMP-3 [2/10, 2/11], GPON03-D5-SMP-2 [7/4] | PIC : RANU / 082139794255',
            ]))
            ->assertOk()
            ->assertJsonPath('gpon.0.name', 'GPON01-D5-SMP-3')
            ->assertJsonPath('gpon.0.ports.0', '2/10')
            ->assertJsonPath('pic.nama', 'RANU');
    }

    public function test_parse_datek_requires_create_permission(): void
    {
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $this->actingAs($teknisi)
            ->getJson(route('lop.parse-datek', ['summary' => 'x']))
            ->assertForbidden();
    }

    public function test_lookup_warns_when_segment_not_mapped(): void
    {
        Branch::create(['code' => 'SDA', 'name' => 'SIDOARJO', 'region' => 'REGION JATIM']);
        $this->seedExternalTicket('INC1', 'GEM', 'SQM');
        $this->seedExternalBranchChain('GEM', 'SIDOARJO');

        $response = $this->actingAs($this->admin())
            ->getJson(route('lop.ticket-lookup', ['incident' => 'INC1']))
            ->assertOk()
            ->assertJson(['found' => true, 'sto' => 'GEM', 'branch' => 'SIDOARJO', 'segment' => null]);

        $this->assertNotEmpty($response->json('warnings'));
    }

    public function test_lookup_warns_when_branch_cannot_be_resolved(): void
    {
        // Tiket ada, tapi workzone tidak punya rantai service_area -> branch.
        $this->seedExternalTicket('INC2', 'ZZZ', 'REG');

        $response = $this->actingAs($this->admin())
            ->getJson(route('lop.ticket-lookup', ['incident' => 'INC2']))
            ->assertOk()
            ->assertJson(['found' => true, 'sto' => 'ZZZ', 'branch' => null]);

        $this->assertNotEmpty($response->json('warnings'));
    }

    public function test_lookup_ignores_branch_not_present_in_local_master(): void
    {
        // Rantai eksternal me-resolve ke "KUPANG" tapi master branches lokal tidak punya.
        $this->seedExternalTicket('INC3', 'KPG', 'REG');
        $this->seedExternalBranchChain('KPG', 'KUPANG');

        $this->actingAs($this->admin())
            ->getJson(route('lop.ticket-lookup', ['incident' => 'INC3']))
            ->assertOk()
            ->assertJson(['found' => true, 'branch' => null]);
    }

    public function test_lookup_returns_not_found_for_unknown_incident(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('lop.ticket-lookup', ['incident' => 'INC-DOES-NOT-EXIST']))
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_lookup_requires_create_permission(): void
    {
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $this->actingAs($teknisi)
            ->getJson(route('lop.ticket-lookup', ['incident' => 'INC999']))
            ->assertForbidden();
    }

    public function test_lookup_validates_incident_param(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('lop.ticket-lookup'))
            ->assertStatus(422);
    }
}
