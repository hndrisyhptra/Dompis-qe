<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // HTTP test Laravel biasanya melewati CSRF; regression ini wajib memakainya.
        $this->app->bind(ValidateCsrfToken::class, EnforcedCsrfToken::class);
    }

    public function test_stale_login_returns_fresh_login_without_authenticating_or_flashing_password(): void
    {
        $user = User::factory()->role(UserRole::ADMIN->value)->create(['username' => 'login-test', 'password' => bcrypt('password-test')]);
        $this->get(route('login'))->assertOk()->assertSee('data-auth-session="login"', false);
        $response = $this->post(route('login'), ['_token' => 'OLD-TOKEN', 'username' => $user->username, 'password' => 'password-test', 'remember' => true]);
        $response->assertRedirect('/login')->assertSessionHas('auth_warning')->assertSessionHasInput('username', $user->username);
        $this->assertGuest();
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('_token'));
        $this->assertNull($user->fresh()->last_login_at);
        $this->get(route('login'))->assertOk()->assertSee('Sesi Anda sudah kedaluwarsa');
    }

    public function test_json_stale_login_keeps_419_and_does_not_retry_or_expose_credentials(): void
    {
        $response = $this->postJson(route('login'), ['username' => 'test', 'password' => 'PRIVATE-PASSWORD', '_token' => 'OLD']);
        $response->assertStatus(419)->assertJsonPath('code', 'session_expired')->assertJsonPath('login_url', '/login');
        $response->assertDontSee('PRIVATE-PASSWORD')->assertDontSee('OLD');
        $this->assertGuest();
    }

    public function test_stale_logout_does_not_logout_active_account_but_valid_token_does(): void
    {
        $user = User::factory()->role(UserRole::TEKNISI->value)->create();
        $this->actingAs($user);
        $this->post(route('logout'), ['_token' => 'STALE'])->assertRedirect('/technician')->assertSessionHas('auth_warning');
        $this->assertAuthenticatedAs($user);
        $context = $this->getJson(route('auth.session'))->assertOk()->assertJsonPath('user_id', $user->getKey());
        $this->post(route('logout'), ['_token' => $context->json('csrf_token')])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_context_is_uncached_stable_and_cross_site_requests_are_denied(): void
    {
        $response = $this->getJson(route('auth.session'))->assertOk()->assertJsonPath('user_id', null)->assertJsonPath('redirect_url', '/login');
        $this->assertNotEmpty($response->json('csrf_token'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('Cookie', $response->headers->get('Vary'));
        $this->getJson(route('auth.session'))->assertJsonPath('csrf_token', $response->json('csrf_token'));
        $this->withHeader('Sec-Fetch-Site', 'cross-site')->getJson(route('auth.session'))->assertForbidden();
    }

    public function test_login_logout_then_another_role_uses_fresh_token_and_session_id(): void
    {
        $admin = User::factory()->role(UserRole::ADMIN->value)->create(['username' => 'switch-admin', 'password' => bcrypt('password-test')]);
        $tech = User::factory()->role(UserRole::TEKNISI->value)->create(['username' => 'switch-tech', 'password' => bcrypt('password-test')]);
        $cookies = [];
        $request = function (string $method, string $route, array $data = []) use (&$cookies) {
            $this->app['auth']->forgetGuards();
            $this->withUnencryptedCookies($cookies);
            $response = $this->call($method, route($route), $data);
            foreach ($response->headers->getCookies() as $cookie) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }

            return $response;
        };
        foreach ([$admin, $tech, $admin] as $user) {
            $context = $request('GET', 'auth.session')->assertOk()->assertJsonPath('user_id', null);
            $before = session()->getId();
            $request('POST', 'login', ['_token' => $context->json('csrf_token'), 'username' => $user->username, 'password' => 'password-test'])
                ->assertRedirect(route($user->postLoginRouteName()));
            $this->assertAuthenticatedAs($user);
            $this->assertNotSame($before, session()->getId());
            $active = $request('GET', 'auth.session')->assertJsonPath('user_id', $user->getKey());
            $this->assertNotSame($context->json('csrf_token'), $active->json('csrf_token'));
            $request('POST', 'logout', ['_token' => $active->json('csrf_token')])->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_operational_form_with_expired_token_remains_blocked_and_never_replays_data(): void
    {
        $processed = false;
        Route::middleware('web')->post('/test-session-operation', function () use (&$processed) {
            $processed = true;

            return 'done';
        });
        $this->post('/test-session-operation', ['_token' => 'OLD', 'job_description' => 'PRIVATE-FORM-DATA'])
            ->assertStatus(419)->assertSee('Halaman perlu diperbarui')->assertSee('Masuk kembali')->assertDontSee('PRIVATE-FORM-DATA');
        $this->assertFalse($processed);
        $this->assertNull(session()->getOldInput('job_description'));
        $this->assertNull(session()->getOldInput('_token'));
    }

    public function test_missing_csrf_on_logout_when_session_is_already_expired_redirects_to_login(): void
    {
        $this->post(route('logout'))->assertRedirect('/login')->assertSessionHas('auth_warning');
        $this->assertGuest();
    }
}

class EnforcedCsrfToken extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
