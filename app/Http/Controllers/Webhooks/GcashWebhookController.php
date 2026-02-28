<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Services\Gateways\Drivers\GcashDriver;
use App\Services\Gateways\PlatformGatewayConfigService;
use App\Services\Webhooks\GcashWebhookReplayValidator;
use App\Services\Webhooks\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GcashWebhookController extends Controller
{
    public function __construct(
        protected GcashWebhookReplayValidator $replayValidator,
        protected PlatformGatewayConfigService $platformGatewayConfigService
    ) {}

    /**
     * Handle GCash webhook callback. Verifies signature, enqueues processing, returns immediately.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $this->getPayload($request);
        if ($payload === null) {
            return response()->json(['received' => true], 200);
        }

        if (! config('gcash.webhook.allow_dev_bypass', false)) {
            $platformConfig = $this->platformGatewayConfigService->forGatewayCode('gcash');
            $driver = new GcashDriver([
                'webhook_key' => (string) ($platformConfig['webhook_key'] ?? config('gcash.webhook.secret', '')),
            ]);

            if (! $driver->verifyWebhook($request)) {
                return response()->json(['message' => 'Invalid signature.'], 401);
            }
        }

        if (! $this->replayValidator->isValid($request, $payload)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        ProcessWebhookJob::dispatch(
            'GCash',
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
}
