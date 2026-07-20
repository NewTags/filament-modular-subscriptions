<?php

use Illuminate\Support\Facades\Http;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Payments\CheckoutService;

function callbackCheckout(?string $returnUrl = null): PaymentCheckout
{
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED'))]);

    return app(CheckoutService::class)->createForInvoice(createUnpaidInvoice(), 'tap', $returnUrl);
}

it('settles and redirects back to the panel with a success notification', function () {
    $checkout = callbackCheckout('https://app.test/subscription');

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('CAPTURED'))]);

    $this->get(route('fms.payments.callback', $checkout->uuid))
        ->assertRedirect('https://app.test/subscription')
        ->assertSessionHas('filament.notifications');

    expect($checkout->invoice->refresh()->status)->toBe(InvoiceStatus::PAID);
});

it('renders the standalone result page for public link checkouts', function () {
    $checkout = callbackCheckout();

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('CAPTURED'))]);

    $this->get(route('fms.payments.callback', $checkout->uuid))
        ->assertOk()
        ->assertSee(__('filament-modular-subscriptions::fms.payments.result.success_title'));
});

it('reports a failed charge without marking anything paid', function () {
    $checkout = callbackCheckout();

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('DECLINED'))]);

    $this->get(route('fms.payments.callback', $checkout->uuid))
        ->assertOk()
        ->assertSee(__('filament-modular-subscriptions::fms.payments.result.failed_title'));

    expect($checkout->invoice->refresh()->status)->toBe(InvoiceStatus::UNPAID);
});

it('is idempotent when the callback is revisited', function () {
    $checkout = callbackCheckout();

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('CAPTURED'))]);

    $this->get(route('fms.payments.callback', $checkout->uuid))->assertOk();
    $this->get(route('fms.payments.callback', $checkout->uuid))->assertOk();

    expect($checkout->invoice->payments()->count())->toBe(1);
});

it('returns 404 for an unknown checkout uuid', function () {
    $this->get(route('fms.payments.callback', 'b1946ac9-2a72-4f54-9f3e-000000000000'))
        ->assertNotFound();
});
