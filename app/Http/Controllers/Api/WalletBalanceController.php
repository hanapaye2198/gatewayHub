<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WalletBalanceRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Billing\WalletBalanceQueryService;
use Illuminate\Http\JsonResponse;

class WalletBalanceController extends Controller
{
    public function __invoke(WalletBalanceRequest $request, WalletBalanceQueryService $queryService): JsonResponse
    {
        if (! config('surepay.features.wallet_settlement', false)) {
            return ApiResponse::error('Not found.', 404);
        }

        $merchant = $request->merchant();
        if ($merchant === null) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $validated = $request->validated();
        $currency = is_string($validated['currency'] ?? null) ? $validated['currency'] : 'PHP';
        $data = $queryService->forMerchant($merchant, $currency);

        return ApiResponse::success($data);
    }
}
