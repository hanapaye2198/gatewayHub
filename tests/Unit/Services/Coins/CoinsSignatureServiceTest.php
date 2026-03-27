<?php

namespace Tests\Unit\Services\Coins;

use App\Services\Coins\CoinsSignatureService;
use App\Services\Gateways\Exceptions\CoinsApiException;
use PHPUnit\Framework\TestCase;

class CoinsSignatureServiceTest extends TestCase
{
    private CoinsSignatureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CoinsSignatureService;
    }

    public function test_sign_returns_signature_and_timestamp(): void
    {
        $params = ['amount' => '100', 'currency' => 'PHP'];
        $result = $this->service->sign($params, 'my-secret', 1700000000000);

        $this->assertArrayHasKey('signature', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertSame('1700000000000', $result['timestamp']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['signature']);
    }

    public function test_sign_is_deterministic_with_fixed_timestamp(): void
    {
        $params = ['amount' => '100', 'currency' => 'PHP'];
        $result1 = $this->service->sign($params, 'my-secret', 1700000000000);
        $result2 = $this->service->sign($params, 'my-secret', 1700000000000);

        $this->assertSame($result1['signature'], $result2['signature']);
        $this->assertSame($result1['timestamp'], $result2['timestamp']);
    }

    public function test_sign_includes_canonical_string_only_when_debug_requested(): void
    {
        $params = ['amount' => '100'];
        $resultWithoutDebug = $this->service->sign($params, 'my-secret', 1700000000000, false);
        $resultWithDebug = $this->service->sign($params, 'my-secret', 1700000000000, true);

        $this->assertArrayNotHasKey('canonical_string', $resultWithoutDebug);
        $this->assertArrayHasKey('canonical_string', $resultWithDebug);
        $this->assertSame('amount=100&timestamp=1700000000000', $resultWithDebug['canonical_string']);
    }

    public function test_sign_sorts_parameters_lexicographically(): void
    {
        $params = ['z' => '1', 'a' => '2', 'm' => '3', 'timestamp' => '1700000000000'];
        $result = $this->service->sign($params, 'secret', null, true);

        $this->assertSame('a=2&m=3&timestamp=1700000000000&z=1', $result['canonical_string']);
    }

    public function test_sign_throws_on_empty_secret(): void
    {
        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('non-empty API secret');

        $this->service->sign(['amount' => '100'], '');
    }

    public function test_verify_returns_true_when_signature_matches(): void
    {
        $payload = ['amount' => '100', 'currency' => 'PHP', 'timestamp' => '1700000000000'];
        $signed = $this->service->sign($payload, 'webhook-secret', 1700000000000, false);

        $this->assertTrue(
            $this->service->verify($payload, 'webhook-secret', $signed['signature'])
        );
    }

    public function test_verify_throws_on_signature_mismatch(): void
    {
        $payload = ['amount' => '100', 'timestamp' => '1700000000000'];

        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('signature mismatch');

        $this->service->verify($payload, 'webhook-secret', 'invalid-signature-hex');
    }

    public function test_verify_throws_on_empty_secret(): void
    {
        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('non-empty API secret');

        $this->service->verify(['timestamp' => '1700000000000'], '', 'abc');
    }

    public function test_verify_throws_when_timestamp_missing_in_payload(): void
    {
        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('timestamp');

        $this->service->verify(['amount' => '100'], 'secret', 'some-sig');
    }

    public function test_sign_webhook_returns_signature_without_injecting_timestamp(): void
    {
        $payload = [
            'cashInBank' => 'gcash',
            'channelInvoiceNo' => '304270',
            'errorMsg' => '',
            'referenceId' => '2007398545514304270',
            'requestId' => 'C0000000000001107',
            'settleDate' => '1754038804000',
            'status' => 'SUCCEEDED',
        ];

        $result = $this->service->signWebhook($payload, 'secret', true);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['signature']);
        $this->assertSame(
            'cashInBank=gcash&channelInvoiceNo=304270&errorMsg=&referenceId=2007398545514304270&requestId=C0000000000001107&settleDate=1754038804000&status=SUCCEEDED',
            $result['canonical_string']
        );
    }

    public function test_verify_webhook_returns_true_when_signature_matches_without_timestamp(): void
    {
        $payload = [
            'referenceId' => '2007398545514304270',
            'requestId' => 'C0000000000001107',
            'status' => 'SUCCEEDED',
            'settleDate' => '1754038804000',
        ];
        $signed = $this->service->signWebhook($payload, 'webhook-secret');

        $this->assertTrue(
            $this->service->verifyWebhook($payload, 'webhook-secret', $signed['signature'])
        );
    }

    public function test_sign_stringifies_values(): void
    {
        $params = ['amount' => 100, 'flag' => true, 'timestamp' => '1700000000000'];
        $result = $this->service->sign($params, 'secret', null, true);

        $this->assertStringContainsString('amount=100', $result['canonical_string']);
        $this->assertStringContainsString('flag=1', $result['canonical_string']);
    }

    public function test_sign_for_fiat_request_returns_signature(): void
    {
        $bodyParams = ['amount' => '100', 'currency' => 'PHP', 'requestId' => 'REF123', 'expiredSeconds' => '1800', 'source' => 'GATEWAYHUB'];
        $result = $this->service->signForFiatRequest($bodyParams, '1700000000000', 'my-secret');

        $this->assertArrayHasKey('signature', $result);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['signature']);
        $this->assertArrayNotHasKey('canonical_string', $result);
    }

    public function test_sign_for_fiat_request_builds_canonical_with_body_then_timestamp(): void
    {
        $bodyParams = ['amount' => '100', 'currency' => 'PHP', 'requestId' => 'REF123', 'expiredSeconds' => '1800', 'source' => 'GATEWAYHUB'];
        $result = $this->service->signForFiatRequest($bodyParams, '1700000000000', 'secret', true);

        $this->assertArrayHasKey('canonical_string', $result);
        $this->assertSame(
            'amount=100&currency=PHP&expiredSeconds=1800&requestId=REF123&source=GATEWAYHUB&timestamp=1700000000000',
            $result['canonical_string']
        );
    }

    public function test_sign_for_fiat_request_sorts_body_params_lexicographically(): void
    {
        $bodyParams = ['source' => 'GATEWAYHUB', 'requestId' => 'REF123', 'amount' => '100', 'currency' => 'PHP', 'expiredSeconds' => '1800'];
        $result = $this->service->signForFiatRequest($bodyParams, '1700000000000', 'secret', true);

        $this->assertSame(
            'amount=100&currency=PHP&expiredSeconds=1800&requestId=REF123&source=GATEWAYHUB&timestamp=1700000000000',
            $result['canonical_string']
        );
    }

    public function test_sign_for_fiat_request_preserves_body_order_when_sorting_disabled(): void
    {
        $bodyParams = ['requestId' => 'REF123', 'amount' => '100', 'source' => 'GATEWAYHUB'];
        $result = $this->service->signForFiatRequest($bodyParams, '1700000000000', 'secret', true, false);

        $this->assertSame(
            'requestId=REF123&amount=100&source=GATEWAYHUB&timestamp=1700000000000',
            $result['canonical_string']
        );
    }

    public function test_sign_for_fiat_request_throws_on_empty_secret(): void
    {
        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('non-empty API secret');

        $this->service->signForFiatRequest(['requestId' => 'REF'], '1700000000000', '');
    }

    public function test_sign_for_fiat_request_is_deterministic(): void
    {
        $bodyParams = ['amount' => '100', 'currency' => 'PHP', 'requestId' => 'REF123'];
        $r1 = $this->service->signForFiatRequest($bodyParams, '1700000000000', 'secret');
        $r2 = $this->service->signForFiatRequest($bodyParams, '1700000000000', 'secret');

        $this->assertSame($r1['signature'], $r2['signature']);
    }
}
