<?php

namespace Tests\Unit\Services\Gateways;

use App\Services\Gateways\Drivers\GcashDriver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class GcashDriverTest extends TestCase
{
    public function test_required_config_keys_include_provider_mode(): void
    {
        $required = GcashDriver::getRequiredConfigKeys();

        $this->assertContains('provider_mode', $required);
        $this->assertContains('client_id', $required);
        $this->assertContains('client_secret', $required);
    }

    public function test_verify_webhook_returns_true_for_valid_signature(): void
    {
        $payload = json_encode(['referenceId' => 'ORDER-123', 'status' => 'paid']);
        $secret = 'gcash-webhook-secret';
        $signature = hash_hmac('sha256', (string) $payload, $secret);

        $request = Request::create('/api/webhooks?provider=gcash', 'POST', [], [], [], [], (string) $payload);
        $request->headers->set('x-gcash-signature', $signature);

        $driver = new GcashDriver(['webhook_key' => $secret]);

        $this->assertTrue($driver->verifyWebhook($request));
    }

    public function test_verify_webhook_returns_false_for_invalid_signature(): void
    {
        $payload = json_encode(['referenceId' => 'ORDER-123', 'status' => 'paid']);
        $secret = 'gcash-webhook-secret';

        $request = Request::create('/api/webhooks?provider=gcash', 'POST', [], [], [], [], (string) $payload);
        $request->headers->set('x-gcash-signature', 'invalid-signature');

        $driver = new GcashDriver(['webhook_key' => $secret]);

        $this->assertFalse($driver->verifyWebhook($request));
    }

    public function test_verify_webhook_returns_false_when_secret_missing(): void
    {
        $payload = json_encode(['referenceId' => 'ORDER-123', 'status' => 'paid']);
        $request = Request::create('/api/webhooks?provider=gcash', 'POST', [], [], [], [], (string) $payload);
        $request->headers->set('x-gcash-signature', 'anything');

        $driver = new GcashDriver([]);

        $this->assertFalse($driver->verifyWebhook($request));
    }
}
