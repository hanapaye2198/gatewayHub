<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CoinsQrController;
use App\Http\Controllers\Dashboard\CreatePaymentController;
use App\Http\Controllers\Dashboard\PaymentDetailController;
use App\Http\Controllers\Dashboard\PaymentsExportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Merchant\MerchantLogoController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

Route::get('/', HomeController::class)->name('home');
Route::get('/health', fn () => response('ok', 200))->name('health');

Route::get('/payment/success/{transaction}', [PaymentRedirectController::class, 'success'])->name('payment.success');
Route::get('/payment/failure/{transaction}', [PaymentRedirectController::class, 'failure'])->name('payment.failure');
Route::get('/payment/cancel/{transaction}', [PaymentRedirectController::class, 'cancel'])->name('payment.cancel');
Route::get('/payment/default/{transaction}', [PaymentRedirectController::class, 'default'])->name('payment.default');

Route::get('/demo/checkout', fn () => view('demo.checkout'))->name('demo.checkout');

Route::get('/coins/qr', fn () => view('coins.qr'))->name('coins.qr');
Route::post('/coins/generate-qr', [CoinsQrController::class, 'generate'])->name('coins.generate-qr');

Route::middleware(['auth', 'verified'])->prefix('onboarding')->name('onboarding.')->group(function (): void {
    Route::get('business', [OnboardingController::class, 'business'])->name('business');
    Route::post('business', [OnboardingController::class, 'storeBusiness'])->name('business.store');
    Route::get('gateways', [OnboardingController::class, 'gateways'])->name('gateways');
    Route::post('gateways', [OnboardingController::class, 'storeGateways'])->name('gateways.store');
    Route::get('api-keys', [OnboardingController::class, 'apiKeys'])->name('api-keys');
    Route::post('complete', [OnboardingController::class, 'complete'])->name('complete');
});

Route::middleware(['auth', 'verified', 'merchant.onboarding', \App\Http\Middleware\EnsureMerchant::class])->group(function () {
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

    Route::post('merchant/logo', [MerchantLogoController::class, 'store'])->name('merchant.logo');
});

require __DIR__.'/settings.php';
