<?php

use App\Http\Controllers\Webhooks\CoinsWebhookController;
use App\Http\Controllers\Webhooks\GcashWebhookController;
use App\Http\Controllers\Webhooks\MayaWebhookController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/coins', [CoinsWebhookController::class, 'handle']);
Route::post('webhooks/gcash', [GcashWebhookController::class, 'handle']);
Route::post('webhooks/maya', [MayaWebhookController::class, 'handle']);
Route::post('webhooks/paypal', [PayPalWebhookController::class, 'handle']);
