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

    public function test_sign_stringifies_values(): void
    {
        $params = ['amount' => 100, 'flag' => true, 'timestamp' => '1700000000000'];
        $result = $this->service->sign($params, 'secret', null, true);

        $this->assertStringContainsString('amount=100', $result['canonical_string']);
        $this->assertStringContainsString('flag=1', $result['canonical_string']);
    }
}
