<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\GatewayHub;
use App\Models\Gateway;
use App\Models\User;
use App\Services\Gateways\Drivers\PayMongoDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GatewaysCoinsModelUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gateways_page_uses_shared_paymongo_credentials_for_payment_methods(): void
    {
        $admin = User::factory()->admin()->create();
        $paymongoGateway = Gateway::query()->where('code', 'paymongo')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.gateways.index'));
        $response->assertOk();
        $response->assertSee('KemoPay offers QR Ph, GCash, Maya, GrabPay, and ShopeePay through one PayMongo account.');
        $response->assertDontSee('PayPal');
        $response->assertSee('Configure shared PayMongo credentials on the PayMongo provider row.');
        $response->assertDontSee('Coins.ph');

        Livewire::actingAs($admin)
            ->test(GatewayHub::class)
            ->call('editConfig', $paymongoGateway->id)
            ->assertSee('PayMongo Secret API Key')
            ->assertSee('PayMongo Public API Key')
            ->assertSee('PayMongo Webhook Secret');

        $html = $response->getContent();
        if (! is_string($html)) {
            $this->fail('Expected HTML response content.');
        }

        $this->assertSame(1, substr_count($html, 'Save Platform Credentials'));
        $this->assertSame(1, substr_count($html, 'wire:click="editConfig('));
    }

    public function test_gateway_bulk_action_enables_the_selected_gateway(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::query()->where('code', 'qrph')->firstOrFail();
        $gateway->update(['is_global_enabled' => false]);

        Livewire::actingAs($admin)
            ->test(GatewayHub::class)
            ->set('selectedGateways', [(string) $gateway->id])
            ->set('selectedBulkAction', 'enable_global')
            ->call('applyBulkAction')
            ->assertSet('showConfirmation', true)
            ->call('executeConfirmedAction')
            ->assertSet('selectedGateways', [])
            ->assertSet('selectedBulkAction', '');

        $this->assertTrue($gateway->fresh()->is_global_enabled);
    }

    public function test_gateway_bulk_action_reports_when_no_action_is_selected(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::query()->where('code', 'qrph')->firstOrFail();
        $gateway->update(['is_global_enabled' => false]);

        Livewire::actingAs($admin)
            ->test(GatewayHub::class)
            ->set('selectedGateways', [(string) $gateway->id])
            ->call('applyBulkAction')
            ->assertDispatched('show-error', message: 'Please choose a bulk action.')
            ->assertSet('showConfirmation', false);

        $this->assertFalse($gateway->fresh()->is_global_enabled);
    }

    public function test_gateway_methods_use_the_paymongo_driver(): void
    {
        $this->assertSame(
            PayMongoDriver::class,
            Gateway::query()->where('code', 'gcash')->value('driver_class')
        );
        $this->assertSame(
            PayMongoDriver::class,
            Gateway::query()->where('code', 'qrph')->value('driver_class')
        );
        $this->assertDatabaseMissing('gateways', ['code' => 'coins']);
    }
}
