@php
    $currency = config('filament-modular-subscriptions.main_currency');
    $taxPercentage = $tax_percentage ?? config('filament-modular-subscriptions.tax_percentage', 15);
    $rate = ((float) $taxPercentage) / 100;
    $subtotal = $total_before_tax ?? $invoice->subtotal;
    $taxValue = $tax_amount ?? $invoice->tax;
    $remaining = (float) $invoice->remaining_amount;
    $paid = max(0, round((float) $invoice->amount - $remaining, 2));

    $money = fn ($value) => number_format((float) $value, 2) . ' <span class="money">' . e($currency) . '</span>';

    $statusColors = [
        'paid' => ['bg' => '#dcfce7', 'text' => '#166534'],
        'partially_paid' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
        'unpaid' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
        'overdue' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
        'cancelled' => ['bg' => '#f3f4f6', 'text' => '#374151'],
        'refunded' => ['bg' => '#f3f4f6', 'text' => '#374151'],
    ];
    $colors = $statusColors[$invoice->status->value] ?? ['bg' => '#f3f4f6', 'text' => '#374151'];
@endphp
<!DOCTYPE html>
<html dir="rtl">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            font-size: 10px;
            font-family: 'dinnextltarabic-medium', sans-serif;
            color: #111827;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .money {
            font-family: 'riyal', 'dinnextltarabic-medium', sans-serif;
        }

        .invoice-title {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 18px;
            color: #333;
        }

        .header-table,
        .header-table td {
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .status-badge {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            display: inline-block;
        }

        .section th {
            background-color: #e5e7eb;
            border: none;
            padding: 6px 8px;
            text-align: center;
        }

        .section td {
            border: 1px solid #e5e7eb;
            border-left: none;
            padding: 6px 8px;
        }

        .ltr {
            direction: ltr;
            text-align: left;
        }
    </style>
</head>

<body dir="rtl">
    <div class="invoice-title">{{ __('filament-modular-subscriptions::fms.invoice.tax_invoice') }} | Tax Invoice</div>

    {{-- Header: status · QR · logo --}}
    <table class="header-table" dir="ltr" style="table-layout: fixed; border-spacing: 0;">
        <colgroup>
            <col style="width: 33.33%;">
            <col style="width: 33.33%;">
            <col style="width: 33.33%;">
        </colgroup>
        <tr style="height: 110px;">
            <td style="text-align: left; vertical-align: middle; padding: 8px 4px;">
                <span class="status-badge" style="background-color: {{ $colors['bg'] }}; color: {{ $colors['text'] }};">
                    {{ $invoice->status->getLabel() }}
                </span>
            </td>
            <td style="text-align: center; vertical-align: middle; padding: 8px 4px;">
                @if (!empty($QrCode))
                    <img width="84" height="84" src="{{ $QrCode }}" alt="QR" style="display: inline-block;" />
                @endif
            </td>
            <td style="text-align: right; vertical-align: middle; padding: 8px 4px;">
                @if (!empty($company_logo))
                    <img src="{{ $company_logo }}" alt="" style="max-width: 100%; max-height: 96px; height: auto;">
                @else
                    <span style="font-size: 13pt; font-weight: bold;">{{ config('filament-modular-subscriptions.company_name') }}</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- Invoice meta --}}
    <table class="section" cellpadding="6" style="margin-top: 4px;">
        <tr>
            <th>{{ __('filament-modular-subscriptions::fms.invoice.number') }}</th>
            <th>{{ $invoice->id }}</th>
            <th class="ltr">Invoice #:</th>
            <th>{{ __('filament-modular-subscriptions::fms.invoice.date') }}</th>
            <th>{{ $invoice->created_at->format('Y/m/d') }}</th>
            <th class="ltr">Date:</th>
        </tr>
    </table>

    <br />

    {{-- From / To --}}
    <table class="section" cellpadding="6">
        <thead>
            <tr>
                <th>{{ __('filament-modular-subscriptions::fms.invoice.from') }}</th>
                <th class="ltr">From:</th>
                <th>{{ __('filament-modular-subscriptions::fms.invoice.bill_to') }}</th>
                <th class="ltr">To:</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="2">
                    <strong>{{ config('filament-modular-subscriptions.company_name') }}</strong><br />
                    {{ __('filament-modular-subscriptions::fms.invoice.address') }}: {{ config('filament-modular-subscriptions.company_address') }}<br />
                    {{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}: {{ config('filament-modular-subscriptions.tax_number') }}
                </td>
                <td colspan="2">
                    <strong>{{ $user['name'] ?? '' }}</strong><br />
                    {{ __('filament-modular-subscriptions::fms.invoice.address') }}: {{ $user['customerInfo']['address'] ?? '—' }}<br />
                    {{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}: {{ $user['customerInfo']['vat_no'] ?? '—' }}
                </td>
            </tr>
        </tbody>
    </table>

    <br />

    {{-- Items --}}
    <table class="section" cellpadding="6">
        <thead>
            <tr>
                <th width="8%">#</th>
                <th width="34%">{{ __('filament-modular-subscriptions::fms.invoice.description') }} | Description</th>
                <th width="10%">{{ __('filament-modular-subscriptions::fms.invoice.quantity') }} | Qty</th>
                <th width="18%">{{ __('filament-modular-subscriptions::fms.invoice.subtotal') }} | Subtotal</th>
                <th width="12%">{{ __('filament-modular-subscriptions::fms.invoice.vat') }} | Tax</th>
                <th width="18%">{{ __('filament-modular-subscriptions::fms.invoice.total') }} | Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                @php
                    $lineSubtotal = (float) $item->total;
                    $lineTax = round($lineSubtotal * $rate, 2);
                    $lineTotal = $lineSubtotal + $lineTax;
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $loop->iteration }}</td>
                    <td>{{ $item->description }}</td>
                    <td style="text-align: center;">{{ (int) $item->quantity }}</td>
                    <td>{!! $money($lineSubtotal) !!}</td>
                    <td>{!! $money($lineTax) !!}</td>
                    <td>{!! $money($lineTotal) !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <br />

    {{-- Amounts --}}
    <table class="section" cellpadding="6">
        <thead>
            <tr>
                <th colspan="3">{{ __('filament-modular-subscriptions::fms.invoice.total') }} | Amounts Details</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('filament-modular-subscriptions::fms.invoice.subtotal') }}</td>
                <td>{!! $money($subtotal) !!}</td>
                <td class="ltr">Subtotal</td>
            </tr>
            <tr>
                <td>{{ __('filament-modular-subscriptions::fms.invoice.tax_amount', ['percentage' => $taxPercentage]) }}</td>
                <td>{!! $money($taxValue) !!}</td>
                <td class="ltr">VAT Amount</td>
            </tr>
            <tr style="background-color: #e5e7eb; font-weight: bold;">
                <td>{{ __('filament-modular-subscriptions::fms.invoice.total_with_tax') }}</td>
                <td>{!! $money($invoice->amount) !!}</td>
                <td class="ltr">Total (Tax Included)</td>
            </tr>
        </tbody>
    </table>

    <br />

    {{-- Payment summary --}}
    <table class="section" cellpadding="6">
        <thead>
            <tr>
                <th colspan="3">{{ __('filament-modular-subscriptions::fms.invoice.payment_summary') }} | Payment Summary</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('filament-modular-subscriptions::fms.invoice.total_amount') }}</td>
                <td>{!! $money($invoice->amount) !!}</td>
                <td class="ltr">Total Amount</td>
            </tr>
            <tr>
                <td>{{ __('filament-modular-subscriptions::fms.invoice.paid_amount') }}</td>
                <td>{!! $money($paid) !!}</td>
                <td class="ltr">Paid Amount</td>
            </tr>
            <tr style="background-color: {{ $remaining > 0 ? '#fee2e2' : '#dcfce7' }}; font-weight: bold;">
                <td>{{ __('filament-modular-subscriptions::fms.invoice.remaining_amount') }}</td>
                <td>{!! $money($remaining) !!}</td>
                <td class="ltr">Remaining Amount</td>
            </tr>
        </tbody>
    </table>

    <div style="text-align: center; margin-top: 22px; font-size: 8px; color: #6b7280;">ZATCA e-Invoice</div>
</body>

</html>
