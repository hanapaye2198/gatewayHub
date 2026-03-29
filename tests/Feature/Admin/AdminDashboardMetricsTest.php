<?php

namespace Tests\Feature\Admin;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_displays_required_surepay_admin_sections_and_total_collections(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant1 = User::factory()->create(['is_active' => true]);
        $merchant2 = User::factory()->create(['is_active' => true]);
        $merchant3 = User::factory()->create(['is_active' => false]);

        Payment::factory()->for($merchant1->merchant)->paid()->create(['amount' => 1000]);
        Payment::factory()->for($merchant1->merchant)->paid()->create(['amount' => 2000]);
        Payment::factory()->for($merchant2->merchant)->paid()->create(['amount' => 500]);
        Payment::factory()->for($merchant3->merchant)->create(['amount' => 900, 'status' => 'pending']);

        $response = $this->actingAs($admin)->get(route('admin.index'));

        $response->assertOk();
        $response->assertSee('Total Collections');
        $response->assertSee('PHP 3,500.00', false);
        $response->assertSee($merchant1->name);
        $response->assertSee($merchant2->name);
        $response->assertSee($merchant3->name);
        $response->assertSee('Configure Gateways');
        $response->assertDontSee('Platform Revenue');
        $response->assertDontSee('Net Volume');
        $response->assertDontSee('Revenue by Gateway');
        $response->assertDontSee('Total Payments');
    }

    public function test_admin_dashboard_filters_total_collections_per_client(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant1 = User::factory()->create(['is_active' => true]);
        $merchant2 = User::factory()->create(['is_active' => true]);

        Payment::factory()->for($merchant1->merchant)->paid()->create(['amount' => 1000]);
        Payment::factory()->for($merchant1->merchant)->paid()->create(['amount' => 2000]);
        Payment::factory()->for($merchant2->merchant)->paid()->create(['amount' => 500]);

        $response = $this->actingAs($admin)->get(route('admin.index', ['client_id' => $merchant1->merchant_id]));

        $response->assertOk();
        $response->assertSee($merchant1->name);
        $response->assertSee('PHP 3,000.00', false);
    }
}
