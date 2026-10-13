<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_trigger_opens_white_confirmation_and_only_yes_is_submit_for_all_roles(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role->value)->create();
            $route = match ($role) {
                UserRole::ADMIN, UserRole::SUPER_ADMIN => 'dashboard',
                UserRole::TEKNISI => 'technician.profile',
                default => 'lop.index',
            };
            $html = $this->actingAs($user)->get(route($route))->assertOk()->assertSee('Keluar dari akun?')->assertSee('Batal')->assertSee('Ya, Logout')->getContent();
            $document = new DOMDocument;
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $triggers = $xpath->query('//button[@data-logout-trigger]');
            $this->assertSame(1, $triggers->length);
            $this->assertSame('button', $triggers->item(0)->getAttribute('type'));
            $this->assertSame(0, $xpath->query('ancestor::form', $triggers->item(0))->length);
            $dialog = $xpath->query('//dialog[.//form[@data-auth-session="logout"]]')->item(0);
            $this->assertNotNull($dialog);
            $this->assertStringContainsString('bg-white', $dialog->getAttribute('class'));
            $this->assertStringContainsString('text-ink-900', $dialog->getAttribute('class'));
            $this->assertStringNotContainsString('dark:bg-', $dialog->getAttribute('class'));
            $form = $xpath->query('.//form', $dialog)->item(0);
            $this->assertSame('POST', $form->getAttribute('method'));
            $this->assertSame(route('logout', [], false), $form->getAttribute('action'));
            $this->assertSame((string) $user->getKey(), $form->getAttribute('data-auth-user-id'));
            $this->assertSame(route('auth.session', [], false), $form->getAttribute('data-auth-session-url'));
            $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->length);
            $submits = $xpath->query('.//button[@type="submit"]', $form);
            $this->assertSame(1, $submits->length);
            $this->assertTrue($submits->item(0)->hasAttribute('data-logout-confirm'));
            $this->assertSame('Ya, Logout', trim($submits->item(0)->textContent));
            $this->assertSame(2, $xpath->query('.//button[@type="button" and @data-auth-session-cancel]', $form)->length);
            $this->assertAuthenticatedAs($user);
        }
    }
}
