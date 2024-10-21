<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot
            name="heading">{{ __('filament-modular-subscriptions::modular-subscriptions.widgets.available_plans') }}</x-slot>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->getPlans() as $plan)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">
                            {{ $plan->trans_name }}
                        </h3>
                        <div class="mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                            <p>{{ $plan->trans_description }}</p>
                        </div>
                        <div class="mt-3 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $plan->price }} {{ $plan->currency }}
                        </div>
                        <div class="mt-3">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ __('filament-modular-subscriptions::modular-subscriptions.widgets.per', ['interval' => __('filament-modular-subscriptions::modular-subscriptions.intervals.' . $plan->invoice_interval->value)]) }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
