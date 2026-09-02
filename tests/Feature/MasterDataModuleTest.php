<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Designator;
use App\Models\DesignatorCategory;
use App\Models\DesignatorType;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterDataModuleTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
    }

    private function makeBranch(string $code = 'SBY', string $name = 'SURABAYA', string $regionCode = 'JATIM'): Branch
    {
        $region = Region::where('code', $regionCode)->first();

        return Branch::create([
            'code' => $code,
            'name' => $name,
            'region' => $region->name,
            'region_id' => $region->id_region,
            'is_active' => true,
        ]);
    }

    // ---- migrations / seed --------------------------------------------------

    public function test_new_master_tables_exist_and_are_seeded(): void
    {
        foreach (['regions', 'designator_categories', 'designator_types'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertTrue(Schema::hasColumns($table, ['code', 'name', 'is_active', 'created_by', 'updated_by', 'deleted_at']));
        }

        $this->assertTrue(Schema::hasColumns('branches', ['region_id', 'is_active', 'created_by', 'updated_by', 'deleted_at']));

        $this->assertSame(3, Region::count());
        $this->assertNotNull(DesignatorType::where('code', 'MATERIAL')->first());
        $this->assertNotNull(DesignatorCategory::where('code', 'ODP')->first());
    }

    public function test_designators_type_column_migrated_to_fk(): void
    {
        $this->assertFalse(Schema::hasColumn('designators', 'type'));
        $this->assertTrue(Schema::hasColumn('designators', 'designator_type_id'));

        $material = DesignatorType::where('code', 'MATERIAL')->value('id_designator_type');
        $d = Designator::create(['code' => 'X-1', 'item_name' => 'X', 'unit' => 'pcs', 'designator_type_id' => $material]);
        $this->assertSame('MATERIAL', $d->fresh()->type->code);
    }

    // ---- permission -------------------------------------------------------

    public function test_hub_and_resources_require_manage_master_data(): void
    {
        foreach ([UserRole::ADMIN, UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $user = User::factory()->role($role->value)->create();

            foreach (['master-data.index', 'regions.index', 'branches.index', 'designator-categories.index', 'designator-types.index'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
        }

        $admin = $this->superAdmin();
        foreach (['master-data.index', 'regions.index', 'branches.index', 'designator-categories.index', 'designator-types.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    // ---- Region CRUD ----------------------------------------------------

    public function test_region_crud_and_delete_guard(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('regions.store'), ['code' => 'sulut', 'name' => 'REGION SULUT', 'is_active' => 1])
            ->assertRedirect(route('regions.index'));
        $this->assertDatabaseHas('regions', ['code' => 'SULUT', 'name' => 'REGION SULUT']);

        // duplicate code rejected
        $this->actingAs($admin)->post(route('regions.store'), ['code' => 'SULUT', 'name' => 'Dup'])
            ->assertSessionHasErrors('code');

        $region = Region::where('code', 'SULUT')->first();
        $this->actingAs($admin)->put(route('regions.update', $region), ['code' => 'SULUT', 'name' => 'REGION SULAWESI UTARA', 'is_active' => 0])
            ->assertRedirect(route('regions.index'));
        $this->assertDatabaseHas('regions', ['id_region' => $region->id_region, 'name' => 'REGION SULAWESI UTARA', 'is_active' => 0]);

        // delete blocked when a branch references it
        $this->makeBranch();
        $used = Region::where('code', 'JATIM')->first();
        $this->actingAs($admin)->delete(route('regions.destroy', $used))->assertRedirect();
        $this->assertNotSoftDeleted('regions', ['id_region' => $used->id_region]);

        // free region can be deleted
        $this->actingAs($admin)->delete(route('regions.destroy', $region))->assertRedirect(route('regions.index'));
        $this->assertSoftDeleted('regions', ['id_region' => $region->id_region]);
    }

    // ---- Branch CRUD --------------------------------------------------

    public function test_branch_store_syncs_region_string_cache(): void
    {
        $admin = $this->superAdmin();
        $region = Region::where('code', 'JATIM')->first();

        $this->actingAs($admin)->post(route('branches.store'), [
            'code' => 'kdr', 'name' => 'KEDIRI', 'region_id' => $region->id_region, 'is_active' => 1,
        ])->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', [
            'code' => 'KDR', 'region_id' => $region->id_region, 'region' => $region->name,
        ]);
    }

    public function test_renaming_region_updates_branch_string_cache(): void
    {
        $admin = $this->superAdmin();
        $this->makeBranch();
        $region = Region::where('code', 'JATIM')->first();

        $this->actingAs($admin)->put(route('regions.update', $region), [
            'code' => 'JATIM', 'name' => 'REGION JAWA TIMUR', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('branches', ['code' => 'SBY', 'region' => 'REGION JAWA TIMUR']);
    }

    public function test_branch_delete_blocked_when_used_by_user(): void
    {
        $admin = $this->superAdmin();
        $branch = $this->makeBranch();
        User::factory()->role(UserRole::ADMIN->value)->create(['branch_id' => $branch->id_branch]);

        $this->actingAs($admin)->delete(route('branches.destroy', $branch))->assertRedirect();
        $this->assertNotSoftDeleted('branches', ['id_branch' => $branch->id_branch]);
    }

    // ---- Designator Category / Type ----------------------------------

    public function test_designator_category_crud(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('designator-categories.store'), ['code' => 'joint', 'name' => 'Joint Closure', 'is_active' => 1])
            ->assertRedirect(route('designator-categories.index'));
        $this->assertDatabaseHas('designator_categories', ['code' => 'JOINT']);

        $cat = DesignatorCategory::where('code', 'JOINT')->first();
        $this->actingAs($admin)->delete(route('designator-categories.destroy', $cat))->assertRedirect();
        $this->assertSoftDeleted('designator_categories', ['id_designator_category' => $cat->id_designator_category]);
    }

    public function test_designator_type_delete_blocked_when_referenced(): void
    {
        $admin = $this->superAdmin();
        $material = DesignatorType::where('code', 'MATERIAL')->first();
        Designator::create(['code' => 'D-1', 'item_name' => 'D', 'unit' => 'pcs', 'designator_type_id' => $material->id_designator_type]);

        $this->actingAs($admin)->delete(route('designator-types.destroy', $material))->assertRedirect();
        $this->assertNotSoftDeleted('designator_types', ['id_designator_type' => $material->id_designator_type]);

        // an unused type can be removed
        $unused = DesignatorType::where('code', 'PENGUKURAN')->first();
        $this->actingAs($admin)->delete(route('designator-types.destroy', $unused))->assertRedirect();
        $this->assertSoftDeleted('designator_types', ['id_designator_type' => $unused->id_designator_type]);
    }
}
