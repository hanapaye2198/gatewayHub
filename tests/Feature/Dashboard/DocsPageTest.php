<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_access_docs_page_and_see_core_sections(): void
    {
        $merchant = User::factory()->create([
            'role' => 'merchant',
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard.docs'));

        $response->assertOk();
        $response->assertSee('Merchant Docs');
        $response->assertSee('Basic Platform Docs');
        $response->assertSee('Get Enabled Gateways');
        $response->assertSee('Create Payment');
        $response->assertSee('Error Info');
        $response->assertSee('GET');
        $response->assertSee('/api/gateways/enabled');
        $response->assertSee('POST');
        $response->assertSee('/api/payments');
        $response->assertSee('style="color:#67e8f9">"success"</span>', false);
        $response->assertSee('style="color:#6ee7b7">true</span>', false);
        $response->assertSee('style="color:#fda4af">false</span>', false);
    }

    public function test_merchant_sidebar_shows_docs_navigation_item(): void
    {
        $merchant = User::factory()->create([
            'role' => 'merchant',
        ]);

        $response = $this->actingAs($merchant)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Docs');
        $response->assertSee(route('dashboard.docs'), false);
    }

    public function test_admin_cannot_access_merchant_docs_page(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard.docs'));

        $response->assertForbidden();
    }
}
