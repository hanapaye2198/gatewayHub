<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Merchant payment list and export share this query.
 * The merchant id is supplied by the caller from the authenticated user, never from request input.
 */
final class MerchantPaymentQuery
{
    public function __construct(private PaymentDisplayStatusResolver $displayStatusResolver) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Payment>
     */
    public function forMerchant(int $merchantId, array $filters = []): Builder
    {
        $gatewayCode = $this->stringFilter($filters, 'gateway_code');
        $status = $this->stringFilter($filters, 'status');
        $reference = $this->stringFilter($filters, 'reference');
        $fromDate = $this->stringFilter($filters, 'from_date');
        $toDate = $this->stringFilter($filters, 'to_date');

        return Payment::query()
            ->where('merchant_id', $merchantId)
            ->when($gatewayCode !== null, static function (Builder $query) use ($gatewayCode): void {
                $query->where('gateway_code', $gatewayCode);
            })
            ->when($status !== null, function (Builder $query) use ($status): void {
                $this->displayStatusResolver->applyFilter($query, $status);
            })
            ->when($reference !== null, static function (Builder $query) use ($reference): void {
                $query->where(static function (Builder $referenceQuery) use ($reference): void {
                    $referenceQuery
                        ->where('reference_id', 'like', '%'.$reference.'%')
                        ->orWhere('provider_reference', 'like', '%'.$reference.'%');
                });
            })
            ->when($fromDate !== null, static function (Builder $query) use ($fromDate): void {
                $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate !== null, static function (Builder $query) use ($toDate): void {
                $query->whereDate('created_at', '<=', $toDate);
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stringFilter(array $filters, string $key): ?string
    {
        if ($key === 'merchant_id' || ! array_key_exists($key, $filters)) {
            return null;
        }

        $value = $filters[$key];
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
