<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Data;

use NewTags\FilamentModularSubscriptions\Payments\Enums\GatewayMode;

/**
 * Credentials of a single merchant account on a gateway. Today these come
 * from the package config (the platform's own account); later the same shape
 * can be hydrated per-tenant for B2C accounts without touching any gateway code.
 */
final readonly class GatewayCredentials
{
    public function __construct(
        public ?string $merchantId,
        public GatewayMode $mode,
        public ?string $testSecretKey = null,
        public ?string $testPublicKey = null,
        public ?string $liveSecretKey = null,
        public ?string $livePublicKey = null,
    ) {}

    /**
     * @param  array{merchant_id?: string|null, mode?: string|null, test_secret_key?: string|null, test_public_key?: string|null, live_secret_key?: string|null, live_public_key?: string|null}  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            merchantId: $config['merchant_id'] ?? null,
            mode: GatewayMode::tryFrom((string) ($config['mode'] ?? GatewayMode::TEST->value)) ?? GatewayMode::TEST,
            testSecretKey: $config['test_secret_key'] ?? null,
            testPublicKey: $config['test_public_key'] ?? null,
            liveSecretKey: $config['live_secret_key'] ?? null,
            livePublicKey: $config['live_public_key'] ?? null,
        );
    }

    public function secretKey(): ?string
    {
        return $this->mode->isLive() ? $this->liveSecretKey : $this->testSecretKey;
    }

    public function publicKey(): ?string
    {
        return $this->mode->isLive() ? $this->livePublicKey : $this->testPublicKey;
    }

    public function isConfigured(): bool
    {
        return filled($this->secretKey());
    }
}
