<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('NIK');
    }

    public function test_user_can_login_with_correct_username_and_password(): void
    {
        $user = User::factory()->role(UserRole::ADMIN->value)->create([
            'username' => '3201010101',
            'password' => bcrypt('rahasia123'),
        ]);

        $response = $this->post(route('login'), [
            'username' => '3201010101',
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect(route('lop.index'));
        $this->assertAuthenticatedAs($user);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        User::factory()->role(UserRole::ADMIN->value)->create([
            'username' => '3201010102',
            'password' => bcrypt('rahasia123'),
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'username' => '3201010102',
            'password' => 'salah',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->role(UserRole::TEKNISI->value)->inactive()->create([
            'username' => '3201010103',
            'password' => bcrypt('rahasia123'),
        ]);

        $response = $this->post(route('login'), [
            'username' => '3201010103',
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        User::factory()->role(UserRole::ADMIN->value)->create([
            'username' => '3201010104',
            'password' => bcrypt('rahasia123'),
        ]);

        RateLimiter::clear('3201010104|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'username' => '3201010104',
                'password' => 'salah-terus',
            ]);
        }

        $response = $this->post(route('login'), [
            'username' => '3201010104',
            'password' => 'rahasia123', // password benar sekalipun tetap ditolak krn locked out
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->role(UserRole::ADMIN->value)->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_when_accessing_protected_route(): void
    {
        $response = $this->get(route('lop.index'));

        $response->assertRedirect('/login');
    }

    public function test_each_role_redirects_via_role_based_resolution_after_login(): void
    {
        // Semua role saat ini masih ke lop.index (dashboard per-role belum
        // dibangun - lihat UserRole::dashboardRouteName()). Test ini bukan
        // sekadar duplikat test login sukses: ini memaksa setiap penambahan
        // dashboard baru meng-update baris role terkait di sini juga,
        // supaya perubahan mapping tidak lolos tanpa sengaja.
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role->value)->create([
                'username' => 'role-'.strtolower($role->value),
                'password' => bcrypt('rahasia123'),
            ]);

            $response = $this->post(route('login'), [
                'username' => 'role-'.strtolower($role->value),
                'password' => 'rahasia123',
            ]);

            $response->assertRedirect(route('lop.index'));

            $this->post(route('logout'));
        }
    }

    public function test_user_without_role_falls_back_to_lop_index_after_login(): void
    {
        $user = User::factory()->create([
            'role_id' => null,
            'username' => '3201010105',
            'password' => bcrypt('rahasia123'),
        ]);

        $response = $this->post(route('login'), [
            'username' => '3201010105',
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect(route('lop.index'));
        $this->assertAuthenticatedAs($user);
    }
}
