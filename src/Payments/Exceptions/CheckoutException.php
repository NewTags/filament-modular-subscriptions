<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Exceptions;

/**
 * Raised when a checkout cannot be started for a reason the payer should see.
 * The message is already translated and safe to display in a notification.
 */
class CheckoutException extends PaymentGatewayException
{
    public static function onlinePaymentUnavailable(): static
    {
        return new static(__('filament-modular-subscriptions::fms.payments.errors.online_payment_unavailable'));
    }

    public static function invoiceNotPayable(): static
    {
        return new static(__('filament-modular-subscriptions::fms.payments.errors.invoice_not_payable'));
    }

    public static function missingContact(): static
    {
        return new static(__('filament-modular-subscriptions::fms.payments.errors.missing_contact'));
    }

    public static function checkoutFailed(): static
    {
        return new static(__('filament-modular-subscriptions::fms.payments.errors.checkout_failed'));
    }
}
