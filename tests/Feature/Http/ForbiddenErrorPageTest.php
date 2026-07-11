<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForbiddenErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_sees_friendly_admin_forbidden_page(): void
    {
        $merchant = User::factory()->create();

        $response = $this->actingAs($merchant)->get(route('admin.index'));

        $response->assertForbidden();
        $response->assertSee(__('Access denied'));
        $response->assertSee(__('You do not have permission to access the admin panel.'));
        $response->assertSee(__('Go to merchant dashboard'));
    }

    public function test_admin_sees_friendly_merchant_dashboard_forbidden_page(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertForbidden();
        $response->assertSee(__('Access denied'));
        $response->assertSee(__('Merchant dashboard access is limited to merchant accounts.'));
        $response->assertSee(__('Go to admin panel'));
    }

    public function test_deactivated_merchant_user_sees_account_status_message(): void
    {
        $merchant = User::factory()->create();
        $merchant->forceFill(['is_active' => false])->save();

        $response = $this->actingAs($merchant->fresh())->get(route('dashboard'));

        $response->assertForbidden();
        $response->assertSee(__('Your account has been deactivated.'));
    }

    public function test_inactive_merchant_account_sees_support_message(): void
    {
        $merchant = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($merchant)->get(route('dashboard'));

        $response->assertForbidden();
        $response->assertSee(__('This merchant account is inactive. Contact support for assistance.'));
    }
}
