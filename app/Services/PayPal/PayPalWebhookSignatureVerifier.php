<?php

namespace App\Services\PayPal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Verifies PayPal webhook signatures via the PayPal verify-webhook-signature API.
 *
 * Requires PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET, PAYPAL_WEBHOOK_ID in config.
 */
class PayPalWebhookSignatureVerifier
{
    private function getBaseUrl(): string
    {
        $mode = config('paypal.webhook.mode', 'sandbox');

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
        $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID');
        $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME');
        $certUrl = $request->header('PAYPAL-CERT-URL');
        $authAlgo = $request->header('PAYPAL-AUTH-ALGO');
        $transmissionSig = $request->header('PAYPAL-TRANSMISSION-SIG');

        if (! $transmissionId || ! $transmissionTime || ! $certUrl || ! $authAlgo || ! $transmissionSig) {
            return false;
        }

        $webhookId = config('paypal.webhook.webhook_id', '');
        $clientId = config('paypal.webhook.client_id', '');
        $clientSecret = config('paypal.webhook.client_secret', '');

        if ($webhookId === '' || $clientId === '' || $clientSecret === '') {
            return false;
        }

        $token = $this->getAccessToken();
        if ($token === null) {
            return false;
        }

        $response = Http::withToken($token)
            ->post($this->getBaseUrl().'/v1/notifications/verify-webhook-signature', [
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

    private function getAccessToken(): ?string
    {
        $clientId = config('paypal.webhook.client_id', '');
        $clientSecret = config('paypal.webhook.client_secret', '');

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->getBaseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('access_token');
    }
}
