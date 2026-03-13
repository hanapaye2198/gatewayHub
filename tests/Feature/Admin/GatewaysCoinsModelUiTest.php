<?php

namespace Tests\Feature\Admin;

use App\Models\Gateway;
use App\Models\User;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Drivers\MayaDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewaysCoinsModelUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gateways_page_shows_coins_orchestrator_credentials_model(): void
    {
        $admin = User::factory()->admin()->create();

        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => CoinsDriver::class,
            'is_global_enabled' => true,
        ]);

        Gateway::query()->create([
            'code' => 'maya',
            'name' => 'Maya',
            'driver_class' => MayaDriver::class,
            'is_global_enabled' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.gateways.index'));
        $response->assertOk();
        $response->assertSee('Current model: customer-facing options (GCash, Maya, PayPal, PayQRPH) are collected through Coins dynamic QR.');
        $response->assertSee('Only Coins.ph has platform payment credentials. All customer-facing payment options share that single Coins.ph payment configuration.');
        $response->assertSee('Coins.ph Platform Configuration');
        $response->assertSee('Single payment configuration used by the Coins.ph API and every customer-facing payment option.');
        $response->assertSee('Edit Coins.ph Config');
        $response->assertSee('Uses the shared Coins.ph payment configuration.');
        $response->assertDontSee('Processed through Coins dynamic QR in current model. No direct platform credentials required.');
        $response->assertDontSee('Uses Coins config');

        $html = $response->getContent();
        if (! is_string($html)) {
            $this->fail('Expected HTML response content.');
        }

        $this->assertSame(1, substr_count($html, 'Save Platform Credentials'));
        $this->assertSame(1, substr_count($html, 'wire:click="editConfig('));
        $this->assertStringNotContainsString('>Actions</th>', $html);
    }
}
