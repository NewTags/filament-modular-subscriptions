<div class="space-y-6">
    <div class="text-center">
        <h2 class="text-xl font-bold">
            {{ __('filament-modular-subscriptions::fms.payment.choose_method') }}
        </h2>
        <p class="text-gray-500">
            {{ __('filament-modular-subscriptions::fms.payment.amount_to_pay', ['amount' => number_format($invoice->amount, 2)]) }}
        </p>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <button wire:click="initiatePayment('paypal')"
            class="flex items-center justify-center p-4 border-2 rounded-lg hover:border-primary-500 transition-colors">
            <img src="{{ asset('images/paypal-logo.png') }}" alt="PayPal" class="h-8">
        </button>

        <button wire:click="initiatePayment('stripe')"
            class="flex items-center justify-center p-4 border-2 rounded-lg hover:border-primary-500 transition-colors">
            <img src="{{ asset('images/stripe-logo.png') }}" alt="Stripe" class="h-8">
        </button>
    </div>
</div>
