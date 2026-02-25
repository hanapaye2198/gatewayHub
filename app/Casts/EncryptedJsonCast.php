<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EncryptedJsonCast implements CastsAttributes
{
    /**
     * Decrypt and decode config_json from storage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_string($value)) {
            return is_array($value) ? $value : [];
        }

        try {
            $decrypted = Crypt::decryptString($value);
            $decoded = json_decode($decrypted, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }
    }

    /**
     * Encode and encrypt config_json for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $array = is_array($value) ? $value : [];

        return Crypt::encryptString(json_encode($array));
    }
}
