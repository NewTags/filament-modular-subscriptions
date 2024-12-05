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
        body {
            font-size: 8px;
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
                    style="border: none;border-left: none; border-right: none;text-align: left;font-weight: bolder;font-size: 12pt;padding: 10px;">
                    <span
                        style="background-color: {{ $invoice->status->value == 'paid' ? '#dcfce7' : '#fee2e2' }};color: {{ $invoice->status->value == 'paid' ? '#166534' : '#991b1b' }};padding: 10px;border-radius: 10px;">
                        {{ $invoice->status->getLabel() }}
                    </span>
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
                <th scope="col" style="border: none;border-left: none; border-right: none;">E-Invoice #:</th>
                <th scope="col" style="border: none;border-left: none; border-right: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.date') }}:</th>
                <th scope="col" style="border: none;border-left: none; border-right: none;">
                    {{ now()->format('Y/m/d') }}</th>
                <th scope="col" style="border: none;border-left: none; border-right: none;text-align: left;">
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
                <th scope="col" style="border: none;border-left: none; border-right: none;text-align: left;">From:
                </th>
                <th scope="col" style="border: none;border-left: none; border-right: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.bill_to') }}:</th>
                <th scope="col" style="border: none;border-left: none; border-right: none;text-align: left;">Bill To:
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
            <tr style="background-color: #e5e7eb;border: none;">
                <th style="border: none;border-left: none; border-right: none;" width="10%">No.</th>
                <th style="border: none;border-left: none; border-right: none;" width="40%">
                    {{ __('filament-modular-subscriptions::fms.invoice.item') }}</th>
                <th style="border: none;border-left: none; border-right: none;" width="10%">
                    {{ __('filament-modular-subscriptions::fms.invoice.quantity') }}</th>
                <th style="border: none;border-left: none; border-right: none;" width="15%">
                    {{ __('filament-modular-subscriptions::fms.invoice.unit_price') }}</th>
                <th style="border: none;border-left: none; border-right: none;" width="25%">
                    {{ __('filament-modular-subscriptions::fms.invoice.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="10%">
                        {{ $loop->iteration }}</td>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="50%">
                        {{ $item->description }}</td>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="10%">
                        {{ $item->quantity }}</td>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="10%">
                        {{ number_format($item->unit_price, 2, '.', '') }}</td>
                    <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;" width="10%">
                        {{ number_format($item->total, 2, '.', '') }}</td>
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
                    {{ __('filament-modular-subscriptions::fms.invoice.total_with_tax') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.subtotal') }}</td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">Subtotal</td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;direction: ltr;">
                    {{ number_format($invoice->subtotal, 2) }} {{ __('filament-modular-subscriptions::fms.invoice.currency') }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.tax_amount', ['percentage' => config('filament-modular-subscriptions.tax_percentage')]) }}
                </td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">VAT
                    ({{ config('filament-modular-subscriptions.tax_percentage') }}%)</td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;direction: ltr;">
                    {{ number_format($invoice->tax, 2) }} {{ __('filament-modular-subscriptions::fms.invoice.currency') }}</td>
            </tr>
            <tr style="background-color: #e5e7eb;border: none;">
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">
                    {{ __('filament-modular-subscriptions::fms.invoice.total_with_tax') }}</td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;">Total with VAT</td>
                <td style="border: 1px solid #e5e7eb; border-collapse: collapse;border-left: none;direction: rtl;">
                    {{ number_format($invoice->amount, 2, '.', '') }}
                    {{ __('filament-modular-subscriptions::fms.invoice.currency') }}</td>
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
