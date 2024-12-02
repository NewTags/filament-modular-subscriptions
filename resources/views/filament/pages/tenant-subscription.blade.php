<x-filament-panels::page class="bg-gray-50 dark:bg-gray-900">
    <div class="mb-8">
        {{ $this->getTable() }}
    </div>
    <div class="max-w-7xl mx-auto space-y-8 px-4 sm:px-6 lg:px-8">
        @if ($activeSubscription)
            @include('filament-modular-subscriptions::filament.pages.components.current-subscription-card', ['subscription' => $activeSubscription, 'tenant' => $tenant])
        @else
            <x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl p-6">
                <x-slot name="heading">
                    <div class="flex items-center space-x-2 gap-2">
                        <x-filament::icon icon="heroicon-o-exclamation-circle" class="w-6 h-6 text-warning-500" />
                        <span class="text-xl font-bold">
                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.no_active_subscription') }}
                        </span>
                    </div>
                </x-slot>

                <p class="text-gray-500 dark:text-gray-400">
                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.no_subscription_message') }}
                </p>
            </x-filament::section>
        @endif

        @include('filament-modular-subscriptions::filament.pages.components.subscription-on-hold-warning', ['subscription' => $activeSubscription])

        @include('filament-modular-subscriptions::filament.pages.components.available-plans', [
            'availablePlans' => $availablePlans,
            'activeSubscription' => $activeSubscription,
            'switchPlanAction' => $switchPlanAction
        ])
    </div>
</x-filament-panels::page>
