@php
    $kind = $state['kind'] ?? 'warning';
    $isRtl = in_array(app()->getLocale(), ['ar', 'fa', 'ur'], true);
    $palette = match ($kind) {
        'success' => ['accent' => '#059669', 'soft' => '#d1fae5', 'glow' => 'rgba(5, 150, 105, 0.18)'],
        'danger' => ['accent' => '#e11d48', 'soft' => '#ffe4e6', 'glow' => 'rgba(225, 29, 72, 0.16)'],
        default => ['accent' => '#d97706', 'soft' => '#fef3c7', 'glow' => 'rgba(217, 119, 6, 0.16)'],
    };
    $invoiceId = $checkout->invoice_id ?? ($invoice->id ?? null);
    $amount = $checkout ? number_format((float) $checkout->amount, 2) : ($invoice ?? null ? number_format((float) $invoice->amount, 2) : null);
    $currency = $checkout->currency ?? strtoupper((string) config('filament-modular-subscriptions.currency_code', 'SAR'));
    $companyName = config('filament-modular-subscriptions.company_name');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $state['title'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: {{ $palette['accent'] }};
            --soft: {{ $palette['soft'] }};
            --glow: {{ $palette['glow'] }};
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Cairo', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #0f172a;
            background:
                radial-gradient(60rem 60rem at 110% -10%, var(--glow), transparent 60%),
                radial-gradient(50rem 50rem at -20% 110%, var(--glow), transparent 55%),
                linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .card {
            width: 100%;
            max-width: 26.5rem;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 1.75rem;
            box-shadow:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 24px 48px -16px rgba(15, 23, 42, 0.14);
            padding: 2.75rem 2.25rem 2rem;
            text-align: center;
            animation: rise 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .icon-wrap {
            width: 5.25rem;
            height: 5.25rem;
            margin: 0 auto 1.5rem;
            border-radius: 9999px;
            background: var(--soft);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: pop 540ms 120ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .icon-wrap::after {
            content: '';
            position: absolute;
            inset: -0.55rem;
            border-radius: 9999px;
            border: 2px solid var(--soft);
            opacity: 0.8;
        }

        @keyframes pop {
            from { opacity: 0; transform: scale(0.5); }
            to { opacity: 1; transform: scale(1); }
        }

        .icon-wrap svg { width: 2.6rem; height: 2.6rem; stroke: var(--accent); }

        .icon-draw {
            stroke-dasharray: 60;
            stroke-dashoffset: 60;
            animation: draw 600ms 420ms ease-out forwards;
        }

        @keyframes draw { to { stroke-dashoffset: 0; } }

        h1 { font-size: 1.45rem; font-weight: 800; letter-spacing: -0.01em; margin-bottom: 0.5rem; }

        .body-text { color: #475569; font-size: 0.95rem; line-height: 1.7; margin-bottom: 1.75rem; }

        .summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1.1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .summary .label { font-size: 0.78rem; color: #64748b; font-weight: 600; }

        .summary .value { font-size: 1.05rem; font-weight: 800; color: #0f172a; }

        .summary .value .currency { font-size: 0.75rem; font-weight: 700; color: #64748b; margin-inline-start: 0.25rem; }

        .ref-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--soft);
            color: var(--accent);
            font-size: 0.78rem;
            font-weight: 700;
            border-radius: 9999px;
            padding: 0.3rem 0.9rem;
            margin-bottom: 1.6rem;
        }

        .footer { font-size: 0.78rem; color: #94a3b8; font-weight: 600; }

        .footer strong { color: #64748b; }
    </style>
</head>
<body>
    <main class="card">
        <div class="icon-wrap">
            @if ($kind === 'success')
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path class="icon-draw" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            @elseif ($kind === 'danger')
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path class="icon-draw" d="M6 18L18 6M6 6l12 12" />
                </svg>
            @else
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path class="icon-draw" d="M12 6v6h4.5" />
                    <circle cx="12" cy="12" r="9" />
                </svg>
            @endif
        </div>

        @if ($invoiceId)
            <span class="ref-chip">
                {{ __('filament-modular-subscriptions::fms.payments.result.invoice_reference', ['id' => $invoiceId]) }}
            </span>
        @endif

        <h1>{{ $state['title'] }}</h1>
        <p class="body-text">{{ $state['body'] }}</p>

        @if ($amount)
            <div class="summary">
                <span class="label">{{ __('filament-modular-subscriptions::fms.payments.result.amount_label') }}</span>
                <span class="value">{{ $amount }}<span class="currency">{{ $currency }}</span></span>
            </div>
        @endif

        @if (filled($companyName))
            <p class="footer">{{ __('filament-modular-subscriptions::fms.payments.result.footer', ['company' => $companyName]) }}</p>
        @endif
    </main>
</body>
</html>
