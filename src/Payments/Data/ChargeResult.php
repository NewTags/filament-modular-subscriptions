<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Data;

use NewTags\FilamentModularSubscriptions\Payments\Enums\GatewayPaymentStatus;

/**
 * Authoritative state of a charge as re-fetched from the gateway API. This —
 * never a webhook body or redirect query string — is what settlement trusts.
 */
final readonly class ChargeResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $chargeId,
        public GatewayPaymentStatus $status,
        public float $amount,
        public string $currencyCode,
        public ?string $gatewayMessage = null,
        public array $raw = [],
    ) {}
}
