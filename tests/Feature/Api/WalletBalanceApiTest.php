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
                'wallet_balance' => 0,
                'available_balance' => 0,
                'settled_balance' => 0,
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
            'wallet_id' => $tunnelWallet->id,
            'payment_id' => $payment->id,
            'direction' => 'debit',
            'entry_type' => WalletTransaction::ENTRY_SUREPAY_TAX_COLLECTED,
            'amount' => 20.00,
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
                'wallet_balance' => 1180,
                'available_balance' => 980,
                'settled_balance' => 200,
                'tunnel_balance' => 980,
                'real_balance' => 200,
                'pending_net_settlement' => 980,
                'today_gross' => 1000,
                'today_platform_fees' => 20,
                'today_net_settled' => 500,
            ],
        ]);
    }

    public function test_wallet_transactions_return_net_receipts_with_platform_fee_and_merchant_scoping(): void
    {
        $merchantToken = Str::random(64);
        $otherMerchantToken = Str::random(64);
        $merchant = User::factory()->withMerchantApiKey($merchantToken)->create();
        $otherMerchant = User::factory()->withMerchantApiKey($otherMerchantToken)->create();

        $payment = Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'gateway_code' => 'qrph',
            'reference_id' => 'ORDER-1001',
            'provider_reference' => 'PAYMONGO-1001',
            'amount' => 500,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 7.50,
            'net_amount' => 492.50,
        ]);
        $otherPayment = Payment::factory()->create([
            'merchant_id' => $otherMerchant->id,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 15,
            'net_amount' => 985,
        ]);

        $merchantWallet = Wallet::factory()->create([
            'merchant_id' => $merchant->id,
            'wallet_type' => Wallet::TYPE_MERCHANT_CLEARING,
            'currency' => 'PHP',
            'balance' => 492.50,
        ]);
        $otherWallet = Wallet::factory()->create([
            'merchant_id' => $otherMerchant->id,
            'wallet_type' => Wallet::TYPE_MERCHANT_CLEARING,
            'currency' => 'PHP',
            'balance' => 985,
        ]);

        WalletTransaction::factory()->create([
            'wallet_id' => $merchantWallet->id,
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'amount' => 492.50,
            'currency' => 'PHP',
            'is_settled' => false,
        ]);
        WalletTransaction::factory()->create([
            'wallet_id' => $otherWallet->id,
            'payment_id' => $otherPayment->id,
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'amount' => 985,
            'currency' => 'PHP',
            'is_settled' => false,
        ]);

        $this->withToken($merchantToken)
            ->getJson('/api/wallets/transactions?currency=php&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.currency', 'PHP')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.transactions.0.reference', 'ORDER-1001')
            ->assertJsonPath('data.transactions.0.gross_amount', 500)
            ->assertJsonPath('data.transactions.0.platform_fee', 7.5)
            ->assertJsonPath('data.transactions.0.net_amount', 492.5)
            ->assertJsonPath('data.transactions.0.wallet_status', 'available')
            ->assertJsonPath('data.transactions.0.provider_reference', 'PAYMONGO-1001');
    }

    public function test_wallet_transactions_reject_invalid_page_size(): void
    {
        $merchantToken = Str::random(64);
        User::factory()->withMerchantApiKey($merchantToken)->create();

        $this->withToken($merchantToken)
            ->getJson('/api/wallets/transactions?per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_wallet_transactions_api_requires_authentication(): void
    {
        $this->getJson('/api/wallets/transactions')->assertUnauthorized();
    }
}
