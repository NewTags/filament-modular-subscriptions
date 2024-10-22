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
                    <div class="relative group h-full">
                        <div
                            class="absolute -inset-2 bg-gradient-to-r {{ $plan->is_pay_as_you_go ? 'from-emerald-600 to-teal-600' : 'from-primary-600 to-secondary-600' }} rounded-2xl blur opacity-25 group-hover:opacity-75 transition duration-200 ">
                        </div>
                        <div
                            class=" relative bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-lg transform transition duration-200 group-hover:scale-[1.001] {{ $activeSubscription && $activeSubscription->plan_id === $plan->id ? 'ring-2 ring-primary-500' : '' }} flex flex-col h-full">
                            <!-- Plan Badge -->
                            <div class="">
                                <x-filament::badge :color="$plan->is_pay_as_you_go ? 'success' : 'primary'" class="text-xs font-medium">
                                    {{ $plan->is_pay_as_you_go ? __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.pay_as_you_go') : __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.subscription') }}
                                </x-filament::badge>
                            </div>

                            <!-- Plan Header -->
                            <div class="px-6 py-8 flex-grow">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ $plan->trans_name }}
                                </h3>

                                <!-- Pricing Display -->
                                <div class="mt-4">
                                    @if ($plan->is_pay_as_you_go)
                                        <!-- Pay As You Go Pricing -->
                                        <div class="space-y-2">
                                            <div class="flex items-baseline">
                                                <span
                                                    class="text-4xl font-extrabold text-emerald-600 dark:text-emerald-400">
                                                    {{ $plan->price }}
                                                </span>
                                                <span class="ml-1 text-2xl font-medium text-gray-500">
                                                    {{ $plan->currency }}
                                                </span>
                                                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.per_unit') }}
                                                </span>
                                            </div>
                                            <p class="text-sm text-emerald-600 dark:text-emerald-400">
                                                {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.only_pay_for_what_you_use') }}
                                            </p>
                                        </div>
                                    @else
                                        <!-- Subscription Pricing -->
                                        <div class="flex items-baseline">
                                            <span
                                                class="text-4xl font-extrabold text-primary-600 dark:text-primary-400">
                                                {{ $plan->price }}
                                            </span>
                                            <span class="ml-1 text-2xl font-medium text-gray-500">
                                                {{ $plan->currency }}
                                            </span>
                                            <span class="ml-2 text-gray-500 dark:text-gray-400">
                                                /{{ __('filament-modular-subscriptions::modular-subscriptions.intervals.' . $plan->invoice_interval->value) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                <p class="mt-4 text-gray-500 dark:text-gray-400">
                                    {{ $plan->trans_description }}
                                </p>
                            </div>

                            <!-- Features List -->
                            <div class="px-6 pb-6 flex-grow">
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mb-4">
                                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">
                                        {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.included_features') }}
                                    </h4>
                                    <ul class="space-y-4">
                                        @foreach ($plan->modules as $module)
                                            <li class="flex items-center">
                                                <x-filament::icon icon="heroicon-o-check-circle"
                                                    class="w-5 h-5 {{ $plan->is_pay_as_you_go ? 'text-emerald-500' : 'text-success-500' }} flex-shrink-0" />
                                                <span class="ml-3 text-gray-700 dark:text-gray-300">
                                                    {{ $module->getLabel() }}:
                                                    <span class="font-semibold">
                                                        @if ($module->pivot->limit !== null)
                                                            {{ $module->pivot->limit }}
                                                            @if ($plan->is_pay_as_you_go)
                                                                <span class="text-sm text-gray-500">
                                                                    ({{ $plan->price }} {{ $plan->currency }}/unit
                                                                    after)
                                                                </span>
                                                            @endif
                                                        @else
                                                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.unlimited') }}
                                                        @endif
                                                    </span>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>

                            <!-- Usage Information for Pay As You Go -->
                            @if ($plan->is_pay_as_you_go)
                                <div class="px-6 pb-4">
                                    <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-emerald-800 dark:text-emerald-300 mb-2">
                                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.usage_information') }}
                                        </h4>
                                        <ul class="space-y-2 text-sm text-emerald-700 dark:text-emerald-300">
                                            <li>•
                                                {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.billed_monthly') }}
                                            </li>
                                            <li>•
                                                {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.no_minimum_commitment') }}
                                            </li>
                                            <li>•
                                                {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.usage_tracked_realtime') }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            @endif

                            <!-- Action Button -->
                            <div class="px-6 pb-8 mx-auto">
                                @if ($activeSubscription && $activeSubscription->plan_id !== $plan->id)
                                    {{ ($this->switchPlanAction)(['plan_id' => $plan->id]) }}
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
