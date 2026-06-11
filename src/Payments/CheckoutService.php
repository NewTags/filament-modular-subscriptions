<?php

namespace NewTags\FilamentModularSubscriptions\Payments;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentCheckoutStatus;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Payments\Data\ChargePayload;
use NewTags\FilamentModularSubscriptions\Payments\Data\CheckoutCustomer;
use NewTags\FilamentModularSubscriptions\Payments\Exceptions\CheckoutException;
use NewTags\FilamentModularSubscriptions\ResolvesCustomerInfo;
use Throwable;

/**
 * Starts a hosted-checkout for an invoice: records the attempt, asks the
 * gateway for a charge, and returns the checkout carrying the redirect URL.
 * Fresh unprocessed checkouts for the same invoice/amount are reused so page
 * refreshes never spawn duplicate charges.
 */
class CheckoutService
{
    public function __construct(
        protected PaymentGatewayManager $manager,
    ) {}

    public function createForInvoice(
        Invoice $invoice,
        string $gateway,
        ?string $returnUrl = null,
        ?Authenticatable $initiator = null,
        string $source = 'panel',
    ): PaymentCheckout {
        if (! config('filament-modular-subscriptions.online_payment_enabled', false) || ! $this->manager->isEnabled($gateway)) {
            throw CheckoutException::onlinePaymentUnavailable();
        }

        $payableStatuses = [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE];
        $amount = round((float) $invoice->remaining_amount, 2);

        if (! in_array($invoice->status, $payableStatuses, true) || $amount <= 0) {
            throw CheckoutException::invoiceNotPayable();
        }

        $checkoutModel = $this->checkoutModel();

        $reusable = $checkoutModel::query()
            ->reusableFor($invoice->getKey(), $gateway, $amount)
            ->latest()
            ->first();

        if ($reusable) {
            $reusable->update(array_filter([
                'return_url' => $returnUrl,
                'source' => $source,
                'created_by' => $initiator?->getAuthIdentifier(),
            ]));

            return $reusable;
        }

        $customer = $this->resolveCustomer($invoice, $initiator);

        if (! $customer->hasContact()) {
            throw CheckoutException::missingContact();
        }

        $checkout = $checkoutModel::query()->create([
            'uuid' => (string) Str::uuid(),
            'invoice_id' => $invoice->getKey(),
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => $this->currencyCode(),
            'status' => PaymentCheckoutStatus::PENDING,
            'return_url' => $returnUrl,
            'source' => $source,
            'created_by' => $initiator?->getAuthIdentifier(),
            'expires_at' => now()->addMinutes(30),
            'metadata' => [
                'tenant_id' => $invoice->tenant_id,
                'subscription_id' => $invoice->subscription_id,
            ],
        ]);

        try {
            $session = $this->manager->gateway($gateway)->createCharge($this->buildPayload($invoice, $checkout, $customer));
        } catch (Throwable $exception) {
            $checkout->update(['status' => PaymentCheckoutStatus::ERROR]);

            Log::error('fms.payments.checkout_failed', [
                'checkout' => $checkout->uuid,
                'invoice_id' => $invoice->getKey(),
                'gateway' => $gateway,
                'error' => $exception->getMessage(),
            ]);

            throw CheckoutException::checkoutFailed();
        }

        $checkout->update([
            'charge_id' => $session->chargeId,
            'checkout_url' => $session->checkoutUrl,
            'status' => PaymentCheckoutStatus::INITIATED,
        ]);

        return $checkout;
    }

    protected function buildPayload(Invoice $invoice, PaymentCheckout $checkout, CheckoutCustomer $customer): ChargePayload
    {
        return new ChargePayload(
            amount: (float) $checkout->amount,
            currencyCode: $checkout->currency,
            reference: $checkout->uuid,
            customer: $customer,
            redirectUrl: route('fms.payments.callback', ['checkout' => $checkout->uuid]),
            webhookUrl: route('fms.payments.webhook', ['gateway' => $checkout->gateway]),
            orderReference: (string) $invoice->getKey(),
            description: __('filament-modular-subscriptions::fms.payments.charge_description', ['id' => $invoice->getKey()]),
            metadata: [
                'invoice_id' => $invoice->getKey(),
                'tenant_id' => $invoice->tenant_id,
                'checkout' => $checkout->uuid,
            ],
        );
    }

    protected function resolveCustomer(Invoice $invoice, ?Authenticatable $initiator): CheckoutCustomer
    {
        $tenant = $invoice->tenant;
        $tenantData = ResolvesCustomerInfo::take($tenant);

        $name = filled($tenantData['name'] ?? null)
            ? (string) $tenantData['name']
            : (string) data_get($tenant, config('filament-modular-subscriptions.tenant_attribute', 'name'), 'Customer');

        $initiatorEmail = $initiator !== null ? data_get($initiator, 'email') : null;
        $email = filled($initiatorEmail) ? (string) $initiatorEmail : (data_get($tenantData, 'customerInfo.email') ?: null);

        [$phoneCountryCode, $phoneNumber] = $this->resolvePhone($tenant);

        return new CheckoutCustomer(
            name: $name,
            email: filled($email) ? $email : null,
            phoneCountryCode: $phoneCountryCode,
            phoneNumber: $phoneNumber,
        );
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    protected function resolvePhone(mixed $tenant): array
    {
        $path = config('filament-modular-subscriptions.tenant_fields.phone');

        if (blank($path)) {
            return [null, null];
        }

        $digits = preg_replace('/\D+/', '', (string) data_get($tenant, $path, ''));

        if (blank($digits)) {
            return [null, null];
        }

        $countryCode = (string) config('filament-modular-subscriptions.payments.default_phone_country_code', '966');

        if (str_starts_with($digits, $countryCode)) {
            return [$countryCode, substr($digits, strlen($countryCode))];
        }

        return [$countryCode, ltrim($digits, '0')];
    }

    protected function currencyCode(): string
    {
        return strtoupper((string) config('filament-modular-subscriptions.currency_code', 'SAR'));
    }

    /**
     * @return class-string<PaymentCheckout>
     */
    protected function checkoutModel(): string
    {
        return config('filament-modular-subscriptions.models.payment_checkout', PaymentCheckout::class);
    }
}
