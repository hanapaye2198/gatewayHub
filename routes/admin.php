<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GatewaysController;
use App\Http\Controllers\Admin\MerchantsController;
use App\Http\Controllers\Admin\PaymentsController;
use App\Http\Controllers\Admin\TunnelWalletsController;
use App\Livewire\Admin\GatewayHub;
use App\Livewire\Admin\MerchantList;
use Illuminate\Support\Facades\Route;

/*
| Admin-only routes. Protected by EnsureAdmin middleware (bootstrap/app.php).
*/

Route::get('/', DashboardController::class)->name('admin.index');
Route::livewire('/merchants', MerchantList::class)->name('admin.merchants.index');
Route::patch('/merchants/{user}', [MerchantsController::class, 'toggleActive'])->name('admin.merchants.toggle');
Route::livewire('/gateways', GatewayHub::class)->name('admin.gateways.index');
Route::patch('/gateways/{gateway}/merchants/{user}', [GatewaysController::class, 'updateMerchantGateway'])->name('admin.gateways.merchant-update');
Route::patch('/gateways/{gateway}/platform-config', [GatewaysController::class, 'updatePlatformConfig'])->name('admin.gateways.platform-config');
Route::patch('/gateways/{gateway}', [GatewaysController::class, 'toggleEnabled'])->name('admin.gateways.toggle');
Route::get('/payments/export', [PaymentsController::class, 'export'])->name('admin.payments.export');
Route::get('/payments', [PaymentsController::class, 'index'])->name('admin.payments.index');

$walletSettlementRoutesEnabled = (bool) config('surepay.features.wallet_settlement', false) || app()->environment('testing');
if ($walletSettlementRoutesEnabled) {
    Route::livewire('/surepay-wallets/dashboard', 'pages::dashboard.tunnel-wallet')->name('admin.surepay-wallets.dashboard');
    Route::get('/surepay-wallets', [TunnelWalletsController::class, 'index'])->name('admin.surepay-wallets.index');
    Route::post('/surepay-wallets/settle-batch', [TunnelWalletsController::class, 'settleBatch'])->name('admin.surepay-wallets.settle-batch');
    Route::patch('/surepay-wallets/surepay-sending-setting', [TunnelWalletsController::class, 'updateSurepaySendingSetting'])->name('admin.surepay-wallets.surepay-sending-setting');
    Route::patch('/surepay-wallets/{user}', [TunnelWalletsController::class, 'updateSetting'])->name('admin.surepay-wallets.update');

    Route::livewire('/tunnel-wallets/dashboard', 'pages::dashboard.tunnel-wallet')->name('admin.tunnel-wallets.dashboard');
    Route::get('/tunnel-wallets', [TunnelWalletsController::class, 'index'])->name('admin.tunnel-wallets.index');
    Route::post('/tunnel-wallets/settle-batch', [TunnelWalletsController::class, 'settleBatch'])->name('admin.tunnel-wallets.settle-batch');
    Route::patch('/tunnel-wallets/tunnel-sending-setting', [TunnelWalletsController::class, 'updateSurepaySendingSetting'])->name('admin.tunnel-wallets.tunnel-sending-setting');
    Route::patch('/tunnel-wallets/surepay-setting', [TunnelWalletsController::class, 'updateSurepaySendingSetting'])->name('admin.tunnel-wallets.surepay-setting');
    Route::patch('/tunnel-wallets/{user}', [TunnelWalletsController::class, 'updateSetting'])->name('admin.tunnel-wallets.update');
}
