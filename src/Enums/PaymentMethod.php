<?php

namespace NewTags\FilamentModularSubscriptions\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case PAYPAL = 'paypal';
    case STRIPE = 'stripe';
    case VISA = 'visa';
    case MASTERCARD = 'mastercard';
    case PADDLE = 'paddle';
    case TAP = 'tap';
    case BANK_TRANSFER = 'bank_transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::PAYPAL => __('filament-modular-subscriptions::fms.payment_methods.paypal'),
            self::TAP => __('filament-modular-subscriptions::fms.payment_methods.tap'),
            self::STRIPE => __('filament-modular-subscriptions::fms.payment_methods.stripe'),
            self::VISA => __('filament-modular-subscriptions::fms.payment_methods.visa'),
            self::MASTERCARD => __('filament-modular-subscriptions::fms.payment_methods.mastercard'),
            self::PADDLE => __('filament-modular-subscriptions::fms.payment_methods.paddle'),
            self::BANK_TRANSFER => __('filament-modular-subscriptions::fms.payment_methods.bank_transfer'),
        };
    }
}
