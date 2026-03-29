<?php

namespace Tests\Feature\Dashboard;

use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DummyWalletDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('surepay.features.wallet_settlement', true);
    }

    public function test_admin_can_open_tunnel_wallet_dashboard_page(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.surepay-wallets.dashboard'));

        $response->assertOk();
        $response->assertSee('SurePay Settlement Dashboard');
        $response->assertSee('Manage Merchant Configurations');
        $response->assertSee('No settlement entries yet.');
    }

    public function test_merchant_cannot_open_admin_tunnel_wallet_dashboard_page(): void
    {
        $merchant = User::factory()->create();

        $response = $this->actingAs($merchant)->get(route('admin.surepay-wallets.dashboard'));

        $response->assertForbidden();
    }

    public function test_tunnel_wallet_dashboard_reads_merchant_data_from_database(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = User::factory()->create(['name' => 'Demo Merchant']);
        $payment = Payment::factory()->for($merchant->merchant)->create([
            'reference_id' => 'TW-REF-001',
            'status' => 'paid',
        ]);

        $clearingWallet = Wallet::factory()->create([
            'merchant_id' => $merchant->id,
            'wallet_type' => Wallet::TYPE_MERCHANT_CLEARING,
            'currency' => 'PHP',
            'balance' => 900,
        ]);
        Wallet::factory()->create([
            'merchant_id' => $merchant->id,
            'wallet_type' => Wallet::TYPE_MERCHANT_REAL,
            'currency' => 'PHP',
            'balance' => 450,
        ]);

        WalletTransaction::factory()->create([
            'wallet_id' => $clearingWallet->id,
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS,
            'direction' => 'credit',
            'amount' => 500,
            'currency' => 'PHP',
        ]);
        WalletTransaction::factory()->create([
            'wallet_id' => $clearingWallet->id,
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
            'direction' => 'credit',
            'amount' => 450,
            'currency' => 'PHP',
        ]);
        WalletTransaction::factory()->create([
            'wallet_id' => $clearingWallet->id,
            'payment_id' => $payment->id,
            'entry_type' => WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            'direction' => 'credit',
            'amount' => 50,
            'currency' => 'PHP',
            'is_settled' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.surepay-wallets.dashboard'));

        $response->assertOk();
        $response->assertSee('Today Gross Collected');
        $response->assertSee('500.00 PHP');
        $response->assertSee('Today Net Settled');
        $response->assertSee('450.00 PHP');
        $response->assertSee('By Merchant Settlement Data');
        $response->assertSee('900.00 PHP');
        $response->assertSee('Demo Merchant');
        $response->assertSee('TW-REF-001');
    }

    public function test_tunnel_wallet_dashboard_displays_separated_flow_logs(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant = User::factory()->create(['name' => 'Flow Merchant']);

        Payment::factory()->for($merchant->merchant)->create([
            'reference_id' => 'FLOW-REF-001',
            'status' => 'paid',
            'raw_response' => [
                'flow_logs' => [
                    'user_to_merchant' => [
                        [
                            'status' => 'success',
                            'stage' => 'gross_received',
                            'amount' => 250,
                            'currency' => 'PHP',
                            'logged_at' => now()->toIso8601String(),
                        ],
                    ],
                    'merchant_to_surepay' => [],
                    'surepay_to_merchant' => [],
                ],
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.surepay-wallets.dashboard'));

        $response->assertOk();
        $response->assertSee('Flow Logs');
        $response->assertSee('All channels');
        $response->assertSee('User to SurePay Collection Flow');
        $response->assertSee('Previous');
        $response->assertSee('Next');
        $response->assertSee('Flow Merchant');
        $response->assertSee('FLOW-REF-001');
        $response->assertSee('gross_received');
    }

    public function test_admin_can_search_filter_and_paginate_flow_logs(): void
    {
        $admin = User::factory()->admin()->create();
        $merchantA = User::factory()->create(['name' => 'Alpha Merchant']);
        $merchantB = User::factory()->create(['name' => 'Beta Merchant']);

        Payment::factory()->for($merchantA->merchant)->create([
            'reference_id' => 'FLOW-ALPHA-001',
            'status' => 'paid',
            'raw_response' => [
                'flow_logs' => [
                    'user_to_merchant' => [
                        [
                            'status' => 'success',
                            'stage' => 'gross_received',
                            'amount' => 100,
                            'currency' => 'PHP',
                            'message' => 'alpha-user-merchant',
                            'logged_at' => now()->subMinutes(3)->toIso8601String(),
                        ],
                    ],
                    'merchant_to_surepay' => [
                        [
                            'status' => 'success',
                            'stage' => 'sent_to_surepay',
                            'amount' => 95,
                            'currency' => 'PHP',
                            'message' => 'alpha-merchant-surepay',
                            'logged_at' => now()->subMinutes(2)->toIso8601String(),
                        ],
                    ],
                    'surepay_to_merchant' => [],
                ],
            ],
        ]);

        Payment::factory()->for($merchantB->merchant)->create([
            'reference_id' => 'FLOW-BETA-001',
            'status' => 'paid',
            'raw_response' => [
                'flow_logs' => [
                    'user_to_merchant' => [],
                    'merchant_to_surepay' => [],
                    'surepay_to_merchant' => [
                        [
                            'status' => 'success',
                            'stage' => 'net_settled',
                            'amount' => 90,
                            'currency' => 'PHP',
                            'message' => 'beta-surepay-merchant',
                            'logged_at' => now()->subMinute()->toIso8601String(),
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::dashboard.tunnel-wallet')
            ->assertSee('alpha-user-merchant')
            ->assertSee('alpha-merchant-surepay')
            ->assertSee('beta-surepay-merchant')
            ->set('logsSearch', 'beta-surepay-merchant')
            ->assertDontSee('alpha-user-merchant')
            ->assertDontSee('alpha-merchant-surepay')
            ->assertSee('beta-surepay-merchant')
            ->set('logsSearch', '')
            ->set('logsChannel', 'user_to_surepay_wallet')
            ->assertSee('alpha-merchant-surepay')
            ->set('logsChannel', 'all')
            ->set('logsPerPage', 1)
            ->set('logsPage', 1)
            ->assertSee('beta-surepay-merchant')
            ->assertDontSee('alpha-merchant-surepay')
            ->call('nextLogsPage')
            ->assertSee('alpha-merchant-surepay');
    }

    public function test_merchant_livewire_access_is_forbidden(): void
    {
        $merchant = User::factory()->create();
        $this->actingAs($merchant);

        Livewire::test('pages::dashboard.tunnel-wallet')->assertForbidden();
    }
}
