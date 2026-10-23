<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WalletPayoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateWalletPayoutRequest;
use App\Http\Requests\Api\WalletPayoutInstitutionsRequest;
use App\Http\Requests\Api\WalletTransactionsRequest;
use App\Http\Resources\WalletPayoutResource;
use App\Http\Responses\ApiResponse;
use App\Models\Merchant;
use App\Models\MerchantPayout;
use App\Services\Billing\WalletPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletPayoutController extends Controller
{
    public function institutions(
        WalletPayoutInstitutionsRequest $request,
        WalletPayoutService $payoutService
    ): JsonResponse {
        if (! config('surepay.features.wallet_settlement', false)) {
            return ApiResponse::error('Not found.', 404);
        }

        try {
            $provider = (string) $request->validated('provider');
            $response = $payoutService->receivingInstitutions($provider);

            return ApiResponse::success([
                'provider' => $provider,
                'institutions' => data_get($response, 'data', []),
            ]);
        } catch (WalletPayoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }
    }

    public function index(WalletTransactionsRequest $request): JsonResponse
    {
        if (! config('surepay.features.wallet_settlement', false)) {
            return ApiResponse::error('Not found.', 404);
        }

        $merchant = $request->merchant();
        if (! $merchant instanceof Merchant) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $validated = $request->validated();
        $currency = strtoupper(trim((string) ($validated['currency'] ?? 'PHP')));
        $perPage = (int) ($validated['per_page'] ?? 20);
        $payouts = MerchantPayout::query()
            ->where('merchant_id', $merchant->id)
            ->where('currency', $currency)
            ->latest('created_at')
            ->paginate($perPage);

        return ApiResponse::success([
            'currency' => $currency,
            'payouts' => WalletPayoutResource::collection($payouts->getCollection())->resolve($request),
            'pagination' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    public function store(CreateWalletPayoutRequest $request, WalletPayoutService $payoutService): JsonResponse
    {
        if (! config('surepay.features.wallet_settlement', false)) {
            return ApiResponse::error('Not found.', 404);
        }

        $merchant = $request->merchant();
        if (! $merchant instanceof Merchant) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        try {
            $payout = $payoutService->create($merchant, $request->validated());

            return ApiResponse::success([
                'payout' => (new WalletPayoutResource($payout))->resolve($request),
            ], 201);
        } catch (WalletPayoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }
    }

    public function show(Request $request, string $payout, WalletPayoutService $payoutService): JsonResponse
    {
        if (! config('surepay.features.wallet_settlement', false)) {
            return ApiResponse::error('Not found.', 404);
        }

        $merchant = $request->merchant();
        if (! $merchant instanceof Merchant) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $merchantPayout = MerchantPayout::query()
            ->where('merchant_id', $merchant->id)
            ->whereKey($payout)
            ->first();

        if (! $merchantPayout instanceof MerchantPayout) {
            return ApiResponse::error('Payout not found.', 404);
        }

        $merchantPayout = $payoutService->refresh($merchantPayout);

        return ApiResponse::success([
            'payout' => (new WalletPayoutResource($merchantPayout))->resolve($request),
        ]);
    }
}
