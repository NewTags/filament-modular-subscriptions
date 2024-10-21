<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .invoice-details {
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .total {
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Invoice #{{ $invoice->id }}</h1>
        </div>

        <div class="invoice-details">
            <p><strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.billing_to') }}:</strong>
                {{ $invoice->tenant->{config('filament-modular-subscriptions.tenant_attribute')} }}</p>
            <p><strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.date') }}:</strong>
                {{ $invoice->created_at->format('M d, Y') }}</p>
            <p><strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.due_date') }}:</strong>
                {{ $invoice->due_date->format('M d, Y') }}</p>
            <p><strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.status') }}:</strong>
                {{ $invoice->status->getLabel() }}</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.description') }}</th>
                    <th>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.quantity') }}</th>
                    <th>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.unit_price') }}</th>
                    <th>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->unit_price, 2) }}</td>
                        <td>{{ number_format($item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="total">
                        {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.total') }}</td>
                    <td>{{ number_format($invoice->amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="footer">
            <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.thank_you_message') }}</p>
        </div>
    </div>
</body>

</html>
