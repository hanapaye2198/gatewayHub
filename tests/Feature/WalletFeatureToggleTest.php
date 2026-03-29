<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletFeatureToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_balance_api_returns_not_found_when_wallet_feature_is_disabled(): void
    {
        $token = Str::random(64);
        User::factory()->withMerchantApiKey($token)->create();

        $response = $this->withToken($token)->getJson('/api/wallets/balances');

        $response->assertNotFound();
        $response->assertJson([
            'success' => false,
            'error' => 'Not found.',
        ]);
    }

    public function test_admin_wallet_pages_return_not_found_when_wallet_feature_is_disabled(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.surepay-wallets.index'))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.surepay-wallets.dashboard'))->assertNotFound();
    }
}
