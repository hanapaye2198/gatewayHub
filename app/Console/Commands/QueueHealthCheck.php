<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class QueueHealthCheck extends Command
{
    /**
     * @var string
     */
    protected $signature = 'queue:health-check {--max-pending=50} {--max-failed=0}';

    /**
     * @var string
     */
    protected $description = 'Check queue backlog and failed jobs; logs warning when thresholds are exceeded.';

    public function handle(): int
    {
        if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
            $this->warn('Queue tables are missing. Skipping queue health check.');

            return self::SUCCESS;
        }

        $pending = (int) DB::table('jobs')->count();
        $failed = (int) DB::table('failed_jobs')->count();
        $maxPending = max(0, (int) $this->option('max-pending'));
        $maxFailed = max(0, (int) $this->option('max-failed'));

        $this->info("Queue health: pending={$pending}, failed={$failed}");

        if ($pending > $maxPending || $failed > $maxFailed) {
            $message = "Queue health degraded: pending={$pending} (max {$maxPending}), failed={$failed} (max {$maxFailed}).";
            Log::warning($message);
            $this->warn($message);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
