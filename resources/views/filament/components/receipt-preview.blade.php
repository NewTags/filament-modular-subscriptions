@php
    $record = $getRecord();
    $path = $record?->receipt_file;
    $disk = \NewTags\FilamentModularSubscriptions\Resources\PaymentResource::receiptDiskFor($path);
    $mime = $disk ? \Illuminate\Support\Facades\Storage::disk($disk)->mimeType($path) : null;
    $isImage = $mime && \Illuminate\Support\Str::startsWith($mime, 'image/');
@endphp

<div>
    @if ($disk && $isImage)
        <img
            src="data:{{ $mime }};base64,{{ base64_encode(\Illuminate\Support\Facades\Storage::disk($disk)->get($path)) }}"
            alt="{{ __('filament-modular-subscriptions::fms.resources.payment.fields.receipt_file') }}"
            style="max-width: 100%; max-height: 420px; border-radius: 0.5rem; border: 1px solid rgb(229 231 235);"
        />
    @elseif ($disk)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament-modular-subscriptions::fms.resources.payment.receipt_pdf_hint') }}
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament-modular-subscriptions::fms.resources.payment.receipt_missing') }}
        </p>
    @endif
</div>
