<?php



if (!function_exists('get_currency_symbol')) {
    /**
     * Get the currency symbol
     * 
     * @param string|null $currency
     * @return string
     */
    function get_currency_symbol($currency = null)
    {
        if ($currency == 'SAR' || $currency == null) {
            return "\xEE\xA4\x80";
        }

        return $currency;
    }
}

if (!function_exists('fms_format_currency')) {
    /**
     * Format a number as currency
     * 
     * @param float $amount
     * @param int $decimals
     * @return string
     */
    function fms_format_currency($amount, $decimals = 2)
    {
        return number_format($amount, $decimals) . ' <span class="money">' . get_currency_symbol() . '</span>';
    }
}
