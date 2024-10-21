<x-filament-panels::page>
    <x-filament::card>
        <h2 class="text-2xl font-bold mb-4">
            {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.invoice_number', ['number' => $this->invoice->id]) }}
        </h2>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <h3 class="text-lg font-semibold mb-2">
                    {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.billing_to') }}</h3>
                <p>{{ $this->invoice->tenant->{config('filament-modular-subscriptions.tenant_attribute')} }}</p>
                <!-- Add more tenant details as needed -->
            </div>
            <div>
                <h3 class="text-lg font-semibold mb-2">
                    {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.invoice_details') }}</h3>
                <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.date') }}:
                    {{ $this->invoice->created_at->format('Y-m-d') }}</p>
                <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.due_date') }}:
                    {{ $this->invoice->due_date->format('Y-m-d') }}</p>
                <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.status') }}:
                    {{ $this->invoice->status }}</p>
            </div>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th
                        class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.description') }}
                    </th>
                    <th
                        class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.quantity') }}
                    </th>
                    <th
                        class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.unit_price') }}
                    </th>
                    <th
                        class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.total') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach ($this->invoice->items as $item)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $item->description }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                            {{ $item->quantity }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                            {{ number_format($item->unit_price, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                            {{ number_format($item->total, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.total') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                        {{ number_format($this->invoice->amount, 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </x-filament::card>
</x-filament-panels::page>
