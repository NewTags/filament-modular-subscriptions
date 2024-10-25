<?php

namespace HoceineEl\FilamentModularSubscriptions\Http\Controllers;

use Filament\Notifications\Notification;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Payment;
use HoceineEl\FilamentModularSubscriptions\Pages\TenantSubscription;
use HoceineEl\FilamentModularSubscriptions\Services\Payments\PaypalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController
{
    public function __construct(
        protected PaypalService $paypalService
    ) {}

    public function success(Request $request)
    {
        try {
            $orderId = $request->get('token');
            $payment = Payment::where('metadata->order_id', $orderId)->firstOrFail();

            $captureResult = $this->paypalService->capturePayment($orderId);

            $payment->update([
                'status' => PaymentStatus::PAID,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'capture_id' => $captureResult['id'],
                    'capture_status' => $captureResult['status'],
                ]),
            ]);

            $payment->invoice->update([
                'status' => PaymentStatus::PAID,
                'paid_at' => now(),
            ]);

            Notification::make()
                ->title(__('filament-modular-subscriptions::modular-subscriptions.payment.success'))
                ->success()
                ->send();

            return redirect(TenantSubscription::getUrl());
        } catch (\Exception $e) {
            Log::error('PayPal payment capture failed: ' . $e->getMessage());

            Notification::make()
                ->title(__('filament-modular-subscriptions::modular-subscriptions.payment.error'))
                ->danger()
                ->send();

            return redirect(TenantSubscription::getUrl());
        }
    }

    public function cancel()
    {
        Notification::make()
            ->title(__('filament-modular-subscriptions::modular-subscriptions.payment.cancelled'))
            ->info()
            ->send();

        return redirect(TenantSubscription::getUrl());
    }
}
