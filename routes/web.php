<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('dashboard/payments', 'pages::dashboard.payments')->name('dashboard.payments');
});

require __DIR__.'/settings.php';
