<?php

namespace NewTags\FilamentModularSubscriptions\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use NewTags\FilamentModularSubscriptions\Models\PaymentCheckout;
use NewTags\FilamentModularSubscriptions\Payments\PaymentGatewayManager;
use NewTags\FilamentModularSubscriptions\Payments\SettlementService;

/**
 * Stateless gateway webhook endpoint. The webhook body is only used to
 * locate the checkout — settlement always re-fetches the charge from the
 * gateway API, so a forged body can never mark an invoice paid.
 */
class PaymentWebhookController
{
    public function __construct(
        protected PaymentGatewayManager $manager,
        protected SettlementService $settlement,
    ) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        try {
            $driver = $this->manager->gateway($gateway);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $payload = (array) $request->json()->all();

        if (! $driver->verifyWebhookSignature($payload, $request->header('hashstring'))) {
            Log::warning('fms.payments.webhook_invalid_signature', [
                'gateway' => $gateway,
                'ip' => $request->ip(),
                'charge_id' => data_get($payload, 'id'),
            ]);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $chargeId = $driver->chargeIdFromWebhook($payload);

        if (blank($chargeId)) {
            return response()->json(['error' => 'missing charge id'], 400);
        }

        $checkoutModel = config('filament-modular-subscriptions.models.payment_checkout', PaymentCheckout::class);

        $checkout = $checkoutModel::query()
            ->where('gateway', $gateway)
            ->where('charge_id', $chargeId)
            ->first();

        if (! $checkout) {
            Log::info('fms.payments.webhook_unknown_charge', [
                'gateway' => $gateway,
                'charge_id' => $chargeId,
            ]);

            return response()->json(['received' => true, 'ignored' => true]);
        }

        $outcome = $this->settlement->settleFromGateway($checkout);

        return response()->json(['received' => true, 'outcome' => $outcome->value]);
    }
}
