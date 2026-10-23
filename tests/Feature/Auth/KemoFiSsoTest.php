<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KemoFiSsoTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_offers_kemofi_account_sign_in(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Continue with your KemoFi account');
        $response->assertSee(route('kemofi.redirect'), false);
    }

    public function test_kemofi_sign_in_starts_a_state_protected_authorization(): void
    {
        config([
            'kemofi_sso.base_url' => 'https://kemofi.com',
            'kemofi_sso.shared_secret' => str_repeat('a', 48),
        ]);

        $response = $this->get(route('kemofi.redirect'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://kemofi.com/kemopay-sso?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{48}$/', (string) ($query['state'] ?? ''));
        $this->assertArrayHasKey($query['state'], session('kemofi_sso_states', []));
    }
}
