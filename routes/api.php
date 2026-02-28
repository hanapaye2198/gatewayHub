<?php

use App\Http\Controllers\Api\EnabledGatewaysController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentStatusController;
use App\Http\Controllers\Api\WalletBalanceController;
use Illuminate\Support\Facades\Route;

Route::get('gateways/enabled', EnabledGatewaysController::class)
    ->middleware('throttle:api');

Route::post('payments', [PaymentController::class, 'create'])
    ->middleware('throttle:api');

Route::get('payments/{id}/status', PaymentStatusController::class)
    ->middleware('throttle:api');

$walletSettlementRoutesEnabled = (bool) config('surepay.features.wallet_settlement', false) || app()->environment('testing');
if ($walletSettlementRoutesEnabled) {
    Route::get('wallets/balances', WalletBalanceController::class)
        ->middleware('throttle:api');
}
