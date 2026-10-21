<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorType;
use App\Models\QeBoq;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\LopService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

/**
 * Filter tanggal laporan pada Master Data > BOQ.
 *
 * Nilainya dibaca dari kolom lokal qe_lops.reported_date yang disalin dari
 * ticket.reported_date (koneksi read-only mysql_dompis) saat LOP dibuat.
 * Halaman dan export tidak lagi menyentuh koneksi tiket.
 */
class DataBoqDateFilterTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    public function test_reported_date_range_is_inclusive_on_both_bounds(): void
    {
        $user = $this->user();
        $this->boq($this->lop($user, 'INC-BEFORE', ['reported_date' => '2026-10-14']), $user);
        $this->boq($this->lop($user, 'INC-START', ['reported_date' => '2026-10-15']), $user);
        $this->boq($this->lop($user, 'INC-END', ['reported_date' => '2026-10-16']), $user);
        $this->boq($this->lop($user, 'INC-AFTER', ['reported_date' => '2026-10-17']), $user);

        $this->actingAs($user)->get(route('data-boqs.index', ['date_from' => '2026-10-15', 'date_to' => '2026-10-16']))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => $this->incidents($boqs) === ['INC-END', 'INC-START'])
            ->assertViewHas('dateFrom', '2026-10-15')
            ->assertViewHas('dateTo', '2026-10-16')
            ->assertSee('Filter aktif')
            ->assertDontSee('INC-BEFORE')
            ->assertDontSee('INC-AFTER');
    }

    public function test_open_range_and_missing_dates_keep_every_boq_visible(): void
    {
        $user = $this->user();
        $this->boq($this->lop($user, 'INC-A', ['reported_date' => '2026-10-14']), $user);
        $this->boq($this->lop($user, 'INC-B', ['reported_date' => '2026-10-17']), $user);
        // Tanggal tidak terbaca tetap null: terlihat tanpa filter, tak pernah masuk rentang.
        $this->boq($this->lop($user, 'INC-UNREADABLE'), $user);

        $this->actingAs($user)->get(route('data-boqs.index'))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => count($this->incidents($boqs)) === 3);

        $this->get(route('data-boqs.index', ['date_from' => '2026-10-15']))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => $this->incidents($boqs) === ['INC-B']);

        $this->get(route('data-boqs.index', ['date_to' => '2026-10-14']))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => $this->incidents($boqs) === ['INC-A']);
    }

    public function test_date_filter_also_narrows_the_technician_reservation_list(): void
    {
        $user = $this->user();
        $designator = $this->designator($user);
        foreach ([
            'INC-RESERVED-IN' => '2026-10-15',
            'INC-RESERVED-OUT' => '2026-10-20',
        ] as $incident => $reportedDate) {
            $lop = $this->lop($user, $incident, ['reported_date' => $reportedDate]);
            $reservation = $lop->materialReservation()->create(['technician_id' => $user->getKey(), 'status' => 'draft']);
            $reservation->items()->create(['designator_id' => $designator->getKey(), 'qty' => 5]);
        }

        $this->actingAs($user)->get(route('data-boqs.index', ['date_from' => '2026-10-15', 'date_to' => '2026-10-15']))
            ->assertOk()
            ->assertViewHas('reservationBoqs', fn ($boqs) => $this->incidents($boqs) === ['INC-RESERVED-IN']);

        $this->get(route('data-boqs.index'))
            ->assertOk()
            ->assertViewHas('reservationBoqs', fn ($boqs) => count($this->incidents($boqs)) === 2);
    }

    public function test_date_filter_never_leaks_out_of_the_admin_branch_scope(): void
    {
        $area = Area::create(['code' => 'BOQ-AREA', 'name' => 'Area BOQ']);
        $region = Region::create(['code' => 'BOQ-R1', 'name' => 'Region BOQ', 'area_id' => $area->getKey()]);
        $inside = Branch::create(['code' => 'BOQ-IN', 'name' => 'BOQ Inside', 'region_id' => $region->getKey(), 'region' => $region->name]);
        $outside = Branch::create(['code' => 'BOQ-OUT', 'name' => 'BOQ Outside', 'region_id' => $region->getKey(), 'region' => $region->name]);
        $insideArea = ServiceArea::create(['workzone' => 'BOQZA', 'name' => 'STO BOQ A', 'branch_id' => $inside->getKey(), 'region_id' => $region->getKey()]);
        $outsideArea = ServiceArea::create(['workzone' => 'BOQZB', 'name' => 'STO BOQ B', 'branch_id' => $outside->getKey(), 'region_id' => $region->getKey()]);
        $admin = $this->user(UserRole::ADMIN, ['admin_scope_type' => 'branch', 'branch_id' => $inside->getKey()]);

        $this->boq($this->lop($admin, 'INC-INSIDE', ['reported_date' => '2026-10-15', 'branch_id' => $inside->getKey(), 'service_area_id' => $insideArea->getKey()]), $admin);
        $this->boq($this->lop($admin, 'INC-OUTSIDE', ['reported_date' => '2026-10-15', 'branch_id' => $outside->getKey(), 'service_area_id' => $outsideArea->getKey()]), $admin);

        // Rentang yang mencakup kedua tiket tidak boleh melebarkan scope admin.
        $this->actingAs($admin)->get(route('data-boqs.index', ['date_from' => '2026-10-15', 'date_to' => '2026-10-15']))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => $this->incidents($boqs) === ['INC-INSIDE']);
    }

    public function test_creating_a_lop_copies_reported_date_from_the_ticket_source(): void
    {
        $this->location();
        $admin = $this->user();
        $this->source();
        $this->ticket('INC-ISO', '2026-10-15 23:59:59');
        $this->ticket('INC-SLASH', '15/10/2026 09:00:00');
        $this->ticket('INC-UNREADABLE', 'not-a-date');

        $iso = app(LopService::class)->create($this->payload('INC-ISO'), $admin);
        $slash = app(LopService::class)->create($this->payload('INC-SLASH'), $admin);
        $unreadable = app(LopService::class)->create($this->payload('INC-UNREADABLE'), $admin);
        $manual = app(LopService::class)->create($this->payload(''), $admin);

        $this->assertSame('2026-10-15', $iso->reported_date);
        // Format hari-dulu mengikuti parsing tiket, bukan teks mentahnya.
        $this->assertSame('2026-10-15', $slash->reported_date);
        $this->assertNull($unreadable->reported_date);
        $this->assertStringStartsWith('INP', $manual->incident);
        $this->assertNull($manual->reported_date);
    }

    public function test_editing_the_incident_refreshes_the_reported_date(): void
    {
        $this->location();
        $admin = $this->user();
        $this->source();
        $this->ticket('INC-OLD', '2026-10-15 09:00:00');
        $this->ticket('INC-NEW', '16/10/2026 09:00:00');

        $service = app(LopService::class);
        $lop = $service->create($this->payload('INC-OLD'), $admin);
        $this->assertSame('2026-10-15', $lop->reported_date);

        $service->update($lop->fresh(), $this->payload('INC-NEW'));

        $this->assertSame('2026-10-16', $lop->fresh()->reported_date);
    }

    public function test_sync_command_backfills_missing_reported_dates(): void
    {
        $user = $this->user();
        $this->source();
        $this->ticket('INC-SYNC', '16/10/2026 09:00:00');
        $this->ticket('INC-MISSING', '2026-10-15 09:00:00');

        $synced = $this->lop($user, 'INC-SYNC');
        $missing = $this->lop($user, 'INC-MISSING');
        $unknown = $this->lop($user, 'INC-NOT-ON-TICKET');

        $this->artisan('dompis:sync-reported-date')
            ->expectsOutputToContain('2 dari 3 LOP diperbarui.')
            ->assertSuccessful();

        $this->assertSame('2026-10-16', $synced->fresh()->reported_date);
        $this->assertSame('2026-10-15', $missing->fresh()->reported_date);
        $this->assertNull($unknown->fresh()->reported_date);

        // Berjalan ulang tanpa perubahan tidak mengubah apa pun.
        $this->artisan('dompis:sync-reported-date')
            ->expectsOutputToContain('0 dari 1 LOP diperbarui.')
            ->assertSuccessful();
    }

    public function test_download_link_carries_the_active_date_range(): void
    {
        $user = $this->user();
        $this->boq($this->lop($user, 'INC-LINK', ['reported_date' => '2026-10-15']), $user);

        $this->actingAs($user)->get(route('data-boqs.index', ['date_from' => '2026-10-15', 'date_to' => '2026-10-16']))
            ->assertOk()
            ->assertSee('date_from=2026-10-15', false)
            ->assertSee('date_to=2026-10-16', false)
            ->assertSee('Download Data BOQ sesuai filter tanggal laporan', false);

        // Tanpa filter, tautan kembali polos sehingga rentang lama tidak menempel.
        $this->assertStringNotContainsString('date_from=', $this->get(route('data-boqs.index'))->getContent());
    }

    public function test_excel_download_follows_the_reported_date_filter(): void
    {
        $user = $this->user();
        $material = $this->designator($user);
        foreach ([
            'INC-DL-IN' => '2026-10-15',
            'INC-DL-OUT' => '2026-10-20',
        ] as $incident => $reportedDate) {
            $this->boqWithItem($this->lop($user, $incident, ['reported_date' => $reportedDate]), $user, $material);
        }
        // LOP tanpa tanggal terbaca tidak boleh diam-diam masuk rentang mana pun.
        $this->boqWithItem($this->lop($user, 'INC-DL-UNKNOWN'), $user, $material);

        $this->assertSame(['INC-DL-IN'], $this->sheetIncidents($this->download($user, ['date_from' => '2026-10-15', 'date_to' => '2026-10-15'])));

        $this->assertSame(
            ['INC-DL-IN', 'INC-DL-OUT'],
            $this->sheetIncidents($this->download($user, ['date_from' => '2026-10-15'])),
        );

        $this->assertSame(
            ['INC-DL-IN', 'INC-DL-OUT', 'INC-DL-UNKNOWN'],
            $this->sheetIncidents($this->download($user, [])),
        );
    }

    public function test_excel_download_rejects_a_reversed_range_like_the_list_does(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from(route('data-boqs.index'))
            ->get(route('data-boqs.export', ['date_from' => '2026-10-16', 'date_to' => '2026-10-15']))
            ->assertSessionHasErrors(['date_to' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.']);
    }

    public function test_reversed_range_is_rejected_on_the_list(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from(route('data-boqs.index'))
            ->get(route('data-boqs.index', ['date_from' => '2026-10-16', 'date_to' => '2026-10-15']))
            ->assertSessionHasErrors(['date_to' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.']);

        $this->get(route('data-boqs.index', ['date_from' => '15/10/2026']))
            ->assertSessionHasErrors(['date_from' => 'Tanggal mulai harus berupa tanggal yang valid.']);
    }

    public function test_filter_and_export_work_when_the_ticket_source_is_down(): void
    {
        $user = $this->user();
        $material = $this->designator($user);
        $this->boqWithItem($this->lop($user, 'INC-KEPT', ['reported_date' => '2026-10-15']), $user, $material);
        $this->boqWithItem($this->lop($user, 'INC-OUT', ['reported_date' => '2026-10-20']), $user, $material);

        // Koneksi tiket diarahkan ke berkas yang gagal dibuka: filter lokal tidak boleh ikut rusak.
        config()->set('database.connections.mysql_dompis', [
            'driver' => 'sqlite', 'database' => '/tidak/ada/basis-data/ticket.sqlite', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('mysql_dompis');

        $this->actingAs($user)->get(route('data-boqs.index', ['date_from' => '2026-10-15', 'date_to' => '2026-10-15']))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => $this->incidents($boqs) === ['INC-KEPT']);

        $this->assertSame(
            ['INC-KEPT'],
            $this->sheetIncidents($this->download($user, ['date_from' => '2026-10-15', 'date_to' => '2026-10-15'])),
        );
    }

    /** @return array<int, string> */
    private function sheetIncidents(Worksheet $sheet): array
    {
        if ($sheet->getHighestRow() === 1) {
            return [];
        }

        return array_values(array_unique(array_column($sheet->rangeToArray('B2:B'.$sheet->getHighestRow()), 0)));
    }

    private function download(User $user, array $parameters): Worksheet
    {
        $response = $this->actingAs($user)->get(route('data-boqs.export', $parameters))->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        return IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    }

    /**
     * @param  LengthAwarePaginator  $rows
     * @return array<int, string>
     */
    private function incidents($rows): array
    {
        return $rows->getCollection()
            ->map(fn ($row) => $row instanceof QeBoq ? $row->lop->incident : $row->incident)
            ->sort()
            ->values()
            ->all();
    }

    /** Referensi lokasi agar LopService dapat menyelesaikan branch dan STO. */
    private function location(): void
    {
        $area = Area::updateOrCreate(['code' => '3'], ['name' => 'Area 3', 'is_active' => true]);
        $region = Region::updateOrCreate(['code' => 'JATIM'], ['name' => 'REGION JATIM', 'area_id' => $area->id_area, 'is_active' => true]);
        $branch = Branch::updateOrCreate(['code' => 'SDA'], ['name' => 'SIDOARJO', 'region' => $region->name, 'region_id' => $region->id_region, 'is_active' => true]);
        ServiceArea::updateOrCreate(['workzone' => 'SDA'], ['name' => 'SIDOARJO', 'branch_id' => $branch->id_branch, 'region_id' => $region->id_region, 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(string $incident): array
    {
        return [
            'incident' => $incident,
            'nama_lop' => 'LOP '.$incident,
            'program_type' => 'recovery',
            'sto' => 'SDA', 'branch' => 'SIDOARJO', 'area' => '3',
            'segment' => 'odp', 'job_description' => 'Kerja lapangan',
        ];
    }

    private function source(): void
    {
        $this->fakeIncidentTickets([]);
    }

    private function ticket(string $incident, ?string $reportedDate): void
    {
        DB::connection('mysql_dompis')->table('ticket')->insert([
            'incident' => $incident,
            'jenis_tiket_2' => 'GAMAS',
            'reported_date' => $reportedDate,
        ]);
    }

    private function user(UserRole $role = UserRole::SUPER_ADMIN, array $attributes = []): User
    {
        return User::factory()->role($role->value)->create($attributes);
    }

    private function lop(User $owner, string $incident, array $attributes = []): QeLop
    {
        return QeLop::create(array_merge([
            'incident' => $incident,
            'nama_lop' => 'LOP '.$incident,
            'program_type' => 'recovery',
            'status_lop' => 'draft',
            'created_by' => $owner->getKey(),
        ], $attributes));
    }

    private function boq(QeLop $lop, User $owner): QeBoq
    {
        return QeBoq::create([
            'qe_lop_id' => $lop->getKey(),
            'source' => 'manual',
            'status' => 'ready',
            'item_count' => 0,
            'grand_total' => 0,
            'created_by' => $owner->getKey(),
            'updated_by' => $owner->getKey(),
        ]);
    }

    private function designator(User $owner): Designator
    {
        $type = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'MATERIAL']);

        return Designator::create([
            'code' => 'M-BOQ-DATE',
            'item_name' => 'Material filter tanggal',
            'unit' => 'unit',
            'designator_type_id' => $type->getKey(),
            'created_by' => $owner->getKey(),
        ]);
    }

    /** BOQ dengan satu item supaya export menghasilkan baris. */
    private function boqWithItem(QeLop $lop, User $owner, Designator $material): QeBoq
    {
        $boq = $this->boq($lop, $owner);
        $boq->items()->create([
            'designator_id' => $material->getKey(),
            'designator_code' => $material->code,
            'item_name' => $material->item_name,
            'unit' => $material->unit,
            'type' => $material->type->code,
            'qty' => 5,
            'unit_price' => 100,
            'total_price' => 500,
        ]);

        return $boq;
    }
}
