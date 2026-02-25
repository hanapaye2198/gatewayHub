<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Cast that stores valid JSON. Encrypts only sensitive field values within the JSON.
 * Sensitive keys are stored as encrypted strings (valid JSON strings).
 */
class PartiallyEncryptedJsonCast implements CastsAttributes
{
    private const SENSITIVE_KEYS = [
        'client_secret',
        'api_secret',
        'webhook_secret',
        'webhook_key',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $decoded = $this->decode($value);
        if (! is_array($decoded)) {
            return [];
        }

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (! array_key_exists($sensitiveKey, $decoded)) {
                continue;
            }
            $encrypted = $decoded[$sensitiveKey];
            if (! is_string($encrypted) || $encrypted === '') {
                continue;
            }
            try {
                $decoded[$sensitiveKey] = Crypt::decryptString($encrypted);
            } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                // Leave as-is if not encrypted (e.g. legacy plain value)
            }
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $array = is_array($value) ? $value : [];
        $out = [];

        foreach ($array as $k => $v) {
            if (in_array($k, self::SENSITIVE_KEYS, true) && is_string($v) && $v !== '') {
                $out[$k] = Crypt::encryptString($v);
            } else {
                $out[$k] = $v;
            }
        }

        return json_encode($out);
    }

    /**
     * Decode value from DB to array. Handles JSON string, already-decoded array, or legacy encrypted blob.
     */
    private function decode(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        try {
            $decrypted = Crypt::decryptString($value);
            $decoded = json_decode($decrypted, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return [];
        }
    }
}
