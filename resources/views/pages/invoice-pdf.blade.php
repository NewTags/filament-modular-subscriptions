<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.invoice_number', ['number' => $invoice->id]) }}
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&family=Roboto:wght@400;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #818cf8;
            --text-color: #1f2937;
            --border-color: #e5e7eb;
        }

        body {
            font-family: {{ app()->getLocale() === 'ar' ? "'Cairo'" : "'Roboto'" }}, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            font-size: 14px;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
        }

        .rtl {
            direction: rtl;
            text-align: right;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .content {
            padding: 20px;
        }

        .invoice-details,
        .billing-details {
            margin-bottom: 30px;
        }

        .invoice-details h2,
        .billing-details h2 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 5px;
            margin-bottom: 15px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .details-item {
            margin-bottom: 10px;
        }

        .details-item strong {
            display: block;
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th,
        td {
            border: 1px solid var(--border-color);
            padding: 12px;
            text-align: left;
        }

        .rtl th,
        .rtl td {
            text-align: right;
        }

        th {
            background-color: #f9fafb;
            font-weight: bold;
            color: var(--primary-color);
        }

        .total-row {
            font-weight: bold;
            background-color: #f3f4f6;
        }

        .total-row td {
            border-top: 2px solid var(--primary-color);
        }

        .footer {
            background-color: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid var(--border-color);
        }
    </style>
</head>

<body class="{{ app()->getLocale() === 'ar' ? 'rtl' : '' }}">
    <div class="container">
        <div class="header">
            <h1>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.invoice_number', ['number' => $invoice->id]) }}
            </h1>
        </div>

        <div class="content">
            <div class="invoice-details">
                <h2>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.invoice_details') }}</h2>
                <div class="details-grid">
                    <div class="details-item">
                        <strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.date') }}:</strong>
                        {{ $invoice->created_at->format('Y-m-d') }}
                    </div>
                    <div class="details-item">
                        <strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.due_date') }}:</strong>
                        {{ $invoice->due_date->format('Y-m-d') }}
                    </div>
                    <div class="details-item">
                        <strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.status') }}:</strong>
                        {{ $invoice->status->getLabel() }}
                    </div>
                    <div class="details-item">
                        <strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.payment_method') }}:</strong>
                        {{ $invoice->payment_method ?? __('filament-modular-subscriptions::modular-subscriptions.invoice.not_specified') }}
                    </div>
                </div>
            </div>

            <div class="billing-details">
                <h2>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.billing_details') }}</h2>
                <div class="details-grid">
                    <div class="details-item">
                        <strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.billing_to') }}:</strong>
                        {{ $invoice->tenant->{config('filament-modular-subscriptions.tenant_attribute')} }}
                    </div>
                    <div class="details-item">
                        <strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.email') }}:</strong>
                        {{ $invoice->tenant->email ?? __('filament-modular-subscriptions::modular-subscriptions.invoice.not_specified') }}
                    </div>
                    <div class="details-item">
                        <strong>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.address') }}:</strong>
                        {{ $invoice->tenant->address ?? __('filament-modular-subscriptions::modular-subscriptions.invoice.not_specified') }}
                    </div>
                </div>
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
                            <td>{{ number_format($item->unit_price, 2) }} {{ $invoice->subscription->plan->currency }}
                            </td>
                            <td>{{ number_format($item->total, 2) }} {{ $invoice->subscription->plan->currency }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3">
                            {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.total') }}</td>
                        <td>{{ number_format($invoice->amount, 2) }} {{ $invoice->subscription->plan->currency }}</td>
                    </tr>
                </tfoot>
            </table>

            <div class="payment-instructions">
                <h2>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.payment_instructions') }}</h2>
                <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.payment_instructions_text') }}
                </p>
            </div>
        </div>

        <div class="footer">
            <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.thank_you_message') }}</p>
            <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.footer_text') }}</p>
        </div>
    </div>
</body>

</html>
