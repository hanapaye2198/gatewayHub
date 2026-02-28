<?php

namespace App\Services\PayPal;

use App\Services\Gateways\PlatformGatewayConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Verifies PayPal webhook signatures via the PayPal verify-webhook-signature API.
 *
 * Requires PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET, PAYPAL_WEBHOOK_ID in config.
 */
class PayPalWebhookSignatureVerifier
{
    public function __construct(
        private readonly PlatformGatewayConfigService $platformGatewayConfigService
    ) {}

    private function getBaseUrl(string $mode): string
    {
        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Verify the webhook signature using PayPal's API.
     *
     * @param  array<string, mixed>  $payload  Decoded webhook body.
     */
    public function verify(Request $request, array $payload): bool
    {
        return $this->resolveVerificationContext($request, $payload)['verified'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{verified: bool, merchant_ids: list<int>}
     */
    public function resolveVerificationContext(Request $request, array $payload): array
    {
        $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID');
        $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME');
        $certUrl = $request->header('PAYPAL-CERT-URL');
        $authAlgo = $request->header('PAYPAL-AUTH-ALGO');
        $transmissionSig = $request->header('PAYPAL-TRANSMISSION-SIG');

        if (! $transmissionId || ! $transmissionTime || ! $certUrl || ! $authAlgo || ! $transmissionSig) {
            return ['verified' => false, 'merchant_ids' => []];
        }

        $platformConfig = $this->platformGatewayConfigService->forGatewayCode('paypal');
        $credentials = [
            'webhook_id' => (string) ($platformConfig['webhook_id'] ?? ''),
            'client_id' => (string) ($platformConfig['client_id'] ?? ''),
            'client_secret' => (string) ($platformConfig['client_secret'] ?? ''),
            'mode' => (string) ($platformConfig['api_base'] ?? 'sandbox'),
        ];

        $verified = $this->verifyWithCredentials(
            (string) $transmissionId,
            (string) $transmissionTime,
            (string) $certUrl,
            (string) $authAlgo,
            (string) $transmissionSig,
            $payload,
            $credentials
        );

        return ['verified' => $verified, 'merchant_ids' => []];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{webhook_id: string, client_id: string, client_secret: string, mode: string}  $credentials
     */
    private function verifyWithCredentials(
        string $transmissionId,
        string $transmissionTime,
        string $certUrl,
        string $authAlgo,
        string $transmissionSig,
        array $payload,
        array $credentials
    ): bool {
        $webhookId = $credentials['webhook_id'];
        $clientId = $credentials['client_id'];
        $clientSecret = $credentials['client_secret'];
        $mode = $credentials['mode'];

        if ($webhookId === '' || $clientId === '' || $clientSecret === '') {
            return false;
        }

        $token = $this->getAccessToken($clientId, $clientSecret, $mode);
        if ($token === null) {
            return false;
        }

        $response = Http::withToken($token)
            ->post($this->getBaseUrl($mode).'/v1/notifications/verify-webhook-signature', [
                'transmission_id' => $transmissionId,
                'transmission_time' => $transmissionTime,
                'cert_url' => $certUrl,
                'auth_algo' => $authAlgo,
                'transmission_sig' => $transmissionSig,
                'webhook_id' => $webhookId,
                'webhook_event' => $payload,
            ]);

        if (! $response->successful()) {
            return false;
        }

        $body = $response->json();

        return ($body['verification_status'] ?? '') === 'SUCCESS';
    }

    private function getAccessToken(string $clientId, string $clientSecret, string $mode): ?string
    {
        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->getBaseUrl($mode).'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('access_token');
    }
}
