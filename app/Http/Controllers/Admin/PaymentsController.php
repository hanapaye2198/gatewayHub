<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformFeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAdminPaymentsRequest;
use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Services\Exports\MerchantPaymentsExcelExporter;
use App\Services\Exports\MerchantPaymentsZipExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentsController extends Controller
{
    public function __construct(
        private MerchantPaymentsExcelExporter $excelExporter,
        private MerchantPaymentsZipExporter $zipExporter,
    ) {}

    public function index(FilterAdminPaymentsRequest $request): View
    {
        $filters = $request->validated();

        $filteredPaymentsQuery = $this->buildFilteredPaymentsQuery($filters);

        $payments = (clone $filteredPaymentsQuery)
            ->with(['merchant', 'gateway', 'platformFee'])
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'total_transactions' => (clone $filteredPaymentsQuery)->count(),
            'paid_collections' => (float) (clone $filteredPaymentsQuery)
                ->where('status', 'paid')
                ->sum('amount'),
            'pending_count' => (clone $filteredPaymentsQuery)
                ->where('status', 'pending')
                ->count(),
            'failed_refunded_count' => (clone $filteredPaymentsQuery)
                ->whereIn('status', ['failed', 'refunded', 'failed_after_paid'])
                ->count(),
        ];

        $totalPlatformRevenue = PlatformFee::query()
            ->where('status', PlatformFeeStatus::Posted)
            ->sum('fee_amount');

        $merchants = Merchant::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $gateways = Gateway::query()
            ->orderBy('name')
            ->get(['code', 'name']);

        return view('admin.payments.index', [
            'title' => 'Payments',
            'payments' => $payments,
            'totalPlatformRevenue' => $totalPlatformRevenue,
            'summary' => $summary,
            'merchants' => $merchants,
            'gateways' => $gateways,
            'statuses' => ['pending', 'paid', 'failed', 'refunded', 'failed_after_paid'],
            'activeFilters' => [
                'merchant_id' => $filters['merchant_id'] ?? null,
                'gateway_code' => $filters['gateway_code'] ?? null,
                'status' => $filters['status'] ?? null,
                'reference' => $filters['reference'] ?? null,
                'from_date' => $filters['from_date'] ?? null,
                'to_date' => $filters['to_date'] ?? null,
            ],
        ]);
    }

    public function export(FilterAdminPaymentsRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        $payments = $this->buildFilteredPaymentsQuery($filters)
            ->with(['gateway:code,name', 'platformFee:id,payment_id,fee_amount,net_amount'])
            ->latest('created_at')
            ->get()
            ->groupBy('merchant_id');

        $merchants = Merchant::query()
            ->when(isset($filters['merchant_id']), static function (Builder $query) use ($filters): void {
                $query->where('id', (int) $filters['merchant_id']);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        if (isset($filters['merchant_id'])) {
            $merchant = $merchants->firstOrFail();
            $workbook = $this->excelExporter->generate($payments->get($merchant->id, new EloquentCollection));
            $fileName = $this->merchantFileName((string) $merchant->name, (int) $merchant->id);

            return response()->streamDownload(function () use ($workbook): void {
                echo $workbook;
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        $merchantFiles = [];
        foreach ($merchants as $merchant) {
            $merchantFiles[(int) $merchant->id] = [
                'name' => (string) $merchant->name,
                'payments' => $payments->get($merchant->id, new EloquentCollection),
            ];
        }

        $archive = $this->zipExporter->generate($merchantFiles);
        $fileName = 'merchant-payments-'.now()->format('Ymd-His').'.zip';

        return response()->streamDownload(function () use ($archive): void {
            echo $archive;
        }, $fileName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Payment>
     */
    private function buildFilteredPaymentsQuery(array $filters): Builder
    {
        return Payment::query()
            ->when(isset($filters['merchant_id']), static function (Builder $query) use ($filters): void {
                $query->where('merchant_id', (int) $filters['merchant_id']);
            })
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

    private function merchantFileName(string $merchantName, int $merchantId): string
    {
        $slug = Str::slug($merchantName);

        return 'merchant-'.($slug === '' ? 'transactions' : $slug).'-'.$merchantId.'-transactions.xlsx';
    }
}
