<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:ADMIN,SUPER_ADMIN'])
            ->get('/__test/admin-only', fn () => 'ok');
    }

    public function test_user_with_allowed_role_can_access_route(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create();

        $response = $this->actingAs($admin)->get('/__test/admin-only');

        $response->assertOk();
        $response->assertSee('ok');
    }

    public function test_user_without_allowed_role_gets_403(): void
    {
        $teknisi = User::factory()->role(UserRole::TEKNISI->value)->create();

        $response = $this->actingAs($teknisi)->get('/__test/admin-only');

        $response->assertForbidden();
    }

    public function test_guest_gets_redirected_not_500(): void
    {
        $response = $this->get('/__test/admin-only');

        $response->assertRedirect('/login');
    }
}
