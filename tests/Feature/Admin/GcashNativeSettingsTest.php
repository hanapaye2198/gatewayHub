<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GcashNativeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_does_not_see_gcash_native_defaults_section(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.gateways.index'));

        $response->assertOk();
        $response->assertDontSee('GCash Native Defaults');
        $response->assertDontSee('GCash Per-Merchant Overrides');
    }

    public function test_legacy_per_merchant_gcash_settings_route_is_removed(): void
    {
        $this->assertFalse(Route::has('admin.gateways.gcash-merchant-setting'));
    }
}
