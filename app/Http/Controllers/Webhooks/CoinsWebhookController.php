<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Services\Coins\CoinsSignatureService;
use App\Services\Coins\CoinsWebhookReplayValidator;
use App\Services\Gateways\Exceptions\CoinsApiException;
use App\Services\Gateways\PlatformGatewayConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CoinsWebhookController extends Controller
{
    private const PROVIDER = 'coins';

    /**
     * How long a raw-body sha256 is remembered as "already seen".
     * Coins does not send a timestamp on webhooks, so we cannot rely on a time
     * window; instead we deduplicate on the exact verified body for 24 hours.
     */
    private const REPLAY_CACHE_TTL_SECONDS = 86400;

    private const REPLAY_CACHE_KEY_PREFIX = 'webhook_hash:';

    public function __construct(
        protected CoinsSignatureService $signatureService,
        protected CoinsWebhookReplayValidator $replayValidator,
        protected PlatformGatewayConfigService $platformGatewayConfigService,
    ) {}

    /**
     * Thin async entry point for Coins.ph webhooks.
     *
     * Contract:
     *  1. Verify signature FIRST. Reject with 401 on missing/mismatched signature.
     *  2. Apply replay protection. Reject with 401 on stale timestamps.
     *  3. Dispatch {@see ProcessWebhookJob} so all DB / business logic runs on the queue.
     *  4. Respond 200 {"received": true} in under ~500ms so the provider stops retrying
     *     once we have taken ownership of the payload. Downstream retries are owned by
     *     the queue worker (ProcessWebhookJob::$tries/$backoff and ::failed()).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $this->getPayload($request);
        if ($payload === null) {
            Log::info('coins.webhook.received', [
                'event_id' => null,
                'status' => null,
                'reason' => 'empty_or_invalid_body',
            ]);

            return $this->ack();
        }

        Log::info('coins.webhook.received', [
            'event_id' => $this->extractReference($payload),
            'status' => $this->extractStatus($payload),
        ]);

        if (! config('coins.webhook.allow_dev_bypass', false)) {
            $platformConfig = $this->platformGatewayConfigService->forGatewayCode(self::PROVIDER);
            $secret = (string) ($platformConfig['webhook_secret'] ?? config('coins.webhook.secret', ''));
            $signature = $this->getSignatureValue($request);
            if ($secret === '' || $signature === null) {
                return $this->rejectInvalidSignature(
                    $request,
                    $payload,
                    $secret === '' ? 'missing_secret' : 'missing_signature'
                );
            }

            try {
                $this->signatureService->verifyWebhook($payload, $secret, $signature, (string) $request->getContent());
            } catch (CoinsApiException) {
                return $this->rejectInvalidSignature($request, $payload, 'signature_mismatch');
            }
        }

        if (! $this->replayValidator->isValid($request, $payload)) {
            return $this->rejectInvalidSignature($request, $payload, 'replay_rejected');
        }

        $rawBody = (string) $request->getContent();
        if (! $this->claimPayloadHash($rawBody, $payload)) {
            return $this->ack();
        }

        $this->dispatchProcessing($payload, $this->captureHeaders($request));

        return $this->ack();
    }

    /**
     * Atomically claim the sha256 of the verified raw body so the same payload
     * cannot be processed twice within the TTL window. Returns false when the
     * payload has already been seen (i.e. this is a replay).
     *
     * @param  array<string, mixed>  $payload
     */
    private function claimPayloadHash(string $rawBody, array $payload): bool
    {
        if ($rawBody === '') {
            return true;
        }

        $hash = hash('sha256', $rawBody);
        $key = self::REPLAY_CACHE_KEY_PREFIX.$hash;

        $context = [
            'hash' => $hash,
            'event_id' => $this->extractReference($payload),
            'status' => $this->extractStatus($payload),
        ];

        if (! Cache::add($key, now()->toIso8601String(), self::REPLAY_CACHE_TTL_SECONDS)) {
            Log::warning('coins.webhook.replay_detected', $context);

            return false;
        }

        Log::info('coins.webhook.accepted', $context);

        return true;
    }

    /**
     * Dispatch the queued processor. Any failure is swallowed so the provider still
     * gets a fast 200 ACK; the WebhookEvent row and queue's retry policy are the
     * source of truth for follow-up.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $headers
     */
    private function dispatchProcessing(array $payload, array $headers): void
    {
        try {
            ProcessWebhookJob::dispatch(self::PROVIDER, $payload, $headers);
        } catch (Throwable $e) {
            Log::error('coins.webhook.dispatch_failed', [
                'error' => $e->getMessage(),
                'event_id' => $this->extractReference($payload),
                'status' => $this->extractStatus($payload),
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function captureHeaders(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $list = is_array($values) ? $values : [$values];
            $headers[(string) $name] = array_values(array_map(
                static fn ($value): string => (string) $value,
                $list
            ));
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPayload(Request $request): ?array
    {
        $content = $request->getContent();
        if ($content === '' || $content === false) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function getSignatureValue(Request $request): ?string
    {
        $headerNames = array_values(array_unique(array_filter([
            trim((string) config('coins.webhook.signature_header', 'X-COINS-SIGNATURE')),
            'Signature',
            'X-COINS-SIGNATURE',
        ])));

        foreach ($headerNames as $headerName) {
            $value = $request->header($headerName);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function ack(): JsonResponse
    {
        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rejectInvalidSignature(
        Request $request,
        array $payload,
        string $reason
    ): JsonResponse {
        Log::warning('coins.webhook.rejected', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'event_id' => $this->extractReference($payload),
            'status' => $this->extractStatus($payload),
            'body_sha256' => hash('sha256', (string) $request->getContent()),
        ]);

        return response()->json(['message' => 'Invalid signature.'], 401);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractReference(array $payload): ?string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $candidates = [
            $payload['requestId'] ?? null,
            $data['requestId'] ?? null,
            $payload['referenceId'] ?? null,
            $data['referenceId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractStatus(array $payload): ?string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $candidates = [
            $payload['status'] ?? null,
            $data['status'] ?? null,
            $payload['qrcodeStatus'] ?? null,
            $data['qrcodeStatus'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
