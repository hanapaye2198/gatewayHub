<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicApiDocsTest extends TestCase
{
    public function test_public_api_docs_is_accessible_without_authentication(): void
    {
        $this->get('/api-docs')
            ->assertOk()
            ->assertSee('KemoPay API Documentation', false)
            ->assertSee('Integrate payments using KemoPay APIs', false)
            ->assertSee('Modern K Payment App Icon.png', false)
            ->assertSee(url('/'), false)
            ->assertDontSee('gatewayhub.io', false)
            ->assertSee('KemoPay-Webhooks/1.0', false);
    }

    public function test_public_api_docs_uses_current_host_in_examples(): void
    {
        $response = $this->get('/api-docs')->assertOk();

        $content = $response->getContent() ?: '';

        $baseUrl = rtrim(url('/'), '/');

        $this->assertStringContainsString('curl -X POST '.$baseUrl.'/api/payments', $content);
        $this->assertStringContainsString('curl -X GET '.$baseUrl.'/api/payments/', $content);
        $this->assertStringNotContainsString('https://your-domain', $content);
        $this->assertStringContainsString('transaction_id', $content);
        $this->assertStringContainsString('checkout_url', $content);
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
        $response->assertSee('provider_availability.paymongo.status', false);
        $response->assertSee('payment_method_not_allowed', false);
        $response->assertSee('HTTP 422', false);
        $response->assertSee('HTTP 503', false);
        $response->assertSee('/api/wallets/balances', false);
        $response->assertSee('/api/wallets/transactions', false);
        $response->assertSee('/api/wallets/payout-institutions', false);
        $response->assertSee('/api/wallets/payouts', false);
        $response->assertSee("customer's total includes the KemoPay platform fee", false);
        $response->assertSee('PayMongo powers QR Ph, GCash, Maya, GrabPay, and ShopeePay', false);
        $response->assertSee('provider-only', false);
        $response->assertSee('submission_unknown', false);
        $response->assertSee('provider_code', false);
        $response->assertSee('409', false);
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

    public function test_public_api_docs_documents_merchant_webhook_headers_and_signing(): void
    {
        $response = $this->get('/api-docs')->assertOk();

        $content = $response->getContent() ?: '';

        $this->assertStringContainsString('X-Merchant-Timestamp', $content);
        $this->assertStringContainsString('X-Merchant-Signature', $content);
        $this->assertStringContainsString("timestamp + '.' + body", $content);
        $this->assertStringContainsString('KemoPay-Webhooks/1.0', $content);
        $this->assertStringContainsString("file_get_contents('php://input')", $content);
        $this->assertStringContainsString('hash_equals($expected, $signature)', $content);
        $this->assertStringContainsString('KEMOPAY_WEBHOOK_SECRET', $content);
        $this->assertStringContainsString('processPaymentUpdateIdempotently($data)', $content);
    }
}
