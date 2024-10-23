<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        {{ __('filament-modular-subscriptions::modular-subscriptions.invoice.email_subject', ['number' => $invoice->id]) }}
    </title>
</head>

<body>
    <h1>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.email_greeting') }}</h1>
    <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.email_body', ['number' => $invoice->id]) }}
    </p>
    <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.email_amount', ['amount' => number_format($invoice->amount, 2), 'currency' => $invoice->subscription->plan->currency]) }}
    </p>
    <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.email_due_date', ['date' => $invoice->due_date->format('Y-m-d')]) }}
    </p>
    <p>{{ __('filament-modular-subscriptions::modular-subscriptions.invoice.email_closing') }}</p>
</body>

</html>