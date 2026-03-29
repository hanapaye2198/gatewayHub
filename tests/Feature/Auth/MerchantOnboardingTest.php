<?php

namespace Tests\Feature\Auth;

use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_onboarding_cannot_access_dashboard(): void
    {
        $user = User::factory()->withoutMerchant()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('onboarding.business'));
    }

    public function test_business_step_creates_merchant_and_stores_hashed_credentials(): void
    {
        $user = User::factory()->withoutMerchant()->create();

        $response = $this->actingAs($user)->post(route('onboarding.business.store'), [
            'business_name' => 'Acme Co',
            'business_email' => 'billing@acme.test',
        ]);

        $response->assertRedirect(route('onboarding.gateways'));

        $user->refresh();
        $this->assertNotNull($user->merchant_id);
        $this->assertNull($user->onboarding_gateways_at);

        $merchant = Merchant::query()->findOrFail($user->merchant_id);
        $this->assertSame('Acme Co', $merchant->name);
        $this->assertTrue($merchant->hasApiKey());
        $this->assertNotNull($merchant->api_secret);
        $this->assertNotSame('', $merchant->api_secret);
    }

    public function test_gateway_step_syncs_and_advances_to_api_keys(): void
    {
        $gateway = Gateway::query()->create([
            'code' => 'onb-test-'.uniqid(),
            'name' => 'Onboarding Test Gateway',
            'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            'is_global_enabled' => true,
        ]);

        $user = User::factory()->withoutMerchant()->create();

        $this->actingAs($user)->post(route('onboarding.business.store'), [
            'business_name' => 'Acme Co',
            'business_email' => 'billing@acme2.test',
        ]);

        $user->refresh();

        $response = $this->actingAs($user)->post(route('onboarding.gateways.store'), [
            'gateway_ids' => [$gateway->id],
        ]);

        $response->assertRedirect(route('onboarding.api-keys'));

        $user->refresh();
        $this->assertNotNull($user->onboarding_gateways_at);

        $merchant = $user->merchant;
        $this->assertNotNull($merchant);
        $this->assertTrue($merchant->gateways()->whereKey($gateway->id)->exists());
    }

    public function test_complete_onboarding_finishes_and_allows_dashboard(): void
    {
        $user = User::factory()->withoutMerchant()->create();

        $this->actingAs($user)->post(route('onboarding.business.store'), [
            'business_name' => 'Acme Co',
            'business_email' => 'billing@acme3.test',
        ]);

        $user->refresh();

        $this->actingAs($user)->post(route('onboarding.gateways.store'), [
            'gateway_ids' => [],
        ]);

        $user->refresh();

        $response = $this->actingAs($user)->post(route('onboarding.complete'));

        $response->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertNotNull($user->onboarding_completed_at);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
