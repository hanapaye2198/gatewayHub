<?php

namespace Tests\Unit\Support;

use App\Support\ServerStorageMetrics;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ServerStorageMetricsTest extends TestCase
{
    public function test_it_reports_storage_usage_and_status_thresholds(): void
    {
        foreach ([
            'healthy below warning threshold' => [800.0, 'healthy'],
            'warning at seventy_five_percent' => [250.0, 'warning'],
            'critical at ninety_percent' => [100.0, 'critical'],
        ] as [$availableSpace, $expectedStatus]) {
            $metrics = $this->storageMetrics(totalSpace: 1000.0, availableSpace: $availableSpace)
                ->forPath(__DIR__);

            $this->assertSame(1000, $metrics['total_bytes']);
            $this->assertSame((int) $availableSpace, $metrics['available_bytes']);
            $this->assertSame(1000 - (int) $availableSpace, $metrics['used_bytes']);
            $this->assertSame((int) round(((1000.0 - $availableSpace) / 1000.0) * 100), $metrics['usage_percentage']);
            $this->assertSame($expectedStatus, $metrics['status']);
        }
    }

    public function test_it_rejects_a_missing_storage_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ServerStorageMetrics)->forPath(__DIR__.'/missing-storage-path');
    }

    public function test_it_throws_when_storage_counters_are_unavailable(): void
    {
        $this->expectException(RuntimeException::class);

        $this->storageMetrics(totalSpace: false, availableSpace: false)->forPath(__DIR__);
    }

    private function storageMetrics(float|false $totalSpace, float|false $availableSpace): ServerStorageMetrics
    {
        return new class($totalSpace, $availableSpace) extends ServerStorageMetrics
        {
            public function __construct(
                private readonly float|false $totalSpace,
                private readonly float|false $availableSpace,
            ) {}

            protected function totalSpace(string $path): float|false
            {
                return $this->totalSpace;
            }

            protected function availableSpace(string $path): float|false
            {
                return $this->availableSpace;
            }
        };
    }
}
