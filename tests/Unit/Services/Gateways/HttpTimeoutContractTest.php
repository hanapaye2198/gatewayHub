<?php

namespace Tests\Unit\Services\Gateways;

use App\Services\Coins\CoinsGenerateQrRequestExecutor;
use App\Services\Gateways\Drivers\CoinsDriver;
use App\Services\Gateways\Drivers\GcashDriver;
use App\Services\Gateways\Drivers\MayaDriver;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class HttpTimeoutContractTest extends TestCase
{
    /**
     * Expected timeout contract (in seconds) applied to every outbound payment
     * gateway HTTP request. Centralised here so any future driver can be
     * bound to the same guarantees.
     */
    private const EXPECTED_CONNECT_TIMEOUT_SECONDS = 3;

    private const EXPECTED_TIMEOUT_SECONDS = 10;

    /**
     * @return array<string, array{class-string}>
     */
    public static function paymentGatewayHttpCallersProvider(): array
    {
        return [
            'CoinsDriver' => [CoinsDriver::class],
            'CoinsGenerateQrRequestExecutor' => [CoinsGenerateQrRequestExecutor::class],
            'GcashDriver' => [GcashDriver::class],
            'MayaDriver' => [MayaDriver::class],
        ];
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('paymentGatewayHttpCallersProvider')]
    public function test_payment_gateway_http_caller_declares_required_timeout_constants(string $class): void
    {
        $reflection = new ReflectionClass($class);

        $this->assertTrue(
            $reflection->hasConstant('HTTP_CONNECT_TIMEOUT_SECONDS'),
            $class.' must declare an HTTP_CONNECT_TIMEOUT_SECONDS constant.'
        );
        $this->assertTrue(
            $reflection->hasConstant('HTTP_TIMEOUT_SECONDS'),
            $class.' must declare an HTTP_TIMEOUT_SECONDS constant.'
        );
        $this->assertSame(
            self::EXPECTED_CONNECT_TIMEOUT_SECONDS,
            $reflection->getConstant('HTTP_CONNECT_TIMEOUT_SECONDS'),
            $class.' must declare HTTP_CONNECT_TIMEOUT_SECONDS = 3.'
        );
        $this->assertSame(
            self::EXPECTED_TIMEOUT_SECONDS,
            $reflection->getConstant('HTTP_TIMEOUT_SECONDS'),
            $class.' must declare HTTP_TIMEOUT_SECONDS = 10.'
        );
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('paymentGatewayHttpCallersProvider')]
    public function test_every_http_call_in_payment_gateway_caller_has_timeouts_applied(string $class): void
    {
        $reflection = new ReflectionClass($class);
        $source = (string) file_get_contents((string) $reflection->getFileName());

        $httpCallCount = preg_match_all('/Http::(withHeaders|withBasicAuth|acceptJson|withBody|get|post|put|delete|send)\(/', $source);
        $connectTimeoutCount = substr_count($source, '->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)');
        $timeoutCount = substr_count($source, '->timeout(self::HTTP_TIMEOUT_SECONDS)');

        $this->assertGreaterThan(
            0,
            $httpCallCount,
            $class.' was expected to make at least one outbound HTTP call.'
        );
        $this->assertGreaterThanOrEqual(
            1,
            $connectTimeoutCount,
            $class.' must apply ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS) to its HTTP call chain.'
        );
        $this->assertGreaterThanOrEqual(
            1,
            $timeoutCount,
            $class.' must apply ->timeout(self::HTTP_TIMEOUT_SECONDS) to its HTTP call chain.'
        );
        $this->assertSame(
            $connectTimeoutCount,
            $timeoutCount,
            $class.' must pair connectTimeout() and timeout() consistently on every outbound HTTP call.'
        );
    }

    public function test_pending_request_propagates_timeouts_to_underlying_guzzle_options(): void
    {
        $pending = Http::connectTimeout(self::EXPECTED_CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::EXPECTED_TIMEOUT_SECONDS);

        $reflection = new ReflectionClass($pending);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);

        /** @var array<string, mixed> $options */
        $options = $optionsProperty->getValue($pending);

        $this->assertSame(
            self::EXPECTED_CONNECT_TIMEOUT_SECONDS,
            $options['connect_timeout'] ?? null,
            'Laravel PendingRequest::connectTimeout() must populate the Guzzle connect_timeout option.'
        );
        $this->assertSame(
            self::EXPECTED_TIMEOUT_SECONDS,
            $options['timeout'] ?? null,
            'Laravel PendingRequest::timeout() must populate the Guzzle timeout option.'
        );
    }
}
