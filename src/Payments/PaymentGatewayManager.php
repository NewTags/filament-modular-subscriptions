<?php

namespace NewTags\FilamentModularSubscriptions\Payments;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use NewTags\FilamentModularSubscriptions\Payments\Contracts\PaymentGateway;
use NewTags\FilamentModularSubscriptions\Payments\Contracts\ResolvesGatewayCredentials;
use NewTags\FilamentModularSubscriptions\Payments\Data\GatewayCredentials;
use NewTags\FilamentModularSubscriptions\Payments\Gateways\Tap\TapGateway;

/**
 * Registry and factory for payment gateway drivers. A gateway is available
 * when a driver exists, its config block is enabled, and its credentials
 * resolve to a usable secret key. Apps may register extra drivers with
 * extend() and swap the credentials source via `payments.credentials_resolver`.
 */
class PaymentGatewayManager
{
    /**
     * @var array<string, class-string<PaymentGateway>|Closure(GatewayCredentials): PaymentGateway>
     */
    protected array $drivers = [
        'tap' => TapGateway::class,
    ];

    /**
     * @param  class-string<PaymentGateway>|Closure(GatewayCredentials): PaymentGateway  $driver
     */
    public function extend(string $name, string | Closure $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->drivers);
    }

    public function isEnabled(string $name, ?Model $merchantTenant = null): bool
    {
        if (! $this->has($name)) {
            return false;
        }

        if (! (bool) config("filament-modular-subscriptions.payment_methods.{$name}.enabled", false)) {
            return false;
        }

        return $this->credentials($name, $merchantTenant)->isConfigured();
    }

    public function gateway(string $name, ?Model $merchantTenant = null): PaymentGateway
    {
        if (! $this->isEnabled($name, $merchantTenant)) {
            throw new InvalidArgumentException("Payment gateway [{$name}] is not available.");
        }

        return $this->build($name, $this->credentials($name, $merchantTenant));
    }

    /**
     * @return array<string, PaymentGateway>
     */
    public function enabled(?Model $merchantTenant = null): array
    {
        $gateways = [];

        foreach (array_keys($this->drivers) as $name) {
            if ($this->isEnabled($name, $merchantTenant)) {
                $gateways[$name] = $this->build($name, $this->credentials($name, $merchantTenant));
            }
        }

        return $gateways;
    }

    /**
     * @return array<string, string>
     */
    public function enabledOptions(?Model $merchantTenant = null): array
    {
        return array_map(
            fn (PaymentGateway $gateway) => $gateway->label(),
            $this->enabled($merchantTenant),
        );
    }

    public function hasEnabled(?Model $merchantTenant = null): bool
    {
        foreach (array_keys($this->drivers) as $name) {
            if ($this->isEnabled($name, $merchantTenant)) {
                return true;
            }
        }

        return false;
    }

    public function default(?Model $merchantTenant = null): ?string
    {
        return array_key_first($this->enabled($merchantTenant));
    }

    protected function build(string $name, GatewayCredentials $credentials): PaymentGateway
    {
        $driver = $this->drivers[$name];

        if ($driver instanceof Closure) {
            return $driver($credentials);
        }

        return new $driver($credentials);
    }

    protected function credentials(string $name, ?Model $merchantTenant = null): GatewayCredentials
    {
        return $this->resolver()->resolve($name, $merchantTenant);
    }

    protected function resolver(): ResolvesGatewayCredentials
    {
        $resolver = config('filament-modular-subscriptions.payments.credentials_resolver');

        if (filled($resolver) && is_a($resolver, ResolvesGatewayCredentials::class, true)) {
            return app($resolver);
        }

        return new ConfigCredentialsResolver;
    }
}
