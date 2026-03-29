<?php

namespace Tests\Feature;

use App\Enums\PlatformFeeStatus;
use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\User;
use App\Services\Billing\PlatformFeeService as BillingPlatformFeeService;
use App\Services\Coins\CoinsSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFeeTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('coins.webhook.secret', self::WEBHOOK_SECRET);
    }

    public function test_platform_fee_applied_when_payment_becomes_paid_via_webhook(): void
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
            'provider_reference' => 'ORD-001',
            'amount' => 1000,
            'status' => 'pending',
            'paid_at' => null,
            'platform_fee' => null,
            'net_amount' => null,
        ]);

        $payload = [
            'referenceId' => 'ORD-001',
            'status' => 'SUCCEEDED',
            'amount' => '1000.00',
            'currency' => 'PHP',
            'settleDate' => (string) (time() * 1000),
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signatureService = new CoinsSignatureService;
        $signed = $signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->platform_fee);
        $this->assertNotNull($payment->net_amount);
        $this->assertSame('20.00', (string) $payment->platform_fee); // (1000 * 1.5/100) + 5 = 15 + 5
        $this->assertSame('980.00', (string) $payment->net_amount);

        $ledger = $payment->platformFee;
        $this->assertNotNull($ledger);
        $this->assertSame('1000.00', (string) $ledger->gross_amount);
        $this->assertSame('20.00', (string) $ledger->fee_amount);
        $this->assertSame('980.00', (string) $ledger->net_amount);
        $this->assertSame(PlatformFeeStatus::Posted, $ledger->status);
    }

    public function test_platform_fee_not_recalculated_on_webhook_retry_when_already_paid(): void
    {
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

        $payment = Payment::factory()->paid()->create([
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'provider_reference' => 'ORD-002',
            'amount' => 500,
        ]);
        $originalFee = $payment->platform_fee;
        $originalNet = $payment->net_amount;
        $this->assertNotNull($originalFee);
        $this->assertNotNull($originalNet);

        $payload = [
            'referenceId' => 'ORD-002',
            'status' => 'SUCCEEDED',
            'timestamp' => (string) (int) (microtime(true) * 1000),
        ];
        $signatureService = new CoinsSignatureService;
        $signed = $signatureService->sign($payload, self::WEBHOOK_SECRET);

        $response = $this->postJson('/api/webhooks?provider=coins', $payload, [
            'Content-Type' => 'application/json',
            'X-COINS-SIGNATURE' => $signed['signature'],
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame((string) $originalFee, (string) $payment->platform_fee);
        $this->assertSame((string) $originalNet, (string) $payment->net_amount);
    }

    public function test_platform_fee_service_does_nothing_when_status_not_paid(): void
    {
        $payment = Payment::factory()->create([
            'status' => 'pending',
            'platform_fee' => null,
            'net_amount' => null,
        ]);

        app(BillingPlatformFeeService::class)->record($payment);

        $this->assertNull($payment->platform_fee);
        $this->assertNull($payment->net_amount);
    }

    public function test_platform_fee_service_does_nothing_when_fee_already_set(): void
    {
        $payment = Payment::factory()->paid()->create([
            'amount' => 100,
            'platform_fee' => 0.50,
            'net_amount' => 99.50,
        ]);

        app(BillingPlatformFeeService::class)->record($payment);

        $this->assertSame('0.50', (string) $payment->platform_fee);
        $this->assertSame('99.50', (string) $payment->net_amount);
    }

    public function test_platform_fee_ledger_model_and_payment_relationship(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create([
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'amount' => 100,
        ]);

        $ledger = PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => 'posted',
        ]);

        $this->assertSame($payment->id, $ledger->payment_id);
        $this->assertTrue($payment->platformFee->is($ledger));
        $this->assertTrue($ledger->payment->is($payment));
        $this->assertTrue($ledger->merchant->is($user->merchant));
    }

    public function test_platform_fees_table_enforces_one_fee_per_payment(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create(['merchant_id' => $user->id]);

        PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => 'pending',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => 'pending',
        ]);
    }

    public function test_billing_service_resolves_global_rule_and_creates_ledger(): void
    {
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
            'config_json' => ['webhook_secret' => 'x'],
        ]);

        $payment = Payment::factory()->create([
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'amount' => 500,
            'status' => 'paid',
            'paid_at' => now(),
            'platform_fee' => null,
            'net_amount' => null,
        ]);
        $this->assertFalse($payment->platformFee()->exists());

        app(BillingPlatformFeeService::class)->record($payment);

        $payment->refresh();
        $this->assertSame('7.50', (string) $payment->platform_fee); // 500 * 1.5% (from seeded global rule)
        $this->assertSame('492.50', (string) $payment->net_amount);
        $ledger = $payment->platformFee;
        $this->assertNotNull($ledger);
        $this->assertSame('500.00', (string) $ledger->gross_amount);
        $this->assertSame('7.50', (string) $ledger->fee_amount);
        $this->assertSame('492.50', (string) $ledger->net_amount);
    }

    public function test_billing_service_skips_when_ledger_already_exists(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create([
            'merchant_id' => $user->id,
            'amount' => 100,
        ]);
        PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => 'posted',
        ]);
        $payment->platform_fee = null;
        $payment->net_amount = null;
        $payment->save();

        app(BillingPlatformFeeService::class)->record($payment);

        $payment->refresh();
        $this->assertNull($payment->platform_fee);
        $this->assertNull($payment->net_amount);
        $this->assertSame(1, $payment->platformFee()->count());
    }

    public function test_platform_fee_reversal_marks_reversed_and_stores_reason(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create(['merchant_id' => $user->id]);
        $fee = PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => 'posted',
        ]);

        app(BillingPlatformFeeService::class)->reverseForPayment($payment, 'Payment refunded by merchant');

        $fee->refresh();
        $this->assertSame(PlatformFeeStatus::Reversed, $fee->status);
        $this->assertSame('Payment refunded by merchant', $fee->reversal_reason);
        $this->assertNotNull($fee->reversed_at);
    }

    public function test_platform_fee_reversal_idempotent_when_already_reversed(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create(['merchant_id' => $user->id]);
        $fee = PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => 'reversed',
            'reversal_reason' => 'First reversal',
            'reversed_at' => now()->subHour(),
        ]);

        app(BillingPlatformFeeService::class)->reverseForPayment($payment, 'Second reason');

        $fee->refresh();
        $this->assertSame(PlatformFeeStatus::Reversed, $fee->status);
        $this->assertSame('First reversal', $fee->reversal_reason);
    }

    public function test_platform_fee_reversal_no_op_when_no_ledger(): void
    {
        $payment = Payment::factory()->paid()->create([
            'platform_fee' => null,
            'net_amount' => null,
        ]);
        $this->assertFalse($payment->platformFee()->exists());

        app(BillingPlatformFeeService::class)->reverseForPayment($payment, 'Refund');

        $this->assertFalse($payment->platformFee()->exists());
    }

    public function test_platform_fee_financial_fields_are_immutable(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create(['merchant_id' => $user->id]);
        $fee = PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => PlatformFeeStatus::Posted,
        ]);

        $fee->update([
            'gross_amount' => 999,
            'fee_amount' => 1,
            'net_amount' => 998,
        ]);

        $fee->refresh();
        $this->assertSame('100.00', (string) $fee->gross_amount);
        $this->assertSame('0.50', (string) $fee->fee_amount);
        $this->assertSame('99.50', (string) $fee->net_amount);
    }

    public function test_platform_fee_status_can_only_transition_posted_to_reversed(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create(['merchant_id' => $user->id]);
        $fee = PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => PlatformFeeStatus::Reversed,
            'reversal_reason' => 'Refunded',
            'reversed_at' => now(),
        ]);

        $fee->update(['status' => PlatformFeeStatus::Posted]);

        $fee->refresh();
        $this->assertSame(PlatformFeeStatus::Reversed, $fee->status);
    }

    public function test_observer_reverses_platform_fee_when_payment_status_changes_to_refunded(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create(['merchant_id' => $user->id]);
        $fee = PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => PlatformFeeStatus::Posted,
        ]);

        $payment->update(['status' => 'refunded']);

        $fee->refresh();
        $this->assertSame(PlatformFeeStatus::Reversed, $fee->status);
        $this->assertSame('Payment refunded', $fee->reversal_reason);
        $this->assertNotNull($fee->reversed_at);
    }

    public function test_observer_reverses_platform_fee_when_payment_status_changes_to_failed_after_paid(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create(['merchant_id' => $user->id]);
        $fee = PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->id,
            'gateway_code' => 'coins',
            'gross_amount' => 100,
            'fee_rate' => 0.005,
            'fee_amount' => 0.50,
            'net_amount' => 99.50,
            'status' => PlatformFeeStatus::Posted,
        ]);

        $payment->update(['status' => 'failed_after_paid']);

        $fee->refresh();
        $this->assertSame(PlatformFeeStatus::Reversed, $fee->status);
        $this->assertSame('Failed after paid', $fee->reversal_reason);
        $this->assertNotNull($fee->reversed_at);
    }
}
