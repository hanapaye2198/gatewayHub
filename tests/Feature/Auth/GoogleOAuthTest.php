<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_google_redirect_redirects_to_google(): void
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('google.redirect'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_google_callback_creates_new_user_and_redirects_to_onboarding(): void
    {
        $googleId = 'google-'.fake()->uuid();
        $email = fake()->unique()->safeEmail();
        $name = fake()->name();

        $socialiteUser = $this->mockSocialiteUser($googleId, $email, $name);
        $this->mockSocialiteDriver($socialiteUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('onboarding.business'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame($name, $user->name);
        $this->assertSame($googleId, $user->google_id);
        $this->assertSame('merchant_user', $user->role);
        $this->assertNull($user->merchant_id);
        $this->assertNotNull($user->password);
    }

    public function test_google_callback_logs_in_existing_user_and_redirects_by_role(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
        ]);

        $socialiteUser = $this->mockSocialiteUser('google-123', $existingUser->email, $existingUser->name);
        $this->mockSocialiteDriver($socialiteUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(url('/dashboard'));
        $this->assertAuthenticatedAs($existingUser);

        $existingUser->refresh();
        $this->assertSame('google-123', $existingUser->google_id);
    }

    public function test_google_callback_redirects_admin_to_admin_panel(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'google_id' => null,
        ]);

        $socialiteUser = $this->mockSocialiteUser('google-admin-1', $admin->email, $admin->name);
        $this->mockSocialiteDriver($socialiteUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(url('/admin'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_page_shows_google_login_button(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('google.redirect'));
        $response->assertSee('Continue with Google', false);
    }

    /**
     * @return \Laravel\Socialite\Contracts\User
     */
    private function mockSocialiteUser(string $id, string $email, string $name)
    {
        $user = Mockery::mock('Laravel\Socialite\Contracts\User');
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($name);

        return $user;
    }

    /**
     * @param  \Laravel\Socialite\Contracts\User  $socialiteUser
     */
    private function mockSocialiteDriver($socialiteUser): void
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);
    }
}
