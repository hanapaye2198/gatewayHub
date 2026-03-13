<?php

namespace App\Services\Coins;

use Illuminate\Http\Client\Response;

class CoinsApiErrorMessageResolver
{
    public const ERROR_CODE_IP_NOT_WHITELISTED = 1006;

    public const ERROR_CODE_QR_PAYMENT_HANDLING_NOT_ENABLED = 88010063;

    /**
     * @param  array<string, mixed>  $body
     */
    public function resolve(array $body, Response $response, mixed $status = null): string
    {
        $resolvedStatus = $this->extractStatusCode($body, $status);

        if ($resolvedStatus === self::ERROR_CODE_QR_PAYMENT_HANDLING_NOT_ENABLED) {
            return 'Coins.ph account is not enabled for QR payment handling yet. Ask Coins support to enable QR integration for this account/API key.';
        }

        $providerMessage = $this->extractProviderMessage($body);
        if ($providerMessage !== null) {
            return $providerMessage;
        }

        if ($resolvedStatus !== null) {
            return 'API returned error status '.$resolvedStatus;
        }

        $responseBody = $response->body();

        return $responseBody !== '' ? $responseBody : 'Unknown error';
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function extractStatusCode(array $body, mixed $status = null): ?int
    {
        $candidate = $status ?? $body['status'] ?? $body['code'] ?? null;
        if (! is_numeric($candidate)) {
            return null;
        }

        return (int) $candidate;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractProviderMessage(array $body): ?string
    {
        $data = $body['data'] ?? null;
        $candidates = [
            $body['msg'] ?? null,
            $body['message'] ?? null,
            $body['error'] ?? null,
            $body['errorMsg'] ?? null,
            is_array($data) ? ($data['errorMsg'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = json_encode($candidate);
            }

            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
