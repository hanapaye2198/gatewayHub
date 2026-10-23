<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayMongoTransferWebhookJob;
use App\Jobs\ProcessWebhookJob;
use App\Services\Gateways\PlatformGatewayConfigService;
use App\Services\Webhooks\PayMongoWebhookReplayValidator;
use App\Services\Webhooks\PayMongoWebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PayMongoWebhookController extends Controller
{
    private const PROVIDER = 'paymongo';

    public function __construct(
        protected PlatformGatewayConfigService $platformGatewayConfigService,
        protected PayMongoWebhookReplayValidator $replayValidator,
        protected PayMongoWebhookSignatureVerifier $signatureVerifier
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON payload.'], 400);
        }

        $livemode = data_get($payload, 'data.attributes.livemode');
        if (! is_bool($livemode)) {
            return response()->json(['message' => 'Webhook mode is missing or invalid.'], 400);
        }

        $config = $this->platformGatewayConfigService->forGatewayCode('paymongo');
        $webhookSecret = trim((string) ($config['webhook_secret'] ?? ''));
        $secretKey = trim((string) ($config['secret_key'] ?? ''));
        $signature = $request->header('Paymongo-Signature');

        if ($webhookSecret === '' || ! $this->signatureVerifier->verify($rawBody, $signature, $webhookSecret, $livemode)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if (! $this->replayValidator->isValid($request, [])) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if (! $this->isModeConsistent($livemode, $secretKey)) {
            Log::warning('paymongo.webhook.rejected', [
                'reason' => 'mode_mismatch',
                'event_id' => data_get($payload, 'data.id'),
            ]);

            return response()->json(['message' => 'Webhook mode does not match configured PayMongo key.'], 401);
        }

        try {
            $eventType = strtolower((string) data_get($payload, 'data.attributes.type', ''));
            if (in_array($eventType, ['transfer.outward.successful', 'transfer.outward.failed'], true)) {
                ProcessPayMongoTransferWebhookJob::dispatch($payload);

                return $this->acknowledge();
            }

            ProcessWebhookJob::dispatch(
                self::PROVIDER,
                $payload,
                $this->captureHeaders($request),
                ['skip_replay_validation' => true]
            );
        } catch (Throwable $exception) {
            Log::error('paymongo.webhook.dispatch_failed', [
                'event_id' => data_get($payload, 'data.id'),
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook could not be queued.'], 503);
        }

        return $this->acknowledge();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isModeConsistent(bool $livemode, string $secretKey): bool
    {
        if (str_starts_with($secretKey, 'sk_test_')) {
            return ! $livemode;
        }

        if (str_starts_with($secretKey, 'sk_live_')) {
            return $livemode;
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    private function captureHeaders(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $values = is_array($values) ? $values : [$values];
            $headers[(string) $name] = array_values(array_map(
                static fn (mixed $value): string => (string) $value,
                $values
            ));
        }

        return $headers;
    }

    private function acknowledge(): JsonResponse
    {
        return response()->json(['received' => true]);
    }
}
