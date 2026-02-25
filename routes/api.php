<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentStatusController;
use Illuminate\Support\Facades\Route;

Route::post('payments', [PaymentController::class, 'create'])
    ->middleware('throttle:api');

Route::get('payments/{id}/status', PaymentStatusController::class)
    ->middleware('throttle:api');
