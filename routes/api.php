<?php

use App\Http\Controllers\Api\EnabledGatewaysController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentStatusController;
use App\Http\Controllers\Api\WalletBalanceController;
use App\Http\Controllers\Api\WalletPayoutController;
use App\Http\Controllers\Api\WalletTransactionsController;
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

    Route::get('wallets/transactions', WalletTransactionsController::class)
        ->middleware('throttle:api');

    Route::get('wallets/payout-institutions', [WalletPayoutController::class, 'institutions'])
        ->middleware('throttle:api');

    Route::get('wallets/payouts', [WalletPayoutController::class, 'index'])
        ->middleware('throttle:api');

    Route::post('wallets/payouts', [WalletPayoutController::class, 'store'])
        ->middleware('throttle:api');

    Route::get('wallets/payouts/{payout}', [WalletPayoutController::class, 'show'])
        ->middleware('throttle:api');
}
