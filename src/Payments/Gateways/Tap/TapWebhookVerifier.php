<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Gateways\Tap;

use NewTags\FilamentModularSubscriptions\Payments\Data\GatewayCredentials;
use NewTags\FilamentModularSubscriptions\Payments\Support\CurrencyDecimals;

/**
 * Verifies the `hashstring` header Tap sends with every webhook: an
 * HMAC-SHA256 over the charge fields, keyed with the merchant secret key.
 * Amount must be formatted with the exact decimals of the charge currency.
 *
 * @see https://developers.tap.company/docs/webhook
 */
class TapWebhookVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload, ?string $signature, GatewayCredentials $credentials): bool
    {
        $secretKey = $credentials->secretKey();

        if (blank($signature) || blank($secretKey)) {
            return false;
        }

        $expected = hash_hmac('sha256', $this->hashString($payload), $secretKey);

        return hash_equals($expected, (string) $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hashString(array $payload): string
    {
        $currency = (string) ($payload['currency'] ?? '');
        $amount = CurrencyDecimals::format((float) ($payload['amount'] ?? 0), $currency);

        return 'x_id' . ($payload['id'] ?? '')
            . 'x_amount' . $amount
            . 'x_currency' . $currency
            . 'x_gateway_reference' . data_get($payload, 'reference.gateway', '')
            . 'x_payment_reference' . data_get($payload, 'reference.payment', '')
            . 'x_status' . ($payload['status'] ?? '')
            . 'x_created' . data_get($payload, 'transaction.created', '');
    }
}
