<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Services\PayPal\PayPalWebhookSignatureVerifier;
use App\Services\Webhooks\PayPalWebhookReplayValidator;
use App\Services\Webhooks\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function __construct(
        protected PayPalWebhookSignatureVerifier $signatureVerifier,
        protected PayPalWebhookReplayValidator $replayValidator
    ) {}

    /**
     * Handle PayPal webhook callback. Verifies signature, enqueues processing, returns immediately.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $this->getPayload($request);
        if ($payload === null) {
            return response()->json(['received' => true], 200);
        }

        $verificationContext = $this->resolveVerificationContext($request, $payload);
        if (! $verificationContext['verified']) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if (! $this->replayValidator->isValid($request, $payload)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        ProcessWebhookJob::dispatch(
            'PayPal',
            $payload,
            WebhookProcessor::captureHeadersForPayload($request),
            [
                'skip_replay_validation' => true,
            ],
        );

        return response()->json(['received' => true], 200);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array{verified: bool, merchant_ids: list<int>}
     */
    private function resolveVerificationContext(Request $request, array $payload): array
    {
        if (config('paypal.webhook.allow_dev_bypass', false)) {
            Log::warning('PayPal webhook: dev bypass enabled, skipping signature verification');

            return ['verified' => true, 'merchant_ids' => []];
        }

        return $this->signatureVerifier->resolveVerificationContext($request, $payload);
    }
}
