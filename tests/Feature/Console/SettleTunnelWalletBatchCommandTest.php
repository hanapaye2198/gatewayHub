<?php

namespace Tests\Feature\Console;

use App\Models\MerchantWalletSetting;
use App\Models\Payment;
use App\Models\SurepayBatchSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettleTunnelWalletBatchCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('surepay.features.wallet_settlement', true);
    }

    public function test_scheduled_settlement_runs_when_interval_is_due(): void
    {
        $merchant = User::factory()->create();
        $payment = Payment::factory()->for($merchant->merchant)->paid()->create([
            'gateway_code' => 'coins',
            'currency' => 'PHP',
            'amount' => 1000,
            'platform_fee' => 20,
            'net_amount' => 980,
        ]);

        app(WalletSettlementService::class)->recordPaidPayment($payment);

        $this->artisan('wallet:settle-surepay-batch --scheduled --limit=200')
            ->expectsOutputToContain('Batch settlement completed.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
            'is_settled' => true,
        ]);

        $setting = SurepayBatchSetting::query()->first();
        $this->assertNotNull($setting);
        $this->assertNotNull($setting->last_batch_settled_at);
    }

    public function test_scheduled_settlement_skips_when_interval_has_not_elapsed(): void
    {
        $merchant = User::factory()->create();
        $payment = Payment::factory()->for($merchant->merchant)->paid()->create([
            'gateway_code' => 'coins',
            'currency' => 'PHP',
            'amount' => 1000,
            'platform_fee' => 20,
            'net_amount' => 980,
        ]);

        app(WalletSettlementService::class)->recordPaidPayment($payment);

        SurepayBatchSetting::query()->create([
            'batch_interval_minutes' => 60,
            'batch_interval_seconds' => 30,
            'tax_percentage' => 0,
            'tax_absolute_value' => 0,
            'last_batch_settled_at' => now(),
            'updated_by' => null,
        ]);

        $this->artisan('wallet:settle-surepay-batch --scheduled --limit=200')
            ->expectsOutputToContain('Batch settlement skipped: configured SurePay sending interval has not elapsed yet.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'is_settled' => false,
        ]);
    }

    public function test_manual_settlement_ignores_interval_gate(): void
    {
        $merchant = User::factory()->create();
        $payment = Payment::factory()->for($merchant->merchant)->paid()->create([
            'gateway_code' => 'coins',
            'currency' => 'PHP',
            'amount' => 1000,
            'platform_fee' => 20,
            'net_amount' => 980,
        ]);

        app(WalletSettlementService::class)->recordPaidPayment($payment);

        SurepayBatchSetting::query()->create([
            'batch_interval_minutes' => 60,
            'batch_interval_seconds' => 30,
            'tax_percentage' => 0,
            'tax_absolute_value' => 0,
            'last_batch_settled_at' => now(),
            'updated_by' => null,
        ]);

        $this->artisan('wallet:settle-surepay-batch --limit=200')
            ->expectsOutputToContain('Batch settlement completed.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
            'is_settled' => true,
        ]);
    }

    public function test_manual_settlement_skips_merchants_with_auto_settle_disabled(): void
    {
        $merchant = User::factory()->create();
        $payment = Payment::factory()->for($merchant->merchant)->paid()->create([
            'gateway_code' => 'coins',
            'currency' => 'PHP',
            'amount' => 1000,
            'platform_fee' => 20,
            'net_amount' => 980,
        ]);

        app(WalletSettlementService::class)->recordPaidPayment($payment);

        MerchantWalletSetting::query()->updateOrCreate(
            ['merchant_id' => $merchant->id],
            [
                'tunnel_wallet_enabled' => true,
                'auto_settle_to_real_wallet' => false,
                'default_currency' => 'PHP',
            ]
        );

        $this->artisan('wallet:settle-surepay-batch --limit=200')
            ->expectsOutputToContain('Batch settlement completed.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'is_settled' => false,
        ]);
    }
}
