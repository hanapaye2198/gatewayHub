<?php

namespace App\Services\Billing;

use App\Exceptions\PayMongoTransferException;
use App\Exceptions\WalletPayoutException;
use App\Models\Merchant;
use App\Models\MerchantPayout;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletPayoutService
{
    private const SUBMISSION_RETRY_DELAY_MINUTES = 2;

    private const IDEMPOTENCY_WINDOW_HOURS = 23;

    public function __construct(private readonly PayMongoTransferService $transferService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Merchant $merchant, array $data): MerchantPayout
    {
        $provider = (string) $data['provider'];
        $currency = strtoupper((string) ($data['currency'] ?? 'PHP'));
        $amount = $this->normalizeAmount((string) $data['amount']);
        $amountInCentavos = $this->toCentavos($amount);

        $maximumAmountInCentavos = $provider === 'instapay' ? 5000000 : 1000000000;
        if ($amountInCentavos > $maximumAmountInCentavos) {
            throw new WalletPayoutException(
                $provider === 'instapay'
                    ? 'InstaPay payouts cannot exceed PHP 50,000. Use PESONet for larger payouts.'
                    : 'PESONet payouts cannot exceed PHP 10,000,000.',
                422
            );
        }

        $destinationAccount = $data['destination_account'];
        $destinationAccount['number'] = preg_replace('/[\s-]+/', '', trim((string) $destinationAccount['number']));
        $destinationAccount['name'] = trim((string) $destinationAccount['name']);
        $destinationAccount['bic'] = strtoupper(trim((string) $destinationAccount['bic']));
        $destinationAccount['bank_name'] = trim((string) ($destinationAccount['bank_name'] ?? ''));

        $purpose = trim((string) ($data['purpose'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $idempotencyKey = (string) $data['idempotency_key'];
        $canonicalPayload = [
            'provider' => $provider,
            'amount' => $amount,
            'currency' => $currency,
            'destination_account' => $destinationAccount,
            'purpose' => $purpose,
            'description' => $description,
        ];
        $requestFingerprint = hash('sha256', json_encode($canonicalPayload, JSON_THROW_ON_ERROR));

        [$payout, $shouldSubmit] = DB::transaction(function () use (
            $merchant,
            $provider,
            $currency,
            $amount,
            $destinationAccount,
            $purpose,
            $description,
            $idempotencyKey,
            $requestFingerprint,
            $amountInCentavos
        ): array {
            $existing = MerchantPayout::query()
                ->where('merchant_id', $merchant->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof MerchantPayout) {
                return $this->resolveIdempotentPayout($existing, $requestFingerprint);
            }

            $wallets = Wallet::query()
                ->where('merchant_id', $merchant->id)
                ->whereIn('wallet_type', [Wallet::TYPE_MERCHANT_CLEARING, Wallet::TYPE_MERCHANT_REAL])
                ->where('currency', $currency)
                ->orderBy('wallet_type')
                ->lockForUpdate()
                ->get();

            $existing = MerchantPayout::query()
                ->where('merchant_id', $merchant->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof MerchantPayout) {
                return $this->resolveIdempotentPayout($existing, $requestFingerprint);
            }

            $availableCentavos = $wallets->sum(fn (Wallet $wallet): int => $this->toCentavos((string) $wallet->balance));

            if ($availableCentavos < $amountInCentavos) {
                throw new WalletPayoutException('Insufficient wallet balance for this payout.', 422);
            }

            $payout = MerchantPayout::query()->create([
                'merchant_id' => $merchant->id,
                'provider' => $provider,
                'amount' => $amount,
                'currency' => $currency,
                'purpose' => $purpose !== '' ? $purpose : null,
                'description' => $description !== '' ? $description : null,
                'destination_account' => $destinationAccount,
                'status' => 'submitting',
                'reference_number' => 'KP'.strtoupper((string) Str::ulid()),
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $requestFingerprint,
                'submission_started_at' => now(),
            ]);

            $remainingCentavos = $amountInCentavos;
            foreach ($wallets as $wallet) {
                if ($remainingCentavos <= 0) {
                    break;
                }

                $walletBalance = $this->toCentavos((string) $wallet->balance);
                $debitCentavos = min($walletBalance, $remainingCentavos);
                if ($debitCentavos <= 0) {
                    continue;
                }

                $wallet->balance = $this->fromCentavos($walletBalance - $debitCentavos);
                $wallet->save();

                WalletTransaction::query()->create([
                    'wallet_id' => $wallet->id,
                    'payment_id' => null,
                    'direction' => 'debit',
                    'entry_type' => WalletTransaction::ENTRY_MERCHANT_PAYOUT,
                    'amount' => $this->fromCentavos($debitCentavos),
                    'currency' => $currency,
                    'metadata' => [
                        'payout_id' => $payout->id,
                        'reference_number' => $payout->reference_number,
                    ],
                    'is_settled' => true,
                    'settled_at' => now(),
                ]);

                $remainingCentavos -= $debitCentavos;
            }

            return [$payout, true];
        });

        if ($shouldSubmit) {
            return $this->submit($payout);
        }

        return $payout;
    }

    /** @return array<string, mixed> */
    public function receivingInstitutions(string $provider): array
    {
        try {
            return $this->transferService->receivingInstitutions($provider);
        } catch (PayMongoTransferException $exception) {
            throw new WalletPayoutException($exception->getMessage(), 502);
        }
    }

    public function refresh(MerchantPayout $payout): MerchantPayout
    {
        if (in_array($payout->status, ['succeeded', 'failed'], true)) {
            return $payout;
        }

        try {
            $transfer = is_string($payout->paymongo_transfer_id) && $payout->paymongo_transfer_id !== ''
                ? $this->transferService->retrieveTransfer($payout->paymongo_transfer_id)
                : $this->transferService->retrieveTransferByReference($payout->reference_number);

            if ($transfer === null) {
                return $payout->fresh() ?? $payout;
            }

            return $this->applyTransferResult($payout->id, $transfer);
        } catch (PayMongoTransferException $exception) {
            Log::warning('PayMongo payout status refresh failed', [
                'payout_id' => $payout->id,
                'transfer_id' => $payout->paymongo_transfer_id,
                'error' => $exception->getMessage(),
            ]);

            return $payout->fresh() ?? $payout;
        }
    }

    /** @param array<string, mixed> $payload */
    public function processWebhook(array $payload): void
    {
        $event = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $eventAttributes = is_array($event['attributes'] ?? null) ? $event['attributes'] : [];
        $resource = is_array($eventAttributes['data'] ?? null) ? $eventAttributes['data'] : [];
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
        $eventType = strtolower((string) ($eventAttributes['type'] ?? ''));
        $transferId = is_string($resource['id'] ?? null)
            ? $resource['id']
            : (is_string($attributes['transfer_id'] ?? null) ? $attributes['transfer_id'] : null);
        $referenceNumber = is_string($attributes['reference_number'] ?? null) ? $attributes['reference_number'] : null;

        if ($transferId === null && $referenceNumber === null) {
            return;
        }

        $payout = MerchantPayout::query()
            ->where(function ($query) use ($transferId, $referenceNumber): void {
                if ($transferId !== null) {
                    $query->where('paymongo_transfer_id', $transferId);
                }
                if ($referenceNumber !== null) {
                    $method = $transferId === null ? 'where' : 'orWhere';
                    $query->{$method}('reference_number', $referenceNumber);
                }
            })
            ->first();

        if (! $payout instanceof MerchantPayout) {
            return;
        }

        if ($payout->paymongo_transfer_id !== null && $transferId !== null && ! hash_equals($payout->paymongo_transfer_id, $transferId)) {
            return;
        }

        $status = match ($eventType) {
            'transfer.outward.successful' => 'succeeded',
            'transfer.outward.failed' => 'failed',
            default => strtolower((string) ($attributes['status'] ?? 'pending')),
        };
        if (! in_array($status, ['pending', 'succeeded', 'failed'], true)) {
            return;
        }

        $this->applyTransferResult($payout->id, [
            'batch_transfer_id' => $attributes['batch_transfer_id'] ?? $attributes['batch_transaction_id'] ?? null,
            'transfer_id' => $transferId,
            'provider_reference_number' => $attributes['provider_reference_number'] ?? null,
            'reference_number' => $referenceNumber,
            'provider_fee' => is_numeric($attributes['fee'] ?? null) ? round(((float) $attributes['fee']) / 100, 2) : null,
            'status' => $status,
            'failure_code' => $attributes['provider_error_code'] ?? null,
            'failure_message' => $attributes['provider_error'] ?? null,
        ]);
    }

    /** @return array{MerchantPayout, bool} */
    private function resolveIdempotentPayout(MerchantPayout $payout, string $requestFingerprint): array
    {
        if (! hash_equals($payout->request_fingerprint, $requestFingerprint)) {
            throw new WalletPayoutException('This Idempotency-Key has already been used with a different payout request.', 409);
        }

        if (! in_array($payout->status, ['submitting', 'submission_unknown'], true)) {
            return [$payout, false];
        }

        $lastAttempt = $payout->submission_started_at;
        if ($lastAttempt !== null && $lastAttempt->addMinutes(self::SUBMISSION_RETRY_DELAY_MINUTES)->isFuture()) {
            return [$payout, false];
        }

        if ($payout->created_at !== null && $payout->created_at->addHours(self::IDEMPOTENCY_WINDOW_HOURS)->isPast()) {
            throw new WalletPayoutException(
                'Payout submission is still being reconciled. Contact KemoPay support before creating another payout.',
                409
            );
        }

        $payout->update([
            'status' => 'submitting',
            'submission_started_at' => now(),
            'failure_code' => null,
            'failure_message' => null,
        ]);

        return [$payout->fresh() ?? $payout, true];
    }

    private function submit(MerchantPayout $payout): MerchantPayout
    {
        try {
            $result = $this->transferService->createTransfer($payout);
        } catch (PayMongoTransferException $exception) {
            Log::warning('PayMongo merchant payout submission failed', [
                'payout_id' => $payout->id,
                'http_status' => $exception->httpStatus,
                'provider_code' => $exception->providerCode,
                'may_have_been_submitted' => $exception->mayHaveBeenSubmitted,
                'error' => $exception->getMessage(),
            ]);

            if ($exception->mayHaveBeenSubmitted) {
                $payout->update([
                    'status' => 'submission_unknown',
                    'failure_code' => $exception->providerCode,
                    'failure_message' => $exception->getMessage(),
                ]);
            } else {
                DB::transaction(function () use ($payout, $exception): void {
                    $lockedPayout = MerchantPayout::query()->whereKey($payout->id)->lockForUpdate()->first();
                    if (! $lockedPayout instanceof MerchantPayout) {
                        return;
                    }

                    $lockedPayout->update([
                        'status' => 'failed',
                        'failure_code' => $exception->providerCode,
                        'failure_message' => $exception->getMessage(),
                    ]);
                    $this->releaseFunds($lockedPayout);
                });
            }

            return $payout->fresh() ?? $payout;
        }

        return $this->applyTransferResult($payout->id, $result);
    }

    /** @param array<string, mixed> $result */
    private function applyTransferResult(string $payoutId, array $result): MerchantPayout
    {
        return DB::transaction(function () use ($payoutId, $result): MerchantPayout {
            $payout = MerchantPayout::query()->whereKey($payoutId)->lockForUpdate()->first();
            if (! $payout instanceof MerchantPayout) {
                throw new WalletPayoutException('Payout not found.', 404);
            }

            if (in_array($payout->status, ['succeeded', 'failed'], true)) {
                if ($payout->status === 'failed' && $payout->funds_released_at === null) {
                    $this->releaseFunds($payout);
                }

                return $payout->fresh() ?? $payout;
            }

            $status = is_string($result['status'] ?? null) ? $result['status'] : 'pending';
            $payout->update([
                'status' => in_array($status, ['pending', 'succeeded', 'failed'], true) ? $status : 'pending',
                'paymongo_batch_transfer_id' => $result['batch_transfer_id'] ?? $payout->paymongo_batch_transfer_id,
                'paymongo_transfer_id' => $result['transfer_id'] ?? $payout->paymongo_transfer_id,
                'provider_reference_number' => $result['provider_reference_number'] ?? $payout->provider_reference_number,
                'provider_fee' => $result['provider_fee'] ?? $payout->provider_fee,
                'failure_code' => $result['failure_code'] ?? null,
                'failure_message' => $result['failure_message'] ?? null,
                'completed_at' => $status === 'succeeded' ? now() : null,
            ]);

            if ($status === 'failed') {
                $this->releaseFunds($payout);
            }

            return $payout->fresh() ?? $payout;
        });
    }

    private function releaseFunds(MerchantPayout $payout): void
    {
        if ($payout->funds_released_at !== null) {
            return;
        }

        $debits = WalletTransaction::query()
            ->where('entry_type', WalletTransaction::ENTRY_MERCHANT_PAYOUT)
            ->where('direction', 'debit')
            ->where('metadata->payout_id', $payout->id)
            ->lockForUpdate()
            ->get();

        foreach ($debits as $debit) {
            $wallet = Wallet::query()->whereKey($debit->wallet_id)->lockForUpdate()->first();
            if (! $wallet instanceof Wallet) {
                continue;
            }

            $wallet->balance = $this->fromCentavos(
                $this->toCentavos((string) $wallet->balance) + $this->toCentavos((string) $debit->amount)
            );
            $wallet->save();

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'payment_id' => null,
                'direction' => 'credit',
                'entry_type' => WalletTransaction::ENTRY_MERCHANT_PAYOUT_REVERSAL,
                'amount' => $debit->amount,
                'currency' => $debit->currency,
                'metadata' => [
                    'payout_id' => $payout->id,
                    'reversed_transaction_id' => $debit->id,
                    'reference_number' => $payout->reference_number,
                ],
                'is_settled' => true,
                'settled_at' => now(),
            ]);
        }

        $payout->funds_released_at = now();
        $payout->save();
    }

    private function toCentavos(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');
        $whole = $whole === '' ? '0' : $whole;

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function normalizeAmount(string $amount): string
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $matches)) {
            throw new WalletPayoutException('Payout amount must have no more than two decimal places.', 422);
        }

        $wholeAmount = ltrim($matches[1], '0');
        $wholeAmount = $wholeAmount === '' ? '0' : $wholeAmount;
        $fractionalAmount = str_pad($matches[2] ?? '', 2, '0');

        return $wholeAmount.'.'.$fractionalAmount;
    }

    private function fromCentavos(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
