<?php

namespace Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

class TunnelWalletDemoFlowTest extends TestCase
{
    public function test_demo_flow_section_is_not_rendered_in_admin_tunnel_wallet_ui(): void
    {
        $viewPath = dirname(__DIR__, 3).'/resources/views/admin/tunnel-wallets/index.blade.php';
        $view = file_get_contents($viewPath);
        $this->assertIsString($view);
        $this->assertStringNotContainsString('Demo Flow', $view);
        $this->assertStringNotContainsString(
            'This demo shows how customer payments pass through tunnel wallet accounting before net settlement reaches each merchant real wallet.',
            $view
        );
        $this->assertStringNotContainsString('1. Customer Pays', $view);
        $this->assertStringNotContainsString('2. Gross Credited to Tunnel', $view);
        $this->assertStringNotContainsString('3. Tax Deduction', $view);
        $this->assertStringNotContainsString('4. Net Held in Tunnel', $view);
        $this->assertStringNotContainsString('5. Batch Settlement Run', $view);
        $this->assertStringNotContainsString('6. Net to Real Wallet', $view);
    }
}
