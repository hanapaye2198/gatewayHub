<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageBusinessDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_business_metrics_from_database(): void
    {
        Gateway::query()->delete();

        $merchantA = User::factory()->create([
        ]);
        $merchantB = User::factory()->create([
        ]);
        User::factory()->admin()->create();

        Gateway::query()->create([
            'code' => 'alpha',
            'name' => 'AlphaPay',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\CoinsDriver',
            'is_global_enabled' => true,
        ]);
        Gateway::query()->create([
            'code' => 'beta',
            'name' => 'BetaPay',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\CoinsDriver',
            'is_global_enabled' => true,
        ]);
        Gateway::query()->create([
            'code' => 'gamma',
            'name' => 'GammaPay',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\CoinsDriver',
            'is_global_enabled' => false,
        ]);
        Gateway::query()->create([
            'code' => 'paymongo',
            'name' => 'PayMongo',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\PayMongoDriver',
            'is_global_enabled' => true,
        ]);
        Gateway::query()->create([
            'code' => 'coins',
            'name' => 'Coins.ph',
            'driver_class' => 'App\\Services\\Gateways\\Drivers\\CoinsDriver',
            'is_global_enabled' => false,
        ]);

        Payment::factory()->paid()->create([
            'merchant_id' => $merchantA->id,
            'gateway_code' => 'alpha',
            'amount' => 100.00,
        ]);
        Payment::factory()->paid()->create([
            'merchant_id' => $merchantB->id,
            'gateway_code' => 'beta',
            'amount' => 250.50,
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchantA->id,
            'gateway_code' => 'gamma',
            'amount' => 999.99,
            'status' => 'pending',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSeeText('2 active');
        $response->assertSeeText('3 total');
        $response->assertSee('350.50');
        $response->assertSee('AlphaPay');
        $response->assertSee('BetaPay');
        $response->assertSee('GammaPay');
        $response->assertDontSee('Coins.ph');
        $response->assertSee('GCash');
        $response->assertDontSee('PayPal');
        $response->assertSee('KemoPay');
        $response->assertSee('Payments and operations, working together.');
        $response->assertSee('Instant propagation to merchants');
        $response->assertSee('A straightforward path from API to payment.');
        $response->assertSee('Modern K Payment App Icon.png', false);
        $response->assertDontSee('No gateways configured yet.');
    }

    public function test_home_page_shows_empty_state_when_no_business_data_exists(): void
    {
        Gateway::query()->delete();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSeeText('0 active');
        $response->assertSeeText('0 total');
        $response->assertSee('No gateways configured yet.');
        $response->assertSee('GCash');
        $response->assertSee('Try live demo');
    }

    public function test_home_page_uses_forwarded_https_scheme_for_generated_urls(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'pay.kemofi.com',
        ])->get(route('home'));

        $response->assertOk();
        $response->assertSee('content="https://pay.kemofi.com"', false);
        $response->assertSee('https://pay.kemofi.com/Modern K Payment App Icon.png', false);
    }

    public function test_home_page_routes_authenticated_admin_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('home'));

        $response->assertOk();
        $response->assertSee('/admin', false);
        $response->assertSee('Go to Dashboard');
    }

    public function test_home_page_routes_authenticated_merchant_to_dashboard(): void
    {
        $merchant = User::factory()->create();

        $response = $this->actingAs($merchant)->get(route('home'));

        $response->assertOk();
        $response->assertSee('/dashboard', false);
    }
}
