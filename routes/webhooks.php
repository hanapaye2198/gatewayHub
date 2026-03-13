<?php

use App\Http\Controllers\Webhooks\CoinsWebhookController;
use App\Http\Controllers\Webhooks\WebhookIngressController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:webhooks')->group(function (): void {
    Route::post('webhooks/coins', [CoinsWebhookController::class, 'handle']);
    Route::post('webhooks', WebhookIngressController::class);
});
