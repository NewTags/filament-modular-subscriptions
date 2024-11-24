@if(count($alerts))
<div class="fixed bottom-4 right-4 z-50 space-y-4 rtl:left-4 rtl:right-auto">
    @foreach($alerts as $alert)
    <div class="animate-in slide-in-from-right bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden max-w-sm rtl:slide-in-from-left">
        <div class="p-4 flex items-start space-x-4 rtl:space-x-reverse">
            {{-- Alert Icon --}}
            <div class="flex-shrink-0">
                @if($alert['type'] === 'danger')
                    <x-filament::icon icon="heroicon-o-exclamation-circle" class="w-6 h-6 text-danger-500"/>
                @elseif($alert['type'] === 'warning')
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-6 h-6 text-warning-500"/>
                @endif
            </div>

            {{-- Alert Content --}}
            <div class="flex-1">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ $alert['title'] }}
                </h3>
                @if(isset($alert['body']))
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $alert['body'] }}
                    </p>
                @endif
                
                {{-- Action Button --}}
                @if(isset($alert['action']))
                <div class="mt-3">
                    <a href="{{ $alert['action']['url'] }}" 
                       class="filament-button filament-button-size-sm inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2rem] px-3 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">
                        {{ $alert['action']['label'] }}
                    </a>
                </div>
                @endif
            </div>

            {{-- Close Button --}}
            <button onclick="this.closest('.animate-in').remove()" class="flex-shrink-0 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400">
                <x-filament::icon icon="heroicon-o-x-mark" class="w-5 h-5"/>
            </button>
        </div>
    </div>
    @endforeach
</div>
@endif 