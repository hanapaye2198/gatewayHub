<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_access_dashboard_routes(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);

        $this->actingAs($merchant);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('dashboard.payments'))->assertOk();
        $this->get(route('dashboard.api-credentials'))->assertOk();
        $this->get(route('dashboard.gateways'))->assertOk();
        $this->assertFalse(Route::has('dashboard.tunnel-wallet'));
        $this->assertFalse(Route::has('dashboard.taxations'));
        $this->assertFalse(Route::has('dashboard.tunnel-wallet-logs'));

        $payment = Payment::factory()->for($merchant)->create();
        $this->get(route('dashboard.payments.show', $payment))->assertOk();
    }

    public function test_admin_cannot_access_merchant_dashboard_routes_and_wallet_dashboard_is_disabled_by_default(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('dashboard.payments'))->assertForbidden();
        $this->get(route('dashboard.api-credentials'))->assertForbidden();
        $this->get(route('dashboard.gateways'))->assertForbidden();
        $this->get(route('admin.surepay-wallets.dashboard'))->assertNotFound();

        $payment = Payment::factory()->for(User::factory()->create())->create();
        $this->get(route('dashboard.payments.show', $payment))->assertForbidden();
    }

    public function test_deactivated_merchant_cannot_access_dashboard(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant', 'is_active' => false]);

        $this->actingAs($merchant);

        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('dashboard.payments'))->assertForbidden();
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->get(route('admin.index'))->assertOk();
        $this->get(route('admin.merchants.index'))->assertOk();
        $this->get(route('admin.gateways.index'))->assertOk();
        $this->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.surepay-wallets.index'))->assertNotFound();
        $this->get(route('admin.surepay-wallets.dashboard'))->assertNotFound();
    }

    public function test_merchant_cannot_access_admin_routes(): void
    {
        $merchant = User::factory()->create(['role' => 'merchant']);

        $this->actingAs($merchant);

        $this->get(route('admin.index'))->assertForbidden();
        $this->get(route('admin.merchants.index'))->assertForbidden();
        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->get(route('admin.surepay-wallets.index'))->assertForbidden();
        $this->get(route('admin.surepay-wallets.dashboard'))->assertForbidden();
    }

    public function test_new_users_default_to_merchant_role(): void
    {
        $user = User::factory()->create();
        $this->assertSame('merchant', $user->role);
    }
}
