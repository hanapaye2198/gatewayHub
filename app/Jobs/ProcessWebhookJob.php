<?php

namespace App\Jobs;

use App\Services\Coins\CoinsWebhookReplayValidator;
use App\Services\Webhooks\Contracts\WebhookNormalizerInterface;
use App\Services\Webhooks\Contracts\WebhookReplayValidatorInterface;
use App\Services\Webhooks\GcashWebhookReplayValidator;
use App\Services\Webhooks\MayaWebhookReplayValidator;
use App\Services\Webhooks\Normalizers\CoinsWebhookNormalizer;
use App\Services\Webhooks\Normalizers\GcashWebhookNormalizer;
use App\Services\Webhooks\Normalizers\MayaWebhookNormalizer;
use App\Services\Webhooks\Normalizers\PayPalWebhookNormalizer;
use App\Services\Webhooks\PayPalWebhookReplayValidator;
use App\Services\Webhooks\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public string $provider,
        public array $payload,
        public array $headers
    ) {}

    public function handle(WebhookProcessor $processor): void
    {
        $request = $this->buildRequest();
        $normalizer = $this->resolveNormalizer();
        $replayValidator = $this->resolveReplayValidator();

        $processor->process($request, $this->payload, $replayValidator, $normalizer, $this->provider);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook job failed after retries', [
            'provider' => $this->provider,
            'error' => $exception->getMessage(),
            'event_id' => $this->payload['referenceId'] ?? $this->payload['data']['id'] ?? null,
        ]);
    }

    private function buildRequest(): Request
    {
        $content = json_encode($this->payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'REQUEST_METHOD' => 'POST'];

        foreach ($this->headers as $name => $values) {
            $key = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
            $server[$key] = is_array($values) ? ($values[0] ?? '') : $values;
        }

        $path = '/api/webhooks/'.strtolower($this->provider);

        return Request::create($path, 'POST', [], [], [], $server, $content);
    }

    private function resolveNormalizer(): WebhookNormalizerInterface
    {
        return match (strtolower($this->provider)) {
            'coins' => app(CoinsWebhookNormalizer::class),
            'gcash' => app(GcashWebhookNormalizer::class),
            'maya' => app(MayaWebhookNormalizer::class),
            'paypal' => app(PayPalWebhookNormalizer::class),
            default => throw new \InvalidArgumentException("Unknown webhook provider: {$this->provider}"),
        };
    }

    private function resolveReplayValidator(): WebhookReplayValidatorInterface
    {
        return match (strtolower($this->provider)) {
            'coins' => app(CoinsWebhookReplayValidator::class),
            'gcash' => app(GcashWebhookReplayValidator::class),
            'maya' => app(MayaWebhookReplayValidator::class),
            'paypal' => app(PayPalWebhookReplayValidator::class),
            default => throw new \InvalidArgumentException("Unknown webhook provider: {$this->provider}"),
        };
    }
}
