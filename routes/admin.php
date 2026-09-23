<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GatewaysController;
use App\Http\Controllers\Admin\MerchantContextController;
use App\Http\Controllers\Admin\MerchantsController;
use App\Http\Controllers\Admin\MerchantUsersController;
use App\Http\Controllers\Admin\PaymentsController;
use App\Http\Controllers\Admin\PlatformAdministratorsController;
use App\Http\Controllers\Admin\PlatformAuditLogsController;
use App\Http\Controllers\Admin\PlatformFeeController;
use App\Http\Controllers\Admin\TunnelWalletsController;
use App\Livewire\Admin\GatewayHub;
use App\Livewire\Admin\MerchantList;
use Illuminate\Support\Facades\Route;

/*
| Admin-only routes. Protected by EnsureAdmin middleware (bootstrap/app.php).
*/

Route::get('/', DashboardController::class)->name('admin.index');
Route::livewire('/merchants', MerchantList::class)->name('admin.merchants.index');
Route::get('/merchants/create', [MerchantsController::class, 'create'])->name('admin.merchants.create');
Route::post('/merchants', [MerchantsController::class, 'store'])->name('admin.merchants.store');
Route::get('/merchants/{merchant}/users', [MerchantUsersController::class, 'index'])->name('admin.merchants.users.index');
Route::get('/merchants/{merchant}/users/create', [MerchantUsersController::class, 'create'])->name('admin.merchants.users.create');
Route::post('/merchants/{merchant}/users', [MerchantUsersController::class, 'store'])->name('admin.merchants.users.store');
Route::get('/merchants/{merchant}/users/{user}/edit', [MerchantUsersController::class, 'edit'])->name('admin.merchants.users.edit')->whereNumber('user');
Route::match(['put', 'patch'], '/merchants/{merchant}/users/{user}', [MerchantUsersController::class, 'update'])->name('admin.merchants.users.update')->whereNumber('user');
Route::patch('/merchants/{merchant}/users/{user}/active', [MerchantUsersController::class, 'toggle'])->name('admin.merchants.users.toggle')->whereNumber('user');
Route::get('/merchants/{merchant}', [MerchantsController::class, 'show'])->name('admin.merchants.show');
Route::get('/merchants/{merchant}/edit', [MerchantsController::class, 'edit'])->name('admin.merchants.edit');
Route::put('/merchants/{merchant}', [MerchantsController::class, 'update'])->name('admin.merchants.update');
Route::patch('/merchants/{merchant}', [MerchantsController::class, 'toggleActive'])->name('admin.merchants.toggle');
Route::post('/merchants/{merchant}/access', [MerchantContextController::class, 'enter'])->name('admin.merchants.access');
Route::post('/merchant-context/exit', [MerchantContextController::class, 'exit'])->name('admin.merchant-context.exit');
Route::livewire('/gateways', GatewayHub::class)->name('admin.gateways.index');
Route::patch('/gateways/{gateway}/merchants/{merchant}', [GatewaysController::class, 'updateMerchantGateway'])->name('admin.gateways.merchant-update');
Route::patch('/gateways/{gateway}/platform-config', [GatewaysController::class, 'updatePlatformConfig'])->name('admin.gateways.platform-config');
Route::patch('/gateways/{gateway}', [GatewaysController::class, 'toggleEnabled'])->name('admin.gateways.toggle');
Route::middleware('super-admin')->group(function (): void {
    Route::get('/administrators', [PlatformAdministratorsController::class, 'index'])->name('admin.administrators.index');
    Route::get('/administrators/create', [PlatformAdministratorsController::class, 'create'])->name('admin.administrators.create');
    Route::post('/administrators', [PlatformAdministratorsController::class, 'store'])->name('admin.administrators.store');
    Route::get('/administrators/{administrator}/edit', [PlatformAdministratorsController::class, 'edit'])->name('admin.administrators.edit')->whereNumber('administrator');
    Route::match(['put', 'patch'], '/administrators/{administrator}', [PlatformAdministratorsController::class, 'update'])->name('admin.administrators.update')->whereNumber('administrator');
    Route::patch('/administrators/{administrator}/active', [PlatformAdministratorsController::class, 'toggle'])->name('admin.administrators.toggle')->whereNumber('administrator');
    Route::get('/platform-fee', [PlatformFeeController::class, 'edit'])->name('admin.platform-fee.edit');
    Route::put('/platform-fee', [PlatformFeeController::class, 'update'])->name('admin.platform-fee.update');
});

Route::get('/audit-logs', [PlatformAuditLogsController::class, 'index'])->name('admin.audit-logs.index');
Route::get('/audit-logs/{platformAuditLog}', [PlatformAuditLogsController::class, 'show'])->name('admin.audit-logs.show')->whereNumber('platformAuditLog');
Route::get('/payments/export', [PaymentsController::class, 'export'])->name('admin.payments.export');
Route::get('/payments', [PaymentsController::class, 'index'])->name('admin.payments.index');

$walletSettlementRoutesEnabled = (bool) config('surepay.features.wallet_settlement', false) || app()->environment('testing');
if ($walletSettlementRoutesEnabled) {
    Route::livewire('/surepay-wallets/dashboard', 'pages::dashboard.tunnel-wallet')->name('admin.surepay-wallets.dashboard');
    Route::get('/surepay-wallets', [TunnelWalletsController::class, 'index'])->name('admin.surepay-wallets.index');
    Route::post('/surepay-wallets/settle-batch', [TunnelWalletsController::class, 'settleBatch'])->name('admin.surepay-wallets.settle-batch');
    Route::patch('/surepay-wallets/surepay-sending-setting', [TunnelWalletsController::class, 'updateSurepaySendingSetting'])->name('admin.surepay-wallets.surepay-sending-setting');
    Route::patch('/surepay-wallets/{merchant}', [TunnelWalletsController::class, 'updateSetting'])->name('admin.surepay-wallets.update');

    Route::livewire('/tunnel-wallets/dashboard', 'pages::dashboard.tunnel-wallet')->name('admin.tunnel-wallets.dashboard');
    Route::get('/tunnel-wallets', [TunnelWalletsController::class, 'index'])->name('admin.tunnel-wallets.index');
    Route::post('/tunnel-wallets/settle-batch', [TunnelWalletsController::class, 'settleBatch'])->name('admin.tunnel-wallets.settle-batch');
    Route::patch('/tunnel-wallets/tunnel-sending-setting', [TunnelWalletsController::class, 'updateSurepaySendingSetting'])->name('admin.tunnel-wallets.tunnel-sending-setting');
    Route::patch('/tunnel-wallets/surepay-setting', [TunnelWalletsController::class, 'updateSurepaySendingSetting'])->name('admin.tunnel-wallets.surepay-setting');
    Route::patch('/tunnel-wallets/{merchant}', [TunnelWalletsController::class, 'updateSetting'])->name('admin.tunnel-wallets.update');
}
