<?php

use Illuminate\Support\Facades\Route;
use NewTags\FilamentModularSubscriptions\Http\Controllers\PaymentCallbackController;
use NewTags\FilamentModularSubscriptions\Http\Controllers\PaymentWebhookController;
use NewTags\FilamentModularSubscriptions\Http\Controllers\PublicPaymentController;

/*
 * The webhook is deliberately registered WITHOUT the `web` middleware group:
 * it is a stateless server-to-server endpoint authenticated by its HMAC
 * signature, so no session or CSRF layer should apply.
 */
Route::post('fms/payments/webhook/{gateway}', PaymentWebhookController::class)
    ->middleware('throttle:60,1')
    ->where('gateway', '[a-z0-9_-]+')
    ->name('fms.payments.webhook');

Route::get('fms/payments/callback/{checkout}', PaymentCallbackController::class)
    ->middleware(['web', 'throttle:30,1'])
    ->name('fms.payments.callback');

Route::get('fms/pay/{invoice}', PublicPaymentController::class)
    ->middleware(['web', 'signed', 'throttle:15,1'])
    ->whereNumber('invoice')
    ->name('fms.pay');
