<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Coins\CoinsSignatureService;
use App\Services\Coins\CoinsWebhookReplayValidator;
use App\Services\Gateways\Exceptions\CoinsApiException;
use App\Services\Gateways\PlatformGatewayConfigService;
use App\Services\Webhooks\Normalizers\CoinsWebhookNormalizer;
use App\Services\Webhooks\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoinsWebhookController extends Controller
{
    public function __construct(
        protected CoinsSignatureService $signatureService,
        protected CoinsWebhookReplayValidator $replayValidator,
        protected PlatformGatewayConfigService $platformGatewayConfigService,
        protected CoinsWebhookNormalizer $coinsWebhookNormalizer,
        protected WebhookProcessor $webhookProcessor
    ) {}

    /**
     * Handle Coins.ph webhook. Verifies signature using webhook secret, finds payment by
     * external payment identifiers, updates status (success/failed), stores full payload,
     * and returns provider-retryable failures when processing cannot complete safely.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $this->getPayload($request);
        if ($payload === null) {
            return response()->json(['received' => true], 200);
        }

        if (! config('coins.webhook.allow_dev_bypass', false)) {
            $platformConfig = $this->platformGatewayConfigService->forGatewayCode('coins');
            $secret = (string) ($platformConfig['webhook_secret'] ?? config('coins.webhook.secret', ''));
            $signature = $this->getSignature($request);
            if ($secret === '' || ! is_string($signature) || $signature === '') {
                return response()->json(['message' => 'Invalid signature.'], 401);
            }

            try {
                $this->signatureService->verify($payload, $secret, $signature);
            } catch (CoinsApiException) {
                return response()->json(['message' => 'Invalid signature.'], 401);
            }
        }

        if (! $this->replayValidator->isValid($request, $payload)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $this->webhookProcessor->process(
            $request,
            $payload,
            $this->replayValidator,
            $this->coinsWebhookNormalizer,
            'Coins',
            [
                'skip_replay_validation' => true,
            ],
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

    private function getSignature(Request $request): ?string
    {
        $headerName = config('coins.webhook.signature_header', 'X-COINS-SIGNATURE');
        $value = $request->header($headerName);

        return is_string($value) ? $value : null;
    }
}
