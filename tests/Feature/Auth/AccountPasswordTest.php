<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\ServiceArea;
use App\Models\User;
use App\Models\UserHistory;
use App\Services\AccountScopeService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role->value], UserRole::cases());
    }

    #[DataProvider('roles')]
    public function test_each_role_can_change_only_its_own_password_without_changing_scope(string $role): void
    {
        $branch = Branch::create(['code' => 'PWBR', 'name' => 'Password Branch', 'is_active' => true]);
        $serviceArea = ServiceArea::create(['workzone' => 'PWSTO', 'name' => 'Password STO', 'branch_id' => $branch->getKey(), 'is_active' => true]);
        $user = User::factory()->role($role)->create(['branch_id' => $branch->getKey(), 'admin_scope_type' => $role === 'ADMIN' ? 'service_area' : null]);
        $user->serviceAreas()->sync([$serviceArea->getKey()]);
        $other = User::factory()->create();
        $oldRemember = $user->remember_token;
        $this->actingAs($user)->get(route('account.password.edit'))->assertOk()->assertSee('Ganti Password')->assertSee('Password saat ini');
        $oldSession = session()->getId();
        $oldCsrf = session()->token();
        $response = $this->put(route('account.password.update'), [
            'current_password' => 'password', 'password' => 'new-secret-pass', 'password_confirmation' => 'new-secret-pass',
            'user_id' => $other->getKey(), 'role_id' => $other->role_id, 'branch_id' => null,
        ]);
        $response->assertRedirect(route('account.password.edit'))->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotSame($oldCsrf, session()->token());
        $this->assertTrue(Hash::check('new-secret-pass', $user->fresh()->password));
        $this->assertTrue(Hash::check('password', $other->fresh()->password));
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->assertSame($branch->getKey(), $user->fresh()->branch_id);
        $this->assertSame([$serviceArea->getKey()], $user->serviceAreas()->pluck('id_service_area')->all());
        $history = UserHistory::where('event_type', 'password_changed')->sole();
        $this->assertSame($user->getKey(), $history->target_user_id);
        $this->assertSame($user->getKey(), $history->actor_id);
        $this->assertNull($history->changes);
        $this->assertStringNotContainsString('new-secret-pass', $history->toJson());
        $this->assertStringNotContainsString($user->fresh()->password, $history->toJson());
        $this->assertNull(session()->getOldInput('password'));
        $this->get(route('account.password.edit'))->assertOk();
        $this->withSession(['password_hash_web' => Auth::guard('web')->hashPasswordForCookie($other->password)])
            ->get(route('account.password.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public static function invalidPasswords(): array
    {
        return [
            [['current_password' => 'wrong-password'], 'current_password'],
            [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            [['password_confirmation' => 'different-pass'], 'password'],
            [['password' => 'password', 'password_confirmation' => 'password'], 'password'],
            [['password' => str_repeat('é', 37), 'password_confirmation' => str_repeat('é', 37)], 'password'],
            [['password' => "new\0password", 'password_confirmation' => "new\0password"], 'password'],
            [['password_confirmation' => ''], 'password_confirmation'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_invalid_password_never_changes_account_or_flashes_secrets(array $changes, string $field): void
    {
        $user = User::factory()->create();
        $hash = $user->password;
        $this->actingAs($user)->from(route('account.password.edit'))->put(route('account.password.update'), array_merge([
            'current_password' => 'password', 'password' => 'new-secret-pass', 'password_confirmation' => 'new-secret-pass',
        ], $changes))->assertRedirect(route('account.password.edit'))->assertSessionHasErrors($field);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('user_histories', 0);
        foreach (['current_password', 'password', 'password_confirmation'] as $secret) {
            $this->assertNull(session()->getOldInput($secret));
        }
    }

    public function test_guest_and_inactive_user_cannot_change_password(): void
    {
        $this->get(route('account.password.edit'))->assertRedirect(route('login'));
        $this->put(route('account.password.update'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->inactive()->create())->get(route('account.password.edit'))->assertForbidden();
        $this->put(route('account.password.update'))->assertForbidden();
    }

    public function test_update_is_throttled_and_missing_csrf_cannot_change_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->putJson(route('account.password.update'), ['current_password' => 'wrong'])->assertUnprocessable();
        }
        $this->putJson(route('account.password.update'))->assertTooManyRequests();
        $this->app->bind(ValidateCsrfToken::class, EnforcedAccountCsrfToken::class);
        $this->putJson(route('account.password.update'), ['password' => 'new-secret-pass'])->assertStatus(419);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_topbar_uses_scope_and_technician_profile_has_password_entry(): void
    {
        $branch = Branch::create(['code' => 'PWUI', 'name' => 'Branch Akun', 'is_active' => true]);
        $admin = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'branch', 'branch_id' => $branch->getKey()]);
        $this->actingAs($admin)->get(route('account.password.edit'))->assertOk()->assertSee('Branch · Branch Akun')->assertSee('Ganti Password');
        $tech = User::factory()->create();
        $this->actingAs($tech)->get(route('technician.profile'))->assertOk()->assertSee(route('account.password.edit'));
        $this->assertSame('LOP penugasan Anda', app(AccountScopeService::class)->label($tech));
        $legacy = User::factory()->role('ADMIN')->create(['admin_scope_type' => 'service_area', 'service_area_id' => ServiceArea::create(['workzone' => 'LEG', 'name' => 'Legacy', 'branch_id' => $branch->getKey()])->getKey()]);
        $this->assertSame('Service Area · LEG', app(AccountScopeService::class)->label($legacy));
    }
}

class EnforcedAccountCsrfToken extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
