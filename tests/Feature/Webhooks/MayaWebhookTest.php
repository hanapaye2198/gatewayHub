<?php

namespace Tests\Feature\Webhooks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MayaWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_maya_webhook_route_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/api/webhooks/maya', [
            'id' => 'maya-test-event',
            'status' => 'PAYMENT_SUCCESS',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid signature.']);
    }

    public function test_webhook_ingress_rejects_maya_provider_query(): void
    {
        $response = $this->postJson('/api/webhooks?provider=maya', [
            'id' => 'maya-test-event',
            'status' => 'PAYMENT_SUCCESS',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Coins webhook ingress only. Use /api/webhooks/coins.',
        ]);
    }

    public function test_webhook_ingress_rejects_maya_signature_header_autodetection(): void
    {
        $response = $this->postJson('/api/webhooks', [
            'id' => 'maya-test-event',
            'status' => 'PAYMENT_SUCCESS',
        ], [
            'x-paymaya-signature' => 'any-signature',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Coins webhook ingress only. Use /api/webhooks/coins.',
        ]);
    }
}
