<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Data;

use NewTags\FilamentModularSubscriptions\Payments\Enums\GatewayPaymentStatus;

/**
 * Result of creating a charge: where to send the payer and how to find the
 * charge again later.
 */
final readonly class CheckoutSession
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $chargeId,
        public string $checkoutUrl,
        public GatewayPaymentStatus $status,
        public array $raw = [],
    ) {}
}
