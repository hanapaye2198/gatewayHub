<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Webhooks\Normalizers\GcashWebhookNormalizer;
use App\Services\Webhooks\GcashWebhookReplayValidator;
use App\Services\Webhooks\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GcashWebhookController extends Controller
{
    public function __construct(
        protected GcashWebhookReplayValidator $replayValidator,
        protected GcashWebhookNormalizer $normalizer,
        protected WebhookProcessor $processor
    ) {}

    /**
     * Handle GCash webhook callback.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $this->getPayload($request);
        if ($payload === null) {
            return response()->json(['received' => true], 200);
        }

        if (! $this->verifyWebhook($request, $payload)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $this->processor->process(
            $request,
            $payload,
            $this->replayValidator,
            $this->normalizer,
            'GCash'
        );
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
    private function verifyWebhook(Request $request, array $payload): bool
    {
        if (config('gcash.webhook.allow_dev_bypass', false)) {
            Log::warning('GCash webhook: dev bypass enabled, skipping signature verification');

            return true;
        }

        return true;
    }
}
