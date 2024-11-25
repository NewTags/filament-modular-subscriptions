<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            direction: rtl;
            font-family: 'cairo', sans-serif;
            margin: 0;
            padding: 0;
        }

        body {
            padding: 20px;
            background-color: #f8f9fa;
        }

        .invoice-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo {
            max-width: 150px;
        }

        .invoice-title {
            font-size: 24px;
            color: #333;
            text-align: center;
        }


        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: right;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .totals {
            width: 50%;
            margin-left: 0;
            margin-right: auto;
        }

        .totals td {
            padding: 8px;
        }

        .qr-code {
            width: 120px;
            height: 120px;
            margin-top: 20px;
        }

        .tax-number {
            text-align: left;
            margin-top: 20px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <div class="header">
            <img src="{{ $company['logo'] }}" alt="{{ $company['name'] }} Logo" class="logo">
            <h1 class="invoice-title">
                {{ __('filament-modular-subscriptions::fms.invoice.invoice_title', ['subscriber' => $invoice->subscription->subscriber?->name, 'id' => $invoice->id, 'date' => $invoice->created_at->format('Y/m/d')]) }}
            </h1>
            <x-filament::badge 
                :color="$invoice->status->getColor()" 
                :icon="$invoice->status->getIcon()">
                {{ $invoice->status->getLabel() }}
            </x-filament::badge>
        </div>

        <div class="invoice-info">
            <div>
                <strong>{{ __('filament-modular-subscriptions::invoice.number') }}:</strong> {{ $invoice->id }}
                <strong>{{ __('filament-modular-subscriptions::invoice.date') }}:</strong>
                {{ $invoice->created_at->format('Y/m/d') }}
            </div>
        </div>

        <div class="details-grid">
            <div>
                <strong>{{ __('filament-modular-subscriptions::invoice.billing_to') }}:</strong>
                <p>{{ $company['name'] }}</p>
                <p>{{ $company['address'] }}</p>
                <p>{{ __('filament-modular-subscriptions::invoice.tax_number') }}: {{ $company['tax_number'] }}</p>
            </div>
            <div>
                <strong>{{ __('filament-modular-subscriptions::invoice.bill_to') }}:</strong>
                <p>{{ $invoice->subscription->subscriber?->name }}</p>
                <p>{{ $invoice->subscription->subscriber?->address }}</p>
                <p>{{ __('filament-modular-subscriptions::invoice.tax_number') }}:
                    {{ $invoice->subscription->subscriber?->tax_number }}</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>{{ __('filament-modular-subscriptions::invoice.no') }}</th>
                    <th>{{ __('filament-modular-subscriptions::invoice.item') }}</th>
                    <th>{{ __('filament-modular-subscriptions::invoice.quantity') }}</th>
                    <th>{{ __('filament-modular-subscriptions::invoice.unit_price') }}</th>
                    <th>{{ __('filament-modular-subscriptions::invoice.vat') }}</th>
                    <th>{{ __('filament-modular-subscriptions::invoice.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->unit_price, 2) }}</td>
                        <td>{{ number_format($item->tax, 2) }}</td>
                        <td>{{ number_format($item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td>{{ __('filament-modular-subscriptions::invoice.subtotal') }}</td>
                <td>{{ number_format($invoice->amount - $invoice->tax, 2) }}
                    {{ $invoice->subscription->plan->currency }}</td>
            </tr>
            <tr>
                <td>{{ __('filament-modular-subscriptions::invoice.tax_amount', ['percentage' => $tax_percentage]) }}
                </td>
                <td>{{ number_format($invoice->tax, 2) }} {{ $invoice->subscription->plan->currency }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('filament-modular-subscriptions::invoice.total_with_tax') }}</strong></td>
                <td><strong>{{ number_format($invoice->amount, 2) }}
                        {{ $invoice->subscription->plan->currency }}</strong></td>
            </tr>
        </table>

        <img src="data:image/png;base64,{{ $QrCode }}" alt="QR Code" class="qr-code">
        <div class="tax-number">
            {{ __('filament-modular-subscriptions::invoice.tax_number') }}: VAT ID : {{ $company['tax_number'] }}
        </div>
    </div>
</body>

</html>
