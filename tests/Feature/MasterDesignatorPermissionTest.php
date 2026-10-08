<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Designator;
use App\Models\DesignatorPackagePrice;
use App\Models\DesignatorType;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDesignatorPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_create_designator(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->actingAs($superAdmin)->get(route('designators.index'))->assertOk();

        $response = $this->actingAs($superAdmin)->post(route('designators.store'), [
            'code' => 'M-0001',
            'item_name' => 'Kabel Fiber Optic',
            'unit' => 'meter',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);

        $response->assertRedirect(route('designators.index'));
        $this->assertDatabaseHas('designators', ['code' => 'M-0001']);
    }

    public function test_super_admin_can_view_and_create_package(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $this->actingAs($superAdmin)->get(route('packages.index'))->assertOk();

        $response = $this->actingAs($superAdmin)->post(route('packages.store'), [
            'code' => 'PKT-01',
            'name' => 'Paket 2026',
        ]);

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseHas('packages', ['code' => 'PKT-01']);
    }

    public function test_super_admin_can_view_and_create_designator_price(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $designator = Designator::create([
            'code' => 'M-0002', 'item_name' => 'Closure', 'unit' => 'pcs',
            'designator_type_id' => DesignatorType::where('code', 'MATERIAL')->value('id_designator_type'),
        ]);
        $package = Package::create(['code' => 'PKT-02', 'name' => 'Paket B']);

        $this->actingAs($superAdmin)->get(route('designator-prices.index'))->assertOk();

        $response = $this->actingAs($superAdmin)->post(route('designator-prices.store'), [
            'designator_id' => $designator->id_designator,
            'package_id' => $package->id_package,
            'price' => 150000,
        ]);

        $response->assertRedirect(route('designator-prices.index'));
        $this->assertDatabaseHas('designator_package_prices', [
            'designator_id' => $designator->id_designator,
            'package_id' => $package->id_package,
        ]);
    }

    public function test_non_super_admin_roles_cannot_access_master_designator(): void
    {
        foreach ([UserRole::ADMIN, UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $user = User::factory()->role($role->value)->create();

            $this->actingAs($user)->get(route('designators.index'))->assertForbidden();
            $this->actingAs($user)->get(route('packages.index'))->assertForbidden();
            $this->actingAs($user)->get(route('designator-prices.index'))->assertForbidden();
        }
    }

    public function test_khs_search_matches_code_or_item_name_and_combines_with_package_filter(): void
    {
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $package = Package::create(['code' => 'SEARCH-5', 'name' => 'Paket 5']);
        $otherPackage = Package::create(['code' => 'SEARCH-10', 'name' => 'Paket 10']);
        $cable = Designator::create(['code' => 'M-SEARCH-CABLE', 'item_name' => 'Kabel Fiber Optik', 'unit' => 'meter']);
        $box = Designator::create(['code' => 'M-SEARCH-BOX', 'item_name' => 'Box ODP', 'unit' => 'pcs']);
        $target = DesignatorPackagePrice::create(['designator_id' => $cable->getKey(), 'package_id' => $package->getKey(), 'price' => 5000]);
        DesignatorPackagePrice::create(['designator_id' => $cable->getKey(), 'package_id' => $otherPackage->getKey(), 'price' => 10000]);
        DesignatorPackagePrice::create(['designator_id' => $box->getKey(), 'package_id' => $package->getKey(), 'price' => 20000]);
        $this->actingAs($super)->get(route('designator-prices.index', ['q' => 'SEARCH-CABLE']))
            ->assertOk()->assertViewHas('prices', fn ($prices) => $prices->total() === 2)->assertDontSee('Box ODP');
        $this->get(route('designator-prices.index', ['q' => '  Fiber  ', 'package' => $package->getKey()]))
            ->assertOk()->assertViewHas('q', 'Fiber')
            ->assertViewHas('prices', fn ($prices) => $prices->total() === 1 && $prices->first()->is($target))
            ->assertSee('Reset')->assertSee('M-SEARCH-CABLE')->assertDontSee('M-SEARCH-BOX');
        $this->get(route('designator-prices.index', ['q' => 'tidak-ditemukan']))
            ->assertOk()->assertViewHas('prices', fn ($prices) => $prices->total() === 0)
            ->assertSee('Tidak ada KHS sesuai pencarian');
        $this->get(route('designator-prices.index'))
            ->assertOk()->assertViewHas('prices', fn ($prices) => $prices->total() === 3);
    }

    public function test_khs_pagination_preserves_search_and_package_filter(): void
    {
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $package = Package::create(['code' => 'SEARCH', 'name' => 'Paket Search']);
        for ($i = 0; $i < 21; $i++) {
            $designator = Designator::create(['code' => "M-PAGE-{$i}", 'item_name' => 'Kabel Search', 'unit' => 'meter']);
            DesignatorPackagePrice::create(['designator_id' => $designator->getKey(), 'package_id' => $package->getKey(), 'price' => 5000]);
        }
        $this->actingAs($super)->get(route('designator-prices.index', ['q' => 'Kabel', 'package' => $package->getKey()]))
            ->assertOk()->assertViewHas('prices', fn ($prices) => $prices->total() === 21 && $prices->count() === 20
                && str_contains($prices->nextPageUrl(), 'q=Kabel')
                && str_contains($prices->nextPageUrl(), 'package='.$package->getKey()));
        $this->get(route('designator-prices.index', ['q' => 'Kabel', 'package' => $package->getKey(), 'page' => 2]))
            ->assertOk()->assertViewHas('prices', fn ($prices) => $prices->count() === 1 && $prices->total() === 21);
    }

    public function test_khs_search_validates_input_and_keeps_existing_authorization(): void
    {
        $super = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $this->actingAs($super)->getJson(route('designator-prices.index', ['q' => ['invalid']]))
            ->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson(route('designator-prices.index', ['q' => str_repeat('a', 151)]))
            ->assertUnprocessable()->assertJsonValidationErrors('q');
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();
        $this->actingAs($admin)->get(route('designator-prices.index', ['q' => 'Kabel']))->assertForbidden();
    }
}
