<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Models\Gateway;
use App\Models\MerchantGateway;
use App\Services\Coins\CoinsSignatureService;
use App\Services\Coins\CoinsWebhookReplayValidator;
use App\Services\Gateways\Exceptions\CoinsApiException;
use App\Services\Webhooks\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CoinsWebhookController extends Controller
{
    public function __construct(
        protected CoinsSignatureService $signatureService,
        protected CoinsWebhookReplayValidator $replayValidator
    ) {}

    /**
     * Handle Coins.ph webhook. Verifies signature using webhook secret, finds payment by
     * external_payment_id (provider_reference), updates status (success/failed), stores full
     * payload. Returns 200 OK. Processing is idempotent (duplicate events ignored).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $this->getPayload($request);
        if ($payload === null) {
            return response()->json(['received' => true], 200);
        }

        if (! $this->verifyWebhookSignature($request, $payload)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if (! $this->replayValidator->isValid($request, $payload)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        ProcessWebhookJob::dispatch(
            'Coins',
            $payload,
            WebhookProcessor::captureHeadersForPayload($request)
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
    private function verifyWebhookSignature(Request $request, array $payload): bool
    {
        if (config('coins.webhook.allow_dev_bypass', false)) {
            Log::warning('Coins webhook: dev bypass enabled, skipping signature verification');

            return true;
        }

        $signature = $this->getSignature($request);
        if ($signature === null || $signature === '') {
            return false;
        }

        return $this->resolveWebhookSecret($payload, $signature) !== null;
    }

    private function getSignature(Request $request): ?string
    {
        $headerName = config('coins.webhook.signature_header', 'X-COINS-SIGNATURE');
        $value = $request->header($headerName);

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveWebhookSecret(array $payload, string $receivedSignature): ?string
    {
        $gateway = Gateway::query()->where('code', 'coins')->first();
        if ($gateway === null) {
            return null;
        }

        $merchantGateways = MerchantGateway::query()
            ->where('gateway_id', $gateway->id)
            ->where('is_enabled', true)
            ->get();

        foreach ($merchantGateways as $mg) {
            $config = $mg->config_json ?? [];
            $secret = $config['webhook_secret'] ?? '';
            if (! is_string($secret) || $secret === '') {
                continue;
            }

            try {
                $this->signatureService->verify($payload, $secret, $receivedSignature);

                return $secret;
            } catch (CoinsApiException) {
                continue;
            }
        }

        return null;
    }
}
