@if ($alerts)
    <div class="space-y-2 px-3 py-2">
        @foreach ($alerts as $alert)
            <div x-data="{ show: true }" x-show="show" x-transition
                class="flex items-center gap-2 p-3 rounded-lg shadow-sm {{ 
                    $alert['type'] === 'danger' 
                    ? 'bg-danger-50 dark:bg-danger-500/10 border border-danger-200 dark:border-danger-500/20 text-danger-700 dark:text-danger-400' 
                    : 'bg-warning-50 dark:bg-warning-500/10 border border-warning-200 dark:border-warning-500/20 text-warning-700 dark:text-warning-400' 
                }}">

                <x-filament::icon
                    icon="{{ $alert['type'] === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle' }}"
                    class="w-5 h-5 flex-shrink-0 {{ 
                        $alert['type'] === 'danger' 
                        ? 'text-danger-500 dark:text-danger-400' 
                        : 'text-warning-500 dark:text-warning-400' 
                    }}" />

                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-medium truncate">{{ $alert['title'] }}</h3>
                    @if (isset($alert['body']))
                        <p class="text-xs mt-0.5 opacity-80 truncate">{{ $alert['body'] }}</p>
                    @endif
                </div>

                @if (isset($alert['action']))
                    <a href="{{ $alert['action']['url'] }}" 
                        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hover:underline">
                        {{ $alert['action']['label'] }}
                    </a>
                @endif

                <button @click="show = false" 
                    class="p-1 rounded-lg text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition duration-150">
                    <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
                </button>

            </div>
        @endforeach
    </div>
@endif
