<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoCheckoutTest extends TestCase
{
    public function test_demo_checkout_page_is_publicly_accessible(): void
    {
        config(['demo.api_key' => 'test-key']);
        $response = $this->get(route('demo.checkout'));

        $response->assertOk();
    }

    public function test_demo_checkout_page_shows_title_and_amount(): void
    {
        config(['demo.api_key' => 'test-key']);
        $response = $this->get(route('demo.checkout'));

        $response->assertOk();
        $response->assertSee('Demo Checkout');
        $response->assertSee('₱500');
        $response->assertSee('Pay Now');
    }

    public function test_demo_checkout_shows_config_message_when_api_key_not_set(): void
    {
        config(['demo.api_key' => null]);
        $response = $this->get(route('demo.checkout'));

        $response->assertOk();
        $response->assertSee('Demo is not configured');
        $response->assertSee('DEMO_API_KEY');
    }

    public function test_demo_checkout_shows_form_when_api_key_is_set(): void
    {
        config(['demo.api_key' => 'sk-demo-123']);
        $response = $this->get(route('demo.checkout'));

        $response->assertOk();
        $response->assertSee('Demo Checkout');
        $response->assertSee('Pay Now');
        $response->assertDontSee('Demo is not configured');
    }

    public function test_demo_checkout_does_not_require_authentication(): void
    {
        config(['demo.api_key' => 'test-key']);
        $response = $this->get(route('demo.checkout'));

        $response->assertOk();
        // Would redirect to login if auth was required
        $response->assertViewIs('demo.checkout');
    }
}
