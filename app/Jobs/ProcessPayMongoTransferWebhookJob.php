<?php

namespace App\Jobs;

use App\Services\Billing\WalletPayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPayMongoTransferWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 60, 120, 240];

    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload) {}

    public function handle(WalletPayoutService $payoutService): void
    {
        $payoutService->processWebhook($this->payload);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('PayMongo transfer webhook job failed after retries', [
            'event_id' => data_get($this->payload, 'data.id'),
            'event_type' => data_get($this->payload, 'data.attributes.type'),
            'error' => $exception->getMessage(),
        ]);
    }
}
