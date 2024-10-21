<x-filament-panels::page>
    <div class="space-y-6">
        @if ($activeSubscription)
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.current_subscription') }}
                </x-slot>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan') }}
                        </h3>
                        <p class="mt-1 text-sm text-primary-600 dark:text-primary-400 font-medium">
                            {{ $activeSubscription->plan->trans_name }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.status') }}
                        </h3>
                        <p class="mt-1 text-sm">
                            <x-filament::badge :color="$activeSubscription->status->getColor()">
                                {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.statuses.' . $activeSubscription->status->value) }}
                            </x-filament::badge>
                        </p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.started_on') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $activeSubscription->starts_at->translatedFormat('l, Y/m/d h:i A') }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.ends_on') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $activeSubscription->ends_at->translatedFormat('l, Y/m/d h:i A') }}
                        </p>
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.subscription_details') }}
                    </h3>
                    <dl class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                        <div class="flex justify-between py-3 text-sm font-medium">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.days_left') }}
                            </dt>
                            <dd class="text-primary-600 dark:text-primary-400">
                                {{ $tenant->daysLeft() }}
                            </dd>
                        </div>
                        <div class="flex justify-between py-3 text-sm font-medium">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.on_trial') }}
                            </dt>
                            <dd>
                                <x-filament::badge :color="$activeSubscription->onTrial() ? 'warning' : 'success'">
                                    {{ $activeSubscription->onTrial() ? __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.yes') : __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.no') }}
                                </x-filament::badge>
                            </dd>
                        </div>
                        @if ($activeSubscription->onTrial())
                            <div class="flex justify-between py-3 text-sm font-medium">
                                <dt class="text-gray-500 dark:text-gray-400">
                                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.trial_ends_at') }}
                                </dt>
                                <dd class="text-warning-600 dark:text-warning-400">
                                    {{ $activeSubscription->trial_ends_at->format('M d, Y') }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.no_active_subscription') }}
                </x-slot>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.no_subscription_message') }}
                </p>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.available_plans') }}
            </x-slot>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($availablePlans as $plan)
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg divide-y divide-gray-200 dark:divide-gray-700 {{ $activeSubscription && $activeSubscription->plan_id === $plan->id ? 'ring-2 ring-primary-500' : '' }}">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">
                                {{ $plan->trans_name }}
                            </h3>
                            <div
                                class="mt-2 flex items-baseline text-3xl font-semibold text-primary-600 dark:text-primary-400">
                                {{ $plan->price }} {{ $plan->currency }}
                                <span class="ml-1 text-xl font-medium text-gray-500 dark:text-gray-400">
                                    /
                                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.per') }}
                                    {{ __('filament-modular-subscriptions::modular-subscriptions.intervals.' . $plan->invoice_interval->value) }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $plan->trans_description }}</p>
                        </div>
                        <div class="px-4 py-4 sm:px-6">
                            <ul role="list" class="mt-4 space-y-2">
                                @foreach ($plan->modules as $module)
                                    <li class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <x-filament::icon icon="heroicon-o-check-circle"
                                                class="h-5 w-5 text-success-500" />
                                        </div>
                                        <p class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $module->trans_name }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="px-4 py-4 sm:px-6">
                            @if ($activeSubscription && $activeSubscription->plan_id === $plan->id)
                                <x-filament::button wire:click="switchPlan({{ $plan->id }})" disabled
                                    class="w-full">
                                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.current_plan') }}
                                </x-filament::button>
                            @else
                                <x-filament::button wire:click="switchPlan({{ $plan->id }})" color="primary"
                                    class="w-full">
                                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.switch_to_plan') }}
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
