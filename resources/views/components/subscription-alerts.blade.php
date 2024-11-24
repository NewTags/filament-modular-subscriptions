@if ($alerts)
    <div class="fixed bottom-4 right-4 z-50 space-y-2 rtl:left-4 rtl:right-auto">
        @foreach ($alerts as $alert)
            <div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-x-2" x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-2"
                class="bg-white dark:bg-gray-800 rounded shadow p-3 max-w-xs rtl:transform-x-reverse">
                <div class="flex items-center space-x-2 rtl:space-x-reverse">
                    <div>
                        @if ($alert['type'] === 'danger')
                            <x-filament::icon icon="heroicon-o-exclamation-circle" class="w-5 h-5 text-danger-500" />
                        @elseif($alert['type'] === 'warning')
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5 text-warning-500" />
                        @endif
                    </div>

                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $alert['title'] }}
                        </h3>
                        @if (isset($alert['body']))
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $alert['body'] }}
                            </p>
                        @endif

                        @if (isset($alert['action']))
                            <a href="{{ $alert['action']['url'] }}"
                                class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                                {{ $alert['action']['label'] }}
                            </a>
                        @endif
                    </div>

                    <button @click="show = false"
                        class="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 transition-colors focus:outline-none"
                        aria-label="{{ __('إغلاق التنبيه') }}">
                        <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@endif
