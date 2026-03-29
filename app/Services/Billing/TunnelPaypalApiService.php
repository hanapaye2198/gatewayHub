<?php

namespace App\Services\Billing;

use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Models\MerchantWalletSetting;
use App\Models\Payment;
use App\Services\Gateways\Exceptions\GatewayException;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TunnelPaypalApiService
{
    /**
     * @return array{provider: string, paypal_mode: string, paypal_auth_verified_at: string}
     */
    public function ensureTunnelPaypalReady(Payment $payment, MerchantWalletSetting $settings): array
    {
        $clientId = trim((string) ($settings->tunnel_client_id ?? ''));
        $clientSecret = trim((string) ($settings->tunnel_client_secret ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new GatewayException('Tunnel wallet PayPal credentials are missing.');
        }

        $mode = $this->resolveMode((int) $payment->merchant_id);
        $this->requestAccessToken($clientId, $clientSecret, $mode);

        return [
            'provider' => 'paypal',
            'paypal_mode' => $mode,
            'paypal_auth_verified_at' => now()->toIso8601String(),
        ];
    }

    private function resolveMode(int $merchantId): string
    {
        $paypalGateway = Gateway::query()->where('code', 'paypal')->first();
        if ($paypalGateway === null) {
            return 'sandbox';
        }

        $merchantGateway = MerchantGateway::query()
            ->where('merchant_id', $merchantId)
            ->where('gateway_id', $paypalGateway->id)
            ->first();

        $config = $merchantGateway?->config_json;
        if (is_array($config) && ($config['api_base'] ?? null) === 'live') {
            return 'live';
        }

        return 'sandbox';
    }

    private function requestAccessToken(string $clientId, string $clientSecret, string $mode): string
    {
        try {
            /** @var Response $response */
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->acceptJson()
                ->post($this->baseUrl($mode).'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (HttpClientException $exception) {
            throw new GatewayException('Tunnel wallet PayPal OAuth request failed: '.$exception->getMessage(), 0, $exception);
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful()) {
            $message = $body['error_description'] ?? $body['error'] ?? $response->body();
            if (! is_string($message) || $message === '') {
                $message = 'Unable to authenticate SurePay wallet PayPal credentials.';
            }

            throw new GatewayException('PayPal API error: '.$message);
        }

        $accessToken = $body['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new GatewayException('PayPal API error: access token missing for SurePay wallet.');
        }

        return $accessToken;
    }

    private function baseUrl(string $mode): string
    {
        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
