<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CoinsQrController;
use App\Http\Controllers\Dashboard\CreatePaymentController;
use App\Http\Controllers\Dashboard\PaymentDetailController;
use App\Http\Controllers\Dashboard\PaymentsExportController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

Route::get('/', HomeController::class)->name('home');

Route::get('/demo/checkout', fn () => view('demo.checkout'))->name('demo.checkout');

Route::get('/coins/qr', fn () => view('coins.qr'))->name('coins.qr');
Route::post('/coins/generate-qr', [CoinsQrController::class, 'generate'])->name('coins.generate-qr');

Route::middleware(['auth', 'verified', \App\Http\Middleware\EnsureMerchant::class])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.payments')->name('dashboard');
    Route::livewire('dashboard/payments', 'pages::dashboard.payments')->name('dashboard.payments');
    Route::get('dashboard/payments/create', [CreatePaymentController::class, 'create'])->name('dashboard.payments.create');
    Route::post('dashboard/payments', [CreatePaymentController::class, 'store'])->name('dashboard.payments.store');
    Route::get('dashboard/payments/export', PaymentsExportController::class)->name('dashboard.payments.export');
    Route::livewire('dashboard/api-credentials', 'pages::dashboard.api-credentials')->name('dashboard.api-credentials');
    Route::livewire('dashboard/gateways', 'pages::dashboard.gateways')->name('dashboard.gateways');
    Route::livewire('dashboard/docs', 'pages::dashboard.docs')->name('dashboard.docs');
    Route::get('dashboard/payments/{payment}', [PaymentDetailController::class, '__invoke'])->name('dashboard.payments.show');
    Route::get('dashboard/payments/{payment}/status', [PaymentDetailController::class, 'status'])->name('dashboard.payments.status');
});

require __DIR__.'/settings.php';
