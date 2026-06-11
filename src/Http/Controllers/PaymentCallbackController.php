<?php

namespace NewTags\FilamentModularSubscriptions\Http\Controllers;

use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NewTags\FilamentModularSubscriptions\Enums\PaymentCheckoutStatus;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Payments\SettlementService;
use Throwable;

/**
 * Where the payer lands after the hosted checkout. Settles from the gateway
 * API (idempotently — the webhook may already have won the race) and then
 * either bounces back into the panel with a notification or renders the
 * standalone result page for public pay-link visitors.
 */
class PaymentCallbackController
{
    public function __construct(
        protected SettlementService $settlement,
    ) {}

    public function __invoke(Request $request, PaymentCheckout $checkout)
    {
        $tapId = (string) $request->query('tap_id', '');

        if (filled($tapId) && filled($checkout->charge_id) && $tapId !== $checkout->charge_id) {
            Log::warning('fms.payments.callback_charge_mismatch', [
                'checkout' => $checkout->uuid,
                'expected' => $checkout->charge_id,
                'received' => $tapId,
            ]);
        }

        try {
            $this->settlement->settleFromGateway($checkout);
        } catch (Throwable $exception) {
            Log::error('fms.payments.callback_settlement_error', [
                'checkout' => $checkout->uuid,
                'error' => $exception->getMessage(),
            ]);
        }

        $checkout->refresh();

        $state = $this->presentState($checkout);

        if (filled($checkout->return_url)) {
            Notification::make()
                ->title($state['title'])
                ->body($state['body'])
                ->{$state['kind']}()
                ->send();

            return redirect()->to($checkout->return_url);
        }

        return response()->view('filament-modular-subscriptions::pages.payment-result', [
            'state' => $state,
            'checkout' => $checkout,
        ]);
    }

    /**
     * @return array{kind: string, title: string, body: string}
     */
    protected function presentState(PaymentCheckout $checkout): array
    {
        $replace = ['id' => $checkout->invoice_id];

        return match ($checkout->status) {
            PaymentCheckoutStatus::PAID => [
                'kind' => 'success',
                'title' => __('filament-modular-subscriptions::fms.payments.result.success_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.success_body', $replace),
            ],
            PaymentCheckoutStatus::PENDING, PaymentCheckoutStatus::INITIATED => [
                'kind' => 'warning',
                'title' => __('filament-modular-subscriptions::fms.payments.result.pending_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.pending_body', $replace),
            ],
            PaymentCheckoutStatus::EXPIRED => [
                'kind' => 'warning',
                'title' => __('filament-modular-subscriptions::fms.payments.result.expired_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.expired_body', $replace),
            ],
            PaymentCheckoutStatus::CANCELLED => [
                'kind' => 'warning',
                'title' => __('filament-modular-subscriptions::fms.payments.result.cancelled_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.cancelled_body', $replace),
            ],
            PaymentCheckoutStatus::ERROR => [
                'kind' => 'danger',
                'title' => __('filament-modular-subscriptions::fms.payments.result.error_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.error_body', $replace),
            ],
            PaymentCheckoutStatus::FAILED => [
                'kind' => 'danger',
                'title' => __('filament-modular-subscriptions::fms.payments.result.failed_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.failed_body', $replace),
            ],
        };
    }
}
