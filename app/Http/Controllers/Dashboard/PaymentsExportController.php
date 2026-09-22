<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\FilterMerchantPaymentsRequest;
use App\Services\Exports\MerchantPaymentsExcelExporter;
use App\Services\Payments\MerchantPaymentQuery;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentsExportController extends Controller
{
    public function __construct(
        private MerchantPaymentsExcelExporter $excelExporter,
        private MerchantPaymentQuery $merchantPaymentQuery,
    ) {}

    public function __invoke(FilterMerchantPaymentsRequest $request): StreamedResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $merchantId = $user->merchant_id;
        if ($merchantId === null) {
            abort(403);
        }

        $payments = $this->merchantPaymentQuery
            ->forMerchant((int) $merchantId, $request->validated())
            ->with([
                'gateway:code,name',
                'platformFee:id,payment_id,fee_amount,fee_rate,net_amount',
                'webhookEvents' => static fn (HasMany $query) => $query->orderByDesc('received_at'),
            ])
            ->latest('created_at')
            ->lazy(200);

        $workbook = $this->excelExporter->generate($payments);
        $fileName = 'transactions_'.now()->format('Y-m-d_H-i-s').'.xlsx';

        return response()->streamDownload(function () use ($workbook): void {
            echo $workbook;
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
