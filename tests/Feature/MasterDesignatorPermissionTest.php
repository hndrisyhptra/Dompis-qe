<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Designator;
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
            'type' => 'material',
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
            'code' => 'M-0002', 'item_name' => 'Closure', 'unit' => 'pcs', 'type' => 'material',
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
}
