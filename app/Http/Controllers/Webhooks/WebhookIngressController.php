<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookIngressController extends Controller
{
    /**
     * Single webhook ingress endpoint that routes payloads to provider-specific handlers.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $provider = $this->resolveProvider($request);
        if ($provider !== 'coins') {
            return response()->json([
                'message' => 'Coins webhook ingress only. Use /api/webhooks/coins.',
            ], 422);
        }

        return app(CoinsWebhookController::class)->handle($request);
    }

    private function resolveProvider(Request $request): ?string
    {
        $explicitProvider = strtolower(trim((string) $request->query('provider', '')));
        if ($explicitProvider === 'coins') {
            return $explicitProvider;
        }

        if ($explicitProvider !== '') {
            return null;
        }

        $coinsSignatureHeaders = array_values(array_unique(array_filter([
            trim((string) config('coins.webhook.signature_header', 'X-COINS-SIGNATURE')),
            'Signature',
            'X-COINS-SIGNATURE',
        ])));
        foreach ($coinsSignatureHeaders as $coinsSignatureHeader) {
            if ($request->headers->has($coinsSignatureHeader)) {
                return 'coins';
            }
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        if (
            is_string($payload['referenceId'] ?? null)
            || is_string($payload['orderId'] ?? null)
            || is_numeric($payload['settleDate'] ?? null)
        ) {
            return 'coins';
        }

        return null;
    }
}
