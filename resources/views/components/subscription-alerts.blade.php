@if ($alerts)
    <div x-data="{ expanded: false }" class="px-3 py-2">
        {{-- Main Alert (Most Important) --}}
        <div class="relative">
            <div class="flex items-center gap-2 p-3 rounded-lg shadow-sm {{ 
                $alerts[0]['type'] === 'danger' 
                ? 'bg-danger-50 dark:bg-danger-500/10 border border-danger-200 dark:border-danger-500/20 text-danger-700 dark:text-danger-400' 
                : 'bg-warning-50 dark:bg-warning-500/10 border border-warning-200 dark:border-warning-500/20 text-warning-700 dark:text-warning-400' 
            }} {{ count($alerts) > 1 ? 'cursor-pointer' : 'rounded-b-lg' }}"
                @click="expanded = !expanded">
                
                {{-- Alert Icon --}}
                <x-filament::icon
                    icon="{{ $alerts[0]['type'] === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle' }}"
                    class="w-5 h-5 flex-shrink-0 {{ 
                        $alerts[0]['type'] === 'danger' 
                        ? 'text-danger-500 dark:text-danger-400' 
                        : 'text-warning-500 dark:text-warning-400' 
                    }}" />

                {{-- Alert Content --}}
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-medium">{{ $alerts[0]['title'] }}</h3>
                    @if (isset($alerts[0]['body']))
                        <p class="text-xs mt-0.5 opacity-80">{{ $alerts[0]['body'] }}</p>
                    @endif
                </div>

                {{-- Action Button --}}
                @if (isset($alerts[0]['action']))
                    <a href="{{ $alerts[0]['action']['url'] }}" 
                        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hover:underline"
                        @click.stop>
                        {{ $alerts[0]['action']['label'] }}
                    </a>
                @endif

                {{-- Expand/Collapse Icon (Only if there are multiple alerts) --}}
                @if (count($alerts) > 1)
                    <x-filament::icon
                        icon="heroicon-o-chevron-down"
                        class="w-5 h-5 text-gray-400 transition-transform duration-200"
                        ::class="{ 'rotate-180': expanded }" />
                @endif
            </div>

            {{-- Additional Alerts (Expandable) --}}
            @if (count($alerts) > 1)
                <div x-show="expanded" 
                    x-collapse 
                    class="border-x border-b rounded-b-lg divide-y {{ 
                        $alerts[0]['type'] === 'danger'
                        ? 'border-danger-200 dark:border-danger-500/20 divide-danger-200 dark:divide-danger-500/20'
                        : 'border-warning-200 dark:border-warning-500/20 divide-warning-200 dark:divide-warning-500/20'
                    }}">
                    @foreach (array_slice($alerts, 1) as $alert)
                        <div class="flex items-center gap-2 p-3 {{ 
                            $alert['type'] === 'danger' 
                            ? 'bg-danger-50/50 dark:bg-danger-500/5 text-danger-700 dark:text-danger-400' 
                            : 'bg-warning-50/50 dark:bg-warning-500/5 text-warning-700 dark:text-warning-400' 
                        }}">
                            <x-filament::icon
                                icon="{{ $alert['type'] === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle' }}"
                                class="w-5 h-5 flex-shrink-0 {{ 
                                    $alert['type'] === 'danger' 
                                    ? 'text-danger-500 dark:text-danger-400' 
                                    : 'text-warning-500 dark:text-warning-400' 
                                }}" />

                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-medium">{{ $alert['title'] }}</h3>
                                @if (isset($alert['body']))
                                    <p class="text-xs mt-0.5 opacity-80">{{ $alert['body'] }}</p>
                                @endif
                            </div>

                            @if (isset($alert['action']))
                                <a href="{{ $alert['action']['url'] }}" 
                                    class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hover:underline">
                                    {{ $alert['action']['label'] }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Alert Counter Badge (if there are multiple alerts) --}}
        @if (count($alerts) > 1)
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-right">
                {{ count($alerts) }} {{ __('filament-modular-subscriptions::fms.alerts.total') }}
            </div>
        @endif
    </div>
@endif
