<?php

namespace Tests\Unit\Services\Gateways;

use App\Services\Gateways\Drivers\MayaDriver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class MayaDriverTest extends TestCase
{
    public function test_required_config_keys_include_provider_mode(): void
    {
        $required = MayaDriver::getRequiredConfigKeys();

        $this->assertContains('provider_mode', $required);
        $this->assertContains('client_id', $required);
        $this->assertContains('client_secret', $required);
    }

    public function test_verify_webhook_returns_true_for_valid_signature(): void
    {
        $payload = json_encode(['id' => 'maya-evt-1', 'status' => 'PAYMENT_SUCCESS']);
        $secret = 'maya-webhook-secret';
        $signature = hash_hmac('sha256', (string) $payload, $secret);

        $request = Request::create('/api/webhooks?provider=maya', 'POST', [], [], [], [], (string) $payload);
        $request->headers->set('x-maya-signature', $signature);

        $driver = new MayaDriver(['webhook_key' => $secret]);

        $this->assertTrue($driver->verifyWebhook($request));
    }

    public function test_verify_webhook_returns_false_for_invalid_signature(): void
    {
        $payload = json_encode(['id' => 'maya-evt-1', 'status' => 'PAYMENT_SUCCESS']);
        $secret = 'maya-webhook-secret';

        $request = Request::create('/api/webhooks?provider=maya', 'POST', [], [], [], [], (string) $payload);
        $request->headers->set('x-maya-signature', 'invalid-signature');

        $driver = new MayaDriver(['webhook_key' => $secret]);

        $this->assertFalse($driver->verifyWebhook($request));
    }

    public function test_verify_webhook_returns_false_when_secret_missing(): void
    {
        $payload = json_encode(['id' => 'maya-evt-1', 'status' => 'PAYMENT_SUCCESS']);
        $request = Request::create('/api/webhooks?provider=maya', 'POST', [], [], [], [], (string) $payload);
        $request->headers->set('x-maya-signature', 'anything');

        $driver = new MayaDriver([]);

        $this->assertFalse($driver->verifyWebhook($request));
    }
}
