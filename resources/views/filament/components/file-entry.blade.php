<div class="flex items-center space-x-2">
    @if ($getState())
        <a href="{{ $getState() }}" target="_blank" rel="noopener noreferrer"
            class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-150 ease-in-out">
            <x-heroicon-o-document-text class="w-5 h-5 mr-2" />
            <span>{{ __('filament-modular-subscriptions::fms.file_entry.view_file') }}</span>
        </a>
    @else
        <div class="text-gray-500 flex items-center">
            <x-heroicon-o-x-circle class="w-5 h-5 mr-2" />
            <span>{{ __('filament-modular-subscriptions::fms.file_entry.no_file') }}</span>
        </div>
    @endif
</div>
