<?php

namespace App\Http\Controllers;

use App\Http\Requests\CoinsGenerateQrRequest;
use App\Models\CoinsTransaction;
use App\Models\Merchant;
use App\Services\CoinsService;
use App\Services\Gateways\Exceptions\CoinsApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CoinsQrController extends Controller
{
    public function __construct(
        protected CoinsService $coinsService
    ) {}

    /**
     * Generate Dynamic QR and save transaction. Returns JSON with qr_code_string for frontend rendering.
     */
    public function generate(CoinsGenerateQrRequest $request): JsonResponse
    {
        $requestId = (string) Str::uuid();
        $amount = (float) $request->validated('amount');
        $currency = 'PHP';

        $merchant = $request->user()?->merchant;
        $qrMerchantName = $merchant !== null
            ? $merchant->getQrMerchantName()
            : Merchant::normalizeQrCodeMerchantName(null);

        try {
            $result = $this->coinsService->generateDynamicQr([
                'requestId' => $requestId,
                'amount' => $amount,
                'currency' => $currency,
                'qr_code_merchant_name' => $qrMerchantName,
            ]);
        } catch (CoinsApiException $e) {
            $body = $e->getResponseBody();
            $code = $body ? (int) ($body['code'] ?? $body['status'] ?? 0) : 0;
            if ($code === CoinsService::ERROR_CODE_IP_NOT_WHITELISTED) {
                return response()->json([
                    'error' => 'IP not whitelisted. Please contact Coins to whitelist server IP.',
                ], 403);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus() ?: 502);
        }

        CoinsTransaction::create([
            'request_id' => $requestId,
            'reference_id' => $result['reference_id'],
            'amount' => $amount,
            'currency' => $currency,
            'status' => CoinsTransaction::STATUS_PENDING,
            'qr_code_string' => $result['qr_code_string'],
            'raw_response' => $result['raw'],
        ]);

        return response()->json([
            'success' => true,
            'request_id' => $requestId,
            'reference_id' => $result['reference_id'],
            'amount' => $amount,
            'currency' => $currency,
            'status' => CoinsTransaction::STATUS_PENDING,
            'qr_code_string' => $result['qr_code_string'],
            'raw_response' => $result['raw'],
        ]);
    }
}
