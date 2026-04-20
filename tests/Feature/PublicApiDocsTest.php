<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicApiDocsTest extends TestCase
{
    public function test_public_api_docs_is_accessible_without_authentication(): void
    {
        $this->get('/api-docs')
            ->assertOk()
            ->assertSee('GatewayHub API Documentation', false)
            ->assertSee('Integrate payments using GatewayHub APIs', false);
    }

    public function test_public_api_docs_contains_core_developer_sections(): void
    {
        $response = $this->get('/api-docs')->assertOk();

        $response->assertSee('Payment Flow', false);
        $response->assertSee('Create Payment', false);
        $response->assertSee('Get Payment Status', false);
        $response->assertSee('Webhook Handling', false);
        $response->assertSee('Security Notes', false);
        $response->assertSee('/api/payments', false);
        $response->assertSee('/api/gateways/enabled', false);
    }

    public function test_public_api_docs_does_not_leak_secrets_or_internal_routes(): void
    {
        $response = $this->get('/api-docs')->assertOk();

        $content = $response->getContent() ?: '';

        $this->assertStringNotContainsString('/dashboard', $content);
        $this->assertStringNotContainsString('/admin', $content);
        $this->assertStringNotContainsString('/onboarding', $content);
        $this->assertStringNotContainsString('webhook_secret', $content);
        $this->assertStringNotContainsString('APP_KEY', $content);
        $this->assertStringNotContainsString('APP_SECRET', $content);
    }
}
