<?php

namespace NewTags\FilamentModularSubscriptions\Http\Controllers;

use Illuminate\Http\Request;
use NewTags\FilamentModularSubscriptions\Payments\CheckoutService;
use NewTags\FilamentModularSubscriptions\Payments\Exceptions\CheckoutException;
use NewTags\FilamentModularSubscriptions\Payments\PaymentGatewayManager;

/**
 * Target of the signed public payment link. Anyone holding a valid link can
 * initiate payment for that invoice — and nothing else: the page only
 * forwards to the gateway's hosted checkout.
 */
class PublicPaymentController
{
    public function __construct(
        protected PaymentGatewayManager $manager,
        protected CheckoutService $checkoutService,
    ) {}

    public function __invoke(Request $request, int $invoice)
    {
        abort_unless(
            config('filament-modular-subscriptions.online_payment_enabled', false) && $this->manager->hasEnabled(),
            404,
        );

        $invoiceModel = config('filament-modular-subscriptions.models.invoice');
        $invoice = $invoiceModel::query()->findOrFail($invoice);

        if (! $invoice->notPaid()) {
            return response()->view('filament-modular-subscriptions::pages.payment-result', [
                'state' => [
                    'kind' => 'success',
                    'title' => __('filament-modular-subscriptions::fms.payments.result.already_paid_title'),
                    'body' => __('filament-modular-subscriptions::fms.payments.result.already_paid_body', ['id' => $invoice->getKey()]),
                ],
                'checkout' => null,
                'invoice' => $invoice,
            ]);
        }

        $gateway = (string) $request->query('gateway', (string) $this->manager->default());

        try {
            $checkout = $this->checkoutService->createForInvoice(
                invoice: $invoice,
                gateway: $gateway,
                returnUrl: null,
                initiator: null,
                source: 'link',
            );
        } catch (CheckoutException $exception) {
            return response()->view('filament-modular-subscriptions::pages.payment-result', [
                'state' => [
                    'kind' => 'danger',
                    'title' => __('filament-modular-subscriptions::fms.payments.result.error_title'),
                    'body' => $exception->getMessage(),
                ],
                'checkout' => null,
                'invoice' => $invoice,
            ]);
        }

        return redirect()->away($checkout->checkout_url);
    }
}
