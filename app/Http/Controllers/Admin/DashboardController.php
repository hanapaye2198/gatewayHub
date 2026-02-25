<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformFeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $totalPayments = Payment::query()->count();
        $totalGrossProcessed = (float) Payment::query()->where('status', 'paid')->sum('amount');
        $platformRevenue = (float) PlatformFee::query()
            ->where('status', PlatformFeeStatus::Posted)
            ->sum('fee_amount');
        $totalNetVolume = (float) Payment::query()->where('status', 'paid')->sum('net_amount');
        $activeMerchants = User::query()
            ->where('role', 'merchant')
            ->where('is_active', true)
            ->count();

        $revenueByGateway = PlatformFee::query()
            ->where('status', PlatformFeeStatus::Posted)
            ->selectRaw('gateway_code, SUM(fee_amount) as total')
            ->groupBy('gateway_code')
            ->orderByDesc('total')
            ->pluck('total', 'gateway_code')
            ->map(fn ($v) => (float) $v)
            ->all();

        return view('admin.dashboard', [
            'title' => 'Dashboard',
            'totalPayments' => $totalPayments,
            'totalGrossProcessed' => $totalGrossProcessed,
            'platformRevenue' => $platformRevenue,
            'totalNetVolume' => $totalNetVolume,
            'activeMerchants' => $activeMerchants,
            'revenueByGateway' => $revenueByGateway,
        ]);
    }
}
