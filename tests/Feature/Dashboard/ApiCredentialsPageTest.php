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
        $merchant = User::factory()->create();
        $merchant->merchant->forceFill(['api_key' => 'test-key-1234'])->save();

        $this->actingAs($merchant);
        $response = $this->get(route('dashboard.api-credentials'));

        $response->assertOk();
        $response->assertSee('API Credentials');
        $response->assertSee('****1234');
        $response->assertSee('Setting up your webhook / callback URL', false);
    }

    public function test_webhook_signing_secret_show_toggle_displays_masked_then_full(): void
    {
        $user = User::factory()->create();
        $user->merchant->forceFill([
            'webhook_url' => 'https://merchant.example/webhooks',
            'webhook_secret' => 'abcdefghijklmnop',
        ])->save();

        $this->actingAs($user);

        Livewire::test('pages::dashboard.api-credentials')
            ->assertSet('showWebhookSecret', false)
            ->assertSee('****mnop', false)
            ->call('toggleShowWebhookSecret')
            ->assertSet('showWebhookSecret', true)
            ->assertSee('abcdefghijklmnop', false);
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
        $merchant = User::factory()->create();
        $merchant->merchant->forceFill([
            'api_key' => 'old-key',
            'api_key_generated_at' => null,
        ])->save();

        $this->actingAs($merchant);

        Livewire::test('pages::dashboard.api-credentials')
            ->call('confirmRegenerate')
            ->assertSet('showRegenerateConfirm', true)
            ->call('regenerateApiKey')
            ->assertRedirect(route('dashboard.api-credentials'));

        $merchant->refresh();
        $m = $merchant->merchant;
        $this->assertNotNull($m);
        $this->assertNull($m->api_key);
        $this->assertNotNull($m->api_key_hash);
        $this->assertSame(64, strlen($m->api_key_hash));
        $this->assertNotNull($m->api_key_last_four);
        $this->assertSame(4, strlen($m->api_key_last_four));
        $this->assertNotNull($m->api_key_generated_at);
    }

    public function test_after_regenerate_key_is_new_and_masked_on_page(): void
    {
        $merchant = User::factory()->create();
        $merchant->merchant->forceFill(['api_key' => 'previous'])->save();

        $this->actingAs($merchant);

        Livewire::test('pages::dashboard.api-credentials')->call('regenerateApiKey');

        $merchant->refresh();
        $m = $merchant->merchant;
        $this->assertNotNull($m);
        $this->assertNull($m->api_key);
        $this->assertNotNull($m->api_key_hash);
        $this->assertSame(64, strlen($m->api_key_hash));
        $this->assertNotNull($m->api_key_last_four);
        $this->assertSame(4, strlen($m->api_key_last_four));

        $response = $this->get(route('dashboard.api-credentials'));
        $response->assertOk();
        $response->assertDontSee('previous');
        $response->assertSee('****'.$m->api_key_last_four);
    }

    public function test_merchant_can_regenerate_webhook_secret(): void
    {
        $merchant = User::factory()->create();
        $merchant->merchant->forceFill([
            'webhook_url' => 'https://merchant.example/webhooks',
            'webhook_secret' => 'old-secret-value',
        ])->save();

        $this->actingAs($merchant);

        Livewire::test('pages::dashboard.api-credentials')
            ->call('regenerateWebhookSecretNow')
            ->assertSet('newWebhookSecret', fn ($value) => is_string($value) && $value !== '');

        $merchant->refresh();
        $this->assertNotSame('old-secret-value', $merchant->merchant?->webhook_secret);
        $this->assertNotNull($merchant->merchant?->webhook_secret);
    }

    public function test_merchant_can_generate_webhook_secret_when_none_exists(): void
    {
        $merchant = User::factory()->create();
        $merchant->merchant->forceFill([
            'webhook_url' => 'https://merchant.example/webhooks',
            'webhook_secret' => null,
        ])->save();

        $this->actingAs($merchant);

        Livewire::test('pages::dashboard.api-credentials')
            ->assertSet('hasWebhookSecret', false)
            ->call('regenerateWebhookSecretNow')
            ->assertSet('hasWebhookSecret', true)
            ->assertSet('newWebhookSecret', fn ($value) => is_string($value) && strlen($value) === 48);

        $merchant->refresh();
        $this->assertSame(48, strlen((string) $merchant->merchant?->webhook_secret));
    }

    public function test_saving_webhook_url_does_not_generate_secret_when_absent(): void
    {
        $merchant = User::factory()->create();
        $merchant->merchant->forceFill([
            'webhook_url' => null,
            'webhook_secret' => null,
        ])->save();

        $this->actingAs($merchant);

        Livewire::test('pages::dashboard.api-credentials')
            ->set('webhookUrl', 'https://merchant.example/webhooks')
            ->call('updateWebhookSettings')
            ->assertSet('newWebhookSecret', null);

        $merchant->refresh();
        $this->assertNull($merchant->merchant?->webhook_secret);
    }

    public function test_livewire_rejects_invalid_webhook_callback_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::dashboard.api-credentials')
            ->set('webhookUrl', 'not-a-valid-callback')
            ->call('updateWebhookSettings')
            ->assertHasErrors(['webhookUrl']);
    }

    public function test_livewire_saves_valid_webhook_callback_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $url = 'https://hooks.example.test/api/v1/callback';

        Livewire::test('pages::dashboard.api-credentials')
            ->set('webhookUrl', $url)
            ->call('updateWebhookSettings')
            ->assertHasNoErrors();

        $this->assertSame($url, $user->merchant->fresh()->webhook_url);
    }
}
