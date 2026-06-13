@php
    $checkout = $checkout ?? null;
    $invoice = $invoice ?? null;

    $variant = $state['variant'] ?? $state['kind'] ?? 'pending';

    $palette = match ($variant) {
        'success' => ['accent' => '#059669', 'soft' => '#d1fae5', 'glow' => 'rgba(5, 150, 105, 0.18)'],
        'failed', 'error' => ['accent' => '#e11d48', 'soft' => '#ffe4e6', 'glow' => 'rgba(225, 29, 72, 0.16)'],
        'expired' => ['accent' => '#475569', 'soft' => '#e2e8f0', 'glow' => 'rgba(71, 85, 105, 0.14)'],
        'cancelled' => ['accent' => '#6b7280', 'soft' => '#e5e7eb', 'glow' => 'rgba(107, 114, 128, 0.14)'],
        default => ['accent' => '#d97706', 'soft' => '#fef3c7', 'glow' => 'rgba(217, 119, 6, 0.16)'],
    };

    $iconShape = match ($variant) {
        'success' => 'check',
        'cancelled' => 'ban',
        'failed' => 'cross',
        'error' => 'alert',
        default => 'clock',
    };

    $invoiceId = $checkout->invoice_id ?? ($invoice?->id);
    $amount = $checkout
        ? number_format((float) $checkout->amount, 2)
        : ($invoice ? number_format((float) $invoice->amount, 2) : null);
    $currencySymbol = config('filament-modular-subscriptions.main_currency')
        ?: strtoupper((string) ($checkout->currency ?? config('filament-modular-subscriptions.currency_code', 'SAR')));
    $companyName = config('filament-modular-subscriptions.company_name');
    $retryUrl = $state['retry_url'] ?? null;
    $shouldPoll = (bool) ($state['poll'] ?? false);

    $isRtl = in_array(app()->getLocale(), ['ar', 'fa', 'ur'], true);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @if ($shouldPoll)
        <meta http-equiv="refresh" content="8">
    @endif
    <meta name="color-scheme" content="light dark">
    <title>{{ $state['title'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'SaudiRiyal';
            src: url('{{ asset('fonts/saudi_riyal/saudi_riyal.woff2') }}') format('woff2'),
                 url('{{ asset('fonts/saudi_riyal/saudi_riyal.woff') }}') format('woff');
            font-display: block;
        }

        :root {
            --accent: {{ $palette['accent'] }};
            --soft: {{ $palette['soft'] }};
            --glow: {{ $palette['glow'] }};
            --bg-from: #f8fafc;
            --bg-to: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.92);
            --card-border: rgba(15, 23, 42, 0.06);
            --text: #0f172a;
            --text-muted: #475569;
            --summary-bg: #f8fafc;
            --summary-border: #e2e8f0;
            --summary-label: #64748b;
            --footer: #94a3b8;
            --footer-strong: #64748b;
            --card-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 24px 48px -16px rgba(15, 23, 42, 0.14);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-from: #0b1120;
                --bg-to: #020617;
                --card-bg: rgba(15, 23, 42, 0.92);
                --card-border: rgba(148, 163, 184, 0.14);
                --text: #f1f5f9;
                --text-muted: #cbd5e1;
                --summary-bg: rgba(148, 163, 184, 0.08);
                --summary-border: rgba(148, 163, 184, 0.16);
                --summary-label: #94a3b8;
                --footer: #64748b;
                --footer-strong: #94a3b8;
                --card-shadow: 0 1px 2px rgba(0, 0, 0, 0.3), 0 24px 48px -16px rgba(0, 0, 0, 0.6);
            }
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
            color: var(--text);
            background:
                radial-gradient(60rem 60rem at 110% -10%, var(--glow), transparent 60%),
                radial-gradient(50rem 50rem at -20% 110%, var(--glow), transparent 55%),
                linear-gradient(180deg, var(--bg-from) 0%, var(--bg-to) 100%);
        }

        .card {
            width: 100%;
            max-width: 26.5rem;
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 1.75rem;
            box-shadow: var(--card-shadow);
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

        .body-text { color: var(--text-muted); font-size: 0.95rem; line-height: 1.7; margin-bottom: 1.75rem; }

        .summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            background: var(--summary-bg);
            border: 1px solid var(--summary-border);
            border-radius: 1.1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .summary .label { font-size: 0.78rem; color: var(--summary-label); font-weight: 600; }

        .summary .value { font-size: 1.05rem; font-weight: 800; color: var(--text); }

        .summary .value .currency {
            font-family: 'SaudiRiyal', 'Cairo', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--summary-label);
            margin-inline-start: 0.25rem;
        }

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

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.85rem 1.25rem;
            margin-bottom: 1.25rem;
            border-radius: 0.9rem;
            background: var(--accent);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            transition: filter 160ms ease, box-shadow 160ms ease;
            box-shadow: 0 10px 20px -8px var(--glow);
        }

        .btn:hover { filter: brightness(0.94); box-shadow: 0 14px 24px -8px var(--glow); }

        .btn svg { width: 1.15rem; height: 1.15rem; stroke: currentColor; }

        .footer { font-size: 0.78rem; color: var(--footer); font-weight: 600; }

        .footer strong { color: var(--footer-strong); }
    </style>
</head>
<body>
    <main class="card">
        <div class="icon-wrap">
            @switch($iconShape)
                @case('check')
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path class="icon-draw" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    @break
                @case('cross')
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path class="icon-draw" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    @break
                @case('ban')
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9" />
                        <path class="icon-draw" d="M5.64 5.64l12.72 12.72" />
                    </svg>
                    @break
                @case('alert')
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9" />
                        <path class="icon-draw" d="M12 8v4.5" />
                        <path d="M12 16h.01" />
                    </svg>
                    @break
                @default
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path class="icon-draw" d="M12 6v6h4.5" />
                        <circle cx="12" cy="12" r="9" />
                    </svg>
            @endswitch
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
                <span class="value">{{ $amount }}<span class="currency">{{ $currencySymbol }}</span></span>
            </div>
        @endif

        @if (filled($retryUrl))
            <a class="btn" href="{{ $retryUrl }}">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4v5h5M20 20v-5h-5" />
                    <path d="M19 9a7.5 7.5 0 00-13-3.5L4 9m16 6l-2 3.5A7.5 7.5 0 015 15" />
                </svg>
                {{ __('filament-modular-subscriptions::fms.payments.result.retry') }}
            </a>
        @endif

        @if (filled($companyName))
            <p class="footer">{{ __('filament-modular-subscriptions::fms.payments.result.footer', ['company' => $companyName]) }}</p>
        @endif
    </main>
</body>
</html>
