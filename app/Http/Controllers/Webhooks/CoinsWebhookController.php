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
use Illuminate\Support\Facades\Log;

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
            $signatureMeta = $this->getSignatureMeta($request);
            $signature = $signatureMeta['value'];
            if ($secret === '' || ! is_string($signature) || $signature === '') {
                return $this->rejectInvalidSignature(
                    $request,
                    $payload,
                    $signatureMeta,
                    $secret === '' ? 'missing_secret' : 'missing_signature'
                );
            }

            try {
                $this->signatureService->verifyWebhook($payload, $secret, $signature);
            } catch (CoinsApiException) {
                return $this->rejectInvalidSignature($request, $payload, $signatureMeta, 'signature_mismatch');
            }
        }

        if (! $this->replayValidator->isValid($request, $payload)) {
            return $this->rejectInvalidSignature($request, $payload, $this->getSignatureMeta($request), 'replay_rejected');
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

    /**
     * @return array{name: string|null, value: string|null}
     */
    private function getSignatureMeta(Request $request): array
    {
        $headerNames = array_values(array_unique(array_filter([
            trim((string) config('coins.webhook.signature_header', 'X-COINS-SIGNATURE')),
            'Signature',
            'X-COINS-SIGNATURE',
        ])));

        foreach ($headerNames as $headerName) {
            $value = $request->header($headerName);
            if (is_string($value) && trim($value) !== '') {
                return [
                    'name' => $headerName,
                    'value' => trim($value),
                ];
            }
        }

        return [
            'name' => null,
            'value' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{name: string|null, value: string|null}  $signatureMeta
     */
    private function rejectInvalidSignature(
        Request $request,
        array $payload,
        array $signatureMeta,
        string $reason
    ): JsonResponse {
        Log::warning('Coins webhook rejected: invalid signature', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'signature_header' => $signatureMeta['name'],
            'signature_length' => is_string($signatureMeta['value']) ? strlen($signatureMeta['value']) : 0,
            'payload_keys' => array_keys($payload),
            'request_id' => $payload['requestId'] ?? $payload['data']['requestId'] ?? null,
            'reference_id' => $payload['referenceId'] ?? $payload['data']['referenceId'] ?? null,
            'status' => $payload['status'] ?? $payload['data']['status'] ?? $payload['data']['qrcodeStatus'] ?? null,
            'body_sha256' => hash('sha256', (string) $request->getContent()),
        ]);

        return response()->json(['message' => 'Invalid signature.'], 401);
    }
}
