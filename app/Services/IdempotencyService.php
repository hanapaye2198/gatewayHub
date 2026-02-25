<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class IdempotencyService
{
    private const TTL_SECONDS = 86400; // 24 hours

    /**
     * Store idempotent response. Returns false if key already exists (caller should fetch instead).
     *
     * @param  array<string, mixed>  $response
     */
    public function store(string $key, array $response): bool
    {
        $cacheKey = $this->cacheKey($key);

        return Cache::add($cacheKey, $response, self::TTL_SECONDS);
    }

    /**
     * Retrieve cached response for idempotency key.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $cached = Cache::get($this->cacheKey($key));

        return is_array($cached) ? $cached : null;
    }

    private function cacheKey(string $key): string
    {
        return 'idempotency:'.hash('sha256', $key);
    }
}
