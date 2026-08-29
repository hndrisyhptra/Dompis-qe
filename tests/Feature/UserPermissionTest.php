<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_user_list(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $response = $this->actingAs($superAdmin)->get(route('users.index'));

        $response->assertOk();
    }

    public function test_super_admin_can_create_user(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $teknisiRoleId = \App\Models\Role::where('code', 'TEKNISI')->value('id');

        $response = $this->actingAs($superAdmin)->post(route('users.store'), [
            'role_id' => $teknisiRoleId,
            'name' => 'User Baru',
            'username' => 'userbaru01',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['username' => 'userbaru01']);
    }

    public function test_non_super_admin_roles_cannot_access_user_management(): void
    {
        foreach ([UserRole::ADMIN, UserRole::TEKNISI, UserRole::MANAGER, UserRole::APPROVER] as $role) {
            $user = User::factory()->role($role->value)->create();

            $response = $this->actingAs($user)->get(route('users.index'));

            $response->assertForbidden();
        }
    }

    public function test_super_admin_cannot_delete_own_account(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $response = $this->actingAs($superAdmin)->delete(route('users.destroy', $superAdmin));

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id_user' => $superAdmin->id_user, 'deleted_at' => null]);
    }

    public function test_super_admin_cannot_deactivate_own_account(): void
    {
        $superAdmin = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();

        $response = $this->actingAs($superAdmin)->post(route('users.deactivate', $superAdmin));

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id_user' => $superAdmin->id_user, 'status' => 'active']);
    }
}
