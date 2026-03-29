<?php

namespace App\Http\Controllers;

use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $gateways = Gateway::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_global_enabled']);

        $gatewayTotal = $gateways->count();
        $enabledGatewayTotal = $gateways->where('is_global_enabled', true)->count();
        $merchantTotal = Merchant::query()->count();
        $paidCollections = (float) Payment::query()
            ->where('status', 'paid')
            ->sum('amount');

        return view('welcome', [
            'stats' => [
                'gateway_total' => $gatewayTotal,
                'enabled_gateway_total' => $enabledGatewayTotal,
                'merchant_total' => $merchantTotal,
                'paid_collections' => $paidCollections,
            ],
            'previewGateways' => $gateways->take(5)->values(),
            'supportedGatewayNames' => $gateways->pluck('name')->values(),
        ]);
    }
}
