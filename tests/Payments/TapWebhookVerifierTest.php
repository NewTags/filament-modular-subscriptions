<?php

use NewTags\FilamentModularSubscriptions\Payments\Data\GatewayCredentials;
use NewTags\FilamentModularSubscriptions\Payments\Enums\GatewayMode;
use NewTags\FilamentModularSubscriptions\Payments\Gateways\Tap\TapWebhookVerifier;
use NewTags\FilamentModularSubscriptions\Payments\Support\CurrencyDecimals;

function tapCredentials(string $secret = 'sk_test_dummysecret'): GatewayCredentials
{
    return new GatewayCredentials(
        merchantId: 'merchant_test_123',
        mode: GatewayMode::TEST,
        testSecretKey: $secret,
        testPublicKey: 'pk_test_dummypublic',
    );
}

it('accepts a payload signed with the correct secret', function () {
    $payload = tapCharge();

    expect((new TapWebhookVerifier)->verify($payload, tapWebhookHash($payload), tapCredentials()))->toBeTrue();
});

it('rejects a payload whose amount was tampered with', function () {
    $payload = tapCharge();
    $signature = tapWebhookHash($payload);

    $payload['amount'] = 1.00;

    expect((new TapWebhookVerifier)->verify($payload, $signature, tapCredentials()))->toBeFalse();
});

it('rejects a payload whose status was tampered with', function () {
    $payload = tapCharge('FAILED');
    $signature = tapWebhookHash($payload);

    $payload['status'] = 'CAPTURED';

    expect((new TapWebhookVerifier)->verify($payload, $signature, tapCredentials()))->toBeFalse();
});

it('rejects a signature computed with a different secret', function () {
    $payload = tapCharge();

    $signature = tapWebhookHash($payload, 'sk_test_wrongsecret');

    expect((new TapWebhookVerifier)->verify($payload, $signature, tapCredentials()))->toBeFalse();
});

it('rejects a missing signature header', function () {
    expect((new TapWebhookVerifier)->verify(tapCharge(), null, tapCredentials()))->toBeFalse();
});

it('rejects when no secret key is configured', function () {
    $credentials = new GatewayCredentials(merchantId: null, mode: GatewayMode::TEST);
    $payload = tapCharge();

    expect((new TapWebhookVerifier)->verify($payload, tapWebhookHash($payload), $credentials))->toBeFalse();
});

it('formats the hashed amount with the decimals of the charge currency', function (string $currency, float $amount, string $expected) {
    expect(CurrencyDecimals::format($amount, $currency))->toBe($expected);

    $payload = tapCharge(amount: $amount, currency: $currency);

    expect((new TapWebhookVerifier)->verify($payload, tapWebhookHash($payload), tapCredentials()))->toBeTrue();
})->with([
    'SAR uses 2 decimals' => ['SAR', 115.5, '115.50'],
    'KWD uses 3 decimals' => ['KWD', 115.5, '115.500'],
    'JPY uses 0 decimals' => ['JPY', 115.0, '115'],
]);
