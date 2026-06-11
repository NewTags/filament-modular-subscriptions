<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Support;

/**
 * ISO 4217 minor-unit helper. Gateways (and webhook signature hashing) must
 * format amounts with the exact number of decimals of the charge currency.
 */
class CurrencyDecimals
{
    private const THREE_DECIMALS = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

    private const ZERO_DECIMALS = ['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public static function for(string $currencyCode): int
    {
        $currencyCode = strtoupper(trim($currencyCode));

        return match (true) {
            in_array($currencyCode, self::THREE_DECIMALS, true) => 3,
            in_array($currencyCode, self::ZERO_DECIMALS, true) => 0,
            default => 2,
        };
    }

    public static function format(float $amount, string $currencyCode): string
    {
        return number_format($amount, self::for($currencyCode), '.', '');
    }

    public static function toMinorUnits(float $amount, string $currencyCode): int
    {
        return (int) round($amount * (10 ** self::for($currencyCode)));
    }
}
