@if ($alerts)
    <div class="space-y-1 px-2 py-1">
        @foreach ($alerts as $alert)
            <div x-data="{ show: true }" x-show="show" x-transition
                class="flex items-center gap-1.5 p-1.5 rounded-sm {{ $alert['type'] === 'danger' ? 'bg-danger-50 border-l-2 border-danger-500 text-danger-700' : 'bg-warning-50 border-l-2 border-warning-500 text-warning-700' }}">

                <x-filament::icon
                    icon="{{ $alert['type'] === 'danger' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle' }}"
                    class="w-4 h-4 {{ $alert['type'] === 'danger' ? 'text-danger-500' : 'text-warning-500' }}" />

                <div class="flex-1 min-w-0">
                    <h3 class="text-xs font-medium truncate">{{ $alert['title'] }}</h3>
                    @if (isset($alert['body']))
                        <p class="text-xs opacity-75 truncate">{{ $alert['body'] }}</p>
                    @endif
                </div>

                @if (isset($alert['action']))
                    <a href="{{ $alert['action']['url'] }}" class="text-xs text-primary-600 hover:underline">
                        {{ $alert['action']['label'] }}
                    </a>
                @endif
                <button @click="show = false" class="text-gray-400 hover:text-gray-500 -mr-1">
                    <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
                </button>

            </div>
        @endforeach
    </div>
@endif
