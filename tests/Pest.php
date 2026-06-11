<?php

use Illuminate\Support\Str;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Models\Invoice;
use NewTags\FilamentModularSubscriptions\Models\Plan;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use NewTags\FilamentModularSubscriptions\Payments\Support\CurrencyDecimals;
use NewTags\FilamentModularSubscriptions\Tests\Fixtures\TestTenant;
use NewTags\FilamentModularSubscriptions\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function createTenant(array $attributes = []): TestTenant
{
    return TestTenant::query()->create(array_merge([
        'name' => 'Acme Academy',
        'email' => 'billing@acme.test',
    ], $attributes));
}

function createPlan(array $attributes = []): Plan
{
    return Plan::query()->create(array_merge([
        'name' => ['en' => 'Basic Plan', 'ar' => 'الخطة الأساسية'],
        'slug' => 'basic-' . Str::random(6),
        'price' => 100,
        'currency' => 'SAR',
        'is_active' => true,
        'trial_period' => 0,
        'trial_interval' => 'day',
        'invoice_period' => 1,
        'invoice_interval' => 'month',
        'grace_period' => 0,
        'grace_interval' => 'day',
    ], $attributes));
}

function createSubscription(TestTenant $tenant, ?Plan $plan = null): Subscription
{
    $plan ??= createPlan();

    return Subscription::query()->create([
        'plan_id' => $plan->id,
        'subscribable_id' => $tenant->id,
        'subscribable_type' => TestTenant::class,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
        'status' => SubscriptionStatus::ACTIVE,
    ]);
}

function createUnpaidInvoice(?TestTenant $tenant = null, float $amount = 115.00): Invoice
{
    $tenant ??= createTenant();
    $subscription = createSubscription($tenant);

    $invoice = Invoice::query()->create([
        'subscription_id' => $subscription->id,
        'tenant_id' => $tenant->id,
        'amount' => $amount,
        'subtotal' => round($amount / 1.15, 2),
        'tax' => round($amount - $amount / 1.15, 2),
        'status' => InvoiceStatus::UNPAID,
        'due_date' => now()->addDays(7),
    ]);

    $invoice->items()->create([
        'description' => 'Subscription fee',
        'quantity' => 1,
        'unit_price' => $invoice->subtotal,
        'total' => $invoice->subtotal,
    ]);

    return $invoice;
}

/**
 * A realistic Tap charge payload as returned by POST/GET /v2/charges.
 */
function tapCharge(string $status = 'CAPTURED', float $amount = 115.00, string $currency = 'SAR', string $chargeId = 'chg_TS_test123456'): array
{
    return [
        'id' => $chargeId,
        'object' => 'charge',
        'status' => $status,
        'amount' => $amount,
        'currency' => $currency,
        'transaction' => [
            'url' => 'https://checkout.payments.tap.company/pay/' . $chargeId,
            'created' => '1718000000000',
        ],
        'reference' => [
            'transaction' => 'uuid-ref',
            'gateway' => 'gw_ref_1',
            'payment' => 'pay_ref_1',
            'order' => '1',
        ],
        'response' => ['code' => '000', 'message' => $status === 'CAPTURED' ? 'Captured' : $status],
        'card' => ['brand' => 'VISA', 'last_four' => '1019'],
        'customer' => ['first_name' => 'Acme Academy', 'email' => 'billing@acme.test'],
    ];
}

/**
 * Compute the valid `hashstring` header Tap would send for a webhook payload.
 */
function tapWebhookHash(array $payload, string $secret = 'sk_test_dummysecret'): string
{
    $amount = CurrencyDecimals::format((float) ($payload['amount'] ?? 0), (string) ($payload['currency'] ?? ''));

    $hashString = 'x_id' . ($payload['id'] ?? '')
        . 'x_amount' . $amount
        . 'x_currency' . ($payload['currency'] ?? '')
        . 'x_gateway_reference' . data_get($payload, 'reference.gateway', '')
        . 'x_payment_reference' . data_get($payload, 'reference.payment', '')
        . 'x_status' . ($payload['status'] ?? '')
        . 'x_created' . data_get($payload, 'transaction.created', '');

    return hash_hmac('sha256', $hashString, $secret);
}
