<?php

namespace App\Services\Billing;

use App\Exceptions\PayMongoTransferException;
use App\Models\MerchantPayout;
use App\Services\Gateways\PlatformGatewayConfigService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PayMongoTransferService
{
    private const API_BASE_URL = 'https://api.paymongo.com';

    public function __construct(private readonly PlatformGatewayConfigService $gatewayConfigService) {}

    /** @return array<string, mixed> */
    public function receivingInstitutions(string $provider): array
    {
        $secretKey = $this->secretKey();

        try {
            $response = $this->client($secretKey)
                ->get(self::API_BASE_URL.'/v1/wallets/receiving_institutions', ['provider' => $provider]);
        } catch (ConnectionException $exception) {
            throw new PayMongoTransferException('PayMongo could not be reached. Try again shortly.', false, null, null, $exception);
        }

        return $this->responseData($response, false);
    }

    /** @return array<string, mixed> */
    public function createTransfer(MerchantPayout $payout): array
    {
        $secretKey = $this->secretKey();
        $sourceAccount = $this->sourceAccount($secretKey);
        $destinationAccount = $payout->destination_account;
        $amountInCentavos = $this->amountInCentavos((string) $payout->amount);

        $destination = [
            'number' => preg_replace('/[\s-]+/', '', $destinationAccount['number']),
            'name' => $destinationAccount['name'],
            'bic' => $destinationAccount['bic'],
        ];
        if (is_string($destinationAccount['bank_name'] ?? null) && $destinationAccount['bank_name'] !== '') {
            $destination['bank_name'] = $destinationAccount['bank_name'];
        }

        $transfer = [
            'provider' => $payout->provider,
            'amount' => $amountInCentavos,
            'currency' => $payout->currency,
            'purpose' => $payout->purpose ?? 'Merchant payout',
            'description' => $payout->description ?? 'KemoPay merchant wallet payout',
            'reference_number' => $payout->reference_number,
            'source_account' => $sourceAccount,
            'destination_account' => $destination,
            'metadata' => [
                'kemopay_payout_id' => $payout->id,
                'kemopay_merchant_id' => (string) $payout->merchant_id,
            ],
        ];
        $callbackUrl = trim((string) config('paymongo.payouts.callback_url', ''));
        if ($callbackUrl !== '') {
            $transfer['callback_url'] = $callbackUrl;
        }
        $payload = ['transfers' => [$transfer]];

        try {
            $response = $this->client($secretKey)
                ->withHeader('Idempotency-Key', hash('sha256', 'kemopay:payout:'.$payout->id))
                ->post(self::API_BASE_URL.'/v2/batch_transfers', $payload);
        } catch (ConnectionException $exception) {
            throw new PayMongoTransferException(
                'PayMongo did not confirm the payout request. The reserved amount is held while the request is reconciled.',
                true,
                null,
                null,
                $exception
            );
        }

        $body = $this->responseData($response, true);
        $transfer = data_get($body, 'data.transfers.0');
        if (! is_array($transfer) || ! is_string($transfer['id'] ?? null) || $transfer['id'] === '') {
            throw new PayMongoTransferException(
                'PayMongo accepted the payout request but returned no transfer ID. The reserved amount is held for reconciliation.',
                true,
                null,
                $response->status()
            );
        }

        return $this->normalizeTransfer($body, $transfer);
    }

    /** @return array<string, mixed> */
    public function retrieveTransfer(string $transferId): array
    {
        $secretKey = $this->secretKey();

        try {
            $response = $this->client($secretKey)
                ->get(self::API_BASE_URL.'/v2/transfers/'.rawurlencode($transferId));
        } catch (ConnectionException $exception) {
            throw new PayMongoTransferException('PayMongo could not be reached. Try again shortly.', false, null, null, $exception);
        }

        $body = $this->responseData($response, false);
        $transfer = data_get($body, 'data');
        if (! is_array($transfer)) {
            throw new PayMongoTransferException('PayMongo returned an incomplete transfer status.', false, null, $response->status());
        }

        if (is_array($transfer['attributes'] ?? null)) {
            $transfer = array_merge($transfer['attributes'], ['id' => $transfer['id'] ?? $transferId]);
        }

        return $this->normalizeTransfer($body, $transfer);
    }

    /** @return array<string, mixed>|null */
    public function retrieveTransferByReference(string $referenceNumber): ?array
    {
        $secretKey = $this->secretKey();

        try {
            $response = $this->client($secretKey)->get(self::API_BASE_URL.'/v2/transfers', [
                'reference_number' => $referenceNumber,
            ]);
        } catch (ConnectionException $exception) {
            throw new PayMongoTransferException('PayMongo could not be reached. Try again shortly.', false, null, null, $exception);
        }

        $body = $this->responseData($response, false);
        $transfers = data_get($body, 'data.transfers', data_get($body, 'data', []));
        if (! is_array($transfers)) {
            return null;
        }
        if (isset($transfers['id'])) {
            $transfers = [$transfers];
        }

        foreach ($transfers as $transfer) {
            if (is_array($transfer)) {
                return $this->normalizeTransfer($body, $transfer);
            }
        }

        return null;
    }

    private function secretKey(): string
    {
        $secretKey = trim((string) ($this->gatewayConfigService->forGatewayCode('paymongo')['secret_key'] ?? ''));
        if (! preg_match('/^sk_(test|live)_.+$/', $secretKey)) {
            throw new PayMongoTransferException('PayMongo secret API key is not configured for wallet payouts.');
        }

        return $secretKey;
    }

    /** @return array<string, string> */
    private function sourceAccount(string $secretKey): array
    {
        $configuredNumber = trim((string) config('paymongo.payouts.source_account_number', ''));
        $configuredName = trim((string) config('paymongo.payouts.source_account_name', ''));
        if ($configuredNumber !== '' && $configuredName !== '') {
            return [
                'number' => $configuredNumber,
                'name' => $configuredName,
                'bic' => 'PAEYPHM2XXX',
            ];
        }

        try {
            $response = $this->client($secretKey)->get(self::API_BASE_URL.'/v2/wallets', [
                'status' => 'activated',
                'fields' => 'account',
            ]);
        } catch (ConnectionException $exception) {
            throw new PayMongoTransferException('Unable to retrieve the activated PayMongo wallet account.', false, null, null, $exception);
        }

        $body = $this->responseData($response, false);
        $wallets = data_get($body, 'data', []);
        if (! is_array($wallets)) {
            $wallets = [];
        }
        if (isset($wallets['id'])) {
            $wallets = [$wallets];
        }

        foreach ($wallets as $wallet) {
            if (! is_array($wallet)) {
                continue;
            }

            $attributes = is_array($wallet['attributes'] ?? null) ? $wallet['attributes'] : [];
            $account = $attributes['source_account']
                ?? $attributes['account']
                ?? data_get($attributes, 'accounts.0')
                ?? (isset($attributes['account_number']) ? $attributes : null)
                ?? $wallet['source_account']
                ?? $wallet['account']
                ?? null;
            if (! is_array($account)) {
                continue;
            }

            $number = $account['number'] ?? $account['account_number'] ?? null;
            $name = $account['name'] ?? $account['account_name'] ?? null;
            if (! is_string($number) || trim($number) === '' || ! is_string($name) || trim($name) === '') {
                continue;
            }

            return [
                'number' => trim($number),
                'name' => trim($name),
                'bic' => 'PAEYPHM2XXX',
            ];
        }

        throw new PayMongoTransferException('No activated PayMongo wallet source account was found.');
    }

    private function client(string $secretKey): PendingRequest
    {
        return Http::withBasicAuth($secretKey, '')
            ->connectTimeout(3)
            ->timeout(15)
            ->acceptJson()
            ->asJson();
    }

    /** @return array<string, mixed> */
    private function responseData(Response $response, bool $mayHaveBeenSubmitted): array
    {
        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful()) {
            $providerCode = data_get($body, 'errors.0.code');
            $providerMessage = data_get($body, 'errors.0.detail')
                ?? data_get($body, 'errors.0.code')
                ?? 'PayMongo rejected the request.';
            $uncertain = $mayHaveBeenSubmitted && ($response->serverError() || $response->status() === 409);

            throw new PayMongoTransferException(
                is_string($providerMessage) ? $providerMessage : 'PayMongo rejected the request.',
                $uncertain,
                is_string($providerCode) ? $providerCode : null,
                $response->status()
            );
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $transfer
     * @return array<string, mixed>
     */
    private function normalizeTransfer(array $body, array $transfer): array
    {
        $fee = $transfer['fee'] ?? null;
        $normalizedStatus = strtolower((string) ($transfer['status'] ?? 'pending'));

        return [
            'batch_transfer_id' => is_string(data_get($body, 'data.id')) ? data_get($body, 'data.id') : null,
            'transfer_id' => is_string($transfer['id'] ?? null) ? $transfer['id'] : null,
            'provider_reference_number' => is_string($transfer['provider_reference_number'] ?? null)
                ? $transfer['provider_reference_number']
                : null,
            'reference_number' => is_string($transfer['reference_number'] ?? null) ? $transfer['reference_number'] : null,
            'provider_fee' => is_numeric($fee) ? round(((float) $fee) / 100, 2) : null,
            'status' => in_array($normalizedStatus, ['pending', 'succeeded', 'failed'], true) ? $normalizedStatus : 'pending',
            'failure_code' => is_string($transfer['provider_error_code'] ?? null) && $transfer['provider_error_code'] !== ''
                ? $transfer['provider_error_code']
                : null,
            'failure_message' => is_string($transfer['provider_error'] ?? null) && $transfer['provider_error'] !== ''
                ? $transfer['provider_error']
                : null,
        ];
    }

    private function amountInCentavos(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $matches)) {
            throw new PayMongoTransferException('Payout amount must have no more than two decimal places.');
        }

        $wholeAmount = ltrim($matches[1], '0');
        $wholeAmount = $wholeAmount === '' ? '0' : $wholeAmount;
        $fractionalAmount = str_pad($matches[2] ?? '', 2, '0');
        $centavos = $wholeAmount.$fractionalAmount;

        if (strlen($centavos) > strlen((string) PHP_INT_MAX)) {
            throw new PayMongoTransferException('Payout amount is outside the supported range.');
        }

        return (int) $centavos;
    }
}
