<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Contracts;

use Illuminate\Database\Eloquent\Model;
use NewTags\FilamentModularSubscriptions\Payments\Data\GatewayCredentials;

/**
 * Resolves the merchant credentials a gateway should charge with. The default
 * implementation reads the package config (the platform's own account). A
 * consuming app may bind its own resolver — e.g. one that loads per-tenant
 * accounts — via the `payments.credentials_resolver` config key, enabling
 * B2C charges with the exact same gateway drivers.
 */
interface ResolvesGatewayCredentials
{
    public function resolve(string $gateway, ?Model $merchantTenant = null): GatewayCredentials;
}
