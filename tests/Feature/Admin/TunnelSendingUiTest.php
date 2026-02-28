<?php

namespace Tests\Feature\Admin;

use App\Models\SurepayBatchSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TunnelSendingUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('surepay.features.wallet_settlement', true);
    }

    public function test_admin_tunnel_wallet_page_shows_tunnel_sending_controls(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.surepay-wallets.index'));

        $response->assertOk();
        $response->assertSee('Configure Settlement Sending');
        $response->assertSee('Settlement Sending Configuration');
        $response->assertSee('id="tunnel-sending-config-modal"', false);
        $response->assertSee('id="tunnel-sending-config-form"', false);
        $response->assertSee('id="tunnel-sending-save-btn"', false);
        $response->assertDontSee('Save Changes');
        $response->assertSee('Seconds');
        $response->assertSee(route('admin.surepay-wallets.surepay-sending-setting'));
    }

    public function test_admin_can_update_tunnel_sending_controls(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.surepay-wallets.surepay-sending-setting'), [
            'batch_interval_value' => 2,
            'batch_interval_unit' => 'days',
            'tax_percentage' => 1.75,
        ]);

        $response->assertRedirect(route('admin.surepay-wallets.index'));
        $response->assertSessionHas('status', 'SurePay settlement sending configuration updated.');

        $setting = SurepayBatchSetting::query()->first();
        $this->assertNotNull($setting);
        $this->assertSame(2880, $setting->batch_interval_minutes);
        $this->assertSame(172800, $setting->batch_interval_seconds);
        $this->assertSame('1.75', (string) $setting->tax_percentage);
    }

    public function test_admin_can_set_tunnel_sending_interval_in_seconds_for_testing(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.surepay-wallets.surepay-sending-setting'), [
            'batch_interval_value' => 30,
            'batch_interval_unit' => 'seconds',
            'tax_percentage' => 0,
        ]);

        $response->assertRedirect(route('admin.surepay-wallets.index'));

        $setting = SurepayBatchSetting::query()->first();
        $this->assertNotNull($setting);
        $this->assertSame(30, $setting->batch_interval_seconds);
        $this->assertSame(1, $setting->batch_interval_minutes);
    }

    public function test_tunnel_sending_controls_validate_inputs(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.surepay-wallets.index'))
            ->patch(route('admin.surepay-wallets.surepay-sending-setting'), [
                'batch_interval_value' => 0,
                'batch_interval_unit' => 'months',
                'tax_percentage' => 110,
            ]);

        $response->assertRedirect(route('admin.surepay-wallets.index'));
        $response->assertSessionHasErrors([
            'batch_interval_value',
            'batch_interval_unit',
            'tax_percentage',
        ]);
    }
}
