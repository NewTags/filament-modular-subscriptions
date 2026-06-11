<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Gateways\Tap;

use NewTags\FilamentModularSubscriptions\Enums\PaymentMethod;
use NewTags\FilamentModularSubscriptions\Payments\Contracts\PaymentGateway;
use NewTags\FilamentModularSubscriptions\Payments\Data\ChargePayload;
use NewTags\FilamentModularSubscriptions\Payments\Data\ChargeResult;
use NewTags\FilamentModularSubscriptions\Payments\Data\CheckoutSession;
use NewTags\FilamentModularSubscriptions\Payments\Data\GatewayCredentials;
use NewTags\FilamentModularSubscriptions\Payments\Enums\GatewayPaymentStatus;
use NewTags\FilamentModularSubscriptions\Payments\Exceptions\GatewayRequestException;
use NewTags\FilamentModularSubscriptions\Payments\Support\CurrencyDecimals;

/**
 * Tap Payments (tap.company) hosted-checkout driver. Charges are created via
 * the Charges API; the payer is redirected to `transaction.url`; the outcome
 * is confirmed exclusively by re-fetching the charge with the secret key.
 *
 * @see https://developers.tap.company/reference/create-a-charge
 */
class TapGateway implements PaymentGateway
{
    protected TapClient $client;

    protected TapWebhookVerifier $verifier;

    public function __construct(
        protected readonly GatewayCredentials $credentials,
    ) {
        $this->client = new TapClient($credentials);
        $this->verifier = new TapWebhookVerifier;
    }

    public function name(): string
    {
        return 'tap';
    }

    public function label(): string
    {
        return __('filament-modular-subscriptions::fms.payments.gateways.tap');
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::TAP;
    }

    public function createCharge(ChargePayload $payload): CheckoutSession
    {
        $response = $this->client->createCharge($this->buildChargeBody($payload));

        $checkoutUrl = data_get($response, 'transaction.url');

        if (blank($checkoutUrl)) {
            throw GatewayRequestException::missingCheckoutUrl($this->name());
        }

        return new CheckoutSession(
            chargeId: (string) data_get($response, 'id'),
            checkoutUrl: (string) $checkoutUrl,
            status: $this->mapStatus((string) data_get($response, 'status')),
            raw: $response,
        );
    }

    public function fetchCharge(string $chargeId): ChargeResult
    {
        $response = $this->client->fetchCharge($chargeId);

        return new ChargeResult(
            chargeId: (string) data_get($response, 'id'),
            status: $this->mapStatus((string) data_get($response, 'status')),
            amount: (float) data_get($response, 'amount', 0),
            currencyCode: (string) data_get($response, 'currency', ''),
            gatewayMessage: data_get($response, 'response.message'),
            raw: $response,
        );
    }

    public function verifyWebhookSignature(array $payload, ?string $signature): bool
    {
        return $this->verifier->verify($payload, $signature, $this->credentials);
    }

    public function chargeIdFromWebhook(array $payload): ?string
    {
        $chargeId = (string) data_get($payload, 'id', '');

        return str_starts_with($chargeId, 'chg_') ? $chargeId : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildChargeBody(ChargePayload $payload): array
    {
        $customer = [
            'first_name' => $payload->customer->name,
        ];

        if (filled($payload->customer->email)) {
            $customer['email'] = $payload->customer->email;
        }

        if ($payload->customer->hasPhone()) {
            $customer['phone'] = [
                'country_code' => $payload->customer->phoneCountryCode,
                'number' => $payload->customer->phoneNumber,
            ];
        }

        $body = [
            'amount' => (float) CurrencyDecimals::format($payload->amount, $payload->currencyCode),
            'currency' => strtoupper($payload->currencyCode),
            'customer' => $customer,
            'source' => ['id' => 'src_all'],
            'redirect' => ['url' => $payload->redirectUrl],
            'reference' => array_filter([
                'transaction' => $payload->reference,
                'idempotent' => $payload->reference,
                'order' => $payload->orderReference,
            ]),
            'description' => $payload->description ?? $payload->reference,
            'metadata' => $payload->metadata ?: null,
            'receipt' => [
                'email' => filled($payload->customer->email),
                'sms' => false,
            ],
            'save_card' => false,
        ];

        if (filled($payload->webhookUrl)) {
            $body['post'] = ['url' => $payload->webhookUrl];
        }

        if (filled($this->credentials->merchantId)) {
            $body['merchant'] = ['id' => $this->credentials->merchantId];
        }

        return array_filter($body, fn ($value) => $value !== null);
    }

    protected function mapStatus(string $status): GatewayPaymentStatus
    {
        return match (strtoupper($status)) {
            'CAPTURED' => GatewayPaymentStatus::PAID,
            'INITIATED', 'PENDING', 'IN_PROGRESS', 'AUTHORIZED' => GatewayPaymentStatus::PENDING,
            'ABANDONED', 'TIMEDOUT', 'EXPIRED' => GatewayPaymentStatus::EXPIRED,
            'CANCELLED', 'VOID', 'VOIDED' => GatewayPaymentStatus::CANCELLED,
            'FAILED', 'DECLINED', 'RESTRICTED' => GatewayPaymentStatus::FAILED,
            default => GatewayPaymentStatus::UNKNOWN,
        };
    }
}
