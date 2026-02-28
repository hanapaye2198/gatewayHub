<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueHealthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_health_check_passes_when_within_thresholds(): void
    {
        $this->artisan('queue:health-check --max-pending=5 --max-failed=0')
            ->expectsOutputToContain('Queue health: pending=0, failed=0')
            ->assertExitCode(0);
    }

    public function test_queue_health_check_fails_when_thresholds_exceeded(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'TestJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'FailedTestJob']),
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $this->artisan('queue:health-check --max-pending=0 --max-failed=0')
            ->expectsOutputToContain('Queue health degraded')
            ->assertExitCode(1);
    }
}
