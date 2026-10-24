<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('google.redirect'));
    }

    public function test_google_callback_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('google.callback'));
    }

    public function test_google_account_identifiers_are_not_stored(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'google_id'));
    }

    public function test_google_callback_cannot_authenticate_an_admin(): void
    {
        $this->get('/auth/google/callback')->assertNotFound();
        $this->assertGuest();
    }

    public function test_login_page_does_not_offer_google_login(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee('Login with Google', false);
        $this->assertFalse(Route::has('google.redirect'));
    }
}
