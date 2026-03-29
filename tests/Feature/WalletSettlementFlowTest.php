<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\MerchantWalletSetting;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletSettlementService;
use App\Services\Coins\CoinsSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletSettlementFlowTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'wallet-flow-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('surepay.features.wallet_settlement', true);
        config()->set('coins.webhook.secret', self::WEBHOOK_SECRET);
    }

    public function test_paid_webhook_creates_clearing_flow_and_holds_net_in_tunnel_for_batch(): void
    {
        config(['platform.fees' => ['percentage' => 1.5, 'fixed' => 5]]);
        \App\Models\PlatformFeeRule::query()->delete();

        $gateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            'is_global_enabled' => true,
        ]);

        $user = User::factory()->create();

        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [
                'client_id' => 'client',
                'client_secret' => 'secret',
                'api_base' => 'sandbox',
                'webhook_secret' => self::WEBHOOK_SECRET,
            ],
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'WALLET-ORD-001',
            'amount' => 1000,
            'currency' => 'PHP',
            'status' => 'pending',
            'platform_fee' => null,
            'net_amount' => null,
        ]);

        $payload = [
            'referenceId' => 'WALLET-ORD-001',
            'status' => 'SUCCEEDED',
            'amount' => '1000.00',
            'currency' => 'PHP',
            'settleDate' => (string) (time() * 1000),
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];

        $signed = (new CoinsSignatureService)->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('20.00', (string) $payment->platform_fee);
        $this->assertSame('980.00', (string) $payment->net_amount);

        $clearingWallet = Wallet::query()
            ->where('merchant_id', $user->id)
            ->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING)
            ->where('currency', 'PHP')
            ->first();
        $realWallet = Wallet::query()
            ->where('merchant_id', $user->id)
            ->where('wallet_type', Wallet::TYPE_MERCHANT_REAL)
            ->where('currency', 'PHP')
            ->first();
        $taxWallet = Wallet::query()
            ->whereNull('merchant_id')
            ->where('wallet_type', Wallet::TYPE_SYSTEM_TAX)
            ->where('currency', 'PHP')
            ->first();

        $this->assertNotNull($clearingWallet);
        $this->assertNotNull($taxWallet);

        $this->assertSame('980.00', (string) $clearingWallet->balance);
        $this->assertNull($realWallet);
        $this->assertSame('20.00', (string) $taxWallet->balance);

        $this->assertSame(4, WalletTransaction::query()->where('payment_id', $payment->id)->count());
        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => 'payment_received_gross',
            'direction' => 'credit',
            'amount' => 1000,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => 'transfer_to_surepay_gross',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => 'surepay_tax_collected',
            'direction' => 'debit',
            'amount' => 20,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => 'surepay_tax_collected',
            'direction' => 'credit',
            'amount' => 20,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => 'tunnel_net_available',
            'direction' => 'credit',
            'amount' => 980,
            'is_settled' => false,
        ]);
    }

    public function test_wallet_settlement_is_idempotent_for_same_paid_payment(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'merchant_id' => $user->id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 7.50,
            'net_amount' => 492.50,
            'paid_at' => now(),
        ]);

        $service = app(WalletSettlementService::class);
        $service->recordPaidPayment($payment);
        $service->recordPaidPayment($payment);

        $this->assertSame(
            1,
            WalletTransaction::query()
                ->where('payment_id', $payment->id)
                ->where('entry_type', WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
                ->count()
        );

        $this->assertSame(4, WalletTransaction::query()->where('payment_id', $payment->id)->count());
    }

    public function test_wallet_settlement_forces_tunnel_flow_even_when_merchant_setting_is_disabled(): void
    {
        $user = User::factory()->create();

        MerchantWalletSetting::query()->create([
            'merchant_id' => $user->id,
            'tunnel_wallet_enabled' => false,
            'auto_settle_to_real_wallet' => true,
            'default_currency' => 'PHP',
            'notes' => null,
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $user->id,
            'amount' => 200,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 10,
            'net_amount' => 190,
            'paid_at' => now(),
        ]);

        app(WalletSettlementService::class)->recordPaidPayment($payment);

        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS,
            'direction' => 'credit',
            'amount' => 200,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'direction' => 'credit',
            'amount' => 190,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
        ]);
    }

    public function test_wallet_settlement_caps_tax_deduction_to_gross_amount(): void
    {
        $user = User::factory()->create();

        $payment = Payment::factory()->create([
            'merchant_id' => $user->id,
            'amount' => 100,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 150,
            'net_amount' => 0,
            'paid_at' => now(),
        ]);

        app(WalletSettlementService::class)->recordPaidPayment($payment);

        $tunnelWallet = Wallet::query()
            ->where('merchant_id', $user->id)
            ->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING)
            ->where('currency', 'PHP')
            ->first();
        $taxWallet = Wallet::query()
            ->whereNull('merchant_id')
            ->where('wallet_type', Wallet::TYPE_SYSTEM_TAX)
            ->where('currency', 'PHP')
            ->first();

        $this->assertNotNull($tunnelWallet);
        $this->assertNotNull($taxWallet);
        $this->assertSame('0.00', (string) $tunnelWallet->balance);
        $this->assertSame('100.00', (string) $taxWallet->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_SUREPAY_TAX_COLLECTED,
            'direction' => 'debit',
            'amount' => 100,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
        ]);

        app(WalletSettlementService::class)->recordPaidPayment($payment->refresh());
        $this->assertSame(3, WalletTransaction::query()->where('payment_id', $payment->id)->count());
    }

    public function test_gateway_config_does_not_override_admin_tunnel_wallet_setting(): void
    {
        $user = User::factory()->create();
        $gateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            'is_global_enabled' => true,
        ]);

        MerchantWalletSetting::query()->create([
            'merchant_id' => $user->id,
            'tunnel_wallet_enabled' => true,
            'auto_settle_to_real_wallet' => true,
            'default_currency' => 'PHP',
            'notes' => null,
        ]);

        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [
                'tunnel_wallet_enabled' => false,
                'auto_settle_to_real_wallet' => true,
            ],
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'amount' => 300,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 15,
            'net_amount' => 285,
            'paid_at' => now(),
        ]);

        app(WalletSettlementService::class)->recordPaidPayment($payment);

        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'amount' => 285,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
        ]);
    }

    public function test_batch_settlement_moves_net_from_tunnel_to_real_wallet(): void
    {
        $user = User::factory()->create();
        $gateway = Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\Services\Gateways\Drivers\CoinsDriver',
            'is_global_enabled' => true,
        ]);

        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => ['tunnel_wallet_enabled' => true],
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'amount' => 1000,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 20,
            'net_amount' => 980,
            'paid_at' => now(),
        ]);

        $service = app(WalletSettlementService::class);
        $service->recordPaidPayment($payment);

        $settled = $service->settlePendingNetBatch((int) $user->id, 10);
        $this->assertSame(1, $settled);

        $tunnel = Wallet::query()->where('merchant_id', $user->id)->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING)->first();
        $real = Wallet::query()->where('merchant_id', $user->id)->where('wallet_type', Wallet::TYPE_MERCHANT_REAL)->first();

        $this->assertNotNull($tunnel);
        $this->assertNotNull($real);
        $this->assertSame('0.00', (string) $tunnel->balance);
        $this->assertSame('980.00', (string) $real->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
            'amount' => 980,
            'is_settled' => true,
        ]);

        $payment->refresh();
        $raw = is_array($payment->raw_response) ? $payment->raw_response : [];
        $surepayLogs = $raw['surepay_sending_logs'] ?? [];
        $this->assertIsArray($surepayLogs);
        $this->assertNotEmpty($surepayLogs);
        $this->assertTrue(collect($surepayLogs)->contains(fn ($log) => ($log['status'] ?? null) === 'success'));
        $flowLogs = $raw['flow_logs'] ?? [];
        $this->assertIsArray($flowLogs);
        $this->assertArrayHasKey('user_to_surepay_wallet', $flowLogs);
        $this->assertTrue(
            collect($flowLogs['user_to_surepay_wallet'])
                ->every(fn ($log) => ($log['source_channel'] ?? null) === 'user_to_surepay')
        );
    }

    public function test_paypal_tunnel_settlement_validates_tunnel_wallet_paypal_credentials(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-tunnel-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $user = User::factory()->create();
        $gateway = Gateway::query()->create([
            'code' => 'paypal',
            'name' => 'PayPal',
            'driver_class' => 'App\Services\Gateways\Drivers\PaypalDriver',
            'is_global_enabled' => true,
        ]);

        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [
                'client_id' => 'merchant-paypal-client',
                'client_secret' => 'merchant-paypal-secret',
                'api_base' => 'sandbox',
            ],
        ]);

        MerchantWalletSetting::query()->create([
            'merchant_id' => $user->id,
            'tunnel_wallet_enabled' => true,
            'auto_settle_to_real_wallet' => true,
            'default_currency' => 'PHP',
            'tunnel_client_id' => 'tunnel-paypal-client',
            'tunnel_client_secret' => 'tunnel-paypal-secret',
            'tunnel_webhook_id' => null,
            'notes' => null,
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $user->id,
            'gateway_code' => 'paypal',
            'amount' => 1000,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 20,
            'net_amount' => 980,
            'paid_at' => now(),
        ]);

        $service = app(WalletSettlementService::class);
        $service->recordPaidPayment($payment);
        $settled = $service->settlePendingNetBatch((int) $user->id, 10);

        $this->assertSame(1, $settled);
        $creditTransaction = WalletTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('entry_type', WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT)
            ->first();

        $this->assertNotNull($creditTransaction);
        $this->assertSame('paypal', $creditTransaction->metadata['provider'] ?? null);
        $this->assertSame('sandbox', $creditTransaction->metadata['paypal_mode'] ?? null);
    }

    public function test_paypal_tunnel_settlement_fails_when_tunnel_paypal_credentials_are_missing(): void
    {
        $user = User::factory()->create();
        $gateway = Gateway::query()->create([
            'code' => 'paypal',
            'name' => 'PayPal',
            'driver_class' => 'App\Services\Gateways\Drivers\PaypalDriver',
            'is_global_enabled' => true,
        ]);

        MerchantGateway::query()->create([
            'merchant_id' => $user->id,
            'gateway_id' => $gateway->id,
            'is_enabled' => true,
            'config_json' => [
                'client_id' => 'merchant-paypal-client',
                'client_secret' => 'merchant-paypal-secret',
                'api_base' => 'sandbox',
            ],
        ]);

        MerchantWalletSetting::query()->create([
            'merchant_id' => $user->id,
            'tunnel_wallet_enabled' => true,
            'auto_settle_to_real_wallet' => true,
            'default_currency' => 'PHP',
            'tunnel_client_id' => '',
            'tunnel_client_secret' => '',
            'tunnel_webhook_id' => null,
            'notes' => null,
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $user->id,
            'gateway_code' => 'paypal',
            'amount' => 500,
            'currency' => 'PHP',
            'status' => 'paid',
            'platform_fee' => 10,
            'net_amount' => 490,
            'paid_at' => now(),
        ]);

        $service = app(WalletSettlementService::class);
        $service->recordPaidPayment($payment);
        $settled = $service->settlePendingNetBatch((int) $user->id, 10);

        $this->assertSame(0, $settled);
        $this->assertDatabaseMissing('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
        ]);

        $payment->refresh();
        $raw = is_array($payment->raw_response) ? $payment->raw_response : [];
        $errors = $raw['surepay_wallet_errors'] ?? [];
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $surepayLogs = $raw['surepay_sending_logs'] ?? [];
        $this->assertIsArray($surepayLogs);
        $this->assertNotEmpty($surepayLogs);
        $this->assertTrue(collect($surepayLogs)->contains(fn ($log) => ($log['status'] ?? null) === 'failed'));
        $flowLogs = $raw['flow_logs'] ?? [];
        $flowErrors = $raw['flow_errors'] ?? [];
        $this->assertIsArray($flowLogs);
        $this->assertIsArray($flowErrors);
        $this->assertArrayHasKey('user_to_surepay_wallet', $flowLogs);
        $this->assertArrayHasKey('user_to_surepay_wallet', $flowErrors);
        $this->assertTrue(
            collect($flowErrors['user_to_surepay_wallet'])
                ->every(fn ($error) => ($error['source_channel'] ?? null) === 'user_to_surepay')
        );
    }
}
