<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiCredentialsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard.api-credentials'));
        $response->assertRedirect(route('login'));
    }

    public function test_merchant_can_access_api_credentials_page(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant', 'api_key' => 'test-key-1234']);

        $this->actingAs($merchant);
        $response = $this->get(route('dashboard.api-credentials'));

        $response->assertOk();
        $response->assertSee('API Credentials');
        $response->assertSee('****1234');
    }

    public function test_admin_cannot_access_api_credentials_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $response = $this->get(route('dashboard.api-credentials'));

        $response->assertForbidden();
    }

    public function test_merchant_can_regenerate_api_key(): void
    {
        $merchant = User::factory()->create([
            'role' => 'merchant',
            'api_key' => 'old-key',
            'api_key_generated_at' => null,
        ]);

        $this->actingAs($merchant);

        Livewire::test('pages::dashboard.api-credentials')
            ->call('confirmRegenerate')
            ->assertSet('showRegenerateConfirm', true)
            ->call('regenerateApiKey')
            ->assertRedirect(route('dashboard.api-credentials'));

        $merchant->refresh();
        $this->assertNotSame('old-key', $merchant->api_key);
        $this->assertNotNull($merchant->api_key_generated_at);
        $this->assertSame(64, strlen($merchant->api_key));
    }

    public function test_after_regenerate_key_is_new_and_masked_on_page(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant', 'api_key' => 'previous']);

        $this->actingAs($merchant);

        Livewire::test('pages::dashboard.api-credentials')->call('regenerateApiKey');

        $merchant->refresh();
        $this->assertNotSame('previous', $merchant->api_key);
        $this->assertNotNull($merchant->api_key);
        $this->assertSame(64, strlen($merchant->api_key));

        $response = $this->get(route('dashboard.api-credentials'));
        $response->assertOk();
        $response->assertDontSee('previous');
        $response->assertSee('****'.substr($merchant->api_key, -4));
    }
}
