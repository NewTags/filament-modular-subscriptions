<x-filament-panels::page>
    Hello World {{ $this->currentSubscription->plan->name }}
    <div class="space-y-6">
        {{-- @if ($this->currentSubscription)
            <x-filament::section>
                <x-slot
                    name="heading">{{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.current_subscription') }}</x-slot>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->currentSubscription->plan->name }}</p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.status') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $this->currentSubscription->status }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.started_on') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->currentSubscription->starts_at->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.ends_on') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->currentSubscription->ends_at->format('M d, Y') }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot
                    name="heading">{{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.switch_plan') }}</x-slot>

                <form wire:submit="switchPlan" class="space-y-6">
                    {{ $this->form }}

                    <div class="flex justify-end">
                        {{ $this->switchPlanAction }}
                    </div>
                </form>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot
                    name="heading">{{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.no_active_subscription') }}</x-slot>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.no_subscription_message') }}
                </p>

                <div class="mt-4">
                    <x-filament::button tag="a" href="{{ route('filament.resources.plans.index') }}"
                        color="primary">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.view_available_plans') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot
                name="heading">{{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.available_plans') }}</x-slot>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->availablePlans as $plan)
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg divide-y divide-gray-200 dark:divide-gray-700">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">
                                {{ $plan->name }}</h3>
                            <div
                                class="mt-2 flex items-baseline text-3xl font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ $plan->price }} {{ $plan->currency }}
                                <span class="ml-1 text-xl font-medium text-gray-500 dark:text-gray-400">/
                                    {{ __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.per') }}
                                    {{ $plan->invoice_interval }}</span>
                            </div>
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $plan->description }}</p>
                        </div>
                        <div class="px-4 py-4 sm:px-6">
                            <ul role="list" class="mt-4 space-y-2">
                                @foreach ($plan->modules as $module)
                                    <li class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <p class="ml-3 text-sm text-gray-700 dark:text-gray-300">{{ $module->name }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section> --}}
    </div>
</x-filament-panels::page>
