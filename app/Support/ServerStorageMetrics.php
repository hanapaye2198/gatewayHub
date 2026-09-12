<?php

namespace App\Support;

use InvalidArgumentException;
use Throwable;

class ServerStorageMetrics
{
    /**
     * @return array{
     *     total_bytes: int,
     *     used_bytes: int,
     *     available_bytes: int,
     *     usage_percentage: int,
     *     status: 'healthy'|'warning'|'critical'|'unavailable'
     * }
     */
    public function forPath(string $path): array
    {
        if (! is_dir($path)) {
            throw new InvalidArgumentException("Storage path [{$path}] does not exist.");
        }

        try {
            $totalSpace = $this->totalSpace($path);
            $availableSpace = $this->availableSpace($path);
        } catch (Throwable) {
            return self::unavailable();
        }

        if ($totalSpace === false || $availableSpace === false || $totalSpace <= 0) {
            return self::unavailable();
        }

        $totalBytes = (int) floor($totalSpace);
        $availableBytes = min($totalBytes, max(0, (int) floor($availableSpace)));
        $usedBytes = $totalBytes - $availableBytes;
        $usagePercentage = (int) round(($usedBytes / $totalBytes) * 100);

        return [
            'total_bytes' => $totalBytes,
            'used_bytes' => $usedBytes,
            'available_bytes' => $availableBytes,
            'usage_percentage' => $usagePercentage,
            'status' => match (true) {
                $usagePercentage >= 90 => 'critical',
                $usagePercentage >= 75 => 'warning',
                default => 'healthy',
            },
        ];
    }

    /**
     * @return array{
     *     total_bytes: int,
     *     used_bytes: int,
     *     available_bytes: int,
     *     usage_percentage: int,
     *     status: 'unavailable'
     * }
     */
    public static function unavailable(): array
    {
        return [
            'total_bytes' => 0,
            'used_bytes' => 0,
            'available_bytes' => 0,
            'usage_percentage' => 0,
            'status' => 'unavailable',
        ];
    }

    protected function totalSpace(string $path): float|false
    {
        try {
            return @disk_total_space($path);
        } catch (Throwable) {
            return false;
        }
    }

    protected function availableSpace(string $path): float|false
    {
        try {
            return @disk_free_space($path);
        } catch (Throwable) {
            return false;
        }
    }
}
