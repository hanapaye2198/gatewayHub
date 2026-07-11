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
        $merchant = User::factory()->create();

        $this->actingAs($merchant);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('dashboard.payments'))->assertOk();
        $this->get(route('dashboard.api-credentials'))->assertOk();
        $this->get(route('dashboard.gateways'))->assertOk();
        $this->assertFalse(Route::has('dashboard.tunnel-wallet'));
        $this->assertFalse(Route::has('dashboard.taxations'));
        $this->assertFalse(Route::has('dashboard.tunnel-wallet-logs'));

        $payment = Payment::factory()->for($merchant->merchant)->create();
        $this->get(route('dashboard.payments.show', $payment))->assertOk();
    }

    public function test_admin_cannot_access_merchant_dashboard_routes_and_wallet_dashboard_is_disabled_by_default(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->get(route('dashboard'))->assertRedirect(route('admin.index'));
        $this->get(route('dashboard.payments'))->assertRedirect(route('admin.index'));
        $this->get(route('dashboard.api-credentials'))->assertRedirect(route('admin.index'));
        $this->get(route('dashboard.gateways'))->assertRedirect(route('admin.index'));
        $this->get(route('admin.surepay-wallets.dashboard'))->assertNotFound();

        $other = User::factory()->create();
        $payment = Payment::factory()->for($other->merchant)->create();
        $this->get(route('dashboard.payments.show', $payment))->assertRedirect(route('admin.index'));
    }

    public function test_deactivated_merchant_cannot_access_dashboard(): void
    {
        $merchant = User::factory()->create(['is_active' => false]);

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
        $merchant = User::factory()->create();

        $this->actingAs($merchant);

        $this->get(route('admin.index'))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.merchants.index'))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.payments.index'))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.surepay-wallets.index'))->assertRedirect(url('/dashboard'));
        $this->get(route('admin.surepay-wallets.dashboard'))->assertRedirect(url('/dashboard'));
    }

    public function test_new_users_default_to_merchant_user_role(): void
    {
        $user = User::factory()->create();
        $this->assertSame(User::ROLE_MERCHANT_USER, $user->role);
    }
}
