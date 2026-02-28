<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Services\Gateways\Drivers\MayaDriver;
use App\Services\Gateways\PlatformGatewayConfigService;
use App\Services\Webhooks\MayaWebhookReplayValidator;
use App\Services\Webhooks\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MayaWebhookController extends Controller
{
    public function __construct(
        protected MayaWebhookReplayValidator $replayValidator,
        protected PlatformGatewayConfigService $platformGatewayConfigService
    ) {}

    /**
     * Handle Maya webhook callback. Verifies signature, enqueues processing, returns immediately.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $this->getPayload($request);
        if ($payload === null) {
            return response()->json(['received' => true], 200);
        }

        if (! config('maya.webhook.allow_dev_bypass', false)) {
            $platformConfig = $this->platformGatewayConfigService->forGatewayCode('maya');
            $driver = new MayaDriver([
                'webhook_key' => (string) ($platformConfig['webhook_key'] ?? config('maya.webhook.secret', '')),
            ]);

            if (! $driver->verifyWebhook($request)) {
                return response()->json(['message' => 'Invalid signature.'], 401);
            }
        }

        if (! $this->replayValidator->isValid($request, $payload)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        ProcessWebhookJob::dispatch(
            'Maya',
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
