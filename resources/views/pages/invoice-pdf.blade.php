<html lang="ar" dir="rtl">

<head>
    <style type="text/css">
        * {
            direction: rtl;
            font-size: 12px;
        }

        html {
            margin: 60px;
        }

        table {
            text-align: center;
            vertical-align: middle;
        }

        td,
        th {
            padding: 5px;
        }

        th {
            background-color: #F3F5F7;
            color: #061C71;
        }

        @page {
            margin: 150px 50px;
        }

        #header {
            position: fixed;
            left: -50px;
            top: -150px;
            right: -50px;
            height: 150px;
            background-color: #061C71;
        }

        #head_line {
            background-color: #061C71;
            height: 8px;
            margin-bottom: 10px;
        }
    </style>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>===TITLEPLACEHOLDER===</title>
</head>

<body style="direction: rtl; text-align: right">

    <div id="head_line"></div>

    <table class="normal-table" align="center" width="100%" style="direction: rtl" border="0">
        <tr>
            <td align="left">
                {{ __('filament-modular-subscriptions::fms.invoice.invoice_title') }}<br>
                <img width="100" height="100" src="{{ $QrCode }}" alt="QR Code" />
            </td>
            <td align="right">{{-- ===LOGOPLACEHOLDER=== --}}</td>
            <td align="right">===COMPANYLOGOPLACEHOLDER===</td>
        </tr>
        <tr>
            <td align="right" valign="top" colspan="2">
                {{ config('filament-modular-subscriptions.company_name') }}
                <br>
                {{ __('filament-modular-subscriptions::fms.invoice.billing_to') }}:
                {{ config('filament-modular-subscriptions.company_address') }}
                <br>
                {{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}:
                {{ config('filament-modular-subscriptions.tax_number') }}
                <br>
            </td>
            <td align="right">
                {{ __('filament-modular-subscriptions::fms.invoice.number') }}:
                {{ $invoice->id }}
                <br>

                {{ now()->format('Y/m/d') }}
                {{ now()->format('h:i') }}
                {{ __('filament-modular-subscriptions::fms.invoice.time_period.' . now()->format('a')) }}
                {{ __('filament-modular-subscriptions::fms.invoice.date') }}:
                <br>

                {{ date('Y/m/d', strtotime($invoice->due_date)) }}
                {{ __('filament-modular-subscriptions::fms.invoice.due_date') }}:
                <br>

                {{ $invoice->status->getLabel() }}
                {{ __('filament-modular-subscriptions::fms.invoice.status') }}:
                <br><br>

                {{ $user['name'] }}
                {{ __('filament-modular-subscriptions::fms.invoice.bill_to') }}:
                <br>

                {{ __('filament-modular-subscriptions::fms.invoice.billing_to') }}:
                {{ $user['customerInfo']['address'] }}
                <br>

                {{ __('filament-modular-subscriptions::fms.invoice.tax_number') }}:
                {{ $user['customerInfo']['vat_no'] }}
                <br>
            </td>
        </tr>
    </table>
    <br>
    <div class="content" style="direction: rtl;">
        @if ($invoice->items->isNotEmpty())
            <span>{{ __('filament-modular-subscriptions::fms.invoice.items') }}:</span>
            <br>
            <table class="normal-table" align="center" width="100%" style="direction: rtl" border="1"
                cellpadding="0" cellspacing="0">
                <tr>
                    <th width="20%">{{ __('filament-modular-subscriptions::fms.invoice.total') }}</th>
                    <th width="10%">{{ __('filament-modular-subscriptions::fms.invoice.quantity') }}</th>
                    <th width="50%">{{ __('filament-modular-subscriptions::fms.invoice.item') }}</th>
                </tr>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>
                            {{ number_format($item->amount, 2, '.', '') }}
                            {{ __('filament-modular-subscriptions::fms.invoice.currency') }}
                        </td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ $item->description }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th>
                        {{ $invoice->amount }}
                        {{ __('filament-modular-subscriptions::fms.invoice.currency') }}
                    </th>
                    <th colspan="2" class="font-bold">
                        {{ __('filament-modular-subscriptions::fms.invoice.total_with_tax') }}
                    </th>
                </tr>
            </table>
        @endif
        <br>
        <p style="font-size: 18px;">
            {{ __('filament-modular-subscriptions::fms.invoice.invoice_details') }}:
        </p>
        <table class="normal-table" align="right" width="50%" style="direction: rtl" border="0" cellpadding="0"
            cellspacing="0">
            <tr style="border-bottom: solid 1px lightgray; padding-bottom: 5px;">
                <td width="10%" align="left">
                    {{ $invoice->amount }}
                    {{ __('filament-modular-subscriptions::fms.invoice.currency') }}
                    <br>
                </td>
                <th width="15%" style="background: unset" align="right">
                    {{ __('filament-modular-subscriptions::fms.invoice.subtotal') }}
                </th>
            </tr>
            <tr style="padding-bottom: 5px;">
                <td width="10%" align="left">
                    {{ $invoice->tax }}
                    {{ __('filament-modular-subscriptions::fms.invoice.currency') }}
                </td>
                <th width="15%" style="background: unset" align="right">
                    {{ __('filament-modular-subscriptions::fms.invoice.tax_amount', ['percentage' => config('filament-modular-subscriptions.tax_percentage')]) }}
                </th>
            </tr>
            <tr style="border-top: solid 1px #061C71; padding-top: 5px;">
                <td width="10%" align="left">
                    {{ $invoice->amount + $invoice->tax }}
                    {{ __('filament-modular-subscriptions::fms.invoice.currency') }}
                </td>
                <th width="15%" style="background: unset" align="right">
                    {{ __('filament-modular-subscriptions::fms.invoice.total_with_tax') }}
                </th>
            </tr>
        </table>
    </div>

</body>

</html>
