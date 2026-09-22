<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\FilterMerchantPaymentsRequest;
use App\Services\Exports\MerchantPaymentsExcelExporter;
use App\Services\Payments\MerchantPaymentQuery;
use App\Support\MerchantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MerchantPaymentReportController extends Controller
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

        $merchantId = app(MerchantContext::class)->id();
        if ($merchantId === null) {
            abort(403);
        }

        $query = $this->merchantPaymentQuery->forMerchant((int) $merchantId, $request->validated());
        $summary = $this->merchantPaymentQuery->reportSummary($query);

        $payments = (clone $query)
            ->select([
                'id',
                'merchant_id',
                'gateway_code',
                'reference_id',
                'amount',
                'currency',
                'status',
                'platform_fee',
                'net_amount',
                'created_at',
                'paid_at',
            ])
            ->with([
                'gateway:code,name',
                'platformFee:id,payment_id,fee_amount,fee_rate,net_amount',
            ])
            ->latest('created_at')
            ->lazy(200);

        $workbook = $this->excelExporter->generateReport(
            $payments,
            $summary,
            (string) (app(MerchantContext::class)->merchant()?->name ?? ''),
        );
        $fileName = 'payment_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';

        return response()->streamDownload(function () use ($workbook): void {
            echo $workbook;
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
