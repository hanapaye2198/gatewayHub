<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WalletTransactionsRequest;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Merchant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;

class WalletTransactionsController extends Controller
{
    public function __invoke(WalletTransactionsRequest $request): JsonResponse
    {
        if (! config('surepay.features.wallet_settlement', false)) {
            return ApiResponse::error('Not found.', 404);
        }

        $merchant = $request->merchant();
        if (! $merchant instanceof Merchant) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $validated = $request->validated();
        $currency = strtoupper(trim($validated['currency'] ?? 'PHP'));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $transactions = WalletTransaction::query()
            ->with('payment')
            ->where('entry_type', WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
            ->where('currency', $currency)
            ->whereHas('wallet', fn ($walletQuery) => $walletQuery
                ->where('merchant_id', $merchant->id)
                ->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING))
            ->whereHas('payment', fn ($paymentQuery) => $paymentQuery
                ->where('merchant_id', $merchant->id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success([
            'currency' => $currency,
            'transactions' => WalletTransactionResource::collection($transactions->getCollection())->resolve($request),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
