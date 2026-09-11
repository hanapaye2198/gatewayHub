<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

class ServerStorageMetrics
{
    /**
     * @return array{
     *     total_bytes: int,
     *     used_bytes: int,
     *     available_bytes: int,
     *     usage_percentage: int,
     *     status: 'healthy'|'warning'|'critical'
     * }
     */
    public function forPath(string $path): array
    {
        if (! is_dir($path)) {
            throw new InvalidArgumentException("Storage path [{$path}] does not exist.");
        }

        $totalSpace = $this->totalSpace($path);
        $availableSpace = $this->availableSpace($path);

        if ($totalSpace === false || $availableSpace === false || $totalSpace <= 0) {
            throw new RuntimeException("Unable to read storage usage for [{$path}].");
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

    protected function totalSpace(string $path): float|false
    {
        return @disk_total_space($path);
    }

    protected function availableSpace(string $path): float|false
    {
        return @disk_free_space($path);
    }
}
