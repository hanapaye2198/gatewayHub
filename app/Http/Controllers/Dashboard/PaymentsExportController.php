<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\FilterMerchantPaymentsRequest;
use App\Models\Payment;
use App\Services\Exports\MerchantPaymentsExcelExporter;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentsExportController extends Controller
{
    public function __construct(private MerchantPaymentsExcelExporter $excelExporter) {}

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

        $workbook = $this->excelExporter->generate($payments);
        $fileName = 'merchant-payments-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($workbook): void {
            echo $workbook;
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
}
