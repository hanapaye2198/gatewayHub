<?php

namespace Tests\Feature\Layout;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_dashboard_renders_collapsible_shell_controls(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('data-flux-sidebar-collapse', false);
        $response->assertSee('data-flux-sidebar-toggle', false);
        $response->assertSee('data-flux-header', false);
        $response->assertSee('data-app-shell-sidebar', false);
        $response->assertSee('data-app-shell-topbar', false);
        $response->assertSee('header-user-menu', false);
    }

    public function test_admin_dashboard_renders_collapsible_shell_controls(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.index'));

        $response->assertOk();
        $response->assertSee('data-flux-sidebar-collapse', false);
        $response->assertSee('data-flux-sidebar-toggle', false);
        $response->assertSee('data-flux-header', false);
        $response->assertSee('data-app-shell-sidebar', false);
        $response->assertSee(__('Admin Panel'), false);
        $response->assertDontSee(__('Back to site'), false);
        $response->assertDontSee(__('Log out'), false);
    }
}
