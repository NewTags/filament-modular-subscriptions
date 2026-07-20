<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Data;

/**
 * Gateway input for creating a hosted-checkout charge. Deliberately free of
 * any package model so drivers can be reused for non-FMS payments (e.g.
 * per-tenant B2C charges) without modification.
 */
final readonly class ChargePayload
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public float $amount,
        public string $currencyCode,
        public string $reference,
        public CheckoutCustomer $customer,
        public string $redirectUrl,
        public ?string $webhookUrl = null,
        public ?string $orderReference = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
