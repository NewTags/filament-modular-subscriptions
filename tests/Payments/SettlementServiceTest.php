<?php

use Illuminate\Support\Facades\Http;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentCheckoutStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentMethod;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Payments\CheckoutService;
use NewTags\FilamentModularSubscriptions\Payments\Enums\SettlementOutcome;
use NewTags\FilamentModularSubscriptions\Payments\SettlementService;

function initiatedCheckout(?Invoice $invoice = null): PaymentCheckout
{
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED'))]);

    $invoice ??= createUnpaidInvoice();

    return app(CheckoutService::class)->createForInvoice($invoice, 'tap');
}

it('settles the invoice exactly once for a captured charge', function () {
    $checkout = initiatedCheckout();
    $invoice = $checkout->invoice;

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('CAPTURED'))]);

    $service = app(SettlementService::class);

    expect($service->settleFromGateway($checkout))->toBe(SettlementOutcome::SETTLED);

    $invoice->refresh();
    $checkout->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($checkout->status)->toBe(PaymentCheckoutStatus::PAID)
        ->and($checkout->processed_at)->not->toBeNull()
        ->and($checkout->payment_id)->not->toBeNull();

    $payment = $checkout->payment;

    expect($payment->payment_method)->toBe(PaymentMethod::TAP)
        ->and($payment->status)->toBe(PaymentStatus::PAID)
        ->and($payment->transaction_id)->toBe('chg_TS_test123456')
        ->and((float) $payment->amount)->toBe(115.00);

    $endsAtAfterRenewal = $invoice->subscription->fresh()->ends_at;

    expect($service->settleFromGateway($checkout->refresh()))->toBe(SettlementOutcome::ALREADY_PROCESSED)
        ->and($invoice->payments()->count())->toBe(1)
        ->and($invoice->subscription->fresh()->ends_at->equalTo($endsAtAfterRenewal))->toBeTrue();
});

it('does not settle when the captured amount does not match the checkout', function () {
    $checkout = initiatedCheckout();

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('CAPTURED', 5.00))]);

    expect(app(SettlementService::class)->settleFromGateway($checkout))->toBe(SettlementOutcome::MISMATCH);

    expect($checkout->refresh())
        ->status->toBe(PaymentCheckoutStatus::ERROR)
        ->payment_id->toBeNull()
        ->and($checkout->invoice->refresh()->status)->toBe(InvoiceStatus::UNPAID);
});

it('does not settle when the captured currency does not match the checkout', function () {
    $checkout = initiatedCheckout();

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('CAPTURED', 115.00, 'USD'))]);

    expect(app(SettlementService::class)->settleFromGateway($checkout))->toBe(SettlementOutcome::MISMATCH)
        ->and($checkout->invoice->refresh()->status)->toBe(InvoiceStatus::UNPAID);
});

it('marks the checkout failed for a declined charge and keeps the invoice unpaid', function () {
    $checkout = initiatedCheckout();

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('DECLINED'))]);

    expect(app(SettlementService::class)->settleFromGateway($checkout))->toBe(SettlementOutcome::FAILED);

    expect($checkout->refresh())
        ->status->toBe(PaymentCheckoutStatus::FAILED)
        ->processed_at->not->toBeNull()
        ->and($checkout->invoice->refresh()->status)->toBe(InvoiceStatus::UNPAID)
        ->and($checkout->invoice->payments()->count())->toBe(0);
});

it('keeps a pending charge unprocessed so it can settle later', function () {
    $checkout = initiatedCheckout();

    Http::fake([
        'api.tap.company/v2/charges/*' => Http::sequence()
            ->push(tapCharge('INITIATED'))
            ->push(tapCharge('CAPTURED')),
    ]);

    expect(app(SettlementService::class)->settleFromGateway($checkout))->toBe(SettlementOutcome::PENDING)
        ->and($checkout->refresh()->processed_at)->toBeNull();

    expect(app(SettlementService::class)->settleFromGateway($checkout->refresh()))->toBe(SettlementOutcome::SETTLED);
});

it('never creates a duplicate payment for the same charge id', function () {
    $checkout = initiatedCheckout();

    $checkout->invoice->payments()->create([
        'amount' => $checkout->amount,
        'payment_method' => PaymentMethod::TAP,
        'status' => PaymentStatus::PAID,
        'transaction_id' => $checkout->charge_id,
    ]);

    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge('CAPTURED'))]);

    expect(app(SettlementService::class)->settleFromGateway($checkout))->toBe(SettlementOutcome::SETTLED)
        ->and($checkout->invoice->payments()->count())->toBe(1);
});
