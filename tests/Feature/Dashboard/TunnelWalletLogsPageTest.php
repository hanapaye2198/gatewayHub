<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TunnelWalletLogsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tunnel_wallet_logs_route_is_not_registered_for_merchants(): void
    {
        $this->assertFalse(Route::has('dashboard.tunnel-wallet-logs'));
    }

    public function test_removed_tunnel_wallet_logs_path_returns_not_found_for_merchant(): void
    {
        $merchant = User::factory()->create();

        $response = $this->actingAs($merchant)->get('/dashboard/tunnel-wallet-logs');

        $response->assertNotFound();
    }
}
