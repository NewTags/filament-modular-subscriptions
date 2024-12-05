<x-filament::card>
    <div class="flex justify-between items-start mb-4">
        <div>
            <h2 class="text-2xl font-bold text-primary-600">
                {{ __('filament-modular-subscriptions::fms.invoice.invoice_number', ['number' => $invoice->id]) }}
            </h2>
            <p class="text-sm text-gray-500">{{ $invoice->created_at->translatedFormat('F d, Y') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <x-filament::card>
            <h3 class="text-lg font-semibold mb-3 text-primary-600">
                {{ __('filament-modular-subscriptions::fms.invoice.billing_to') }}
            </h3>
            <p class="font-medium">{{ $invoice->tenant->{config('filament-modular-subscriptions.tenant_attribute')} }}
            </p>
            <!-- Add more tenant details as needed -->
        </x-filament::card>

        <x-filament::card>
            <h3 class="text-lg font-semibold mb-3 text-primary-600">
                {{ __('filament-modular-subscriptions::fms.invoice.invoice_details') }}
            </h3>
            <div class="space-y-2 mb-4">
                <div class="flex justify-between">
                    <span class="text-gray-600">{{ __('filament-modular-subscriptions::fms.invoice.date') }}:</span>
                    <span class="font-medium">{{ $invoice->created_at->translatedFormat('F d, Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">{{ __('filament-modular-subscriptions::fms.invoice.due_date') }}:</span>
                    <span class="font-medium">{{ $invoice->due_date->translatedFormat('M d, Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">{{ __('filament-modular-subscriptions::fms.invoice.status') }}:</span>
                    <x-filament::badge :color="$invoice->status->getColor()">
                        {{ $invoice->status->getLabel() }}
                    </x-filament::badge>
                </div>
            </div>
        </x-filament::card>
    </div>

    <x-filament::card>
        <h3 class="text-lg font-semibold mb-4 text-primary-600 ">
            {{ __('filament-modular-subscriptions::fms.invoice.items') }}
        </h3>
        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            {{ __('filament-modular-subscriptions::fms.invoice.description') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-right">
                            {{ __('filament-modular-subscriptions::fms.invoice.quantity') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-right">
                            {{ __('filament-modular-subscriptions::fms.invoice.unit_price') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-right">
                            {{ __('filament-modular-subscriptions::fms.invoice.total') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr class="bg-white border-b">
                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                {{ $item->description }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                {{ $item->quantity }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                {{ number_format($item->unit_price, 2) }} {{ $invoice->subscription->plan->currency }}
                            </td>
                            <td class="px-6 py-4 text-right font-medium">
                                {{ number_format($item->total, 2) }} {{ $invoice->subscription->plan->currency }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-semibold text-gray-900 bg-gray-50 border-t-2 border-gray-200">
                        <td class="px-6 py-4" colspan="3" style="text-align: right;">
                            {{ __('filament-modular-subscriptions::fms.invoice.subtotal') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            {{ number_format($invoice->subtotal, 2) }} {{ $invoice->subscription->plan->currency }}
                        </td>
                    </tr>
                    <tr class="font-semibold text-gray-900 bg-gray-50">
                        <td class="px-6 py-4" colspan="3" style="text-align: right;">
                            {{ __('filament-modular-subscriptions::fms.invoice.tax_amount', ['percentage' => config('filament-modular-subscriptions.tax_percentage')]) }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            {{ number_format($invoice->tax, 2) }} {{ $invoice->subscription->plan->currency }}
                        </td>
                    </tr>
                    <tr class="font-semibold text-gray-900 bg-gray-50 border-t-2 border-gray-200">
                        <td class="px-6 py-4" colspan="3" style="text-align: right;">
                            {{ __('filament-modular-subscriptions::fms.invoice.total_with_tax') }}
                        </td>
                        <td class="px-6 py-4 text-right text-lg">
                            {{ number_format($invoice->amount, 2) }} {{ $invoice->subscription->plan->currency }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::card>

    <div class="mt-8 text-center text-sm text-gray-500">
        {{ __('filament-modular-subscriptions::fms.invoice.thank_you_message') }}
    </div>
</x-filament::card>
