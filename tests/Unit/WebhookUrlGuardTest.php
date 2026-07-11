<?php

namespace Tests\Unit;

use App\Support\WebhookUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebhookUrlGuardTest extends TestCase
{
    #[DataProvider('allowedUrlsProvider')]
    public function test_allows_public_webhook_urls(string $url): void
    {
        $this->assertTrue(WebhookUrlGuard::isAllowed($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedUrlsProvider(): array
    {
        return [
            'https merchant callback' => ['https://merchant.example/webhooks'],
            'https with port and path' => ['https://api.merchant.example:8443/v1/payments/callback?source=gh'],
        ];
    }

    #[DataProvider('blockedUrlsProvider')]
    public function test_blocks_internal_webhook_urls(string $url): void
    {
        $this->assertFalse(WebhookUrlGuard::isAllowed($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedUrlsProvider(): array
    {
        return [
            'localhost' => ['http://localhost/webhook'],
            'loopback ip' => ['http://127.0.0.1/webhook'],
            'metadata endpoint' => ['http://169.254.169.254/latest/meta-data'],
            'private network' => ['http://10.0.0.5/webhook'],
            'file scheme' => ['file:///etc/passwd'],
        ];
    }
}
