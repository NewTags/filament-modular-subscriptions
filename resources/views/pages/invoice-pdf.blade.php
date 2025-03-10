<!DOCTYPE html>
<html dir="rtl">
<header>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</header>

<body dir="rtl">
    @php
        if (function_exists('arabic')) {
            function arabic($text)
            {
                return $text;
                $arabic = new \ArPHP\I18N\Arabic();
                return $arabic->utf8Glyphs($text);
            }
        }
    @endphp
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');

        body {
            font-size: 6px;
            font-family: Cairo;
        }

        table {
            border-collapse: collapse;
        }

        tr {
            border-bottom: 1pt solid black;
        }

        th,
        td {
            border-left: 1pt solid black;
        }

        table {
            width: 100%;
            border: 1px solid #000;
        }
    </style>
    <table style="border: none; border-collapse:collapse;">
        <tbody style="border: none;">
            <tr style="border: none;">
                <td style="border: none;border-left: none; border-right: none; width: 100px;">
                    <img src="{{ config('filament-modular-subscriptions.company_logo') }}" alt="Company Logo"
                        style="max-width: 100px; height: auto;">
                </td>
                <td
                    style="border: none;border-left: none; border-right: none;text-align: center;font-weight: bolder;font-size: 12pt;">
                    {{ __('filament-modular-subscriptions::fms.invoice.invoice_title') }}</td>
                <td
                    style="border: none;border-left: none; border-right: none;text-align: left;font-weight: bolder;font-size: 12pt;">
                    @if ($invoice->status->value != 'paid')
                        <span
                            style="background-color: #fee2e2;color: #991b1b;text-align: center;padding: 5px 10px;display: inline-block;">
                            {{ $invoice->status->getLabel() }}
                        </span>
                    @else
                        <span style="background-color: #dcfce7;color: #166534;padding: 5px 10px;display: inline-block;">
                            {{ $invoice->status->getLabel() }}
                        </span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
    <h4> </h4>
    <table style="border: none; border-collapse:collapse;" cellpadding="6">
        <thead style="border: none;">
            <tr style="background-color: #e5e7eb;border: none; border-collapse:collapse;">
                <th scope="col" style="border: none;border-left: none; border-right: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.number') }}</th>
                <th scope="col" style="border: none;border-left: none; border-right: none;">{{ $invoice->id }}</th>
                <th scope="col" style="border: none;border-left: none; border-right: none; direction: ltr;">E-Invoice
                    #:</th>
                <th scope="col" style="border: none;border-left: none; border-right: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.date') }}:</th>
                <th scope="col" style="border: none;border-left: none; border-right: none;">
                    {{ now()->format('Y/m/d') }}</th>
                <th scope="col"
                    style="border: none;border-left: none; border-right: none;text-align: left;direction: ltr;">
                    E-Invoice Date #:</th>
            </tr>
        </thead>
    </table>
    <br />
    <br />
    <table style="border: none;border-collapse: collapse;" cellpadding="6">
        <thead style="border: none;">
            <tr style="background-color: #e5e7eb;border: none;">
                <th scope="col" style="border: none;border-left: none; border-right: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.billing_to') }}:</th>
                <th scope="col"
                    style="border: none;border-left: none; border-right: none;text-align: left;direction: ltr;">From:
                </th>
                <th scope="col" style="border: none;border-left: none; border-right: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.bill_to') }}:</th>
                <th scope="col"
                    style="border: none;border-left: none; border-right: none;text-align: left;direction: ltr;">Bill To:
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="2" style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    <span>{{ config('filament-modular-subscriptions.company_name') }}</span>
                    <br />
                    <span>{{ __('filament-modular-subscriptions::fms.invoice.billing_to') }}:
                        {{ config('filament-modular-subscriptions.company_address') }}</span>
                    <br />
                    <span>{{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}:
                        {{ config('filament-modular-subscriptions.tax_number') }}</span>
                    <br />
                </td>
                <td colspan="2" style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    <span>{{ $user['name'] }}</span>
                    <br />
                    <span>{{ __('filament-modular-subscriptions::fms.invoice.billing_to') }}:
                        {{ $user['customerInfo']['address'] }}</span>
                    <br />
                    <span>{{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}:
                        {{ $user['customerInfo']['vat_no'] }}</span>
                    <br />
                </td>
            </tr>
        </tbody>
    </table>
    <br />
    <br />
    <table style="border: none;border-collapse: collapse;" cellpadding="6">
        <thead style="border: none;">
            <tr style="background-color: #e5e7eb;border: none; ">
                <th style="border: none;border-left: none; border-right: none;" width="10%">No.</th>
                <th style="border: none;border-left: none; border-right: none;" width="50%">الصنف | Item</th>
                <th style="border: none;border-left: none; border-right: none;" width="10%">الكمية | Qty</th>
                <th style="border: none;border-left: none; border-right: none;" width="10%">السعر | Price</th>
                <th style="border: none;border-left: none; border-right: none;" width="10%">الضريبة | VAT</th>
                <th style="border: none;border-left: none; border-right: none;" width="10%">الإجمالي | Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                @php
                    $itemSubtotal = $item->quantity * $item->unit_price;
                    $itemTax = $itemSubtotal * 0.15;
                    $itemTotal = $itemSubtotal + $itemTax;
                @endphp
                <tr>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="10%">
                        {{ $loop->iteration }}</td>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="20%">
                        {{ $item->description }}</td>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="15%">
                        {{ $item->quantity }}</td>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="15%">
                        {{ number_format($item->unit_price, 2, '.', '') }}</td>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="15%">
                        {{ number_format($itemTax, 2, '.', '') }}</td>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="20%">
                        {{ number_format($itemTotal, 2, '.', '') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <br />
    <br />
    <table style="border: none;border-collapse: collapse;" cellpadding="6">
        <thead style="border: none;">
            <tr style="background-color: #e5e7eb;border: none;">
                <th colspan="3" style="border: none;border-left: none; border-right: none;text-align: center;">
                    {{ __('filament-modular-subscriptions::fms.invoice.total') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.subtotal') }}
                </td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    {!! fms_format_currency($invoice->subtotal, 2) !!}
                </td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;direction: ltr;">
                    Subtotal
                </td>
            </tr>
            <tr>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.tax_amount', ['percentage' => config('filament-modular-subscriptions.tax_percentage')]) }}
                </td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    {!! fms_format_currency($invoice->tax, 2) !!}
                </td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;direction: ltr;">
                    VAT({{ config('filament-modular-subscriptions.tax_percentage') }}%)
                </td>
            </tr>
            <tr style="background-color: #e5e7eb;border: none;">
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.total_with_tax') }}</td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    {!! fms_format_currency($invoice->amount, 2, '.', '') !!}
                </td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;direction: ltr;">
                    Total with VAT
                </td>
            </tr>
        </tbody>
    </table>
    <br />
    <br />
    <table style="border: none;border-collapse: collapse;" width="100%">
        <tbody>
            <tr style="border: none;">
                <td style="vertical-align: middle;text-align: center;border-collapse: collapse;border-left: none;"
                    width="50%">
                    <br />
                    <br />
                    <br />
                    <br />
                    <br />
                    <br />
                    <span>{{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}:
                        {{ config('filament-modular-subscriptions.tax_number') }}</span>
                </td>
                <td style="text-align:left;border-collapse: collapse;border-left: none;" width="50%">
                    <span>
                        <img width="100" height="100" src="{{ $QrCode }}" alt="QR Code" />
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
