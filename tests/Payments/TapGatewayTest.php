<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use NewTags\FilamentModularSubscriptions\Payments\Data\ChargePayload;
use NewTags\FilamentModularSubscriptions\Payments\Data\CheckoutCustomer;
use NewTags\FilamentModularSubscriptions\Payments\Data\GatewayCredentials;
use NewTags\FilamentModularSubscriptions\Payments\Enums\GatewayPaymentStatus;
use NewTags\FilamentModularSubscriptions\Payments\Exceptions\GatewayRequestException;
use NewTags\FilamentModularSubscriptions\Payments\Gateways\Tap\TapGateway;

function makeTapGateway(?array $config = null): TapGateway
{
    return new TapGateway(GatewayCredentials::fromConfig(
        $config ?? config('filament-modular-subscriptions.payment_methods.tap'),
    ));
}

function makeChargePayload(float $amount = 115.00): ChargePayload
{
    return new ChargePayload(
        amount: $amount,
        currencyCode: 'SAR',
        reference: 'checkout-uuid-1',
        customer: new CheckoutCustomer(name: 'Acme Academy', email: 'billing@acme.test', phoneCountryCode: '966', phoneNumber: '512345678'),
        redirectUrl: 'https://app.test/fms/payments/callback/checkout-uuid-1',
        webhookUrl: 'https://app.test/fms/payments/webhook/tap',
        orderReference: '42',
        description: 'Invoice #42',
        metadata: ['invoice_id' => 42],
    );
}

it('creates a charge with the correct request body and test credentials', function () {
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED'))]);

    $session = makeTapGateway()->createCharge(makeChargePayload());

    expect($session->chargeId)->toBe('chg_TS_test123456')
        ->and($session->checkoutUrl)->toContain('checkout.payments.tap.company')
        ->and($session->status)->toBe(GatewayPaymentStatus::PENDING);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.tap.company/v2/charges'
            && $request->hasHeader('Authorization', 'Bearer sk_test_dummysecret')
            && $request['amount'] === 115.0
            && $request['currency'] === 'SAR'
            && $request['source']['id'] === 'src_all'
            && $request['save_card'] === false
            && $request['redirect']['url'] === 'https://app.test/fms/payments/callback/checkout-uuid-1'
            && $request['post']['url'] === 'https://app.test/fms/payments/webhook/tap'
            && $request['reference']['transaction'] === 'checkout-uuid-1'
            && $request['reference']['idempotent'] === 'checkout-uuid-1'
            && $request['reference']['order'] === '42'
            && $request['customer']['first_name'] === 'Acme Academy'
            && $request['customer']['phone']['country_code'] === '966'
            && $request['merchant']['id'] === 'merchant_test_123';
    });
});

it('uses the live secret key when mode is live', function () {
    Http::fake(['api.tap.company/v2/charges' => Http::response(tapCharge('INITIATED'))]);

    $config = array_merge(config('filament-modular-subscriptions.payment_methods.tap'), ['mode' => 'live']);

    makeTapGateway($config)->createCharge(makeChargePayload());

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk_live_dummysecret'));
});

it('maps tap charge statuses onto normalized gateway statuses', function (string $tapStatus, GatewayPaymentStatus $expected) {
    Http::fake(['api.tap.company/v2/charges/*' => Http::response(tapCharge($tapStatus))]);

    expect(makeTapGateway()->fetchCharge('chg_TS_test123456')->status)->toBe($expected);
})->with([
    ['CAPTURED', GatewayPaymentStatus::PAID],
    ['INITIATED', GatewayPaymentStatus::PENDING],
    ['IN_PROGRESS', GatewayPaymentStatus::PENDING],
    ['AUTHORIZED', GatewayPaymentStatus::PENDING],
    ['ABANDONED', GatewayPaymentStatus::EXPIRED],
    ['TIMEDOUT', GatewayPaymentStatus::EXPIRED],
    ['CANCELLED', GatewayPaymentStatus::CANCELLED],
    ['VOID', GatewayPaymentStatus::CANCELLED],
    ['FAILED', GatewayPaymentStatus::FAILED],
    ['DECLINED', GatewayPaymentStatus::FAILED],
    ['RESTRICTED', GatewayPaymentStatus::FAILED],
    ['SOMETHING_NEW', GatewayPaymentStatus::UNKNOWN],
]);

it('throws a gateway exception without leaking the secret key on API errors', function () {
    Http::fake([
        'api.tap.company/v2/charges' => Http::response([
            'errors' => [['code' => '1101', 'description' => 'Invalid amount']],
        ], 400),
    ]);

    try {
        makeTapGateway()->createCharge(makeChargePayload());

        $this->fail('Expected GatewayRequestException was not thrown.');
    } catch (GatewayRequestException $exception) {
        expect($exception->getMessage())->toContain('Invalid amount')
            ->and($exception->getMessage())->toContain('400')
            ->not->toContain('sk_test')
            ->not->toContain('dummysecret');
    }
});

it('fails when the charge response has no checkout url', function () {
    $response = tapCharge('INITIATED');
    unset($response['transaction']['url']);

    Http::fake(['api.tap.company/v2/charges' => Http::response($response)]);

    makeTapGateway()->createCharge(makeChargePayload());
})->throws(GatewayRequestException::class);
