<?php

use HoceineEl\FilamentModularSubscriptions\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('payment/paypal/success', [PaymentController::class, 'success'])->name('paypal.success');
    Route::get('payment/paypal/cancel', [PaymentController::class, 'cancel'])->name('paypal.cancel');
});
