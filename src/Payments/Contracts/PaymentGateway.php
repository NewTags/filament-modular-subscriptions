<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Contracts;

use NewTags\FilamentModularSubscriptions\Enums\PaymentMethod;
use NewTags\FilamentModularSubscriptions\Payments\Data\ChargePayload;
use NewTags\FilamentModularSubscriptions\Payments\Data\ChargeResult;
use NewTags\FilamentModularSubscriptions\Payments\Data\CheckoutSession;

/**
 * Contract every payment gateway driver implements. Drivers are pure
 * wrappers around a provider API: they create hosted-checkout charges,
 * re-fetch charge state, and verify webhook authenticity. They know nothing
 * about invoices, payments or tenants.
 */
interface PaymentGateway
{
    public function name(): string;

    public function label(): string;

    public function paymentMethod(): PaymentMethod;

    public function createCharge(ChargePayload $payload): CheckoutSession;

    public function fetchCharge(string $chargeId): ChargeResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload, ?string $signature): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function chargeIdFromWebhook(array $payload): ?string;
}
