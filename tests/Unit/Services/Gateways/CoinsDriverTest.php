<?php

namespace Tests\Unit\Services\Gateways;

use App\Services\Coins\CoinsSignatureService;
use App\Services\Gateways\Drivers\CoinsDriver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class CoinsDriverTest extends TestCase
{
    public function test_verify_webhook_returns_true_for_guide_compliant_signature_without_timestamp(): void
    {
        $payload = [
            'requestId' => 'C0000000000001107',
            'referenceId' => '2007398545514304270',
            'cashInBank' => 'gcash',
            'status' => 'SUCCEEDED',
            'settleDate' => '1754038804000',
        ];
        $secret = 'coins-webhook-secret';
        $signature = (new CoinsSignatureService)->signWebhook($payload, $secret)['signature'];

        $request = Request::create('/api/webhooks/coins', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('Signature', $signature);

        $driver = new CoinsDriver(['webhook_secret' => $secret]);

        $this->assertTrue($driver->verifyWebhook($request));
    }

    public function test_verify_webhook_returns_false_for_invalid_signature(): void
    {
        $payload = [
            'requestId' => 'C0000000000001107',
            'referenceId' => '2007398545514304270',
            'status' => 'SUCCEEDED',
        ];
        $request = Request::create('/api/webhooks/coins', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('Signature', 'invalid-signature');

        $driver = new CoinsDriver(['webhook_secret' => 'coins-webhook-secret']);

        $this->assertFalse($driver->verifyWebhook($request));
    }

    public function test_verify_webhook_returns_false_when_secret_missing(): void
    {
        $payload = [
            'requestId' => 'C0000000000001107',
            'referenceId' => '2007398545514304270',
            'status' => 'SUCCEEDED',
        ];
        $request = Request::create('/api/webhooks/coins', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('Signature', 'anything');

        $driver = new CoinsDriver([]);

        $this->assertFalse($driver->verifyWebhook($request));
    }
}
