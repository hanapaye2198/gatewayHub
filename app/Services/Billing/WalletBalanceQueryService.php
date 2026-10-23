<?php

namespace App\Services\Billing;

use App\Models\Merchant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;

class WalletBalanceQueryService
{
    /**
     * @return array{
     *   currency: string,
     *   wallet_balance: float,
     *   available_balance: float,
     *   settled_balance: float,
     *   tunnel_balance: float,
     *   real_balance: float,
     *   pending_net_settlement: float,
     *   today_gross: float,
     *   today_platform_fees: float,
     *   today_net_settled: float,
     *   as_of: string
     * }
     */
    public function forMerchant(Merchant $merchant, string $currency): array
    {
        $normalizedCurrency = strtoupper(trim($currency));
        $today = CarbonImmutable::now()->startOfDay();

        $tunnelBalance = (float) Wallet::query()
            ->where('merchant_id', $merchant->id)
            ->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING)
            ->where('currency', $normalizedCurrency)
            ->sum('balance');

        $realBalance = (float) Wallet::query()
            ->where('merchant_id', $merchant->id)
            ->where('wallet_type', Wallet::TYPE_MERCHANT_REAL)
            ->where('currency', $normalizedCurrency)
            ->sum('balance');

        $pendingNetSettlement = (float) WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
            ->where('is_settled', false)
            ->where('currency', $normalizedCurrency)
            ->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('merchant_id', $merchant->id))
            ->sum('amount');

        $todayGross = (float) WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS)
            ->where('currency', $normalizedCurrency)
            ->where('created_at', '>=', $today)
            ->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('merchant_id', $merchant->id))
            ->sum('amount');

        $todayNetSettled = (float) WalletTransaction::query()
            ->whereIn('entry_type', [
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT,
                WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
            ])
            ->where('currency', $normalizedCurrency)
            ->where('created_at', '>=', $today)
            ->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('merchant_id', $merchant->id))
            ->sum('amount');

        $todayPlatformFees = (float) WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_SUREPAY_TAX_COLLECTED)
            ->where('direction', 'debit')
            ->where('currency', $normalizedCurrency)
            ->where('created_at', '>=', $today)
            ->whereHas('wallet', fn ($walletQuery) => $walletQuery
                ->where('merchant_id', $merchant->id)
                ->where('wallet_type', Wallet::TYPE_MERCHANT_CLEARING))
            ->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('merchant_id', $merchant->id))
            ->sum('amount');

        $walletBalance = round($tunnelBalance + $realBalance, 2);

        return [
            'currency' => $normalizedCurrency,
            'wallet_balance' => $walletBalance,
            'available_balance' => round($tunnelBalance, 2),
            'settled_balance' => round($realBalance, 2),
            'tunnel_balance' => round($tunnelBalance, 2),
            'real_balance' => round($realBalance, 2),
            'pending_net_settlement' => round($pendingNetSettlement, 2),
            'today_gross' => round($todayGross, 2),
            'today_platform_fees' => round($todayPlatformFees, 2),
            'today_net_settled' => round($todayNetSettled, 2),
            'as_of' => now()->toIso8601String(),
        ];
    }
}
