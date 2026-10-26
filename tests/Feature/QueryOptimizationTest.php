<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\QeBoq;
use App\Models\QeLop;
use App\Models\Region;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\LopBoqProjectionService;
use App\Services\LopBoqValueService;
use App\Services\LopVisibilityService;
use App\Services\ProjectProgressService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithIncidentTickets;
use Tests\TestCase;

class QueryOptimizationTest extends TestCase
{
    use InteractsWithIncidentTickets, RefreshDatabase;

    private User $admin;

    private User $super;

    private User $tech;

    private Branch $branch;

    private ServiceArea $sto;

    private Designator $material;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeIncidentTickets([]);
        $area = Area::create(['code' => 'PERF', 'name' => 'Performance area']);
        $region = Region::create(['code' => 'PERF-JATIM', 'name' => 'REGION JATIM', 'area_id' => $area->getKey()]);
        $this->branch = Branch::create(['code' => 'PERF', 'name' => 'PERF BRANCH', 'region' => 'REGION JATIM', 'region_id' => $region->getKey()]);
        $this->sto = ServiceArea::create(['workzone' => 'PERF', 'name' => 'PERF STO', 'branch_id' => $this->branch->getKey(), 'region_id' => $region->getKey()]);
        $this->admin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'branch', 'branch_id' => $this->branch->getKey()]);
        $this->super = User::factory()->role('SUPER_ADMIN')->create();
        $this->tech = User::factory()->role('TEKNISI')->create();
        $type = DesignatorType::firstOrCreate(['code' => 'MATERIAL'], ['name' => 'Material']);
        $this->material = Designator::create(['code' => 'M-PERF', 'item_name' => 'Performance material', 'unit' => 'M', 'designator_type_id' => $type->getKey()]);
        $this->package = Package::create(['code' => 'Paket-5', 'name' => 'Paket 5']);
        DesignatorPackagePrice::create(['designator_id' => $this->material->getKey(), 'package_id' => $this->package->getKey(), 'price' => 100]);
    }

    private function lop(int $index, bool $withBoq = true, array $attributes = []): QeLop
    {
        $lop = QeLop::create(array_replace([
            'incident' => 'PERF-'.$index, 'nama_lop' => 'Performance LOP '.$index, 'program_type' => 'recovery', 'status_lop' => 'progress',
            'branch_id' => $this->branch->getKey(), 'service_area_id' => $this->sto->getKey(), 'branch' => $this->branch->name,
            'sto' => $this->sto->workzone, 'created_by' => $this->admin->getKey(), 'package_id' => $this->package->getKey(), 'ba_bongkar_required' => false,
        ], $attributes));
        $lop->assignments()->create(['technician_id' => $this->tech->getKey(), 'assigned_by' => $this->admin->getKey(), 'assigned_at' => now(), 'status' => 'active']);
        $reservation = $lop->materialReservation()->create(['technician_id' => $this->tech->getKey(), 'status' => 'submitted', 'submitted_at' => now()]);
        $reservation->items()->create(['designator_id' => $this->material->getKey(), 'qty' => 10, 'qty_actual' => 5]);
        if ($withBoq) {
            $boq = QeBoq::create(['qe_lop_id' => $lop->getKey(), 'package_id' => $this->package->getKey(), 'source' => 'import', 'status' => 'ready', 'item_count' => 1, 'grand_total' => 1000, 'created_by' => $this->admin->getKey()]);
            $boq->items()->create(['designator_id' => $this->material->getKey(), 'designator_code' => $this->material->code, 'item_name' => 'Performance material', 'unit' => 'M', 'type' => 'MATERIAL', 'qty' => 10, 'unit_price' => 100, 'total_price' => 1000]);
        }

        return $lop;
    }

    public function test_active_list_batches_permissions_and_progress_without_changing_summaries(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->lop($i);
        }
        $expected = QeLop::all()->mapWithKeys(fn ($lop) => [$lop->getKey() => app(ProjectProgressService::class)->summary($lop)])->all();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($this->admin->fresh())->get(route('lop.index'))->assertOk();
        $this->assertLessThanOrEqual(55, count(DB::getQueryLog()));
        $this->assertSame(20, $response->viewData('lops')->total());
        foreach ($response->viewData('lops') as $lop) {
            $this->assertSame($expected[$lop->getKey()], $lop->progress_summary);
        }
        $response->assertSee('Detail LOP')->assertSee('Reassign Teknisi');
    }

    public function test_data_lop_list_does_not_query_scope_per_action_and_row(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->lop($i);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($this->admin->fresh())->get(route('data-lops.index'))->assertOk();
        $this->assertLessThanOrEqual(35, count(DB::getQueryLog()));
        $this->assertSame(20, $response->viewData('lops')->total());
    }

    public function test_data_boq_list_batches_locations_and_prices(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->lop($i, false);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($this->super)->get(route('data-boqs.index'))->assertOk();
        $queries = collect(DB::getQueryLog());
        $this->assertLessThanOrEqual(45, $queries->count());
        $this->assertSame(1, $queries->filter(fn ($q) => str_contains($q['query'], 'from "designator_package_prices"'))->count());
        $this->assertSame(15, $response->viewData('reservationBoqs')->total());
        foreach ($response->viewData('reservationBoqs') as $lop) {
            $this->assertSame(500.0, $lop->effective_boq['grand_total']);
            $this->assertSame('Paket-5 · otomatis wilayah', $lop->effective_boq['package_label']);
        }
    }

    public function test_view_access_matches_canonical_query_for_all_scopes_and_legacy_rows(): void
    {
        $inside = $this->lop(1);
        $legacy = $this->lop(2, attributes: ['branch_id' => null, 'service_area_id' => null]);
        $outside = $this->lop(3, attributes: ['branch_id' => null, 'branch' => 'OUTSIDE', 'service_area_id' => null, 'sto' => 'OUTSIDE']);
        $lops = QeLop::whereKey([$inside->getKey(), $legacy->getKey(), $outside->getKey()])->get();
        $region = $this->branch->regionRef;
        foreach ([
            ['admin_scope_type' => 'area', 'area_id' => $region->area_id],
            ['admin_scope_type' => 'region', 'region_id' => $region->getKey()],
            ['admin_scope_type' => 'branch', 'branch_id' => $this->branch->getKey()],
            ['admin_scope_type' => null, 'branch_id' => $this->branch->getKey()],
            ['admin_scope_type' => 'service_area', 'service_area_id' => $this->sto->getKey()],
            ['admin_scope_type' => 'service_area'],
            ['admin_scope_type' => null],
        ] as $attributes) {
            $user = User::factory()->role('ADMIN')->create($attributes);
            if (($attributes['admin_scope_type'] ?? null) === 'service_area') {
                $user->serviceAreas()->sync([$this->sto->getKey()]);
            }
            $this->app->instance('request', Request::create('/scope-preview', 'GET'));
            $visibility = app(LopVisibilityService::class);
            $expected = $visibility->apply(QeLop::query(), $user)->pluck('id_qe_lops')->all();
            $visibility->prepareForView($user, $lops);
            DB::enableQueryLog();
            DB::flushQueryLog();
            foreach ($lops as $lop) {
                $this->assertSame(in_array($lop->getKey(), $expected, true), app(LopVisibilityService::class)->canAccess($user, $lop));
            }
            $this->assertCount(0, DB::getQueryLog());
        }
    }

    public function test_prepared_view_access_is_not_reused_by_mutations_other_users_or_requests(): void
    {
        $lop = $this->lop(1);
        $visibility = app(LopVisibilityService::class);
        $this->app->instance('request', Request::create('/scope-preview', 'GET'));
        $visibility->prepareForView($this->admin, new Collection([$lop]));
        $this->assertTrue($visibility->canAccess($this->admin, $lop));
        $other = User::factory()->role('ADMIN')->create();
        $this->assertFalse($visibility->canAccess($other, $lop));
        DB::table('qe_lops')->where('id_qe_lops', $lop->getKey())->update(['branch_id' => null, 'branch' => 'OUTSIDE', 'service_area_id' => null, 'sto' => 'OUTSIDE']);
        foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'GET'] as $method) {
            // Mutating the method on the same request also must bypass prepared view results.
            if ($method === 'GET') {
                $this->app->instance('request', Request::create('/another-request', $method));
            } else {
                request()->setMethod($method);
            }
            $this->assertFalse($visibility->canAccess($this->admin, $lop));
        }
    }

    public function test_batch_boq_values_match_single_values_and_prices_are_fresh_after_batch(): void
    {
        $lops = collect();
        foreach (['recovery', 'relok_utilitas', 'preventive', 'relok_tiang'] as $i => $program) {
            $lops->push($this->lop($i * 2 + 1, true, ['program_type' => $program]));
            $lops->push($this->lop($i * 2 + 2, false, ['program_type' => $program]));
        }
        $ids = $lops->map->getKey()->all();
        $single = app(LopBoqValueService::class);
        $expected = QeLop::whereKey($ids)->get()->mapWithKeys(fn ($lop) => [$lop->getKey() => $single->summarize($lop)])->toJson();
        $batch = app(LopBoqValueService::class);
        $actual = $batch->summarizeMany(QeLop::whereKey($ids)->get());
        $this->assertSame($expected, $actual->toJson());
        DesignatorPackagePrice::where('package_id', $this->package->getKey())->update(['price' => 200]);
        $updated = $batch->summarize($lops->last()->fresh());
        $this->assertSame(1000.0, $updated['grand_total']);
        $this->assertSame(1000.0, $batch->summarizeMany(QeLop::whereKey([$lops->last()->getKey()])->get())->first()['grand_total']);
    }

    public function test_view_access_is_invalidated_when_scope_or_model_location_changes(): void
    {
        $lop = $this->lop(1);
        $this->app->instance('request', Request::create('/scope-preview', 'GET'));
        $visibility = app(LopVisibilityService::class);
        $visibility->prepareForView($this->admin, new Collection([$lop]));
        $this->assertTrue($visibility->canAccess($this->admin, $lop));
        $this->admin->branch_id = null;
        $this->assertFalse($visibility->canAccess($this->admin, $lop));
        $this->admin->branch_id = $this->branch->getKey();
        $lop->update(['branch_id' => null, 'service_area_id' => null, 'branch' => 'OUTSIDE', 'sto' => 'OUTSIDE']);
        $this->assertFalse($visibility->canAccess($this->admin, $lop));
    }

    public function test_price_batch_cleans_up_after_exception(): void
    {
        $lop = $this->lop(1, false);
        $projection = app(LopBoqProjectionService::class);
        try {
            $projection->withPreparedPrices(QeLop::whereKey($lop->getKey())->get(), function () {
                throw new \RuntimeException('Expected test exception');
            });
            $this->fail('Expected an exception');
        } catch (\RuntimeException $error) {
            $this->assertSame('Expected test exception', $error->getMessage());
        }
        DesignatorPackagePrice::where('package_id', $this->package->getKey())->update(['price' => 300]);
        $this->assertSame(1500.0, $projection->report($lop->fresh(), 'actual')['grand']['total_actual']);
    }

    public function test_batched_reports_preserve_snapshot_regional_reference_service_and_missing_price_rules(): void
    {
        $serviceType = DesignatorType::firstOrCreate(['code' => 'JASA'], ['name' => 'Jasa']);
        $service = Designator::create(['code' => 'J-PERF', 'item_name' => 'Paired service', 'unit' => 'M', 'designator_type_id' => $serviceType->getKey()]);
        DesignatorPackagePrice::create(['designator_id' => $service->getKey(), 'package_id' => $this->package->getKey(), 'price' => 50]);
        $balnus = Region::create(['code' => 'PERF-BALNUS', 'name' => 'REGION BALNUS']);
        $branch = Branch::create(['code' => 'PERF-BALI', 'name' => 'PERF BALI', 'region' => 'REGION BALNUS', 'region_id' => $balnus->getKey()]);
        $package10 = Package::create(['code' => 'Paket-10', 'name' => 'Paket 10']);
        foreach ([$this->material->getKey() => 200, $service->getKey() => 80] as $id => $price) {
            DesignatorPackagePrice::create(['designator_id' => $id, 'package_id' => $package10->getKey(), 'price' => $price]);
        }
        $reference = Package::create(['code' => 'REF-PERF', 'name' => 'Reference']);
        DesignatorPackagePrice::create(['designator_id' => $this->material->getKey(), 'package_id' => $reference->getKey(), 'price' => 0]);
        $snapshot = $this->lop(1);
        $snapshot->boq->items()->update(['unit_price' => 7, 'total_price' => 70]);
        $utility = $this->lop(2, false, ['program_type' => 'relok_utilitas', 'branch_id' => $branch->getKey(), 'branch' => $branch->name, 'service_area_id' => null]);
        $utility->materialReservation->items()->update(['qty_actual' => 0]);
        $legacy = $this->lop(3, false, ['program_type' => 'relok_utilitas', 'branch_id' => null, 'service_area_id' => null]);
        $unmapped = $this->lop(4, false, ['program_type' => 'relok_utilitas', 'branch_id' => null, 'service_area_id' => null, 'branch' => 'UNKNOWN', 'package_id' => null]);
        $missing = Designator::create(['code' => 'M-NO-PRICE', 'item_name' => 'Missing price', 'unit' => 'M', 'designator_type_id' => $this->material->designator_type_id]);
        $unmapped->materialReservation->items()->create(['designator_id' => $missing->getKey(), 'qty' => 1, 'qty_actual' => null]);
        $ids = [$snapshot->getKey(), $utility->getKey(), $legacy->getKey(), $unmapped->getKey()];
        $projection = app(LopBoqProjectionService::class);
        $expected = QeLop::whereKey($ids)->get()->mapWithKeys(fn ($lop) => [$lop->getKey() => $projection->report($lop, 'actual')])->toJson();
        $lops = QeLop::whereKey($ids)->get();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $actual = $projection->withPreparedPrices($lops, fn () => $lops->mapWithKeys(fn ($lop) => [$lop->getKey() => $projection->report($lop, 'actual')]));
        $this->assertSame($expected, $actual->toJson());
        $this->assertSame(35.0, $actual[$snapshot->getKey()]['grand']['total_actual']);
        $this->assertSame(0.0, $actual[$utility->getKey()]['grand']['total_actual']);
        $this->assertSame(750.0, $actual[$legacy->getKey()]['grand']['total_actual']);
        $this->assertTrue($actual[$unmapped->getKey()]['lines'][0]['price'] === 0.0);
        // One reference-package lookup (EXISTS prices), then one batched price fetch.
        $this->assertSame(2, collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "designator_package_prices"'))->count());
    }

    public function test_batch_handles_lops_without_prices_or_package_and_keeps_snapshot_prices(): void
    {
        DesignatorPackagePrice::query()->delete();
        $snapshot = $this->lop(1);
        $unpriced = $this->lop(2, false, ['branch_id' => null, 'service_area_id' => null, 'branch' => 'UNKNOWN', 'package_id' => null]);
        $lops = QeLop::whereKey([$snapshot->getKey(), $unpriced->getKey()])->get();
        $projection = app(LopBoqProjectionService::class);
        $results = $projection->withPreparedPrices($lops, fn () => $lops->mapWithKeys(fn ($lop) => [$lop->getKey() => $projection->report($lop, 'actual')]));
        $this->assertSame(500.0, $results[$snapshot->getKey()]['grand']['total_actual']);
        $this->assertFalse($results[$unpriced->getKey()]['priced']);
        $this->assertNull($results[$unpriced->getKey()]['grand']['total_actual']);
    }
}
