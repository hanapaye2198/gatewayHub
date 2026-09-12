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

    public function test_it_returns_unavailable_when_storage_counters_cannot_be_read(): void
    {
        $metrics = $this->storageMetrics(totalSpace: false, availableSpace: false)->forPath(__DIR__);

        $this->assertSame(0, $metrics['total_bytes']);
        $this->assertSame(0, $metrics['used_bytes']);
        $this->assertSame(0, $metrics['available_bytes']);
        $this->assertSame(0, $metrics['usage_percentage']);
        $this->assertSame('unavailable', $metrics['status']);
    }

    public function test_it_returns_unavailable_when_disk_functions_throw(): void
    {
        $metrics = new class extends ServerStorageMetrics
        {
            protected function totalSpace(string $path): float|false
            {
                throw new RuntimeException('disk_total_space has been disabled');
            }

            protected function availableSpace(string $path): float|false
            {
                throw new RuntimeException('disk_free_space has been disabled');
            }
        };

        $result = $metrics->forPath(__DIR__);

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame(0, $result['usage_percentage']);
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
