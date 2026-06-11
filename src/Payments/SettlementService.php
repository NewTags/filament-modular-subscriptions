<?php

namespace NewTags\FilamentModularSubscriptions\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NewTags\FilamentModularSubscriptions\Enums\PaymentCheckoutStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Models\Payment;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Payments\Contracts\PaymentGateway;
use NewTags\FilamentModularSubscriptions\Payments\Data\ChargeResult;
use NewTags\FilamentModularSubscriptions\Payments\Enums\GatewayPaymentStatus;
use NewTags\FilamentModularSubscriptions\Payments\Enums\SettlementOutcome;
use NewTags\FilamentModularSubscriptions\Payments\Support\CurrencyDecimals;
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;

/**
 * The single settlement authority used by both the webhook and the redirect
 * callback. It re-fetches the charge from the gateway API (the only trusted
 * source), verifies amount and currency against the checkout snapshot, then
 * records a Payment and settles the invoice — exactly once, no matter how
 * many times or through which path it is invoked.
 */
class SettlementService
{
    public function __construct(
        protected PaymentGatewayManager $manager,
        protected InvoiceService $invoiceService,
    ) {}

    public function settleFromGateway(PaymentCheckout $checkout): SettlementOutcome
    {
        if ($checkout->isProcessed()) {
            return SettlementOutcome::ALREADY_PROCESSED;
        }

        if (blank($checkout->charge_id)) {
            return SettlementOutcome::PENDING;
        }

        $gateway = $this->manager->gateway($checkout->gateway);
        $result = $gateway->fetchCharge($checkout->charge_id);

        return DB::transaction(function () use ($checkout, $gateway, $result) {
            /** @var PaymentCheckout $checkout */
            $checkout = $checkout->newQuery()->lockForUpdate()->findOrFail($checkout->getKey());

            if ($checkout->isProcessed()) {
                return SettlementOutcome::ALREADY_PROCESSED;
            }

            $checkout->forceFill([
                'status' => $this->checkoutStatusFor($result->status),
                'gateway_response' => $this->trimResponse($result->raw),
            ]);

            if (! $result->status->isPaid()) {
                if ($result->status->isTerminal()) {
                    $checkout->processed_at = now();
                }

                $checkout->save();

                return $result->status === GatewayPaymentStatus::PENDING
                    ? SettlementOutcome::PENDING
                    : SettlementOutcome::FAILED;
            }

            if (! $this->matchesCheckout($result, $checkout)) {
                $checkout->forceFill([
                    'status' => PaymentCheckoutStatus::ERROR,
                    'processed_at' => now(),
                ])->save();

                Log::error('fms.payments.settlement_mismatch', [
                    'checkout' => $checkout->uuid,
                    'charge_id' => $checkout->charge_id,
                    'expected' => ['amount' => (string) $checkout->amount, 'currency' => $checkout->currency],
                    'received' => ['amount' => $result->amount, 'currency' => $result->currencyCode],
                ]);

                return SettlementOutcome::MISMATCH;
            }

            $payment = $this->findOrRecordPayment($checkout, $gateway, $result);

            $this->invoiceService->settleInvoice($checkout->invoice, $payment);

            $checkout->forceFill([
                'payment_id' => $payment->getKey(),
                'status' => PaymentCheckoutStatus::PAID,
                'processed_at' => now(),
            ])->save();

            return SettlementOutcome::SETTLED;
        });
    }

    protected function findOrRecordPayment(PaymentCheckout $checkout, PaymentGateway $gateway, ChargeResult $result): Payment
    {
        $paymentModel = config('filament-modular-subscriptions.models.payment', Payment::class);

        $existing = $paymentModel::query()
            ->where('transaction_id', $checkout->charge_id)
            ->where('payment_method', $gateway->paymentMethod())
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($checkout->invoice->paid()) {
            Log::warning('fms.payments.overpayment_recorded', [
                'checkout' => $checkout->uuid,
                'charge_id' => $checkout->charge_id,
                'invoice_id' => $checkout->invoice_id,
            ]);
        }

        return $checkout->invoice->payments()->create([
            'amount' => $checkout->amount,
            'payment_method' => $gateway->paymentMethod(),
            'status' => PaymentStatus::PAID,
            'transaction_id' => $checkout->charge_id,
            'reviewed_at' => now(),
            'metadata' => array_filter([
                'checkout' => $checkout->uuid,
                'gateway' => $checkout->gateway,
                'source' => $checkout->source,
                'card_brand' => data_get($result->raw, 'card.brand'),
                'card_last_four' => data_get($result->raw, 'card.last_four'),
            ]),
        ]);
    }

    protected function matchesCheckout(ChargeResult $result, PaymentCheckout $checkout): bool
    {
        $expected = CurrencyDecimals::toMinorUnits((float) $checkout->amount, $checkout->currency);
        $received = CurrencyDecimals::toMinorUnits($result->amount, $checkout->currency);

        return $expected === $received
            && strtoupper($result->currencyCode) === strtoupper($checkout->currency);
    }

    protected function checkoutStatusFor(GatewayPaymentStatus $status): PaymentCheckoutStatus
    {
        return match ($status) {
            GatewayPaymentStatus::PAID => PaymentCheckoutStatus::PAID,
            GatewayPaymentStatus::FAILED => PaymentCheckoutStatus::FAILED,
            GatewayPaymentStatus::EXPIRED => PaymentCheckoutStatus::EXPIRED,
            GatewayPaymentStatus::CANCELLED => PaymentCheckoutStatus::CANCELLED,
            GatewayPaymentStatus::PENDING, GatewayPaymentStatus::UNKNOWN => PaymentCheckoutStatus::INITIATED,
        };
    }

    /**
     * Keep only reconciliation-relevant, non-sensitive parts of the gateway
     * response (Tap only ever returns masked card data).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function trimResponse(array $raw): array
    {
        return collect($raw)
            ->only(['id', 'object', 'status', 'amount', 'currency', 'reference', 'response', 'transaction', 'card', 'acquirer', 'created'])
            ->all();
    }
}
