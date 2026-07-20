<?php

use Illuminate\Support\Facades\Http;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentCheckoutStatus;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Payments\CheckoutService;

function webhookCheckout(): PaymentCheckout
{
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED'))]);

    return app(CheckoutService::class)->createForInvoice(createUnpaidInvoice(), 'tap');
}

it('settles the invoice for a correctly signed webhook', function () {
    $checkout = webhookCheckout();

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('CAPTURED'))]);

    $payload = tapCharge('CAPTURED');

    $this->postJson(route('fms.payments.webhook', 'tap'), $payload, ['hashstring' => tapWebhookHash($payload)])
        ->assertOk()
        ->assertJson(['received' => true, 'outcome' => 'settled']);

    expect($checkout->invoice->refresh()->status)->toBe(InvoiceStatus::PAID)
        ->and($checkout->refresh()->status)->toBe(PaymentCheckoutStatus::PAID);
});

it('rejects a webhook with an invalid signature and writes nothing', function () {
    $checkout = webhookCheckout();

    $payload = tapCharge('CAPTURED');

    $this->postJson(route('fms.payments.webhook', 'tap'), $payload, ['hashstring' => 'forged-signature'])
        ->assertStatus(401);

    expect($checkout->invoice->refresh()->status)->toBe(InvoiceStatus::UNPAID)
        ->and($checkout->invoice->payments()->count())->toBe(0);
});

it('rejects a webhook with no signature header', function () {
    webhookCheckout();

    $this->postJson(route('fms.payments.webhook', 'tap'), tapCharge('CAPTURED'))
        ->assertStatus(401);
});

it('acknowledges but ignores webhooks for unknown charges', function () {
    createUnpaidInvoice();

    $payload = tapCharge('CAPTURED', chargeId: 'chg_TS_unknown999');

    $this->postJson(route('fms.payments.webhook', 'tap'), $payload, ['hashstring' => tapWebhookHash($payload)])
        ->assertOk()
        ->assertJson(['received' => true, 'ignored' => true]);

    expect(Invoice::query()->first()->status)->toBe(InvoiceStatus::UNPAID);
});

it('returns 404 for an unknown gateway', function () {
    $this->postJson(route('fms.payments.webhook', 'paypal'), tapCharge('CAPTURED'))
        ->assertNotFound();
});

it('never settles from the webhook body alone — the re-fetched charge wins', function () {
    $checkout = webhookCheckout();

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('DECLINED'))]);

    $payload = tapCharge('CAPTURED');

    $this->postJson(route('fms.payments.webhook', 'tap'), $payload, ['hashstring' => tapWebhookHash($payload)])
        ->assertOk();

    expect($checkout->invoice->refresh()->status)->toBe(InvoiceStatus::UNPAID)
        ->and($checkout->refresh()->status)->toBe(PaymentCheckoutStatus::FAILED)
        ->and($checkout->invoice->payments()->count())->toBe(0);
});
