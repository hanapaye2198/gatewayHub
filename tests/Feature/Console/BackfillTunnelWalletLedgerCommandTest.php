<?php

namespace Tests\Feature\Console;

use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillTunnelWalletLedgerCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('surepay.features.wallet_settlement', true);
    }

    public function test_command_backfills_missing_tunnel_wallet_entries_for_paid_payment(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        $payment = Payment::factory()->for($merchant)->paid()->create([
            'gateway_code' => 'coins',
            'currency' => 'PHP',
        ]);

        $this->assertSame(0, WalletTransaction::query()->count());

        $this->artisan('wallet:backfill-surepay-ledger --limit=10')
            ->assertExitCode(0);

        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'direction' => 'credit',
        ]);
    }

    public function test_command_dry_run_does_not_create_wallet_entries(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);
        Payment::factory()->for($merchant)->paid()->create([
            'gateway_code' => 'coins',
            'currency' => 'PHP',
        ]);

        $this->artisan('wallet:backfill-surepay-ledger --limit=10 --dry-run')
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        $this->assertSame(0, WalletTransaction::query()->count());
    }
}
