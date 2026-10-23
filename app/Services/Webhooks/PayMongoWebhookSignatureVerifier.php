<?php

namespace App\Services\Webhooks;

class PayMongoWebhookSignatureVerifier
{
    public function verify(string $rawBody, ?string $signatureHeader, string $secret, bool $livemode): bool
    {
        if ($rawBody === '' || $secret === '' || ! is_string($signatureHeader) || $signatureHeader === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $component) {
            [$key, $value] = array_pad(explode('=', trim($component), 2), 2, '');
            if ($key !== '') {
                $parts[strtolower(trim($key))] = trim($value);
            }
        }

        $timestamp = $parts['t'] ?? '';
        if ($timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        $signature = $livemode ? ($parts['li'] ?? '') : ($parts['te'] ?? '');
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
