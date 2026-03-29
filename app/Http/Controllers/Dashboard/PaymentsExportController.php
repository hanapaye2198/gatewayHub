<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\FilterMerchantPaymentsRequest;
use App\Models\Payment;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentsExportController extends Controller
{
    public function __invoke(FilterMerchantPaymentsRequest $request): StreamedResponse
    {
        $merchant = $request->user();
        if ($merchant === null) {
            abort(401);
        }

        $filters = $request->validated();
        $mid = $merchant->merchant_id;
        if ($mid === null) {
            abort(403);
        }

        $payments = $this->buildFilteredPaymentsQuery($mid, $filters)
            ->with(['gateway:code,name', 'platformFee:id,payment_id,fee_amount,net_amount'])
            ->latest('created_at')
            ->get();

        $fileName = 'merchant-payments-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($payments): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM improves Excel compatibility on Windows.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Created At',
                'Reference',
                'Provider Reference',
                'Gateway',
                'Amount',
                'Currency',
                'Platform Fee',
                'Net Amount',
                'Status',
            ]);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $this->sanitizeCsvCell($this->formatCsvDate($payment->created_at)),
                    $this->sanitizeCsvCell($payment->reference_id),
                    $this->sanitizeCsvCell($payment->provider_reference ?? ''),
                    $this->sanitizeCsvCell($payment->gateway?->name ?? $payment->gateway_code),
                    number_format((float) $payment->amount, 2, '.', ''),
                    $this->sanitizeCsvCell($payment->currency),
                    $payment->platformFee !== null ? number_format((float) $payment->platformFee->fee_amount, 2, '.', '') : '',
                    $payment->platformFee !== null ? number_format((float) $payment->platformFee->net_amount, 2, '.', '') : '',
                    $this->sanitizeCsvCell($payment->status),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Payment>
     */
    private function buildFilteredPaymentsQuery(int $merchantId, array $filters): Builder
    {
        return Payment::query()
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['gateway_code']), static function (Builder $query) use ($filters): void {
                $query->where('gateway_code', (string) $filters['gateway_code']);
            })
            ->when(isset($filters['status']), static function (Builder $query) use ($filters): void {
                $query->where('status', (string) $filters['status']);
            })
            ->when(isset($filters['reference']), static function (Builder $query) use ($filters): void {
                $reference = (string) $filters['reference'];
                $query->where(static function (Builder $referenceQuery) use ($reference): void {
                    $referenceQuery
                        ->where('reference_id', 'like', '%'.$reference.'%')
                        ->orWhere('provider_reference', 'like', '%'.$reference.'%');
                });
            })
            ->when(isset($filters['from_date']), static function (Builder $query) use ($filters): void {
                $query->whereDate('created_at', '>=', (string) $filters['from_date']);
            })
            ->when(isset($filters['to_date']), static function (Builder $query) use ($filters): void {
                $query->whereDate('created_at', '<=', (string) $filters['to_date']);
            });
    }

    private function formatCsvDate(?DateTimeInterface $date): string
    {
        return $date?->format('Y-m-d H:i:s') ?? '';
    }

    private function sanitizeCsvCell(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $firstCharacter = substr($trimmed, 0, 1);
        if (in_array($firstCharacter, ['=', '+', '-', '@'], true)) {
            return "'".$trimmed;
        }

        return $trimmed;
    }
}
