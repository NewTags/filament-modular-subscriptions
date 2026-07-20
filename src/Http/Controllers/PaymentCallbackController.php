<?php

namespace NewTags\FilamentModularSubscriptions\Http\Controllers;

use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use NewTags\FilamentModularSubscriptions\Enums\PaymentCheckoutStatus;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Payments\PaymentGatewayManager;
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
        protected PaymentGatewayManager $manager,
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
            'invoice' => $checkout->invoice,
        ]);
    }

    /**
     * Builds the result-page state. `kind` stays one of success|warning|danger so it
     * doubles as the Filament notification status on the panel return path; `variant`
     * carries the finer per-status visual for the standalone result page.
     *
     * @return array{kind: string, variant: string, title: string, body: string, retry_url?: ?string, poll?: bool}
     */
    protected function presentState(PaymentCheckout $checkout): array
    {
        $replace = ['id' => $checkout->invoice_id];
        $retryUrl = $this->retryUrlFor($checkout);

        return match ($checkout->status) {
            PaymentCheckoutStatus::PAID => [
                'kind' => 'success',
                'variant' => 'success',
                'title' => __('filament-modular-subscriptions::fms.payments.result.success_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.success_body', $replace),
            ],
            PaymentCheckoutStatus::PENDING, PaymentCheckoutStatus::INITIATED => [
                'kind' => 'warning',
                'variant' => 'pending',
                'poll' => true,
                'title' => __('filament-modular-subscriptions::fms.payments.result.pending_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.pending_body', $replace),
            ],
            PaymentCheckoutStatus::EXPIRED => [
                'kind' => 'warning',
                'variant' => 'expired',
                'retry_url' => $retryUrl,
                'title' => __('filament-modular-subscriptions::fms.payments.result.expired_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.expired_body', $replace),
            ],
            PaymentCheckoutStatus::CANCELLED => [
                'kind' => 'warning',
                'variant' => 'cancelled',
                'retry_url' => $retryUrl,
                'title' => __('filament-modular-subscriptions::fms.payments.result.cancelled_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.cancelled_body', $replace),
            ],
            PaymentCheckoutStatus::ERROR => [
                'kind' => 'danger',
                'variant' => 'error',
                'title' => __('filament-modular-subscriptions::fms.payments.result.error_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.error_body', $replace),
            ],
            PaymentCheckoutStatus::FAILED => [
                'kind' => 'danger',
                'variant' => 'failed',
                'retry_url' => $retryUrl,
                'title' => __('filament-modular-subscriptions::fms.payments.result.failed_title'),
                'body' => __('filament-modular-subscriptions::fms.payments.result.failed_body', $replace),
            ],
            default => $this->unhandledState($checkout, $replace),
        };
    }

    /**
     * Forward-compatible fallback: a future checkout status that isn't mapped above
     * degrades to a safe "still processing" screen instead of throwing.
     *
     * @param  array{id: int|null}  $replace
     * @return array{kind: string, variant: string, title: string, body: string, poll: bool}
     */
    protected function unhandledState(PaymentCheckout $checkout, array $replace): array
    {
        Log::warning('fms.payments.unhandled_checkout_status', [
            'checkout' => $checkout->uuid,
            'status' => $checkout->status?->value,
        ]);

        return [
            'kind' => 'warning',
            'variant' => 'pending',
            'poll' => true,
            'title' => __('filament-modular-subscriptions::fms.payments.result.pending_title'),
            'body' => __('filament-modular-subscriptions::fms.payments.result.pending_body', $replace),
        ];
    }

    /**
     * A fresh signed pay link for the same invoice, used as the "try again" target on
     * failed/expired/cancelled results — only when online payments are still enabled.
     */
    protected function retryUrlFor(PaymentCheckout $checkout): ?string
    {
        if (! config('filament-modular-subscriptions.online_payment_enabled', false) || ! $this->manager->hasEnabled()) {
            return null;
        }

        if (blank($checkout->invoice_id)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'fms.pay',
            now()->addDays((int) config('filament-modular-subscriptions.payments.link_ttl_days', 30)),
            ['invoice' => $checkout->invoice_id],
        );
    }
}
