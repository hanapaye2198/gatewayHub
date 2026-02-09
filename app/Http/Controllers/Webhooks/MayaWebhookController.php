<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Webhooks\MayaWebhookReplayValidator;
use App\Services\Webhooks\Normalizers\MayaWebhookNormalizer;
use App\Services\Webhooks\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MayaWebhookController extends Controller
{
    public function __construct(
        protected MayaWebhookReplayValidator $replayValidator,
        protected MayaWebhookNormalizer $normalizer,
        protected WebhookProcessor $processor
    ) {}

    /**
     * Handle Maya webhook callback.
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
            'Maya'
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
        if (config('maya.webhook.allow_dev_bypass', false)) {
            Log::warning('Maya webhook: dev bypass enabled, skipping signature verification');

            return true;
        }

        return true;
    }
}
