<?php

namespace NewTags\FilamentModularSubscriptions\Payments;

use Illuminate\Database\Eloquent\Model;
use NewTags\FilamentModularSubscriptions\Payments\Contracts\ResolvesGatewayCredentials;
use NewTags\FilamentModularSubscriptions\Payments\Data\GatewayCredentials;

class ConfigCredentialsResolver implements ResolvesGatewayCredentials
{
    public function resolve(string $gateway, ?Model $merchantTenant = null): GatewayCredentials
    {
        return GatewayCredentials::fromConfig(
            (array) config("filament-modular-subscriptions.payment_methods.{$gateway}", [])
        );
    }
}
