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
        $response->assertSee('2 active');
        $response->assertSee('3 total');
        $response->assertSee('PHP 350.50');
        $response->assertSee('AlphaPay');
        $response->assertSee('BetaPay');
        $response->assertSee('GammaPay');
        $response->assertDontSee('No gateways configured yet.');
        $response->assertDontSee('No gateways available yet.');
    }

    public function test_home_page_shows_empty_state_when_no_business_data_exists(): void
    {
        Gateway::query()->delete();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('0 active');
        $response->assertSee('0 total');
        $response->assertSee('PHP 0.00');
        $response->assertSee('No gateways configured yet.');
        $response->assertSee('No gateways available yet.');
    }
}
