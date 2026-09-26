<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentPaidEffectsJob;
use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\MerchantGateway;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\PlatformFeeRule;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Billing\PlatformFeeService;
use App\Services\Billing\WalletSettlementService;
use App\Services\Gateways\Drivers\CoinsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdditivePaymentFeeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private array $capturedAmounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'platform.fees.convenience_fee' => 20,
            'surepay.features.wallet_settlement' => true,
        ]);

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
            'config_json' => [
                'client_id' => 'c',
                'client_secret' => 's',
                'api_base' => 'sandbox',
            ],
        ]);
    }

    public function test_global_default_adds_one_point_five_percent_and_convenience_fee_on_top(): void
    {
        $this->fakeCoins();
        $user = $this->merchant('global-key');

        $response = $this->createPayment($user, 'global-key', 1000, 'GLOBAL-1000');

        $response->assertCreated();
        $response->assertJsonPath('data.amount', 1000);
        $response->assertJsonPath('data.base_amount', 1000);
        $response->assertJsonPath('data.platform_fee_rate', 1.5);
        $response->assertJsonPath('data.platform_fee', 15);
        $response->assertJsonPath('data.convenience_fee', 20);
        $response->assertJsonPath('data.customer_total', 1035);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('1000.00', (string) $payment->amount);
        $this->assertSame('15.00', (string) $payment->platform_fee);
        $this->assertSame('0.0150', (string) $payment->platform_fee_rate);
        $this->assertSame('20.00', (string) $payment->convenience_fee);
        $this->assertSame('1035.00', (string) $payment->customer_total);
        $this->assertSame('1000.00', (string) $payment->net_amount);
        $this->assertSame('1035.00', $this->capturedAmounts[0]);
    }

    public function test_merchant_override_is_calculated_from_the_base_amount_and_sent_to_coins(): void
    {
        $this->fakeCoins();
        $user = $this->merchant('override-key');
        $this->setMerchantRate((int) $user->merchant_id, '3.00');

        $response = $this->createPayment($user, 'override-key', 1000, 'OVERRIDE-1000');

        $response->assertCreated();
        $response->assertJsonPath('data.amount', 1000);
        $response->assertJsonPath('data.platform_fee', 30);
        $response->assertJsonPath('data.platform_fee_rate', 3);
        $response->assertJsonPath('data.convenience_fee', 20);
        $response->assertJsonPath('data.customer_total', 1050);
        $this->assertNotSame(31.5, $response->json('data.platform_fee'));

        $payment = Payment::query()->where('reference_id', 'OVERRIDE-1000')->firstOrFail();
        $this->assertSame('1000.00', (string) $payment->amount);
        $this->assertSame('30.00', (string) $payment->platform_fee);
        $this->assertSame('1050.00', (string) $payment->customer_total);
        $this->assertSame('1050.00', $this->capturedAmounts[0]);

        $this->actingAs($user)
            ->get(route('dashboard.payments.show', $payment))
            ->assertOk()
            ->assertSee('Transaction Amount')
            ->assertSee('1,000.00')
            ->assertSee('Platform Fee')
            ->assertSee('30.00')
            ->assertSee('Convenience Fee')
            ->assertSee('20.00')
            ->assertSee('Customer Total')
            ->assertSee('1,050.00');
    }

    public function test_a_merchant_without_an_override_does_not_inherit_another_merchants_rate(): void
    {
        $this->fakeCoins();
        $overridden = $this->merchant('merchant-one-key');
        $plain = $this->merchant('merchant-two-key');
        $this->setMerchantRate((int) $overridden->merchant_id, '3.00');

        $this->createPayment($overridden, 'merchant-one-key', 1000, 'M1');
        $this->createPayment($plain, 'merchant-two-key', 1000, 'M2');

        $first = Payment::query()->where('reference_id', 'M1')->firstOrFail();
        $second = Payment::query()->where('reference_id', 'M2')->firstOrFail();

        $this->assertSame('30.00', (string) $first->platform_fee);
        $this->assertSame('1050.00', (string) $first->customer_total);
        $this->assertSame('15.00', (string) $second->platform_fee);
        $this->assertSame('0.0150', (string) $second->platform_fee_rate);
        $this->assertSame('1035.00', (string) $second->customer_total);
    }

    public function test_changing_the_merchant_rate_does_not_recalculate_an_existing_payment(): void
    {
        $this->fakeCoins();
        $user = $this->merchant('snapshot-key');
        $this->setMerchantRate((int) $user->merchant_id, '3.00');
        $this->createPayment($user, 'snapshot-key', 1000, 'SNAPSHOT');

        $payment = Payment::query()->where('reference_id', 'SNAPSHOT')->firstOrFail();
        $this->setMerchantRate((int) $user->merchant_id, '5.00');

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        app(PlatformFeeService::class)->record($payment->fresh());

        $payment->refresh();
        $this->assertSame('0.0300', (string) $payment->platform_fee_rate);
        $this->assertSame('30.00', (string) $payment->platform_fee);
        $this->assertSame('1050.00', (string) $payment->customer_total);
        $this->assertSame('1000.00', (string) $payment->amount);
        $this->assertSame('0.0300', (string) $payment->platformFee?->fee_rate);
        $this->assertSame('30.00', (string) $payment->platformFee?->fee_amount);
        $this->assertSame(1, $payment->platformFee()->count());
    }

    public function test_repeated_paid_processing_does_not_duplicate_fees_or_settlement(): void
    {
        $this->fakeCoins();
        $user = $this->merchant('idempotent-key');
        $this->setMerchantRate((int) $user->merchant_id, '3.00');
        $this->createPayment($user, 'idempotent-key', 1000, 'IDEMPOTENT');

        $payment = Payment::query()->where('reference_id', 'IDEMPOTENT')->firstOrFail();
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $job = new ProcessPaymentPaidEffectsJob($payment->id);
        $job->handle(app(PlatformFeeService::class), app(WalletSettlementService::class));
        $job->handle(app(PlatformFeeService::class), app(WalletSettlementService::class));

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('30.00', (string) $payment->platform_fee);
        $this->assertSame('20.00', (string) $payment->convenience_fee);
        $this->assertSame('1050.00', (string) $payment->customer_total);
        $this->assertSame(1, PlatformFee::query()->where('payment_id', $payment->id)->count());
        $this->assertSame(1, WalletTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('entry_type', WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS)
            ->count());
        $this->assertSame(1, WalletTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('entry_type', WalletTransaction::ENTRY_CONVENIENCE_FEE_COLLECTED)
            ->where('direction', 'debit')
            ->count());

        $clearing = Wallet::query()
            ->where('merchant_id', $user->merchant_id)
            ->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING)
            ->firstOrFail();
        $this->assertSame('1000.00', (string) $clearing->balance);

        $gross = WalletTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('entry_type', WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS)
            ->firstOrFail();
        $this->assertSame('1050.00', (string) $gross->amount);

        $settlement = WalletTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('entry_type', WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
            ->firstOrFail();
        $this->assertSame('1000.00', (string) $settlement->amount);
    }

    public function test_payments_created_before_the_additive_model_keep_their_original_figures(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->paid()->create([
            'merchant_id' => $user->merchant_id,
            'amount' => 1000,
            'platform_fee' => 15,
            'net_amount' => 985,
        ]);
        $ledger = PlatformFee::query()->create([
            'payment_id' => $payment->id,
            'merchant_id' => $user->merchant_id,
            'gateway_code' => 'coins',
            'gross_amount' => 1000,
            'fee_rate' => 0.015,
            'fee_amount' => 15,
            'net_amount' => 985,
            'status' => 'posted',
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin)
            ->put(route('admin.platform-fee.update'), ['percentage' => '3'])
            ->assertRedirect();

        $payment->refresh();
        $ledger->refresh();

        $this->assertNull($payment->customer_total);
        $this->assertNull($payment->convenience_fee);
        $this->assertSame('15.00', (string) $payment->platform_fee);
        $this->assertSame('985.00', (string) $payment->net_amount);
        $this->assertSame('15.00', (string) $ledger->fee_amount);
        $this->assertSame('0.0150', (string) $ledger->fee_rate);

        $this->actingAs($user)
            ->get(route('dashboard.payments'))
            ->assertOk()
            ->assertSee('985.00')
            ->assertSee('1,000.00');
    }

    public function test_only_super_admin_can_configure_global_and_merchant_platform_fees(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $merchantUser = User::factory()->create();
        $merchant = Merchant::query()->findOrFail($merchantUser->merchant_id);

        $this->actingAs($superAdmin)
            ->get(route('admin.platform-fee.edit'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->put(route('admin.platform-fee.update'), ['percentage' => '1.50'])
            ->assertRedirect(route('admin.platform-fee.edit'));

        $this->actingAs($superAdmin)
            ->put(route('admin.merchants.platform-fee.update', $merchant), ['percentage' => '3'])
            ->assertRedirect(route('admin.merchants.show', $merchant));

        $this->assertSame('3.00', PlatformFeeRule::activeMerchantPercentageRule((int) $merchant->id)?->percentage());

        $this->actingAs($admin)
            ->put(route('admin.platform-fee.update'), ['percentage' => '9'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.merchants.platform-fee.update', $merchant), ['percentage' => '9'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.merchants.show', $merchant))
            ->assertOk()
            ->assertDontSee('Save platform fee');

        $this->actingAs($merchantUser)
            ->put(route('admin.merchants.platform-fee.update', $merchant), ['percentage' => '8'])
            ->assertRedirect(url('/dashboard'));

        $this->assertSame('3.00', PlatformFeeRule::activeMerchantPercentageRule((int) $merchant->id)?->percentage());
        $this->assertSame('1.50', PlatformFeeRule::activeGlobalPercentageRule()?->percentage());
    }

    public function test_percentage_is_rounded_from_the_base_amount_for_awkward_values(): void
    {
        $service = app(PlatformFeeService::class);
        $merchant = User::factory()->create();
        $this->setMerchantRate((int) $merchant->merchant_id, '1.50');

        $cases = [
            [1000.01, 15.00],
            [999.99, 15.00],
            [0.01, 0.00],
            [1234.56, 18.52],
        ];

        foreach ($cases as [$base, $expectedFee]) {
            $quote = $service->quote($base, (int) $merchant->merchant_id, 'coins');
            $this->assertSame($expectedFee, $quote['platform_fee']);
            $this->assertSame(round($base + $expectedFee + 20, 2), $quote['customer_total']);
            $this->assertSame(round($base, 2), $quote['base_amount']);
        }

        $this->setMerchantRate((int) $merchant->merchant_id, '3.00');
        $threePercent = $service->quote(1000, (int) $merchant->merchant_id, 'coins');
        $this->assertSame(30.0, $threePercent['platform_fee']);
        $this->assertSame(1050.0, $threePercent['customer_total']);
    }

    private function fakeCoins(): void
    {
        $this->capturedAmounts = [];

        Http::fake([
            'api.9001.pl-qa.coinsxyz.me/*' => function (\Illuminate\Http\Client\Request $request) {
                $body = json_decode($request->body(), true);
                $this->capturedAmounts[] = (string) ($body['amount'] ?? '');

                return Http::response([
                    'status' => 0,
                    'data' => [
                        'orderId' => 'order-'.count($this->capturedAmounts),
                        'qrCode' => 'qr-payload',
                    ],
                ], 200);
            },
        ]);
    }

    private function merchant(string $apiKey): User
    {
        $user = User::factory()->withMerchantApiKey($apiKey)->create();

        MerchantGateway::query()->create([
            'merchant_id' => $user->merchant_id,
            'gateway_id' => Gateway::query()->where('code', 'coins')->firstOrFail()->id,
            'is_enabled' => true,
            'config_json' => [],
        ]);

        return $user;
    }

    private function createPayment(User $user, string $apiKey, float $amount, string $reference): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/payments', [
            'amount' => $amount,
            'currency' => 'PHP',
            'gateway' => 'coins',
            'reference' => $reference,
        ], [
            'Authorization' => 'Bearer '.$apiKey,
        ]);
    }

    private function setMerchantRate(int $merchantId, string $percentage): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $merchant = Merchant::query()->findOrFail($merchantId);

        $this->actingAs($superAdmin)
            ->put(route('admin.merchants.platform-fee.update', $merchant), [
                'percentage' => $percentage,
            ])
            ->assertRedirect();
    }
}
