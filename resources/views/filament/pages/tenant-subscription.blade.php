<x-filament-panels::page class="bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto space-y-8 px-4 sm:px-6 lg:px-8">
        @if ($activeSubscription)
            <x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl overflow-hidden">
                <x-slot name="heading">
                    <div class="flex items-center space-x-2">
                        <x-filament::icon icon="heroicon-o-credit-card" class="w-6 h-6 text-primary-500" />
                        <span class="text-xl font-bold">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.current_subscription') }}
                        </span>
                    </div>
                </x-slot>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Plan Info Card -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan') }}
                        </h3>
                        <p class="mt-2 text-2xl font-bold text-primary-600 dark:text-primary-400">
                            {{ $activeSubscription->plan->trans_name }}
                        </p>
                    </div>

                    <!-- Status Card -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.status') }}
                        </h3>
                        <div class="mt-2">
                            <x-filament::badge size="xl" :color="$activeSubscription->status->getColor()" class="text-lg">
                                {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.statuses.' . $activeSubscription->status->value) }}
                            </x-filament::badge>
                        </div>
                    </div>

                    <!-- Start Date Card -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.started_on') }}
                        </h3>
                        <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $activeSubscription->starts_at->translatedFormat('M d, Y') }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $activeSubscription->starts_at->translatedFormat('h:i A') }}
                        </p>
                    </div>

                    <!-- End Date Card -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.ends_on') }}
                        </h3>
                        <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ $activeSubscription->ends_at->translatedFormat('M d, Y') }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $activeSubscription->ends_at->translatedFormat('h:i A') }}
                        </p>
                    </div>
                </div>

                <!-- Subscription Details -->
                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.subscription_details') }}
                    </h3>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6 space-y-4">
                        <!-- Days Left -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <x-filament::icon icon="heroicon-o-clock" class="w-5 h-5 text-gray-400" />
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.days_left') }}
                                </span>
                            </div>
                            <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                                {{ $tenant->daysLeft() }}
                            </span>
                        </div>

                        <!-- Trial Status -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <x-filament::icon icon="heroicon-o-beaker" class="w-5 h-5 text-gray-400" />
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.on_trial') }}
                                </span>
                            </div>
                            <x-filament::badge :color="$activeSubscription->onTrial() ? 'warning' : 'success'" class="text-sm">
                                {{ $activeSubscription->onTrial()
                                    ? __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.yes')
                                    : __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.no') }}
                            </x-filament::badge>
                        </div>

                        @if ($activeSubscription->onTrial())
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <x-filament::icon icon="heroicon-o-calendar" class="w-5 h-5 text-gray-400" />
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.trial_ends_at') }}
                                    </span>
                                </div>
                                <span class="text-lg font-semibold text-warning-600 dark:text-warning-400">
                                    {{ $activeSubscription->trial_ends_at->translatedFormat('M d, Y') }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl p-6">
                <x-slot name="heading">
                    <div class="flex items-center space-x-2">
                        <x-filament::icon icon="heroicon-o-exclamation-circle" class="w-6 h-6 text-warning-500" />
                        <span class="text-xl font-bold">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.no_active_subscription') }}
                        </span>
                    </div>
                </x-slot>

                <p class="text-gray-500 dark:text-gray-400">
                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.no_subscription_message') }}
                </p>
            </x-filament::section>
        @endif

        <!-- Available Plans Section -->
        <x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl">
            <x-slot name="heading">
                <div class="flex items-center space-x-2">
                    <x-filament::icon icon="heroicon-o-currency-dollar" class="w-6 h-6 text-primary-500" />
                    <span class="text-xl font-bold">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.available_plans') }}
                    </span>
                </div>
            </x-slot>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($availablePlans as $plan)
                    <div class="relative group">
                        <div
                            class="absolute -inset-0.5 bg-gradient-to-r from-primary-600 to-secondary-600 rounded-2xl blur opacity-25  transition duration-200">
                        </div>
                        <div
                            class="relative bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-lg transform transition duration-200 {{ $activeSubscription && $activeSubscription->plan_id === $plan->id ? 'ring-2 ring-primary-500' : '' }}">
                            <!-- Plan Header -->
                            <div class="px-6 py-8">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ $plan->trans_name }}
                                </h3>
                                <div class="mt-4 flex items-baseline">
                                    <span class="text-4xl font-extrabold text-primary-600 dark:text-primary-400">
                                        {{ $plan->price }}
                                    </span>
                                    <span class="ml-1 text-2xl font-medium text-gray-500">
                                        {{ $plan->currency }}
                                    </span>
                                    <span class="ml-2 text-gray-500 dark:text-gray-400">
                                        /{{ __('filament-modular-subscriptions::modular-subscriptions.intervals.' . $plan->invoice_interval->value) }}
                                    </span>
                                </div>
                                <p class="mt-4 text-gray-500 dark:text-gray-400">
                                    {{ $plan->trans_description }}
                                </p>
                            </div>

                            <!-- Features List -->
                            <div class="px-6 pb-6">
                                <ul class="space-y-4">
                                    @foreach ($plan->modules as $module)
                                        <li class="flex items-center">
                                            <x-filament::icon icon="heroicon-o-check-circle"
                                                class="w-5 h-5 text-success-500 flex-shrink-0" />
                                            <span class="ml-3 text-gray-700 dark:text-gray-300">
                                                {{ $module->getLabel() }}:
                                                <span class="font-semibold">
                                                    @if ($module->pivot->limit !== null)
                                                        {{ $module->pivot->limit }}
                                                    @else
                                                        {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.unlimited') }}
                                                    @endif
                                                </span>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <!-- Action Button -->
                            <div class="px-6 pb-8">
                                @if ($activeSubscription && $activeSubscription->plan_id === $plan->id)
                                    <x-filament::button disabled class="w-full justify-center text-lg py-3">
                                        {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.current_plan') }}
                                    </x-filament::button>
                                @else
                                    <x-filament::button wire:click="switchPlan({{ $plan->id }})" color="primary"
                                        class="w-full justify-center text-lg py-3">
                                        {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.switch_to_plan') }}
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    <div class="mt-8">
        {{ $this->getTable() }}
    </div>
</x-filament-panels::page>
