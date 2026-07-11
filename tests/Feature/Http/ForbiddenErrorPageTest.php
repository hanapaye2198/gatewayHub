<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForbiddenErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_is_redirected_from_admin_to_dashboard(): void
    {
        $merchant = User::factory()->create();

        $response = $this->actingAs($merchant)->get(route('admin.index'));

        $response->assertRedirect(url('/dashboard'));
    }

    public function test_admin_is_redirected_from_merchant_dashboard_to_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertRedirect(route('admin.index'));
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
