<?php

namespace HoceineEl\FilamentModularSubscriptions\Services\Payments;

use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Models\Payment;
use HoceineEl\FilamentModularSubscriptions\Services\Concerns\PaymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaypalService extends PaymentService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = config('filament-modular-subscriptions.payment_methods.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
        $this->clientId = config('filament-modular-subscriptions.payment_methods.paypal.client_id');
        $this->clientSecret = config('filament-modular-subscriptions.payment_methods.paypal.secret');
    }

    public function processPayment(Invoice $invoice): Payment
    {
        try {
            $order = $this->createOrder($invoice);

            return $this->createPaymentRecord($invoice, true, [
                'order_id' => $order['id'],
                'status' => $order['status'],
                'links' => $order['links'],
            ]);
        } catch (\Exception $e) {
            Log::error('PayPal payment processing failed: ' . $e->getMessage());

            return $this->createPaymentRecord($invoice, false, [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function createOrder(Invoice $invoice): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v2/checkout/orders", [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $invoice->id,
                        'amount' => [
                            'currency_code' => $invoice->subscription->plan->currency,
                            'value' => number_format($invoice->amount, 2, '.', ''),
                        ],
                        'description' => "Invoice #{$invoice->id} payment",
                    ],
                ],
                'application_context' => [
                    'return_url' => route('paypal.success'),
                    'cancel_url' => route('paypal.cancel'),
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create PayPal order: ' . $response->body());
        }

        return $response->json();
    }

    public function capturePayment(string $orderId): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

        if (!$response->successful()) {
            throw new \Exception('Failed to capture PayPal payment: ' . $response->body());
        }

        return $response->json();
    }

    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get PayPal access token: ' . $response->body());
        }

        $this->accessToken = $response->json()['access_token'];
        return $this->accessToken;
    }

    public function getPaymentMethod(): string
    {
        return 'paypal';
    }
}
