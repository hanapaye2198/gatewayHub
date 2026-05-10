<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendMerchantWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $paymentId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payment = Payment::query()->with('merchant')->find($this->paymentId);
        if ($payment === null) {
            return;
        }

        $merchant = $payment->merchant;
        if ($merchant === null) {
            return;
        }

        $webhookUrl = $merchant->webhook_url;
        $secret = $merchant->webhook_secret;
        if (! is_string($webhookUrl) || trim($webhookUrl) === '') {
            return;
        }

        if (! is_string($secret) || trim($secret) === '') {
            return;
        }

        $payload = [
            'event' => 'payment.updated',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'gateway' => $payment->gateway_code,
                'reference' => $payment->reference_id,
                'provider_reference' => $payment->provider_reference,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'created_at' => $payment->created_at?->toIso8601String(),
                'updated_at' => $payment->updated_at?->toIso8601String(),
            ],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! is_string($body)) {
            return;
        }

        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'GatewayHub-Webhooks/1.0',
                'X-Merchant-Timestamp' => $timestamp,
                'X-Merchant-Signature' => $signature,
            ])
            ->post($webhookUrl, $payload)
            ->throw();
    }
}
