<?php

namespace App\Http\Controllers;

use App\Http\Responses\Fortify\PostLoginRedirect;
use App\Models\Gateway;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

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
        $paidPaymentsCount = Payment::query()
            ->where('status', 'paid')
            ->count();

        $user = auth()->user();
        $dashboardUrl = $user instanceof User ? PostLoginRedirect::path($user) : null;

        return view('welcome', [
            'stats' => [
                'gateway_total' => $gatewayTotal,
                'enabled_gateway_total' => $enabledGatewayTotal,
                'merchant_total' => $merchantTotal,
                'paid_collections' => $paidCollections,
                'paid_payments_count' => $paidPaymentsCount,
            ],
            'previewGateways' => $gateways->take(5)->values(),
            'supportedGatewayNames' => $gateways->pluck('name')->values(),
            'enabledGatewayCodes' => $gateways->where('is_global_enabled', true)->pluck('code')->values(),
            'gatewayCatalog' => $this->gatewayCatalog(),
            'isOperational' => $this->isOperational(),
            'dashboardUrl' => $dashboardUrl,
        ]);
    }

    /**
     * @return list<array{code: string, name: string, color: string, tag: string, logo: ?string}>
     */
    private function gatewayCatalog(): array
    {
        return [
            ['code' => 'gcash', 'name' => 'GCash', 'color' => '#007DFE', 'tag' => 'E-wallet', 'logo' => null],
            ['code' => 'maya', 'name' => 'Maya', 'color' => '#00B14F', 'tag' => 'E-wallet', 'logo' => null],
            ['code' => 'paypal', 'name' => 'PayPal', 'color' => '#003087', 'tag' => 'International', 'logo' => null],
            ['code' => 'coins', 'name' => 'Coins.ph', 'color' => '#F7931A', 'tag' => 'Crypto & fiat', 'logo' => null],
            ['code' => 'qrph', 'name' => 'QRPh', 'color' => '#204884', 'tag' => 'QR payments', 'logo' => 'images/logos/qrph.svg'],
        ];
    }

    private function isOperational(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
