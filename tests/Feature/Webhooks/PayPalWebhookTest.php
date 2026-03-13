<?php

namespace Tests\Feature\Webhooks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_ingress_rejects_paypal_provider_query(): void
    {
        $response = $this->postJson('/api/webhooks?provider=paypal', [
            'id' => 'evt-paypal-test',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Coins webhook ingress only. Use /api/webhooks/coins.',
        ]);
    }

    public function test_webhook_ingress_rejects_paypal_header_autodetection(): void
    {
        $response = $this->postJson('/api/webhooks', [
            'id' => 'evt-paypal-test',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        ], [
            'PAYPAL-TRANSMISSION-ID' => 'tx-id-1',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Coins webhook ingress only. Use /api/webhooks/coins.',
        ]);
    }
}
