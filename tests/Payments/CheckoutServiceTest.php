<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentCheckoutStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentMethod;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Payments\CheckoutService;
use NewTags\FilamentModularSubscriptions\Payments\Exceptions\CheckoutException;

it('creates an initiated checkout holding the charge id and hosted page url', function () {
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED'))]);

    $invoice = createUnpaidInvoice();

    $checkout = app(CheckoutService::class)->createForInvoice($invoice, 'tap', 'https://app.test/subscription', null, 'panel');

    expect($checkout)
        ->status->toBe(PaymentCheckoutStatus::INITIATED)
        ->charge_id->toBe('chg_TS_test123456')
        ->checkout_url->toContain('checkout.payments.tap.company')
        ->currency->toBe('SAR')
        ->return_url->toBe('https://app.test/subscription')
        ->source->toBe('panel')
        ->and((float) $checkout->amount)->toBe(115.00)
        ->and($checkout->uuid)->not->toBeEmpty()
        ->and($checkout->expires_at)->not->toBeNull();
});

it('refuses to start a checkout for a paid invoice', function () {
    Http::fake();

    $invoice = createUnpaidInvoice();
    $invoice->update(['status' => InvoiceStatus::PAID, 'paid_at' => now()]);

    app(CheckoutService::class)->createForInvoice($invoice, 'tap');
})->throws(CheckoutException::class);

it('refuses to start a checkout when no billing contact can be resolved', function () {
    Http::fake();

    $invoice = createUnpaidInvoice(createTenant(['email' => null]));

    app(CheckoutService::class)->createForInvoice($invoice, 'tap');
})->throws(CheckoutException::class);

it('charges only the remaining amount of a partially paid invoice', function () {
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED', 15.00))]);

    $invoice = createUnpaidInvoice();
    $invoice->payments()->create([
        'amount' => 100.00,
        'payment_method' => PaymentMethod::BANK_TRANSFER,
        'status' => PaymentStatus::PAID,
        'transaction_id' => 'manual-1',
    ]);
    $invoice->update(['status' => InvoiceStatus::PARTIALLY_PAID]);

    $checkout = app(CheckoutService::class)->createForInvoice($invoice, 'tap');

    expect((float) $checkout->amount)->toBe(15.00);

    Http::assertSent(fn (Request $request) => $request['amount'] === 15.0);
});

it('reuses a fresh checkout instead of creating a duplicate charge', function () {
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED'))]);

    $invoice = createUnpaidInvoice();
    $service = app(CheckoutService::class);

    $first = $service->createForInvoice($invoice, 'tap');
    $second = $service->createForInvoice($invoice, 'tap');

    expect($second->id)->toBe($first->id)
        ->and(PaymentCheckout::query()->count())->toBe(1);

    Http::assertSentCount(1);
});

it('marks the checkout as error when the gateway rejects the charge', function () {
    Http::fake(['api.tap.company/v2/charges' => Http::response(['errors' => [['description' => 'Invalid API key']]], 401)]);

    $invoice = createUnpaidInvoice();

    try {
        app(CheckoutService::class)->createForInvoice($invoice, 'tap');

        $this->fail('Expected CheckoutException was not thrown.');
    } catch (CheckoutException) {
        expect(PaymentCheckout::query()->first())
            ->status->toBe(PaymentCheckoutStatus::ERROR)
            ->charge_id->toBeNull();
    }
});
