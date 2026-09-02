<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root "/" sekarang dilindungi auth (redirect ke /login untuk guest)
     * sejak modul Authentication dibangun - bukan lagi welcome page publik.
     */
    public function test_guest_visiting_root_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
