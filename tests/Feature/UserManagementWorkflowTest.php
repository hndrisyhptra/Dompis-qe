<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class UserManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_hashes_password_and_records_history(): void
    {
        $actor = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $teknisiRoleId = Role::where('code', 'TEKNISI')->value('id');

        $user = app(UserService::class)->create([
            'role_id' => $teknisiRoleId,
            'name' => 'Teknisi Baru',
            'username' => 'teknisi01',
            'password' => 'rahasia123',
        ], $actor);

        $this->assertNotEquals('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));

        $this->assertDatabaseHas('user_histories', [
            'target_user_id' => $user->id_user,
            'actor_id' => $actor->id_user,
            'event_type' => 'created',
        ]);
    }

    public function test_update_changing_role_records_role_changed_with_diff(): void
    {
        $actor = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $target = User::factory()->role(UserRole::TEKNISI->value)->create();
        $adminRoleId = Role::where('code', 'ADMIN')->value('id');
        $originalRoleId = $target->role_id;

        $updated = app(UserService::class)->update($target, [
            'role_id' => $adminRoleId,
            'name' => $target->name,
            'username' => $target->username,
        ], $actor);

        $this->assertEquals($adminRoleId, $updated->role_id);

        $history = $updated->historyEntries()->latest('created_at')->first();
        $this->assertEquals('role_changed', $history->event_type);
        $this->assertEquals($originalRoleId, $history->changes['role_id']['from']);
        $this->assertEquals($adminRoleId, $history->changes['role_id']['to']);
    }

    public function test_actor_cannot_change_their_own_role(): void
    {
        $actor = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $teknisiRoleId = Role::where('code', 'TEKNISI')->value('id');

        $this->expectException(InvalidArgumentException::class);

        app(UserService::class)->update($actor, [
            'role_id' => $teknisiRoleId,
            'name' => $actor->name,
            'username' => $actor->username,
        ], $actor);
    }

    public function test_deactivate_and_activate_toggle_status_and_record_history(): void
    {
        $actor = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $target = User::factory()->role(UserRole::TEKNISI->value)->create();

        $service = app(UserService::class);

        $deactivated = $service->deactivate($target, $actor);
        $this->assertEquals('inactive', $deactivated->status);

        $reactivated = $service->activate($target, $actor);
        $this->assertEquals('active', $reactivated->status);

        $this->assertEquals(2, $target->historyEntries()->where('event_type', 'status_changed')->count());
    }

    public function test_delete_soft_deletes_and_restore_reverses_it(): void
    {
        $actor = User::factory()->role(UserRole::SUPER_ADMIN->value)->create();
        $target = User::factory()->role(UserRole::TEKNISI->value)->create();

        $service = app(UserService::class);

        $service->delete($target, $actor);
        $this->assertSoftDeleted('users', ['id_user' => $target->id_user]);
        $this->assertDatabaseHas('user_histories', [
            'target_user_id' => $target->id_user,
            'event_type' => 'deleted',
        ]);

        $trashed = User::withTrashed()->find($target->id_user);
        $service->restore($trashed, $actor);

        $this->assertNull($target->fresh()->deleted_at);
        $this->assertDatabaseHas('user_histories', [
            'target_user_id' => $target->id_user,
            'event_type' => 'restored',
        ]);
    }
}
