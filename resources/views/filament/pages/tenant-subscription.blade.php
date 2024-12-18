<x-filament-panels::page>
    <div class="md:mx-auto space-y-8 px-4 sm:px-6 lg:px-8 max-w-7xl">
        <div x-data="{
            tab: 'subscription',
            init() {
                const urlParams = new URLSearchParams(window.location.search);
                const tabParam = urlParams.get('tab');
                if (['subscription', 'plans', 'invoices', 'usage'].includes(tabParam)) {
                    this.tab = tabParam;
                }
            }
        }">
            <div class="overflow-x-auto">
                <x-filament::tabs label="Subscription tabs" class="flex-nowrap" persistent>
                    <x-filament::tabs.item icon="heroicon-o-credit-card"
                        @click="tab = 'subscription'; $dispatch('change-tab', 'subscription'); window.history.pushState(null, '', window.location.pathname + '?tab=subscription')"
                        :alpine-active="'tab === \'subscription\' || !tab'">
                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.current_subscription') }}
                    </x-filament::tabs.item>

                    <x-filament::tabs.item icon="heroicon-o-clipboard-document-list"
                        @click="tab = 'plans'; $dispatch('change-tab', 'plans'); window.history.pushState(null, '', window.location.pathname + '?tab=plans')"
                        :alpine-active="'tab === \'plans\''">
                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.available_plans') }}
                    </x-filament::tabs.item>

                    <x-filament::tabs.item icon="heroicon-o-document-text"
                        @click="tab = 'invoices'; $dispatch('change-tab', 'invoices'); window.history.pushState(null, '', window.location.pathname + '?tab=invoices')"
                        :alpine-active="'tab === \'invoices\''">
                        <x-slot name="badge" >
                            {{ $tenant->unpaidInvoices()->count() }}
                        </x-slot>
                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.invoices') }}
                    </x-filament::tabs.item>
                    <x-filament::tabs.item icon="heroicon-o-document-text"
                        @click="tab = 'usage'; $dispatch('change-tab', 'usage'); window.history.pushState(null, '', window.location.pathname + '?tab=usage')"
                        :alpine-active="'tab === \'usage\''">
                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.usage') }}
                    </x-filament::tabs.item>
                </x-filament::tabs>
            </div>

            <div class="mt-4">
                {{-- Current Subscription Tab --}}
                <div x-show="tab === 'subscription'" x-cloak x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 transform -translate-x-2"
                    x-transition:enter-end="opacity-100 transform translate-x-0">
                    @if ($activeSubscription)
                        <x-filament::section
                            class="bg-white/90 dark:bg-gray-800/90 shadow-xl rounded-2xl overflow-hidden backdrop-blur-sm">
                            <x-slot name="heading">
                                <div
                                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span
                                            class="text-xl sm:text-2xl font-bold text-primary-500 dark:text-primary-400">
                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.current_subscription') }}
                                        </span>
                                        <x-filament::badge size="lg" :color="$activeSubscription->status->getColor()">
                                            {{ $activeSubscription->status->getLabel() }}
                                        </x-filament::badge>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.plan') }}:
                                        <span
                                            class="font-semibold text-primary-600 dark:text-primary-400">{{ $activeSubscription->plan->trans_name }}</span>
                                    </div>
                                </div>
                            </x-slot>

                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                                <!-- Subscription Progress -->
                                @if (!$activeSubscription->onTrial())
                                    <div
                                        class="col-span-full lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl p-4 sm:p-6 shadow-lg border border-gray-100 dark:border-gray-700">
                                        <div class="flex flex-col space-y-4">
                                            <div
                                                class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                                <div class="flex items-center gap-2">
                                                    <x-filament::icon icon="heroicon-o-clock"
                                                        class="w-5 h-5 text-primary-500 dark:text-primary-400" />
                                                    <span
                                                        class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.subscription_progress') }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="text-xl sm:text-2xl font-bold text-primary-600 dark:text-primary-400">
                                                    {{ $tenant->daysLeft() }}
                                                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.days_left') }}
                                                </div>
                                            </div>

                                            @php
                                                $totalDays = $activeSubscription->ends_at->diffInDays(
                                                    $activeSubscription->starts_at,
                                                );
                                                $daysLeft = $tenant->daysLeft();
                                                $progress =
                                                    $totalDays > 0 ? (($totalDays - $daysLeft) / $totalDays) * 100 : 0;
                                            @endphp

                                            <div class="relative">
                                                <div
                                                    class="w-full h-3 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                                    <div class="h-full bg-gradient-to-r from-primary-600 to-primary-300 dark:from-primary-300 dark:to-primary-600 rounded-full transition-all duration-500 shadow-sm"
                                                        style="width: {{ $progress }}%"
                                                        x-tooltip.raw="{{ $daysLeft }} days remaining">
                                                    </div>
                                                </div>

                                                <div
                                                    class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                    <span>{{ $activeSubscription->starts_at->translatedFormat('M d, Y') }}</span>
                                                    <span>{{ $activeSubscription->ends_at->translatedFormat('M d, Y') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <!-- Trial Status -->
                                @if ($activeSubscription->onTrial())
                                    <div
                                        class="bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-700/30 rounded-xl p-4 sm:p-6">
                                        <div class="flex items-center gap-3 mb-3">
                                            <x-filament::icon icon="heroicon-o-beaker"
                                                class="w-6 h-6 text-warning-500" />
                                            <h3 class="text-base font-semibold text-warning-700 dark:text-warning-400">
                                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.trial_period') }}
                                            </h3>
                                        </div>
                                        <p class="text-sm text-warning-600 dark:text-warning-400">
                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.trial_ends_at') }}:
                                            <span
                                                class="font-bold">{{ $activeSubscription->trial_ends_at->translatedFormat('M d, Y') }}</span>
                                        </p>
                                    </div>
                                @endif

                                <!-- Subscription Details -->
                                <div
                                    class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 sm:p-6 {{ $activeSubscription->onTrial() ? 'lg:col-span-1' : 'lg:col-span-2' }}">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                        {{ __('filament-modular-subscriptions::fms.tenant_subscription.subscription_details') }}
                                    </h3>
                                    <div class="space-y-3">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <x-filament::icon icon="heroicon-o-calendar"
                                                    class="w-5 h-5 text-gray-400" />
                                                <span
                                                    class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament-modular-subscriptions::fms.tenant_subscription.started_on') }}</span>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $activeSubscription->starts_at->translatedFormat('M d, Y') }}
                                            </span>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <x-filament::icon icon="heroicon-o-clock"
                                                    class="w-5 h-5 text-gray-400" />
                                                <span
                                                    class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament-modular-subscriptions::fms.tenant_subscription.ends_on') }}</span>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $activeSubscription->ends_at->translatedFormat('M d, Y') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-filament::section>
                    @else
                        <div
                            class="flex flex-col items-center justify-center p-6 sm:p-12 bg-white/90 dark:bg-gray-800/90 rounded-2xl shadow-xl backdrop-blur-sm">
                            <div
                                class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                                <x-filament::icon icon="heroicon-o-exclamation-circle" class="w-8 h-8 text-gray-400" />
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2 text-center">
                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.no_active_subscription') }}
                            </h2>
                            <p class="text-gray-500 dark:text-gray-400 text-center max-w-md">
                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.no_subscription_message') }}
                            </p>
                            <x-filament::button color="primary" class="mt-6" @click="tab = 'plans'">
                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.view_available_plans') }}
                            </x-filament::button>
                        </div>
                    @endif
                </div>

                {{-- Available Plans Tab --}}
                <div x-show="tab === 'plans'" class="animate-fade-in">
                    <x-filament::section class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl"
                        icon="heroicon-o-currency-dollar">
                        <x-slot name="heading">
                            <div class="flex items-center space-x-2 gap-2">
                                <span class="text-xl sm:text-2xl font-bold text-primary-600 dark:text-primary-400">
                                    {{ __('filament-modular-subscriptions::fms.tenant_subscription.available_plans') }}
                                </span>
                            </div>
                        </x-slot>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                            @foreach ($availablePlans as $plan)
                                @if ($activeSubscription && $plan->is_trial_plan && $activeSubscription->has_used_trial)
                                @else
                                    <div
                                        class="relative group h-full transform transition hover:-translate-y-1 duration-300">
                                        <div
                                            class="absolute -inset-2 bg-gradient-to-r {{ $plan->is_pay_as_you_go ? 'from-emerald-500/80 to-teal-500/80' : 'from-primary-500/80 to-secondary-500/80' }} rounded-2xl blur-lg opacity-20 group-hover:opacity-100 transition duration-300">
                                        </div>
                                        <div
                                            class="relative bg-white dark:bg-gray-800/90 backdrop-blur-sm rounded-xl overflow-hidden shadow-lg transition duration-300 {{ $activeSubscription && $activeSubscription->plan_id === $plan->id ? 'ring-2 ring-primary-500/50' : '' }} flex flex-col h-full">
                                            <!-- Plan Badge -->
                                            <div class="p-4 border-b dark:border-gray-700">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <x-filament::badge :color="$plan->is_pay_as_you_go ? 'success' : 'primary'"
                                                        class="text-xs font-semibold px-3 py-1">
                                                        {{ $plan->is_pay_as_you_go ? __('filament-modular-subscriptions::fms.tenant_subscription.pay_as_you_go') : __('filament-modular-subscriptions::fms.tenant_subscription.subscription') }}
                                                    </x-filament::badge>
                                                    @if ($activeSubscription && $activeSubscription->plan_id === $plan->id)
                                                        <x-filament::badge color="info"
                                                            class="text-xs font-semibold px-3 py-1">
                                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.current_plan') }}
                                                        </x-filament::badge>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Plan Content -->
                                            <div class="px-4 sm:px-6 py-6 flex-grow">
                                                <h3
                                                    class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100">
                                                    {{ $plan->trans_name }}
                                                </h3>

                                                <!-- Pricing Display -->
                                                <div class="mt-4">
                                                    @if ($plan->is_pay_as_you_go)
                                                        <div class="space-y-2">
                                                            <p
                                                                class="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.only_pay_for_what_you_use') }}
                                                            </p>
                                                        </div>
                                                    @else
                                                        <div class="flex flex-wrap items-baseline gap-1">
                                                            <span
                                                                class="text-3xl sm:text-4xl font-extrabold text-primary-500 dark:text-primary-400">
                                                                {{ $plan->price }}
                                                            </span>
                                                            <span class="text-xl sm:text-2xl font-medium text-gray-500">
                                                                {{ $plan->currency }}
                                                            </span>
                                                            <span class="text-gray-500 dark:text-gray-400">
                                                                /{{ __('filament-modular-subscriptions::fms.intervals.' . $plan->invoice_interval->value) }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>

                                                <p class="mt-4 text-sm sm:text-base text-gray-600 dark:text-gray-300">
                                                    {{ $plan->trans_description }}
                                                </p>

                                                <!-- Features List -->
                                                <div class="mt-8">
                                                    <h4
                                                        class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4 uppercase tracking-wider">
                                                        @if ($plan->is_pay_as_you_go)
                                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.usage_information') }}
                                                        @else
                                                            {{ __('filament-modular-subscriptions::fms.tenant_subscription.included_features') }}
                                                        @endif
                                                    </h4>
                                                    <ul class="space-y-4">
                                                        @foreach ($plan->modules as $module)
                                                            <li class="flex items-start group/item">
                                                                <x-filament::icon icon="heroicon-o-check-circle"
                                                                    class="w-5 h-5 {{ $plan->is_pay_as_you_go ? 'text-emerald-500' : 'text-success-500' }} flex-shrink-0 mt-1 group-hover/item:scale-110 transition-transform" />
                                                                <span class="ml-3 text-gray-700 dark:text-gray-300">
                                                                    <span
                                                                        class="font-medium">{{ $module->getLabel() }}</span>
                                                                    @if ($plan->is_pay_as_you_go)
                                                                        <div class="text-sm text-gray-500 mt-1">
                                                                            {{ number_format($module->pivot->price, 2) }}
                                                                            {{ $plan->currency }}/{{ __('filament-modular-subscriptions::fms.tenant_subscription.unit') }}
                                                                        </div>
                                                                    @else
                                                                        @if ($module->pivot->limit !== null)
                                                                            <span class="ml-1 text-sm text-gray-500">
                                                                                ({{ $module->pivot->limit }}
                                                                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.units') }})
                                                                            </span>
                                                                        @else
                                                                            <span
                                                                                class="ml-1 text-sm text-primary-500 font-medium">
                                                                                ({{ __('filament-modular-subscriptions::fms.tenant_subscription.unlimited') }})
                                                                            </span>
                                                                        @endif
                                                                    @endif
                                                                </span>
                                                            </li>
                                                        @endforeach
                                                    </ul>

                                                    @if ($plan->is_pay_as_you_go)
                                                        <div
                                                            class="mt-6 space-y-3 text-sm text-gray-500 bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg">
                                                            <div class="flex items-center gap-2">
                                                                <x-filament::icon icon="heroicon-o-shield-check"
                                                                    class="w-4 h-4 text-emerald-500" />
                                                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.no_minimum_commitment') }}
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <x-filament::icon icon="heroicon-o-chart-bar"
                                                                    class="w-4 h-4 text-emerald-500" />
                                                                {{ __('filament-modular-subscriptions::fms.tenant_subscription.usage_tracked_realtime') }}
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            @if ($activeSubscription)
                                                <!-- Action Button -->
                                                <div class="px-4 sm:px-6 pb-6 mt-auto">
                                                    @if (!$activeSubscription || $activeSubscription->plan_id !== $plan->id)
                                                        {{ ($this->switchPlanAction)(['plan_id' => $plan->id]) }}
                                                    @endif
                                                </div>
                                            @else
                                                <div class="px-4 sm:px-6 pb-6 mt-auto mx-auto w-full">
                                                    {{ ($this->newSubscriptionAction)(['plan_id' => $plan->id]) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </x-filament::section>
                    <x-filament-actions::modals />
                </div>


                <div x-show="tab === 'usage'" class="animate-fade-in">
                    @livewire(\NewTags\FilamentModularSubscriptions\Widgets\ModuleUsageWidget::class)
                </div>
                <div x-show="tab === 'invoices'" class="animate-fade-in">
                    {{ $this->getTable() }}
                </div>
            </div>
        </div>
    </div>

</x-filament-panels::page>
