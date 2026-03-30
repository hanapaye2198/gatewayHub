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

    public function test_verify_webhook_falls_back_to_client_secret_when_webhook_secret_missing(): void
    {
        $payload = [
            'requestId' => 'C0000000000001108',
            'referenceId' => '2007398545514304271',
            'status' => 'SUCCEEDED',
            'settleDate' => '1754038804000',
        ];
        $secret = 'coins-client-secret';
        $signature = (new CoinsSignatureService)->signWebhook($payload, $secret)['signature'];

        $request = Request::create('/api/webhooks/coins', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('Signature', $signature);

        $driver = new CoinsDriver([
            'client_id' => 'coins-client-id',
            'client_secret' => $secret,
            'api_base' => 'sandbox',
        ]);

        $this->assertTrue($driver->verifyWebhook($request));
    }

    public function test_verify_webhook_accepts_raw_payload_signature(): void
    {
        $rawPayload = '{"amount":"1","settleDate":"1774841898000","senderBic":"","userId":"6","referenceId":"2181934522336370231","errorMsg":"success","senderName":"","senderNumber":"","referenceNumber":"","requestId":"GH-6-01KMYE1RG5HW6MZNZ6K6FJG8WK","cashInBank":"GCash","channelInvoiceNo":"251598","createDate":"1774842864000","status":"SUCCEEDED"}';
        $signature = (new CoinsSignatureService)->signRawPayload($rawPayload, 'coins-webhook-secret')['signature'];

        $request = Request::create('/api/webhooks/coins', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $rawPayload);
        $request->headers->set('Signature', $signature);

        $driver = new CoinsDriver(['webhook_secret' => 'coins-webhook-secret']);

        $this->assertTrue($driver->verifyWebhook($request));
    }
}
