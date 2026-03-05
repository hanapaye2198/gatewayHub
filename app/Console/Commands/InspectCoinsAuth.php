<?php

namespace App\Console\Commands;

use App\Services\Gateways\PlatformGatewayConfigService;
use Illuminate\Console\Command;

class InspectCoinsAuth extends Command
{
    /**
     * @var string
     */
    protected $signature = 'coins:auth:inspect';

    /**
     * @var string
     */
    protected $description = 'Inspect Coins generate_qr auth configuration and credential source without exposing secrets.';

    public function handle(PlatformGatewayConfigService $platformGatewayConfigService): int
    {
        $resolved = $platformGatewayConfigService->forGatewayCodeWithMeta('coins');
        $config = $resolved['config'];
        $credentialSource = (string) ($resolved['credential_source'] ?? 'env');

        $apiBase = (string) ($config['api_base'] ?? config('coins.gateway.api_base', 'sandbox'));
        $source = (string) ($config['source'] ?? config('coins.gateway.source', config('coins.source', 'GATEWAYHUB')));
        $strategy = (string) config('coins.auth.generate_qr.strategy', 'auto');
        $timestampUnit = (string) config('coins.auth.generate_qr.timestamp_unit', 'milliseconds');
        $signatureEncoding = (string) config('coins.auth.generate_qr.signature_encoding', 'hex_lower');
        $maxAttempts = (int) config('coins.auth.generate_qr.max_attempts', 4);

        $clientId = trim((string) ($config['client_id'] ?? $config['api_key'] ?? ''));
        $clientSecret = trim((string) ($config['client_secret'] ?? $config['api_secret'] ?? ''));
        $webhookSecret = trim((string) ($config['webhook_secret'] ?? config('coins.webhook.secret', '')));

        $this->line('Coins Auth Inspect');
        $this->line(str_repeat('-', 64));
        $this->line(sprintf('%-26s %s', 'credential_source', $credentialSource));
        $this->line(sprintf('%-26s %s', 'api_base', $apiBase));
        $this->line(sprintf('%-26s %s', 'source', $source));
        $this->line(sprintf('%-26s %s', 'strategy', $strategy));
        $this->line(sprintf('%-26s %s', 'timestamp_unit', $timestampUnit));
        $this->line(sprintf('%-26s %s', 'signature_encoding', $signatureEncoding));
        $this->line(sprintf('%-26s %d', 'max_attempts', $maxAttempts));
        $this->line(sprintf('%-26s %s', 'client_id', $clientId !== '' ? 'SET' : 'EMPTY'));
        $this->line(sprintf('%-26s %s', 'client_secret', $clientSecret !== '' ? 'SET' : 'EMPTY'));
        $this->line(sprintf('%-26s %s', 'webhook_secret', $webhookSecret !== '' ? 'SET' : 'EMPTY'));

        return self::SUCCESS;
    }
}
