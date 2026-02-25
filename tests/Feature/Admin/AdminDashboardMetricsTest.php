<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformFeeStatus;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_displays_metrics(): void
    {
        $admin = User::factory()->admin()->create();
        $merchant1 = User::factory()->create(['role' => 'merchant', 'is_active' => true]);
        $merchant2 = User::factory()->create(['role' => 'merchant', 'is_active' => true]);
        $merchant3 = User::factory()->create(['role' => 'merchant', 'is_active' => false]);

        $payment1 = Payment::factory()->for($merchant1)->paid()->create(['amount' => 1000]);
        $payment2 = Payment::factory()->for($merchant1)->paid()->create(['amount' => 2000]);
        $payment3 = Payment::factory()->for($merchant2)->paid()->create(['amount' => 500]);

        PlatformFee::query()->create([
            'payment_id' => $payment1->id,
            'merchant_id' => $merchant1->id,
            'gateway_code' => 'coins',
            'gross_amount' => 1000,
            'fee_rate' => 0.005,
            'fee_amount' => 5,
            'net_amount' => 995,
            'status' => PlatformFeeStatus::Posted,
        ]);
        PlatformFee::query()->create([
            'payment_id' => $payment2->id,
            'merchant_id' => $merchant1->id,
            'gateway_code' => 'coins',
            'gross_amount' => 2000,
            'fee_rate' => 0.005,
            'fee_amount' => 10,
            'net_amount' => 1990,
            'status' => PlatformFeeStatus::Posted,
        ]);
        PlatformFee::query()->create([
            'payment_id' => $payment3->id,
            'merchant_id' => $merchant2->id,
            'gateway_code' => 'coins',
            'gross_amount' => 500,
            'fee_rate' => 0.005,
            'fee_amount' => 2.50,
            'net_amount' => 497.50,
            'status' => PlatformFeeStatus::Posted,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.index'));

        $response->assertOk();
        $response->assertSee('3', false); // Total Payments
        $response->assertSee('3,500.00', false); // Total Payment Volume (1000+2000+500)
        $response->assertSee('17.50', false); // Platform Revenue (5+10+2.50)
        $response->assertSee('2', false); // Active Merchants (merchant1, merchant2)
    }
}
