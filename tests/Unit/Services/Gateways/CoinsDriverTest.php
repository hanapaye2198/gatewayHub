<?php

namespace Tests\Unit\Services\Gateways;

use App\Services\Coins\CoinsSignatureService;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Exceptions\CoinsApiException;
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

    public function test_verify_webhook_accepts_uppercase_hex_signature(): void
    {
        $payload = [
            'requestId' => 'C0000000000001107',
            'referenceId' => '2007398545514304270',
            'cashInBank' => 'gcash',
            'status' => 'SUCCEEDED',
            'settleDate' => '1754038804000',
        ];
        $secret = 'coins-webhook-secret';
        $signature = strtoupper((new CoinsSignatureService)->signWebhook($payload, $secret)['signature']);

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

    public function test_verify_webhook_throws_when_secret_missing(): void
    {
        $payload = [
            'requestId' => 'C0000000000001107',
            'referenceId' => '2007398545514304270',
            'status' => 'SUCCEEDED',
        ];
        $request = Request::create('/api/webhooks/coins', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('Signature', 'anything');

        $driver = new CoinsDriver([]);

        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('webhook_secret is not configured');

        $driver->verifyWebhook($request);
    }

    public function test_verify_webhook_does_not_fall_back_to_client_secret_when_webhook_secret_missing(): void
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

        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('webhook_secret is not configured');

        $driver->verifyWebhook($request);
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

    public function test_verify_webhook_accepts_documented_qrph_subset_signature_with_live_payload_extras(): void
    {
        $payload = [
            'amount' => '1',
            'settleDate' => '1774841898000',
            'senderBic' => '',
            'userId' => '6',
            'referenceId' => '2181934522336370231',
            'errorMsg' => 'success',
            'senderName' => '',
            'senderNumber' => '',
            'referenceNumber' => '',
            'requestId' => 'GH-6-01KMYE1RG5HW6MZNZ6K6FJG8WK',
            'cashInBank' => 'GCash',
            'channelInvoiceNo' => '251598',
            'createDate' => '1774842864000',
            'status' => 'SUCCEEDED',
        ];
        $signature = (new CoinsSignatureService)->signWebhook([
            'requestId' => 'GH-6-01KMYE1RG5HW6MZNZ6K6FJG8WK',
            'referenceId' => '2181934522336370231',
            'cashInBank' => 'GCash',
            'channelInvoiceNo' => '251598',
            'settleDate' => '1774841898000',
            'errorMsg' => 'success',
            'status' => 'SUCCEEDED',
        ], 'coins-webhook-secret')['signature'];

        $request = Request::create('/api/webhooks/coins', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('Signature', $signature);

        $driver = new CoinsDriver(['webhook_secret' => 'coins-webhook-secret']);

        $this->assertTrue($driver->verifyWebhook($request));
    }

    public function test_verify_webhook_accepts_sorted_json_signature_with_live_payload(): void
    {
        $payload = [
            'amount' => '1.000000000000000000',
            'settleDate' => '1775746470000',
            'senderBic' => 'GXCHPHM2XXX',
            'userId' => '1583222866450592768',
            'referenceId' => '2189514213746393519',
            'errorMsg' => 'success',
            'senderName' => 'ARNIEQUE AMABA',
            'senderNumber' => '09916694076',
            'referenceNumber' => '20260409GXCHPHM2XXXB000000013593043',
            'requestId' => 'GH-6-01KNSBRGMAZP6D2R7S7WK5ZDRA',
            'cashInBank' => 'GCash',
            'channelInvoiceNo' => '593043',
            'createDate' => '1775746433000',
            'status' => 'SUCCEEDED',
        ];
        $sortedPayload = $payload;
        ksort($sortedPayload, SORT_STRING);
        $signature = hash_hmac(
            'sha256',
            (string) json_encode($sortedPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'coins-webhook-secret'
        );

        $request = Request::create('/api/webhooks/coins', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $request->headers->set('Signature', $signature);

        $driver = new CoinsDriver(['webhook_secret' => 'coins-webhook-secret']);

        $this->assertTrue($driver->verifyWebhook($request));
    }
}
