<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;

class WalletBalanceQueryService
{
    /**
     * @return array{
     *   currency: string,
     *   tunnel_balance: float,
     *   real_balance: float,
     *   pending_net_settlement: float,
     *   today_gross: float,
     *   today_net_settled: float,
     *   as_of: string
     * }
     */
    public function forMerchant(User $merchant, string $currency): array
    {
        $normalizedCurrency = strtoupper(trim($currency));
        $today = CarbonImmutable::now()->startOfDay();

        $tunnelBalance = (float) Wallet::query()
            ->where('user_id', $merchant->id)
            ->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING)
            ->where('currency', $normalizedCurrency)
            ->sum('balance');

        $realBalance = (float) Wallet::query()
            ->where('user_id', $merchant->id)
            ->where('wallet_type', Wallet::TYPE_MERCHANT_REAL)
            ->where('currency', $normalizedCurrency)
            ->sum('balance');

        $pendingNetSettlement = (float) WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
            ->where('is_settled', false)
            ->where('currency', $normalizedCurrency)
            ->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('user_id', $merchant->id))
            ->sum('amount');

        $todayGross = (float) WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS)
            ->where('currency', $normalizedCurrency)
            ->where('created_at', '>=', $today)
            ->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('user_id', $merchant->id))
            ->sum('amount');

        $todayNetSettled = (float) WalletTransaction::query()
            ->whereIn('entry_type', [
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
            ])
            ->where('currency', $normalizedCurrency)
            ->where('created_at', '>=', $today)
            ->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('user_id', $merchant->id))
            ->sum('amount');

        return [
            'currency' => $normalizedCurrency,
            'tunnel_balance' => round($tunnelBalance, 2),
            'real_balance' => round($realBalance, 2),
            'pending_net_settlement' => round($pendingNetSettlement, 2),
            'today_gross' => round($todayGross, 2),
            'today_net_settled' => round($todayNetSettled, 2),
            'as_of' => now()->toIso8601String(),
        ];
    }
}
