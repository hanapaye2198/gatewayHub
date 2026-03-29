<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Track payments collected through Coins dynamic QR.');
        $response->assertSee('Create Payment');
        $response->assertDontSee('View payment history');
        $response->assertDontSee('Manage your API key');
        $response->assertDontSee('Review gateway availability');
        $response->assertDontSee('Use the sidebar to navigate');
        $response->assertDontSee('configure gateways');
        $response->assertDontSee('Repository');
        $response->assertDontSee('Documentation');
    }

    public function test_dashboard_displays_total_collections_for_logged_in_merchant(): void
    {
        $merchant = User::factory()->create();
        $otherMerchant = User::factory()->create();

        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'paid',
            'amount' => 120.50,
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'paid',
            'amount' => 30.25,
        ]);
        Payment::factory()->create([
            'merchant_id' => $merchant->id,
            'status' => 'pending',
            'amount' => 999.99,
        ]);
        Payment::factory()->create([
            'merchant_id' => $otherMerchant->id,
            'status' => 'paid',
            'amount' => 500,
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Paid collections');
        $response->assertSee('PHP 150.75');
    }
}
