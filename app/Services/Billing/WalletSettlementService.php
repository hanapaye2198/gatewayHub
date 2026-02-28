<?php

namespace App\Services\Billing;

use App\Models\MerchantWalletSetting;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletSettlementService
{
    private const FLOW_CHANNEL_USER_TO_SUREPAY = 'user_to_surepay_wallet';

    private const SOURCE_CHANNEL_USER_TO_SUREPAY = 'user_to_surepay';

    public function __construct(
        private readonly TunnelPaypalApiService $tunnelPaypalApiService
    ) {}

    /**
     * Record paid payment into SurePay flow and keep net in SurePay for batch settlement.
     */
    public function recordPaidPayment(Payment $payment): void
    {
        if (! $this->walletSettlementEnabled()) {
            return;
        }

        try {
            DB::transaction(function () use ($payment): void {
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
                if ($lockedPayment === null || $lockedPayment->status !== 'paid') {
                    return;
                }

                if ($this->hasEntry($lockedPayment->id, WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS)
                    || $this->hasEntry($lockedPayment->id, WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
                    || $this->hasEntry($lockedPayment->id, WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT)) {
                    return;
                }

                $grossAmount = round((float) $lockedPayment->amount, 2);
                $rawTaxAmount = round((float) ($lockedPayment->platform_fee ?? 0), 2);
                $taxAmount = min($grossAmount, max(0.0, $rawTaxAmount));
                $configuredNetAmount = max(0.0, round((float) ($lockedPayment->net_amount ?? ($grossAmount - $taxAmount)), 2));
                $maxAllowedNetAmount = max(0.0, round($grossAmount - $taxAmount, 2));
                $netAmount = min($configuredNetAmount, $maxAllowedNetAmount);

                $settings = $this->resolveSettings((int) $lockedPayment->user_id);

                $currency = $lockedPayment->currency;
                $metadata = [
                    'gateway' => $lockedPayment->gateway_code,
                    'reference_id' => $lockedPayment->reference_id,
                ];

                $tunnelWallet = $this->resolveMerchantWallet((int) $lockedPayment->user_id, Wallet::TYPE_MERCHANT_CLEARING, $currency);
                $taxWallet = $this->resolveSystemWallet(Wallet::TYPE_SYSTEM_TAX, $currency);

                $this->post($tunnelWallet, $lockedPayment, 'credit', $grossAmount, WalletTransaction::ENTRY_PAYMENT_RECEIVED_GROSS, $metadata, true);
                $this->appendFlowLogToLockedPayment($lockedPayment, self::SOURCE_CHANNEL_USER_TO_SUREPAY, [
                    'status' => 'success',
                    'stage' => 'gross_received',
                    'amount' => $grossAmount,
                    'currency' => $currency,
                    'reference_id' => $lockedPayment->reference_id,
                    'gateway' => $lockedPayment->gateway_code,
                ]);

                if ($taxAmount > 0) {
                    $this->post($tunnelWallet, $lockedPayment, 'debit', $taxAmount, WalletTransaction::ENTRY_SUREPAY_TAX_COLLECTED, $metadata, true);
                    $this->post($taxWallet, $lockedPayment, 'credit', $taxAmount, WalletTransaction::ENTRY_SUREPAY_TAX_COLLECTED, $metadata, true);
                }

                $this->post($tunnelWallet, $lockedPayment, 'credit', $netAmount, WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE, $metadata, false, false);
                $this->appendSurepaySendingLogToLockedPayment($lockedPayment, [
                    'status' => 'queued',
                    'stage' => 'net_held_in_tunnel',
                    'amount' => $netAmount,
                    'currency' => $currency,
                    'reference_id' => $lockedPayment->reference_id,
                    'gateway' => $lockedPayment->gateway_code,
                ], self::SOURCE_CHANNEL_USER_TO_SUREPAY);
            });
        } catch (\Throwable $exception) {
            $this->recordFailureLog($payment, $exception->getMessage(), self::SOURCE_CHANNEL_USER_TO_SUREPAY);
        }
    }

    /**
     * Batch settle SurePay net balances to real wallets.
     */
    public function settlePendingNetBatch(?int $merchantId = null, int $limit = 100): int
    {
        if (! $this->walletSettlementEnabled()) {
            return 0;
        }

        $query = WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE)
            ->where('is_settled', false)
            ->orderBy('created_at')
            ->limit($limit);

        if ($merchantId !== null) {
            $query->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('user_id', $merchantId));
        }

        $pendingEntries = $query->get();
        $settledCount = 0;

        foreach ($pendingEntries as $entry) {
            try {
                DB::transaction(function () use ($entry, &$settledCount): void {
                    $lockedEntry = WalletTransaction::query()->whereKey($entry->id)->lockForUpdate()->first();
                    if ($lockedEntry === null || $lockedEntry->is_settled) {
                        return;
                    }

                    $payment = Payment::query()->whereKey($lockedEntry->payment_id)->lockForUpdate()->first();
                    if ($payment === null) {
                        return;
                    }

                    $amount = round((float) $lockedEntry->amount, 2);
                    if ($amount <= 0) {
                        $lockedEntry->is_settled = true;
                        $lockedEntry->settled_at = now();
                        $lockedEntry->save();

                        return;
                    }

                    $settings = $this->resolveSettings((int) $payment->user_id);
                    if (! $settings->auto_settle_to_real_wallet) {
                        return;
                    }

                    $tunnelWallet = $this->resolveMerchantWallet((int) $payment->user_id, Wallet::TYPE_MERCHANT_CLEARING, $lockedEntry->currency);
                    $realWallet = $this->resolveMerchantWallet((int) $payment->user_id, Wallet::TYPE_MERCHANT_REAL, $lockedEntry->currency);

                    if ((float) $tunnelWallet->balance < $amount) {
                        throw new \RuntimeException('SurePay wallet insufficient balance for settlement.');
                    }

                    $metadata = [
                        'gateway' => $payment->gateway_code,
                        'reference_id' => $payment->reference_id,
                        'batch_settlement' => true,
                    ];

                    if ($payment->gateway_code === 'paypal') {
                        $metadata = array_merge(
                            $metadata,
                            $this->tunnelPaypalApiService->ensureTunnelPaypalReady($payment, $settings)
                        );
                    }

                    $this->post($tunnelWallet, $payment, 'debit', $amount, WalletTransaction::ENTRY_TUNNEL_BATCH_SETTLEMENT_OUT, $metadata, true);
                    $this->post($realWallet, $payment, 'credit', $amount, WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT, $metadata, true);
                    $this->appendSurepaySendingLogToLockedPayment($payment, [
                        'status' => 'success',
                        'stage' => 'net_settlement_sent',
                        'amount' => $amount,
                        'currency' => $lockedEntry->currency,
                        'reference_id' => $payment->reference_id,
                        'gateway' => $payment->gateway_code,
                    ], self::SOURCE_CHANNEL_USER_TO_SUREPAY);

                    $lockedEntry->is_settled = true;
                    $lockedEntry->settled_at = now();
                    $lockedEntry->save();

                    $settledCount++;
                });
            } catch (\Throwable $exception) {
                $payment = $entry->payment;
                if ($payment instanceof Payment) {
                    $this->recordFailureLog($payment, $exception->getMessage(), self::SOURCE_CHANNEL_USER_TO_SUREPAY);
                    $this->appendSurepaySendingLog($payment, [
                        'status' => 'failed',
                        'stage' => 'net_settlement_sent',
                        'reference_id' => $payment->reference_id,
                        'gateway' => $payment->gateway_code,
                        'error' => $exception->getMessage(),
                    ], self::SOURCE_CHANNEL_USER_TO_SUREPAY);
                }
            }
        }

        return $settledCount;
    }

    /**
     * Refund/rollback must debit SurePay wallet only; real wallet remains net-only.
     */
    public function reverseFromTunnel(Payment $payment, string $reason): void
    {
        if (! $this->walletSettlementEnabled()) {
            return;
        }

        try {
            DB::transaction(function () use ($payment, $reason): void {
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
                if ($lockedPayment === null) {
                    return;
                }

                if ($this->hasEntry($lockedPayment->id, WalletTransaction::ENTRY_TUNNEL_REVERSAL_DEBIT)) {
                    return;
                }

                $amount = max(0.0, round((float) ($lockedPayment->net_amount ?? 0), 2));
                if ($amount <= 0) {
                    return;
                }

                $tunnelWallet = $this->resolveMerchantWallet((int) $lockedPayment->user_id, Wallet::TYPE_MERCHANT_CLEARING, $lockedPayment->currency);
                if ((float) $tunnelWallet->balance < $amount) {
                    throw new \RuntimeException('SurePay wallet insufficient balance for reversal: '.$reason);
                }

                $this->post(
                    $tunnelWallet,
                    $lockedPayment,
                    'debit',
                    $amount,
                    WalletTransaction::ENTRY_TUNNEL_REVERSAL_DEBIT,
                    ['reason' => $reason, 'reference_id' => $lockedPayment->reference_id],
                    true
                );
            });
        } catch (\Throwable $exception) {
            $this->recordFailureLog($payment, $exception->getMessage(), self::SOURCE_CHANNEL_USER_TO_SUREPAY);
        }
    }

    private function hasEntry(string $paymentId, string $entryType): bool
    {
        return WalletTransaction::query()
            ->where('payment_id', $paymentId)
            ->where('entry_type', $entryType)
            ->exists();
    }

    private function resolveSettings(int $userId): MerchantWalletSetting
    {
        $settings = MerchantWalletSetting::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($settings !== null) {
            if (! $settings->tunnel_wallet_enabled) {
                $settings->tunnel_wallet_enabled = true;
                $settings->save();
            }

            return $settings;
        }

        return MerchantWalletSetting::query()->create([
            'user_id' => $userId,
            'tunnel_wallet_enabled' => true,
            'auto_settle_to_real_wallet' => true,
            'default_currency' => 'PHP',
            'notes' => null,
        ]);
    }

    private function resolveMerchantWallet(int $userId, string $walletType, string $currency): Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->where('wallet_type', $walletType)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        if ($wallet !== null) {
            return $wallet;
        }

        return Wallet::query()->create([
            'user_id' => $userId,
            'wallet_type' => $walletType,
            'currency' => $currency,
            'balance' => 0,
        ]);
    }

    private function resolveSystemWallet(string $walletType, string $currency): Wallet
    {
        $wallet = Wallet::query()
            ->whereNull('user_id')
            ->where('wallet_type', $walletType)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        if ($wallet !== null) {
            return $wallet;
        }

        return Wallet::query()->create([
            'user_id' => null,
            'wallet_type' => $walletType,
            'currency' => $currency,
            'balance' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function post(
        Wallet $wallet,
        Payment $payment,
        string $direction,
        float $amount,
        string $entryType,
        array $metadata,
        bool $isSettled,
        bool $affectsBalance = true
    ): void {
        if ($amount <= 0) {
            return;
        }

        if ($affectsBalance) {
            $delta = $direction === 'debit' ? -1 * $amount : $amount;
            $wallet->balance = round((float) $wallet->balance + $delta, 2);
            $wallet->save();
        }

        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'payment_id' => $payment->id,
            'direction' => $direction,
            'entry_type' => $entryType,
            'amount' => $amount,
            'currency' => $wallet->currency,
            'metadata' => $metadata,
            'is_settled' => $isSettled,
            'settled_at' => $isSettled ? now() : null,
        ]);
    }

    private function recordFailureLog(Payment $payment, string $message, string $channel = self::SOURCE_CHANNEL_USER_TO_SUREPAY): void
    {
        $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
        if ($lockedPayment === null) {
            return;
        }

        $raw = $lockedPayment->raw_response;
        if (! is_array($raw)) {
            $raw = [];
        }

        $errors = $raw['surepay_wallet_errors'] ?? ($raw['tunnel_wallet_errors'] ?? []);
        if (! is_array($errors)) {
            $errors = [];
        }

        $errors[] = [
            'logged_at' => now()->toIso8601String(),
            'message' => $message,
            'channel' => self::FLOW_CHANNEL_USER_TO_SUREPAY,
            'source_channel' => $channel,
        ];

        $raw['surepay_wallet_errors'] = $errors;
        $flowErrors = $raw['flow_errors'] ?? [];
        if (! is_array($flowErrors)) {
            $flowErrors = [];
        }
        $flowChannel = self::FLOW_CHANNEL_USER_TO_SUREPAY;
        $channelErrors = $flowErrors[$flowChannel] ?? [];
        if (! is_array($channelErrors)) {
            $channelErrors = [];
        }
        $channelErrors[] = [
            'logged_at' => now()->toIso8601String(),
            'message' => $message,
            'source_channel' => $channel,
        ];
        $flowErrors[$flowChannel] = $channelErrors;
        $raw['flow_errors'] = $flowErrors;
        $lockedPayment->raw_response = $raw;
        $lockedPayment->save();
    }

    /**
     * @param  array<string, mixed>  $log
     */
    private function appendSurepaySendingLog(Payment $payment, array $log, string $channel): void
    {
        $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
        if ($lockedPayment === null) {
            return;
        }

        $this->appendSurepaySendingLogToLockedPayment($lockedPayment, $log, $channel);
    }

    /**
     * @param  array<string, mixed>  $log
     */
    private function appendSurepaySendingLogToLockedPayment(Payment $lockedPayment, array $log, string $channel): void
    {
        $raw = $lockedPayment->raw_response;
        if (! is_array($raw)) {
            $raw = [];
        }

        $logs = $raw['surepay_sending_logs'] ?? [];
        if (! is_array($logs)) {
            $logs = [];
        }

        $log['logged_at'] = now()->toIso8601String();
        $logs[] = $log;
        $raw['surepay_sending_logs'] = $logs;
        $flowLogs = $raw['flow_logs'] ?? [];
        if (! is_array($flowLogs)) {
            $flowLogs = [];
        }
        $flowChannel = self::FLOW_CHANNEL_USER_TO_SUREPAY;
        $channelLogs = $flowLogs[$flowChannel] ?? [];
        if (! is_array($channelLogs)) {
            $channelLogs = [];
        }
        $log['source_channel'] = $channel;
        $channelLogs[] = $log;
        $flowLogs[$flowChannel] = $channelLogs;
        $raw['flow_logs'] = $flowLogs;
        $lockedPayment->raw_response = $raw;
        $lockedPayment->save();
    }

    /**
     * @param  array<string, mixed>  $log
     */
    private function appendFlowLogToLockedPayment(Payment $lockedPayment, string $channel, array $log): void
    {
        $raw = $lockedPayment->raw_response;
        if (! is_array($raw)) {
            $raw = [];
        }

        $flowLogs = $raw['flow_logs'] ?? [];
        if (! is_array($flowLogs)) {
            $flowLogs = [];
        }

        $flowChannel = self::FLOW_CHANNEL_USER_TO_SUREPAY;
        $channelLogs = $flowLogs[$flowChannel] ?? [];
        if (! is_array($channelLogs)) {
            $channelLogs = [];
        }

        $log['logged_at'] = now()->toIso8601String();
        $log['source_channel'] = $channel;
        $channelLogs[] = $log;
        $flowLogs[$flowChannel] = $channelLogs;
        $raw['flow_logs'] = $flowLogs;
        $lockedPayment->raw_response = $raw;
        $lockedPayment->save();
    }

    private function walletSettlementEnabled(): bool
    {
        return (bool) config('surepay.features.wallet_settlement', false);
    }
}
