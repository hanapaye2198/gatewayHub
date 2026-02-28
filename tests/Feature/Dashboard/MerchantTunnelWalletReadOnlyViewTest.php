<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MerchantTunnelWalletReadOnlyViewTest extends TestCase
{
    public function test_tunnel_wallet_dashboard_is_db_driven_and_read_only(): void
    {
        $matches = File::glob(resource_path('views/pages/dashboard/*tunnel-wallet.blade.php'));
        $this->assertNotEmpty($matches);
        $viewPath = $matches[0];
        $contents = File::get($viewPath);

        $this->assertStringContainsString('Today Gross Collected', $contents);
        $this->assertStringContainsString('Recent Settlement Entries', $contents);
        $this->assertStringContainsString('Manage Merchant Configurations', $contents);
        $this->assertStringNotContainsString('wire:submit="saveConfiguration"', $contents);
    }
}
