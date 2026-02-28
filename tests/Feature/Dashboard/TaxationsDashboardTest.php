<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TaxationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_taxations_dashboard_route_is_removed(): void
    {
        $this->assertFalse(Route::has('dashboard.taxations'));
    }

    public function test_guest_gets_not_found_for_removed_taxations_path(): void
    {
        $response = $this->get('/dashboard/taxations');
        $response->assertNotFound();
    }

    public function test_authenticated_merchant_gets_not_found_for_removed_taxations_path(): void
    {
        $merchant = User::factory()->create();

        $response = $this->actingAs($merchant)->get('/dashboard/taxations');
        $response->assertNotFound();
    }
}
