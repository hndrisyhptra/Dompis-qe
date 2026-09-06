<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exports\SisaMaterialExport;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeLop;
use App\Models\QeMaterialReservation;
use App\Models\QeMaterialReservationItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class MaterialReportTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $name, string $region = 'REGION JATIM'): Branch
    {
        return Branch::firstOrCreate(
            ['name' => $name],
            ['code' => Str::slug($name), 'region' => $region],
        );
    }

    private function materialType(): int
    {
        return DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material'])->id_designator_type;
    }

    private function designator(string $code): Designator
    {
        return Designator::create([
            'code' => $code,
            'item_name' => "Uraian {$code}",
            'unit' => 'meter',
            'designator_type_id' => $this->materialType(),
        ]);
    }

    private function lop(User $creator, string $incident, string $branch, string $program = 'recovery', string $status = 'completed'): QeLop
    {
        return QeLop::create([
            'incident' => $incident,
            'nama_lop' => "Project {$incident}",
            'program_type' => $program,
            'branch' => $branch,
            'status_lop' => $status,
            'created_by' => $creator->id_user,
        ]);
    }

    /**
     * @param  array<int, array{0: Designator, 1: float, 2: float|null}>  $items
     */
    private function recap(QeLop $lop, User $tech, array $items, string $status = 'submitted'): QeMaterialReservation
    {
        $reservation = QeMaterialReservation::create([
            'qe_lop_id' => $lop->id_qe_lops,
            'technician_id' => $tech->id_user,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        foreach ($items as [$designator, $qty, $actual]) {
            QeMaterialReservationItem::create([
                'reservation_id' => $reservation->id_reservation,
                'designator_id' => $designator->id_designator,
                'qty' => $qty,
                'qty_actual' => $actual,
            ]);
        }

        return $reservation;
    }

    private function user(UserRole $role, ?Branch $branch = null): User
    {
        return User::factory()->role($role->value)->create([
            'branch_id' => $branch?->id_branch,
        ]);
    }

    public function test_permission_gate(): void
    {
        $routes = ['reports.boq-actual', 'reports.sisa-material'];

        foreach ($routes as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $cases = [
            [UserRole::MANAGER, null, 200],
            [UserRole::SUPER_ADMIN, null, 200],
            [UserRole::ADMIN, $this->branch('SIDOARJO'), 200],
            [UserRole::TEKNISI, null, 403],
            [UserRole::APPROVER, null, 403],
        ];

        foreach ($cases as [$role, $branch, $status]) {
            $user = $this->user($role, $branch);
            foreach ($routes as $route) {
                $this->actingAs($user)->get(route($route))->assertStatus($status);
            }
        }
    }

    public function test_admin_is_branch_scoped(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $sda = $this->branch('SIDOARJO');
        $sby = $this->branch('SURABAYA');
        $admin = $this->user(UserRole::ADMIN, $sda);

        $d = $this->designator('M-A');
        $this->recap($this->lop($creator, 'INC-SDA', 'SIDOARJO'), $tech, [[$d, 10, 7]]);
        $this->recap($this->lop($creator, 'INC-SBY', 'SURABAYA'), $tech, [[$d, 5, 5]]);

        $this->actingAs($admin)->get(route('reports.boq-actual'))
            ->assertOk()
            ->assertSee('INC-SDA')
            ->assertDontSee('INC-SBY')
            ->assertViewHas('data', fn ($d) => $d['grand']['lop_count'] === 1);
    }

    public function test_manager_sees_all_branches_and_can_filter(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $this->branch('SIDOARJO', 'REGION JATIM');
        $this->branch('DENPASAR', 'REGION BALNUS');
        $manager = $this->user(UserRole::MANAGER);

        $d = $this->designator('M-B');
        $this->recap($this->lop($creator, 'INC-J', 'SIDOARJO'), $tech, [[$d, 10, 8]]);
        $this->recap($this->lop($creator, 'INC-B', 'DENPASAR'), $tech, [[$d, 4, 4]]);

        $this->actingAs($manager)->get(route('reports.boq-actual'))
            ->assertViewHas('data', fn ($x) => $x['grand']['lop_count'] === 2);

        $this->actingAs($manager)->get(route('reports.boq-actual', ['branch' => 'DENPASAR']))
            ->assertViewHas('data', fn ($x) => $x['grand']['lop_count'] === 1)
            ->assertSee('INC-B')->assertDontSee('INC-J');

        $this->actingAs($manager)->get(route('reports.boq-actual', ['region' => 'REGION JATIM']))
            ->assertViewHas('data', fn ($x) => $x['grand']['lop_count'] === 1)
            ->assertSee('INC-J');
    }

    public function test_only_submitted_reservations_counted(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $d = $this->designator('M-C');

        $this->recap($this->lop($creator, 'INC-DRAFT', 'SBY'), $tech, [[$d, 10, 5]], 'draft');
        $this->recap($this->lop($creator, 'INC-SUB', 'SBY'), $tech, [[$d, 8, 6]], 'submitted');

        $this->actingAs($creator)->get(route('reports.boq-actual'))
            ->assertSee('INC-SUB')->assertDontSee('INC-DRAFT')
            ->assertViewHas('data', fn ($x) => $x['grand']['lop_count'] === 1 && (float) $x['grand']['qty'] === 8.0);
    }

    public function test_per_lop_subtotals_and_grand_total(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $d1 = $this->designator('M-D1');
        $d2 = $this->designator('M-D2');

        $this->recap($this->lop($creator, 'INC-1', 'SBY'), $tech, [[$d1, 10, 7], [$d2, 5, 5]]);
        $this->recap($this->lop($creator, 'INC-2', 'SBY'), $tech, [[$d1, 4, 4]]);

        $this->actingAs($creator)->get(route('reports.boq-actual'))
            ->assertViewHas('data', function ($x) {
                $groups = collect($x['groups'] instanceof Paginator ? $x['groups']->items() : $x['groups']);
                $g1 = $groups->firstWhere('lop.incident', 'INC-1');

                return (float) $g1['subtotal']['qty'] === 15.0
                    && (float) $g1['subtotal']['qty_actual'] === 12.0
                    && (float) $x['grand']['qty'] === 19.0
                    && (float) $x['grand']['qty_actual'] === 16.0
                    && $x['grand']['lop_count'] === 2;
            });
    }

    public function test_rekap_aggregates_per_designator(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $d1 = $this->designator('M-R1');

        $this->recap($this->lop($creator, 'INC-A', 'SBY'), $tech, [[$d1, 10, 7]]);
        $this->recap($this->lop($creator, 'INC-B', 'SBY'), $tech, [[$d1, 4, 4]]);

        $this->actingAs($creator)->get(route('reports.boq-actual', ['view' => 'rekap']))
            ->assertViewHas('mode', 'rekap')
            ->assertViewHas('data', function ($x) {
                $rows = collect($x['rows'] instanceof Paginator ? $x['rows']->items() : $x['rows']);
                $row = $rows->firstWhere('designator_code', 'M-R1');

                return $rows->count() === 1
                    && (float) $row['qty'] === 14.0
                    && (float) $row['qty_actual'] === 11.0
                    && (float) $row['sisa'] === 3.0
                    && $row['lop_count'] === 2;
            });
    }

    public function test_sisa_computation_and_not_recapped_line(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $d1 = $this->designator('M-S1');
        $d2 = $this->designator('M-S2');

        $this->recap($this->lop($creator, 'INC-S', 'SBY'), $tech, [[$d1, 10, 7], [$d2, 5, null]]);

        $this->actingAs($creator)->get(route('reports.sisa-material'))
            ->assertSee('belum direkap')
            ->assertViewHas('data', function ($x) {
                $groups = collect($x['groups'] instanceof Paginator ? $x['groups']->items() : $x['groups']);
                $lines = collect($groups->first()['lines']);

                return $lines->firstWhere('designator_code', 'M-S1')['sisa'] === 3.0
                    && $lines->firstWhere('designator_code', 'M-S2')['sisa'] === null
                    && (float) $x['grand']['sisa'] === 3.0;
            });
    }

    public function test_price_applied_when_package_selected(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $d1 = $this->designator('M-P1');
        $pkg = Package::create(['code' => 'PK5', 'name' => 'Paket 5']);
        DesignatorPackagePrice::create(['designator_id' => $d1->id_designator, 'package_id' => $pkg->id_package, 'price' => 1000]);

        $this->recap($this->lop($creator, 'INC-P', 'SBY'), $tech, [[$d1, 10, 7]]);

        $this->actingAs($creator)->get(route('reports.boq-actual', ['package' => $pkg->id_package]))
            ->assertOk()
            ->assertViewHas('priced', true)
            ->assertSee('Total Actual')
            ->assertViewHas('data', function ($x) {
                $groups = collect($x['groups'] instanceof Paginator ? $x['groups']->items() : $x['groups']);
                $line = collect($groups->first()['lines'])->firstWhere('designator_code', 'M-P1');

                return $line['total_actual'] === 7000.0 && $line['nilai_sisa'] === 3000.0
                    && (float) $x['grand']['total_actual'] === 7000.0;
            });
    }

    public function test_no_package_shows_qty_only(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $this->recap($this->lop($creator, 'INC-Q', 'SBY'), $tech, [[$this->designator('M-Q1'), 10, 7]]);

        $this->actingAs($creator)->get(route('reports.boq-actual'))
            ->assertViewHas('priced', false)
            ->assertSee('Tanpa harga')
            ->assertDontSee('Total Actual')
            ->assertViewHas('data', fn ($x) => $x['grand']['total_actual'] === null);
    }

    public function test_harga_belum_diset_marker(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $d1 = $this->designator('M-H1');
        $d2 = $this->designator('M-H2');
        $pkg = Package::create(['code' => 'PKX', 'name' => 'Paket X']);
        DesignatorPackagePrice::create(['designator_id' => $d1->id_designator, 'package_id' => $pkg->id_package, 'price' => 500]);

        $this->recap($this->lop($creator, 'INC-H', 'SBY'), $tech, [[$d1, 10, 8], [$d2, 4, 4]]);

        $this->actingAs($creator)->get(route('reports.boq-actual', ['package' => $pkg->id_package]))
            ->assertSee('belum diset')
            ->assertViewHas('data', function ($x) {
                $groups = collect($x['groups'] instanceof Paginator ? $x['groups']->items() : $x['groups']);
                $line = collect($groups->first()['lines'])->firstWhere('designator_code', 'M-H2');

                return $line['price_missing'] === true
                    && (float) $x['grand']['total_actual'] === 4000.0
                    && $x['grand']['price_missing_count'] === 1;
            });
    }

    public function test_status_and_date_filters(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $d = $this->designator('M-F1');

        $waiting = $this->lop($creator, 'INC-W', 'SBY', 'recovery', 'waiting_approval');
        $done = $this->lop($creator, 'INC-C', 'SBY', 'preventive', 'completed');
        $rW = $this->recap($waiting, $tech, [[$d, 3, 3]]);
        $rC = $this->recap($done, $tech, [[$d, 6, 6]]);
        $rW->update(['submitted_at' => now()->subDays(10)]);
        $rC->update(['submitted_at' => now()->subDay()]);

        $this->actingAs($creator)->get(route('reports.boq-actual', ['status' => ['completed']]))
            ->assertSee('INC-C')->assertDontSee('INC-W');

        $this->actingAs($creator)->get(route('reports.boq-actual', ['program' => 'preventive']))
            ->assertSee('INC-C')->assertDontSee('INC-W');

        $this->actingAs($creator)->get(route('reports.boq-actual', ['date_from' => now()->subDays(3)->toDateString()]))
            ->assertSee('INC-C')->assertDontSee('INC-W');

        $this->actingAs($creator)->get(route('reports.boq-actual', ['q' => 'INC-W']))
            ->assertSee('INC-W')->assertDontSee('INC-C');
    }

    public function test_csv_download(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $this->recap($this->lop($creator, 'INC-CSV', 'SBY'), $tech, [[$this->designator('M-CSV'), 10, 7]]);

        $res = $this->actingAs($creator)->get(route('reports.boq-actual.export', ['format' => 'csv']));
        $res->assertOk();
        $this->assertStringStartsWith('text/csv', $res->headers->get('Content-Type'));
        $body = $res->streamedContent();
        $this->assertStringContainsString('Qty Actual', $body);
        $this->assertStringContainsString('M-CSV', $body);
    }

    public function test_xlsx_download(): void
    {
        Excel::fake();
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $this->recap($this->lop($creator, 'INC-XLSX', 'SBY'), $tech, [[$this->designator('M-XLSX'), 10, 7]]);

        $this->actingAs($creator)
            ->get(route('reports.sisa-material.export', ['format' => 'xlsx', 'view' => 'rekap']))
            ->assertOk();

        Excel::assertDownloaded('sisa-material-rekap-'.now()->format('Ymd-His').'.xlsx', function (SisaMaterialExport $export) {
            return count($export->array()) > 0 && in_array('Σ Sisa', $export->headings(), true);
        });
    }

    public function test_export_respects_scope(): void
    {
        $creator = $this->user(UserRole::SUPER_ADMIN);
        $tech = $this->user(UserRole::TEKNISI);
        $sda = $this->branch('SIDOARJO');
        $this->branch('SURABAYA');
        $admin = $this->user(UserRole::ADMIN, $sda);
        $d = $this->designator('M-SC1');

        $this->recap($this->lop($creator, 'INC-MINE', 'SIDOARJO'), $tech, [[$d, 10, 7]]);
        $this->recap($this->lop($creator, 'INC-OTHER', 'SURABAYA'), $tech, [[$d, 5, 5]]);

        $body = $this->actingAs($admin)->get(route('reports.boq-actual.export', ['format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('INC-MINE', $body);
        $this->assertStringNotContainsString('INC-OTHER', $body);
    }
}
