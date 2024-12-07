<x-filament-panels::page class="bg-gray-50 dark:bg-gray-900">

    <div class="max-w-7xl mx-auto space-y-8 px-4 sm:px-6 lg:px-8">
        <div x-data="{ tab: 'subscription' }">
            <x-filament::tabs label="Subscription tabs">
                <x-filament::tabs.item icon="heroicon-o-credit-card" @click="tab = 'subscription'" :alpine-active="'tab === \'subscription\''">
                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.current_subscription') }}
                </x-filament::tabs.item>

                <x-filament::tabs.item icon="heroicon-o-clipboard-document-list" @click="tab = 'plans'" :alpine-active="'tab === \'plans\''">
                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.available_plans') }}
                </x-filament::tabs.item>
            </x-filament::tabs>

            <div class="mt-4">
                {{-- Current Subscription Tab --}}
                <div x-show="tab === 'subscription'">
                    @if ($activeSubscription)
                        <x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl overflow-hidden">
                            <x-slot name="heading">
                                <div class="flex items-center space-x-2">
                                    <x-filament::icon icon="heroicon-o-credit-card" class="w-6 h-6 text-primary-500" />
                                    <span class="text-xl font-bold">
                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.current_subscription') }}
                                    </span>
                                </div>
                            </x-slot>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                <!-- Plan Info Card -->
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.plan') }}
                                    </h3>
                                    <p class="mt-2 text-2xl font-bold text-primary-600 dark:text-primary-400">
                                        {{ $activeSubscription->plan->trans_name }}
                                    </p>
                                </div>

                                <!-- Status Card -->
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.status') }}
                                    </h3>
                                    <div class="mt-2">
                                        <x-filament::badge size="xl" :color="$activeSubscription->status->getColor()" class="text-lg">
                                            {{ $activeSubscription->status->getLabel() }}
                                        </x-filament::badge>
                                    </div>
                                </div>

                                <!-- Start Date Card -->
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6">
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.started_on') }}
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
                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.ends_on') }}
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
                                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.subscription_details') }}
                                </h3>
                                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6 space-y-4">
                                    <!-- Days Left -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2 gap-2">
                                            <x-filament::icon icon="heroicon-o-clock" class="w-5 h-5 text-gray-400" />
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.days_left') }}
                                            </span>
                                        </div>
                                        <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                                            {{ $tenant->daysLeft() }}
                                        </span>
                                    </div>

                                    <!-- Trial Status -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2 gap-2">
                                            <x-filament::icon icon="heroicon-o-beaker" class="w-5 h-5 text-gray-400" />
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.on_trial') }}
                                            </span>
                                        </div>
                                        <x-filament::badge :color="$activeSubscription->onTrial() ? 'warning' : 'success'" class="text-sm">
                                            {{ $activeSubscription->onTrial() ? __('filament-modular-subscriptions::fms.tenant_subscription.yes') : __('filament-modular-subscriptions::fms.tenant_subscription.no') }}
                                        </x-filament::badge>
                                    </div>

                                    @if ($activeSubscription->onTrial())
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-2 gap-2">
                                                <x-filament::icon icon="heroicon-o-calendar"
                                                    class="w-5 h-5 text-gray-400" />
                                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.trial_ends_at') }}
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

                        @if ($activeSubscription->status === 'on_hold')
                            <x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl p-6 mt-8">
                                <x-slot name="heading">
                                    <div class="flex items-center space-x-2 gap-2">
                                        <x-filament::icon icon="heroicon-o-exclamation-triangle"
                                            class="w-6 h-6 text-warning-500" />
                                        <span class="text-xl font-bold">
                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.subscription_on_hold') }}
                                        </span>
                                    </div>
                                </x-slot>

                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.please_pay_invoice_to_activate') }}
                                </p>

                                @if ($pendingInvoice = $activeSubscription->pendingInvoice)
                                    <div class="mt-4">
                                        <x-filament::button :href="route('filament.resources.invoices.view', $pendingInvoice)" color="primary">
                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.view_invoice') }}
                                        </x-filament::button>
                                    </div>
                                @endif
                            </x-filament::section>
                        @endif
                    @else
                        <x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl p-6">
                            <x-slot name="heading">
                                <div class="flex items-center space-x-2 gap-2">
                                    <x-filament::icon icon="heroicon-o-exclamation-circle"
                                        class="w-6 h-6 text-warning-500" />
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
                </div>

                {{-- Available Plans Tab --}}
                <div x-show="tab === 'plans'">
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
                                    <div
                                        class="absolute -inset-2 bg-gradient-to-r {{ $plan->is_pay_as_you_go ? 'from-emerald-600 to-teal-600' : 'from-primary-600 to-secondary-600' }} rounded-2xl blur opacity-25 group-hover:opacity-75 transition duration-200">
                                    </div>
                                    <div
                                        class="relative bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-lg transform transition duration-200 group-hover:scale-[1.001] {{ $activeSubscription && $activeSubscription->plan_id === $plan->id ? 'ring-2 ring-primary-500' : '' }} flex flex-col h-full">
                                        <!-- Plan Badge -->
                                        <div class="p-4">
                                            <x-filament::badge :color="$plan->is_pay_as_you_go ? 'success' : 'primary'" class="text-xs font-medium">
                                                {{ $plan->is_pay_as_you_go ? __('filament-modular-subscriptions::fms.tenant_subscription.pay_as_you_go') : __('filament-modular-subscriptions::fms.tenant_subscription.subscription') }}
                                            </x-filament::badge>
                                            @if ($activeSubscription && $activeSubscription->plan_id === $plan->id)
                                                <x-filament::badge color="info" class="text-xs font-medium ml-2">
                                                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.current_plan') }}
                                                </x-filament::badge>
                                            @endif
                                        </div>

                                        <!-- Plan Content -->
                                        <div class="px-6 py-4 flex-grow">
                                            <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                                {{ $plan->trans_name }}
                                            </h3>

                                            <!-- Pricing Display -->
                                            <div class="mt-4">
                                                @if ($plan->is_pay_as_you_go)
                                                    <div class="space-y-2">
                                                        <p class="text-sm text-emerald-600 dark:text-emerald-400">
                                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.only_pay_for_what_you_use') }}
                                                        </p>
                                                    </div>
                                                @else
                                                    <div class="flex items-baseline">
                                                        <span
                                                            class="text-4xl font-extrabold text-primary-600 dark:text-primary-400">
                                                            {{ $plan->price }}
                                                        </span>
                                                        <span class="ml-1 text-2xl font-medium text-gray-500">
                                                            {{ $plan->currency }}
                                                        </span>
                                                        <span class="ml-2 text-gray-500 dark:text-gray-400">
                                                            /{{ __('filament-modular-subscriptions::fms.intervals.' . $plan->invoice_interval->value) }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>

                                            <p class="mt-4 text-gray-500 dark:text-gray-400">
                                                {{ $plan->trans_description }}
                                            </p>

                                            <!-- Features List -->
                                            <div class="mt-6">
                                                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">
                                                    @if ($plan->is_pay_as_you_go)
                                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.usage_information') }}
                                                    @else
                                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.included_features') }}
                                                    @endif
                                                </h4>
                                                <ul class="space-y-3">
                                                    @foreach ($plan->modules as $module)
                                                        <li class="flex items-start">
                                                            <x-filament::icon icon="heroicon-o-check-circle"
                                                                class="w-5 h-5 {{ $plan->is_pay_as_you_go ? 'text-emerald-500' : 'text-success-500' }} flex-shrink-0 mt-1" />
                                                            <span class="ml-3 text-gray-700 dark:text-gray-300">
                                                                <span
                                                                    class="font-medium">{{ $module->getLabel() }}</span>
                                                                @if ($plan->is_pay_as_you_go)
                                                                    <div class="text-sm text-gray-500">
                                                                        {{ number_format($module->pivot->price, 2) }}
                                                                        {{ $plan->currency }}/{{ __('filament-modular-subscriptions::fms.tenant_subscription.unit') }}
                                                                    </div>
                                                                @else
                                                                    @if ($module->pivot->limit !== null)
                                                                        <span class="ml-1">
                                                                            ({{ $module->pivot->limit }}
                                                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.units') }})
                                                                        </span>
                                                                    @else
                                                                        <span class="ml-1">
                                                                            ({{ __('filament-modular-subscriptions::fms.tenant_subscription.unlimited') }})
                                                                        </span>
                                                                    @endif
                                                                @endif
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>

                                                @if ($plan->is_pay_as_you_go)
                                                    <div class="mt-4 space-y-2 text-sm text-gray-500">
                                                        <div class="flex items-center gap-2">
                                                            <x-filament::icon icon="heroicon-o-shield-check"
                                                                class="w-4 h-4" />
                                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.no_minimum_commitment') }}
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <x-filament::icon icon="heroicon-o-chart-bar"
                                                                class="w-4 h-4" />
                                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.usage_tracked_realtime') }}
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Action Button -->
                                        <div class="px-6 pb-6 mt-6">
                                            @if (!$activeSubscription || $activeSubscription->plan_id !== $plan->id)
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
            </div>
        </div>
    </div>
</x-filament-panels::page>
