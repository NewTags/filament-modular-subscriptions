<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * A gateway API call failed. The message carries the provider's error
 * description and HTTP status — never credentials or request headers.
 */
class GatewayRequestException extends PaymentGatewayException
{
    public static function fromResponse(string $gateway, Response $response): static
    {
        $description = data_get($response->json(), 'errors.0.description')
            ?? data_get($response->json(), 'message')
            ?? 'Unexpected gateway error';

        return new static(sprintf(
            '[%s] API request failed (HTTP %d): %s',
            $gateway,
            $response->status(),
            $description,
        ));
    }

    public static function missingCheckoutUrl(string $gateway): static
    {
        return new static(sprintf('[%s] charge response did not contain a checkout URL.', $gateway));
    }
}
