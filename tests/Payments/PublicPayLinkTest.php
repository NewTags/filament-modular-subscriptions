<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentMethod;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;

function signedPayLink(Invoice $invoice): string
{
    return URL::temporarySignedRoute('fms.pay', now()->addDays(30), ['invoice' => $invoice->id]);
}

it('redirects a valid signed link to the hosted checkout', function () {
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED'))]);

    $invoice = createUnpaidInvoice();

    $this->get(signedPayLink($invoice))
        ->assertRedirect('https://checkout.payments.tap.company/pay/chg_TS_test123456');

    expect(PaymentCheckout::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('rejects an unsigned link', function () {
    Http::fake();

    $invoice = createUnpaidInvoice();

    $this->get(route('fms.pay', $invoice->id))->assertForbidden();

    Http::assertNothingSent();
});

it('rejects a tampered signature', function () {
    Http::fake();

    $invoice = createUnpaidInvoice();

    $this->get(signedPayLink($invoice) . 'tampered')->assertForbidden();

    Http::assertNothingSent();
});

it('shows already paid without creating a charge for settled invoices', function () {
    Http::fake();

    $invoice = createUnpaidInvoice();
    $invoice->payments()->create([
        'amount' => $invoice->amount,
        'payment_method' => PaymentMethod::BANK_TRANSFER,
        'status' => PaymentStatus::PAID,
        'transaction_id' => 'manual-paid-1',
    ]);
    $invoice->update(['status' => InvoiceStatus::PAID, 'paid_at' => now()]);

    $this->get(signedPayLink($invoice))
        ->assertOk()
        ->assertSee(__('filament-modular-subscriptions::fms.payments.result.already_paid_title'));

    Http::assertNothingSent();
});

it('reuses the same checkout when the link is opened twice', function () {
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED'))]);

    $invoice = createUnpaidInvoice();

    $this->get(signedPayLink($invoice))->assertRedirect();
    $this->get(signedPayLink($invoice))->assertRedirect();

    Http::assertSentCount(1);

    expect(PaymentCheckout::query()->count())->toBe(1);
});

it('returns 404 when online payment is disabled', function () {
    Http::fake();

    config()->set('filament-modular-subscriptions.online_payment_enabled', false);

    $invoice = createUnpaidInvoice();

    $this->get(signedPayLink($invoice))->assertNotFound();
});
