<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GatewaysController;
use App\Http\Controllers\Admin\MerchantsController;
use App\Http\Controllers\Admin\PaymentsController;
use Illuminate\Support\Facades\Route;

/*
| Admin-only routes. Protected by EnsureAdmin middleware (bootstrap/app.php).
*/

Route::get('/', DashboardController::class)->name('admin.index');
Route::get('/merchants', [MerchantsController::class, 'index'])->name('admin.merchants.index');
Route::patch('/merchants/{user}', [MerchantsController::class, 'toggleActive'])->name('admin.merchants.toggle');
Route::get('/gateways', [GatewaysController::class, 'index'])->name('admin.gateways.index');
Route::patch('/gateways/{gateway}', [GatewaysController::class, 'toggleEnabled'])->name('admin.gateways.toggle');
Route::get('/payments', [PaymentsController::class, 'index'])->name('admin.payments.index');
