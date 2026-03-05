<?php

namespace Tests\Unit\Services\Coins;

use App\Services\Coins\CoinsGenerateQrSigner;
use App\Services\Gateways\Exceptions\CoinsApiException;
use PHPUnit\Framework\TestCase;

class CoinsGenerateQrSignerTest extends TestCase
{
    private CoinsGenerateQrSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new CoinsGenerateQrSigner;
    }

    public function test_sign_raw_json_is_deterministic_with_fixed_payload_and_timestamp(): void
    {
        $body = [
            'requestId' => 'REQ-123',
            'amount' => '100',
            'currency' => 'PHP',
        ];
        $jsonBody = $this->signer->encodeJsonBody($body);

        $result1 = $this->signer->sign($body, 'secret', '1700000000000', CoinsGenerateQrSigner::STRATEGY_RAW_JSON, $jsonBody);
        $result2 = $this->signer->sign($body, 'secret', '1700000000000', CoinsGenerateQrSigner::STRATEGY_RAW_JSON, $jsonBody);

        $this->assertSame($jsonBody, $result1['canonical']);
        $this->assertSame($result1['signature'], $result2['signature']);
    }

    public function test_sign_kv_sorted_with_timestamp_builds_expected_canonical_payload(): void
    {
        $body = [
            'source' => 'GATEWAYHUB',
            'requestId' => 'REQ-123',
            'amount' => '100',
        ];

        $result = $this->signer->sign(
            $body,
            'secret',
            '1700000000000',
            CoinsGenerateQrSigner::STRATEGY_KV_SORTED_WITH_TIMESTAMP
        );

        $this->assertSame(
            'amount=100&requestId=REQ-123&source=GATEWAYHUB&timestamp=1700000000000',
            $result['canonical']
        );
    }

    public function test_sign_kv_input_order_with_timestamp_preserves_input_order(): void
    {
        $body = [
            'requestId' => 'REQ-123',
            'source' => 'GATEWAYHUB',
            'amount' => '100',
        ];

        $result = $this->signer->sign(
            $body,
            'secret',
            '1700000000000',
            CoinsGenerateQrSigner::STRATEGY_KV_INPUT_ORDER_WITH_TIMESTAMP
        );

        $this->assertSame(
            'requestId=REQ-123&source=GATEWAYHUB&amount=100&timestamp=1700000000000',
            $result['canonical']
        );
    }

    public function test_sign_throws_when_secret_is_empty(): void
    {
        $this->expectException(CoinsApiException::class);
        $this->expectExceptionMessage('non-empty API secret');

        $this->signer->sign(
            ['requestId' => 'REQ-123'],
            '',
            '1700000000000',
            CoinsGenerateQrSigner::STRATEGY_RAW_JSON
        );
    }
}
