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
 * Tanggal laporan tidak ada di dompis_qe sehingga direferensikan dari
 * ticket.reported_date pada koneksi read-only mysql_dompis.
 */
class DataBoqDateFilterTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    public function test_reported_date_range_is_inclusive_for_iso_and_day_first_formats(): void
    {
        $user = $this->user();
        $this->source();
        $this->ticket('INC-BEFORE', '2026-10-14 23:59:59');
        $this->ticket('INC-START', '2026-10-15 00:00:00');
        $this->ticket('INC-SLASH-START', '15/10/2026 23:59:59');
        $this->ticket('INC-END', '2026-10-16 23:59:59');
        $this->ticket('INC-AFTER', '2026-10-17 00:00:00');
        foreach (['INC-BEFORE', 'INC-START', 'INC-SLASH-START', 'INC-END', 'INC-AFTER'] as $incident) {
            $this->boq($this->lop($user, $incident), $user);
        }

        $this->actingAs($user)->get(route('data-boqs.index', ['date_from' => '2026-10-15', 'date_to' => '2026-10-16']))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => $this->incidents($boqs) === ['INC-END', 'INC-SLASH-START', 'INC-START'])
            ->assertViewHas('dateFrom', '2026-10-15')
            ->assertViewHas('dateTo', '2026-10-16')
            ->assertSee('Filter aktif')
            ->assertDontSee('INC-BEFORE')
            ->assertDontSee('INC-AFTER');
    }

    public function test_open_range_and_missing_dates_keep_every_boq_visible(): void
    {
        $user = $this->user();
        $this->source();
        $this->ticket('INC-A', '2026-10-14 23:59:59');
        $this->ticket('INC-B', '2026-10-17 00:00:00');
        $this->ticket('INC-UNREADABLE', 'not-a-date');
        foreach (['INC-A', 'INC-B', 'INC-UNREADABLE'] as $incident) {
            $this->boq($this->lop($user, $incident), $user);
        }

        $this->actingAs($user)->get(route('data-boqs.index'))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => count($this->incidents($boqs)) === 3)
            ->assertViewHas('sourceUnavailable', false);

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
        $this->source();
        $this->ticket('INC-RESERVED-IN', '2026-10-15 09:00:00');
        $this->ticket('INC-RESERVED-OUT', '2026-10-20 09:00:00');
        foreach (['INC-RESERVED-IN', 'INC-RESERVED-OUT'] as $incident) {
            $lop = $this->lop($user, $incident);
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

        $this->source();
        $this->ticket('INC-INSIDE', '2026-10-15 09:00:00');
        $this->ticket('INC-OUTSIDE', '2026-10-15 09:00:00');
        $this->boq($this->lop($admin, 'INC-INSIDE', ['branch_id' => $inside->getKey(), 'service_area_id' => $insideArea->getKey()]), $admin);
        $this->boq($this->lop($admin, 'INC-OUTSIDE', ['branch_id' => $outside->getKey(), 'service_area_id' => $outsideArea->getKey()]), $admin);

        // Rentang yang mencakup kedua tiket tidak boleh melebarkan scope admin.
        $this->actingAs($admin)->get(route('data-boqs.index', ['date_from' => '2026-10-15', 'date_to' => '2026-10-15']))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => $this->incidents($boqs) === ['INC-INSIDE']);
    }

    public function test_incident_matching_ignores_case_and_surrounding_whitespace(): void
    {
        $user = $this->user();
        $this->source();
        $this->ticket('  inc-normalized  ', '2026-10-15 09:00:00');
        $this->boq($this->lop($user, 'INC-NORMALIZED'), $user);

        $this->actingAs($user)->get(route('data-boqs.index', ['date_from' => '2026-10-15', 'date_to' => '2026-10-15']))
            ->assertOk()
            ->assertViewHas('boqs', fn ($boqs) => $this->incidents($boqs) === ['INC-NORMALIZED']);
    }

    public function test_reversed_range_is_rejected_before_the_ticket_source_is_queried(): void
    {
        $user = $this->user();
        $this->source();
        $this->ticket('INC-A', '2026-10-15 09:00:00');
        $this->boq($this->lop($user, 'INC-A'), $user);

        $this->actingAs($user)->from(route('data-boqs.index'))
            ->get(route('data-boqs.index', ['date_from' => '2026-10-16', 'date_to' => '2026-10-15']))
            ->assertSessionHasErrors(['date_to' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.']);

        $this->get(route('data-boqs.index', ['date_from' => '15/10/2026']))
            ->assertSessionHasErrors(['date_from' => 'Tanggal mulai harus berupa tanggal yang valid.']);
    }

    public function test_unavailable_ticket_source_warns_instead_of_silently_dropping_the_filter(): void
    {
        $user = $this->user();
        $this->source();
        $this->ticket('INC-KEPT', '2026-10-15 09:00:00');
        $this->boq($this->lop($user, 'INC-KEPT'), $user);

        // Sumber kedua diarahkan ke basis data tanpa tabel ticket.
        config()->set('database.connections.mysql_dompis', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('mysql_dompis');

        $this->actingAs($user)->get(route('data-boqs.index', ['date_from' => '2026-10-15', 'date_to' => '2026-10-15']))
            ->assertOk()
            ->assertSee('Sumber tiket Dompis sedang tidak tersedia')
            ->assertViewHas('sourceUnavailable', true)
            ->assertViewHas('boqs', fn ($boqs) => $boqs->isEmpty());
    }

    public function test_download_link_carries_the_active_date_range(): void
    {
        $user = $this->user();
        $this->source();
        $this->ticket('INC-LINK', '2026-10-15 09:00:00');
        $this->boq($this->lop($user, 'INC-LINK'), $user);

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
        $this->source();
        $this->ticket('INC-DL-IN', '2026-10-15 09:00:00');
        $this->ticket('INC-DL-SLASH', '15/10/2026 23:59:59');
        $this->ticket('INC-DL-OUT', '2026-10-20 09:00:00');
        foreach (['INC-DL-IN', 'INC-DL-SLASH', 'INC-DL-OUT'] as $incident) {
            $this->boqWithItem($this->lop($user, $incident), $user, $material);
        }

        // Rentang harian yang hanya menangkap dua tiket pertama.
        $sheet = $this->download($user, ['date_from' => '2026-10-15', 'date_to' => '2026-10-15']);
        $this->assertSame(['INC-DL-IN', 'INC-DL-SLASH'], $this->sheetIncidents($sheet));

        // Satu batas bawah saja.
        $this->assertSame(
            ['INC-DL-IN', 'INC-DL-SLASH', 'INC-DL-OUT'],
            $this->sheetIncidents($this->download($user, ['date_from' => '2026-10-15'])),
        );

        // Tanpa filter seluruh periode ikut.
        $this->assertSame(
            ['INC-DL-IN', 'INC-DL-SLASH', 'INC-DL-OUT'],
            $this->sheetIncidents($this->download($user, [])),
        );
    }

    public function test_excel_download_aborts_with_a_message_when_ticket_source_is_unavailable(): void
    {
        $user = $this->user();
        $this->source();

        config()->set('database.connections.mysql_dompis', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('mysql_dompis');

        // Mengunduh tanpa filter berarti mengabaikan filter yang diminta user.
        $this->actingAs($user)
            ->from(route('data-boqs.index', ['date_from' => '2026-10-15']))
            ->get(route('data-boqs.export', ['date_from' => '2026-10-15']))
            ->assertRedirect(route('data-boqs.index', ['date_from' => '2026-10-15']))
            ->assertSessionHas('error');

        $this->get(route('data-boqs.index'))
            ->assertOk()
            ->assertSee('Sumber tiket Dompis sedang tidak tersedia, unduhan dengan filter tanggal tidak dapat diproses', false);
    }

    public function test_excel_download_rejects_a_reversed_range_like_the_list_does(): void
    {
        $user = $this->user();
        $this->source();

        $this->actingAs($user)
            ->from(route('data-boqs.index'))
            ->get(route('data-boqs.export', ['date_from' => '2026-10-16', 'date_to' => '2026-10-15']))
            ->assertSessionHasErrors(['date_to' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.']);
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
