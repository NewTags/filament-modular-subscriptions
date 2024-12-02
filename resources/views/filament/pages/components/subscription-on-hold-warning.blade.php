@if ($subscription && $subscription->status === 'on_hold')
    <x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl p-6">
        <x-slot name="heading">
            <div class="flex items-center space-x-2 gap-2">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-6 h-6 text-warning-500" />
                <span class="text-xl font-bold">
                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.subscription_on_hold') }}
                </span>
            </div>
        </x-slot>

        <p class="text-gray-500 dark:text-gray-400">
            {{ __('filament-modular-subscriptions::fms.tenant_subscription.please_pay_invoice_to_activate') }}
        </p>

        @if ($pendingInvoice = $subscription->pendingInvoice)
            <div class="mt-4">
                <x-filament::button :href="route('filament.resources.invoices.view', $pendingInvoice)" color="primary">
                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.view_invoice') }}
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
@endif 