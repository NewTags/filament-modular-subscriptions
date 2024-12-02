<x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl">
    <x-slot name="heading">
        <div class="flex items-center space-x-2 gap-2">
            <x-filament::icon icon="heroicon-o-currency-dollar" class="w-6 h-6 text-primary-500" />
            <span class="text-xl font-bold">
                {{ __('filament-modular-subscriptions::fms.tenant_subscription.available_plans') }}
            </span>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($availablePlans as $plan)
            <div class="relative group h-full">
                <!-- Plan card content -->
                @include('filament.pages.components.plan-card', [
                    'plan' => $plan,
                    'activeSubscription' => $activeSubscription,
                    'switchPlanAction' => $switchPlanAction
                ])
            </div>
        @endforeach
    </div>
</x-filament::section> 