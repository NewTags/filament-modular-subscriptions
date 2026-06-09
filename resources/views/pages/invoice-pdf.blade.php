@php
    $currency = config('filament-modular-subscriptions.main_currency');
    $taxPercentage = $tax_percentage ?? config('filament-modular-subscriptions.tax_percentage', 15);
    $subtotal = $total_before_tax ?? $invoice->subtotal;
    $taxValue = $tax_amount ?? $invoice->tax;
    $isPaid = $invoice->status->value === 'paid';
    $money = fn ($value) => number_format((float) $value, 2, '.', ',') . ' <span class="riyal">' . e($currency) . '</span>';
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            font-family: 'dinnextltarabic-medium', sans-serif;
            font-size: 9.5px;
            color: #1f2937;
        }

        .riyal {
            font-family: 'riyal', 'dinnextltarabic-medium', sans-serif;
        }

        .wrap {
            width: 100%;
        }

        .head {
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .head td {
            vertical-align: middle;
        }

        .doc-title {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
        }

        .doc-sub {
            font-size: 9px;
            color: #6b7280;
            direction: ltr;
        }

        .status {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
        }

        .status-paid {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-unpaid {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .meta {
            width: 100%;
            margin-bottom: 12px;
        }

        .party {
            width: 49%;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: top;
        }

        .party-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: .5px;
            margin-bottom: 3px;
        }

        .party-name {
            font-weight: bold;
            font-size: 11px;
            color: #111827;
        }

        .party-line {
            font-size: 9px;
            color: #4b5563;
            margin-top: 2px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        table.items th {
            background-color: #111827;
            color: #ffffff;
            padding: 6px 8px;
            font-size: 9px;
            font-weight: bold;
        }

        table.items td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9.5px;
        }

        table.items tr:nth-child(even) td {
            background-color: #f9fafb;
        }

        .num {
            text-align: center;
        }

        .end {
            text-align: left;
        }

        .totals {
            width: 45%;
            border-collapse: collapse;
        }

        .totals td {
            padding: 5px 8px;
            font-size: 10px;
        }

        .totals .label {
            color: #6b7280;
        }

        .totals .value {
            text-align: left;
            font-weight: bold;
            color: #111827;
        }

        .totals .grand td {
            border-top: 2px solid #111827;
            font-size: 12px;
            font-weight: bold;
            color: #111827;
        }

        .foot {
            width: 100%;
            margin-top: 18px;
        }
    </style>
</head>

<body dir="rtl">
    {{-- Header --}}
    <table class="head">
        <tr>
            <td style="width: 22%;">
                @if (is_file($company_logo))
                    <img src="{{ $company_logo }}" alt="" style="max-width: 110px; max-height: 60px;">
                @endif
            </td>
            <td style="width: 40%; text-align: center;">
                <div class="doc-title">{{ __('filament-modular-subscriptions::fms.invoice.tax_invoice') }}</div>
                <div class="doc-sub">Tax Invoice</div>
            </td>
            <td style="width: 38%; text-align: left;">
                <div style="font-size: 11px; font-weight: bold;">
                    {{ __('filament-modular-subscriptions::fms.invoice.invoice_number', ['number' => $invoice->id]) }}
                </div>
                <div style="font-size: 9px; color: #6b7280; margin: 3px 0;">
                    {{ __('filament-modular-subscriptions::fms.invoice.date') }}:
                    {{ $invoice->created_at->format('Y/m/d') }}
                </div>
                <span class="status {{ $isPaid ? 'status-paid' : 'status-unpaid' }}">
                    {{ $invoice->status->getLabel() }}
                </span>
            </td>
        </tr>
    </table>

    {{-- Seller / Buyer --}}
    <table class="meta">
        <tr>
            <td class="party">
                <div class="party-label">{{ __('filament-modular-subscriptions::fms.invoice.from') }}</div>
                <div class="party-name">{{ config('filament-modular-subscriptions.company_name') }}</div>
                <div class="party-line">{{ config('filament-modular-subscriptions.company_address') }}</div>
                <div class="party-line">
                    {{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}:
                    {{ config('filament-modular-subscriptions.tax_number') }}
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td class="party">
                <div class="party-label">{{ __('filament-modular-subscriptions::fms.invoice.bill_to') }}</div>
                <div class="party-name">{{ $user['name'] ?? '' }}</div>
                <div class="party-line">{{ $user['customerInfo']['address'] ?? '' }}</div>
                <div class="party-line">
                    {{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}:
                    {{ $user['customerInfo']['vat_no'] ?? '—' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="text-align: start;">{{ __('filament-modular-subscriptions::fms.invoice.description') }}</th>
                <th style="width: 12%;">{{ __('filament-modular-subscriptions::fms.invoice.quantity') }}</th>
                <th style="width: 20%;">{{ __('filament-modular-subscriptions::fms.invoice.unit_price') }}</th>
                <th style="width: 22%;">{{ __('filament-modular-subscriptions::fms.invoice.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td class="num">{{ $loop->iteration }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ (int) $item->quantity }}</td>
                    <td class="end">{!! $money($item->unit_price) !!}</td>
                    <td class="end">{!! $money($item->total) !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <table style="width: 100%;">
        <tr>
            <td style="width: 55%; vertical-align: bottom; font-size: 8px; color: #6b7280;">
                {{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}:
                {{ config('filament-modular-subscriptions.tax_number') }}
            </td>
            <td style="width: 45%;">
                <table class="totals">
                    <tr>
                        <td class="label">{{ __('filament-modular-subscriptions::fms.invoice.subtotal') }}</td>
                        <td class="value">{!! $money($subtotal) !!}</td>
                    </tr>
                    <tr>
                        <td class="label">
                            {{ __('filament-modular-subscriptions::fms.invoice.tax_amount', ['percentage' => $taxPercentage]) }}
                        </td>
                        <td class="value">{!! $money($taxValue) !!}</td>
                    </tr>
                    <tr class="grand">
                        <td>{{ __('filament-modular-subscriptions::fms.invoice.total_with_tax') }}</td>
                        <td class="value">{!! $money($invoice->amount) !!}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- QR --}}
    <table class="foot">
        <tr>
            <td style="text-align: center;">
                @if (!empty($QrCode))
                    <img src="{{ $QrCode }}" alt="QR" style="width: 110px; height: 110px;" />
                    <div style="font-size: 8px; color: #6b7280; margin-top: 4px;">ZATCA e-Invoice</div>
                @endif
            </td>
        </tr>
    </table>
</body>

</html>
