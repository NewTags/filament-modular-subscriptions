<?php

namespace NewTags\FilamentModularSubscriptions\Payments\Gateways\Tap;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use NewTags\FilamentModularSubscriptions\Payments\Data\GatewayCredentials;
use NewTags\FilamentModularSubscriptions\Payments\Exceptions\GatewayRequestException;

/**
 * Thin HTTP wrapper around the Tap REST API. Test and live share the same
 * base URL — the key prefix (sk_test_/sk_live_) selects the environment.
 */
class TapClient
{
    public const BASE_URL = 'https://api.tap.company/v2';

    public function __construct(
        protected readonly GatewayCredentials $credentials,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCharge(array $payload): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post('/charges', $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchCharge(string $chargeId): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get('/charges/' . $chargeId));
    }

    /**
     * @param  callable(PendingRequest): Response  $callback
     * @return array<string, mixed>
     */
    protected function send(callable $callback): array
    {
        $response = $callback($this->request());

        if ($response->failed()) {
            throw GatewayRequestException::fromResponse('tap', $response);
        }

        return (array) $response->json();
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken((string) $this->credentials->secretKey())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('filament-modular-subscriptions.payments.http_timeout', 15))
            ->retry(2, 250, fn (\Throwable $exception) => $exception instanceof ConnectionException, throw: false);
    }
}
