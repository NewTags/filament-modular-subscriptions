<?php

namespace HoceineEl\FilamentModularSubscriptions\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ON_HOLD = 'on_hold';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return match($this) {
            self::ACTIVE => __('filament-modular-subscriptions::fms.subscription_status.active'),
            self::INACTIVE => __('filament-modular-subscriptions::fms.subscription_status.inactive'),
            self::ON_HOLD => __('filament-modular-subscriptions::fms.subscription_status.on_hold'),
            self::CANCELLED => __('filament-modular-subscriptions::fms.subscription_status.cancelled'),
            self::EXPIRED => __('filament-modular-subscriptions::fms.subscription_status.expired'),
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'danger',
            self::ON_HOLD => 'warning',
            self::CANCELLED => 'danger',
            self::EXPIRED => 'danger',
        };
    }
}
