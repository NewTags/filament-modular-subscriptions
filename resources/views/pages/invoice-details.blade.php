@php
    $currency = config('filament-modular-subscriptions.main_currency');
    $taxPercentage = config('filament-modular-subscriptions.tax_percentage', 15);
    $tenantName = optional($invoice->tenant)->{config('filament-modular-subscriptions.tenant_attribute')} ?? __('filament-modular-subscriptions::fms.invoice.subscriber');
    $planName = $invoice->subscription?->plan?->trans_name;
@endphp

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-5 dark:border-white/10">
        <div class="space-y-1">
            <h2 class="text-2xl font-bold text-gray-950 dark:text-white">
                {{ __('filament-modular-subscriptions::fms.invoice.invoice_number', ['number' => $invoice->id]) }}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $invoice->created_at->translatedFormat('d F Y') }}
            </p>
        </div>
        <x-filament::badge :color="$invoice->status->getColor()" size="lg">
            {{ $invoice->status->getLabel() }}
        </x-filament::badge>
    </div>

    {{-- Parties --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('filament-modular-subscriptions::fms.invoice.bill_to') }}
            </h3>
            <p class="font-semibold text-gray-950 dark:text-white">{{ $tenantName }}</p>
            @if ($planName)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $planName }}</p>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('filament-modular-subscriptions::fms.invoice.invoice_details') }}
            </h3>
            <dl class="space-y-1.5 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('filament-modular-subscriptions::fms.invoice.date') }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $invoice->created_at->translatedFormat('d M Y') }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('filament-modular-subscriptions::fms.invoice.due_date') }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $invoice->due_date->translatedFormat('d M Y') }}</dd>
                </div>
                @if ($invoice->paid_at)
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('filament-modular-subscriptions::fms.invoice.paid_at') }}</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $invoice->paid_at->translatedFormat('d M Y') }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- Items --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <th class="px-4 py-3 text-start font-medium">{{ __('filament-modular-subscriptions::fms.invoice.description') }}</th>
                    <th class="px-4 py-3 text-center font-medium">{{ __('filament-modular-subscriptions::fms.invoice.quantity') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('filament-modular-subscriptions::fms.invoice.unit_price') }}</th>
                    <th class="px-4 py-3 text-end font-medium">{{ __('filament-modular-subscriptions::fms.invoice.total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($invoice->items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $item->description }}</td>
                        <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-300">{{ (int) $item->quantity }}</td>
                        <td class="px-4 py-3 text-end text-gray-700 dark:text-gray-300">
                            {{ number_format($item->unit_price, 2) }} <span class="fms-money">{{ $currency }}</span>
                        </td>
                        <td class="px-4 py-3 text-end font-medium text-gray-950 dark:text-white">
                            {{ number_format($item->total, 2) }} <span class="fms-money">{{ $currency }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            {{ __('filament-modular-subscriptions::fms.invoice.no_items') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Totals --}}
    <div class="flex justify-end">
        <div class="w-full max-w-xs space-y-2 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-gray-500 dark:text-gray-400">{{ __('filament-modular-subscriptions::fms.invoice.subtotal') }}</span>
                <span class="font-medium text-gray-950 dark:text-white">
                    {{ number_format($invoice->subtotal, 2) }} <span class="fms-money">{{ $currency }}</span>
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-500 dark:text-gray-400">{{ __('filament-modular-subscriptions::fms.invoice.tax_amount', ['percentage' => $taxPercentage]) }}</span>
                <span class="font-medium text-gray-950 dark:text-white">
                    {{ number_format($invoice->tax, 2) }} <span class="fms-money">{{ $currency }}</span>
                </span>
            </div>
            <div class="flex items-center justify-between border-t border-gray-200 pt-2 dark:border-white/10">
                <span class="text-base font-semibold text-gray-950 dark:text-white">{{ __('filament-modular-subscriptions::fms.invoice.total_with_tax') }}</span>
                <span class="text-base font-bold text-primary-600 dark:text-primary-400">
                    {{ number_format($invoice->amount, 2) }} <span class="fms-money">{{ $currency }}</span>
                </span>
            </div>
        </div>
    </div>

    <p class="border-t border-gray-200 pt-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
        {{ __('filament-modular-subscriptions::fms.invoice.thank_you_message') }}
    </p>
</div>
