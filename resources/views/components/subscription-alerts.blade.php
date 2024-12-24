@if ($alerts)
    <div x-data="{ expanded: false, hidden: false }">
        <div x-show="!hidden" x-transition.opacity class="relative">
            <div class="flex items-center gap-2 py-1.5 px-3 backdrop-blur-sm text-xs {{ 
                $alerts[0]['type'] === 'danger' 
                ? 'bg-danger-50/95 dark:bg-danger-950/95 border border-danger-300 dark:border-danger-700 text-danger-800 dark:text-danger-200' 
                : 'bg-warning-50/95 dark:bg-warning-950/95 border border-warning-300 dark:border-warning-700 text-warning-800 dark:text-warning-200' 
            }} {{ count($alerts) > 1 ? 'cursor-pointer hover:scale-[1.01] transition-transform duration-200' : 'rounded-none' }}"
                @click="expanded = !expanded">
                
                <div class="{{ 
                    $alerts[0]['type'] === 'danger'
                    ? 'bg-danger-100 dark:bg-danger-900/50 text-danger-600 dark:text-danger-400'
                    : 'bg-warning-100 dark:bg-warning-900/50 text-warning-600 dark:text-warning-400'
                }} p-2 rounded-full">
                    <x-filament::icon
                        icon="{{ $alerts[0]['type'] === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle' }}"
                        class="w-4 h-4" />
                </div>

                <div class="flex-1 min-w-0">
                    <h3 class="text-xs font-semibold">{{ $alerts[0]['title'] }}</h3>
                    @if (isset($alerts[0]['body']))
                        <p class="text-xs mt-0.5 opacity-90 line-clamp-1">{{ $alerts[0]['body'] }}</p>
                    @endif
                </div>

                @if (isset($alerts[0]['action']))
                    <a href="{{ $alerts[0]['action']['url'] }}" 
                        class="px-3 py-1 rounded-md bg-primary-600 hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 text-white text-xs font-medium transition-colors duration-200"
                        @click.stop>
                        {{ $alerts[0]['action']['label'] }}
                    </a>
                @endif

                <button @click.stop="hidden = true" class="p-1 rounded-full hover:bg-black/5 dark:hover:bg-white/10 transition-colors duration-200">
                    <x-filament::icon
                        icon="heroicon-o-x-mark"
                        class="w-3 h-3 opacity-70" />
                </button>

                @if (count($alerts) > 1)
                    <div class="pl-1 border-l border-current/10">
                        <x-filament::icon
                            icon="heroicon-o-chevron-down"
                            class="w-4 h-4 opacity-70 transition-transform duration-300"
                            ::class="{ 'rotate-180': expanded }" />
                    </div>
                @endif
            </div>

            @if (count($alerts) > 1)
                <div x-show="expanded" 
                    x-collapse 
                    class="mt-1 space-y-1">
                    @foreach (array_slice($alerts, 1) as $alert)
                        <div class="flex items-center gap-2 p-3  backdrop-blur-sm {{ 
                            $alert['type'] === 'danger' 
                            ? 'bg-danger-50/80 dark:bg-danger-950/80 text-danger-800 dark:text-danger-200' 
                            : 'bg-warning-50/80 dark:bg-warning-950/80 text-warning-800 dark:text-warning-200' 
                        }}">
                            <div class="{{ 
                                $alert['type'] === 'danger'
                                ? 'bg-danger-100 dark:bg-danger-900/50 text-danger-600 dark:text-danger-400'
                                : 'bg-warning-100 dark:bg-warning-900/50 text-warning-600 dark:text-warning-400'
                            }} p-1.5 rounded-full">
                                <x-filament::icon
                                    icon="{{ $alert['type'] === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle' }}"
                                    class="w-3 h-3" />
                            </div>

                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-medium">{{ $alert['title'] }}</h3>
                                @if (isset($alert['body']))
                                    <p class="text-xs mt-0.5 opacity-80 line-clamp-1">{{ $alert['body'] }}</p>
                                @endif
                            </div>

                            @if (isset($alert['action']))
                                <a href="{{ $alert['action']['url'] }}" 
                                    class="px-2 py-1 rounded-md bg-primary-600/90 hover:bg-primary-700 dark:bg-primary-500/90 dark:hover:bg-primary-600 text-white text-xs font-medium transition-colors duration-200">
                                    {{ $alert['action']['label'] }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if (count($alerts) > 1)
            <div x-show="!hidden" x-transition.opacity class="mt-1 text-xs text-gray-600 dark:text-gray-400 text-right font-medium">
                {{ count($alerts) }} {{ __('filament-modular-subscriptions::fms.alerts.total') }}
            </div>
        @endif
    </div>
@endif
