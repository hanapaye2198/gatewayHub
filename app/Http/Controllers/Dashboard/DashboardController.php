<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $merchantId = auth()->id();
        $totalCollections = 0.0;

        if (is_int($merchantId)) {
            $totalCollections = (float) Payment::query()
                ->where('user_id', $merchantId)
                ->where('status', 'paid')
                ->sum('amount');
        }

        return view('dashboard', [
            'totalCollections' => $totalCollections,
        ]);
    }
}
