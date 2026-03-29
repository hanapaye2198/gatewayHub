<?php

namespace Tests\Feature\Api;

use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletBalanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('surepay.features.wallet_settlement', true);
    }

    public function test_wallet_balance_api_requires_authentication(): void
    {
        $this->getJson('/api/wallets/balances')->assertUnauthorized();
    }

    public function test_wallet_balance_api_is_for_merchants_only(): void
    {
        $adminToken = Str::random(64);
        User::factory()->admin()->create();

        $this->withToken($adminToken)
            ->getJson('/api/wallets/balances')
            ->assertUnauthorized();
    }

    public function test_wallet_balance_api_returns_zero_amounts_when_wallets_do_not_exist(): void
    {
        $merchantToken = Str::random(64);
        User::factory()->withMerchantApiKey($merchantToken)->create();

        $response = $this->withToken($merchantToken)
            ->getJson('/api/wallets/balances');

        $response->assertOk()->assertJson([
            'success' => true,
            'error' => null,
            'data' => [
                'currency' => 'PHP',
                'tunnel_balance' => 0,
                'real_balance' => 0,
                'pending_net_settlement' => 0,
                'today_gross' => 0,
                'today_net_settled' => 0,
            ],
        ]);
        $response->assertJsonPath('data.as_of', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_wallet_balance_api_returns_aggregated_merchant_balances_and_totals(): void
    {
        $merchantToken = Str::random(64);
        $otherMerchantToken = Str::random(64);
        $merchant = User::factory()->withMerchantApiKey($merchantToken)->create();
        $otherMerchant = User::factory()->withMerchantApiKey($otherMerchantToken)->create();

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'gateway_code' => 'paypal',
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $otherPayment = Payment::factory()->create([
            'merchant_id' => $otherMerchant->id,
            'gateway_code' => 'paypal',
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $tunnelWallet = Wallet::factory()->create([
            'merchant_id' => $merchant->id,
            'wallet_type' => Wallet::TYPE_MERCHANT_CLEARING,
            'currency' => 'PHP',
            'balance' => 980.00,
        ]);
        $realWallet = Wallet::factory()->create([
            'merchant_id' => $merchant->id,
            'wallet_type' => Wallet::TYPE_MERCHANT_REAL,
            'currency' => 'PHP',
            'balance' => 200.00,
        ]);

        $otherTunnelWallet = Wallet::factory()->create([
            'merchant_id' => $otherMerchant->id,
            'wallet_type' => Wallet::TYPE_MERCHANT_CLEARING,
            'currency' => 'PHP',
            'balance' => 999.00,
        ]);

        WalletTransaction::factory()->create([
            'wallet_id' => $tunnelWallet->id,
            'payment_id' => $payment->id,
            'direction' => 'credit',
            'entry_type' => WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS,
            'amount' => 1000.00,
            'currency' => 'PHP',
            'is_settled' => true,
            'created_at' => now(),
        ]);
        WalletTransaction::factory()->create([
            'wallet_id' => $tunnelWallet->id,
            'payment_id' => $payment->id,
            'direction' => 'credit',
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'amount' => 980.00,
            'currency' => 'PHP',
            'is_settled' => false,
            'created_at' => now(),
        ]);
        WalletTransaction::factory()->create([
            'wallet_id' => $realWallet->id,
            'payment_id' => $payment->id,
            'direction' => 'credit',
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
            'amount' => 500.00,
            'currency' => 'PHP',
            'is_settled' => true,
            'created_at' => now(),
        ]);

        WalletTransaction::factory()->create([
            'wallet_id' => $otherTunnelWallet->id,
            'payment_id' => $otherPayment->id,
            'direction' => 'credit',
            'entry_type' => WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS,
            'amount' => 999.00,
            'currency' => 'PHP',
            'is_settled' => true,
            'created_at' => now(),
        ]);
        WalletTransaction::factory()->create([
            'wallet_id' => $otherTunnelWallet->id,
            'payment_id' => $otherPayment->id,
            'direction' => 'credit',
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'amount' => 999.00,
            'currency' => 'PHP',
            'is_settled' => false,
            'created_at' => now(),
        ]);

        $response = $this->withToken($merchantToken)
            ->getJson('/api/wallets/balances?currency=php');

        $response->assertOk()->assertJson([
            'success' => true,
            'error' => null,
            'data' => [
                'currency' => 'PHP',
                'tunnel_balance' => 980,
                'real_balance' => 200,
                'pending_net_settlement' => 980,
                'today_gross' => 1000,
                'today_net_settled' => 500,
            ],
        ]);
    }
}
