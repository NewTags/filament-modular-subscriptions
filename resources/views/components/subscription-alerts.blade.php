@if ($alerts)
    <div x-data="{ expanded: false }" class="px-3 py-2">
        {{-- Main Alert (Most Important) --}}
        <x-filament::section
            :icon="$alerts[0]['type'] === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle'"
            :icon-color="$alerts[0]['type'] === 'danger' ? 'danger' : 'warning'"
            collapsible="{{ count($alerts) > 1 }}"
            id="subscription-alerts"
            persist-collapsed
        >
            <x-slot name="heading">
                {{ $alerts[0]['title'] }}
            </x-slot>

            @if (isset($alerts[0]['body']))
                <x-slot name="description">
                    {{ $alerts[0]['body'] }}
                </x-slot>
            @endif

            @if (isset($alerts[0]['action']))
                <x-slot name="headerEnd">
                    <a href="{{ $alerts[0]['action']['url'] }}" 
                        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hover:underline">
                        {{ $alerts[0]['action']['label'] }}
                    </a>
                </x-slot>
            @endif

            {{-- Additional Alerts --}}
            @if (count($alerts) > 1)
                <div class="space-y-3">
                    @foreach (array_slice($alerts, 1) as $alert)
                        <x-filament::section
                            :icon="$alert['type'] === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle'"
                            :icon-color="$alert['type'] === 'danger' ? 'danger' : 'warning'"
                            icon-size="sm"
                        >
                            <x-slot name="heading">
                                {{ $alert['title'] }}
                            </x-slot>

                            @if (isset($alert['body']))
                                <x-slot name="description">
                                    {{ $alert['body'] }}
                                </x-slot>
                            @endif

                            @if (isset($alert['action']))
                                <x-slot name="headerEnd">
                                    <a href="{{ $alert['action']['url'] }}" 
                                        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hover:underline">
                                        {{ $alert['action']['label'] }}
                                    </a>
                                </x-slot>
                            @endif
                        </x-filament::section>
                    @endforeach
                </div>

                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-right">
                    {{ count($alerts) }} {{ __('filament-modular-subscriptions::fms.alerts.total') }}
                </div>
            @endif
        </x-filament::section>
    </div>
@endif
