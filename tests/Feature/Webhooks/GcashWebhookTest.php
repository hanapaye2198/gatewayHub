<?php

namespace Tests\Feature\Webhooks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GcashWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_ingress_rejects_gcash_provider_query(): void
    {
        $response = $this->postJson('/api/webhooks?provider=gcash', [
            'data' => [
                'id' => 'evt-test-gcash',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Coins webhook ingress only. Use ?provider=coins.',
        ]);
    }

    public function test_webhook_ingress_rejects_gcash_signature_header_autodetection(): void
    {
        $response = $this->postJson('/api/webhooks', [
            'data' => [
                'id' => 'evt-test-gcash',
            ],
        ], [
            'x-gcash-signature' => 'any-signature',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Coins webhook ingress only. Use ?provider=coins.',
        ]);
    }
}
