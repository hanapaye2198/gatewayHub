<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformFeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PlatformFee;
use Illuminate\Contracts\View\View;

class PaymentsController extends Controller
{
    public function index(): View
    {
        $payments = Payment::query()
            ->with(['user', 'gateway', 'platformFee'])
            ->latest('created_at')
            ->paginate(25);

        $totalPlatformRevenue = PlatformFee::query()
            ->where('status', PlatformFeeStatus::Posted)
            ->sum('fee_amount');

        return view('admin.payments.index', [
            'title' => 'Payments',
            'payments' => $payments,
            'totalPlatformRevenue' => $totalPlatformRevenue,
        ]);
    }
}
