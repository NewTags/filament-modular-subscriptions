@props([
    'invoice',
])

@php
    $currency = config('filament-modular-subscriptions.main_currency')
        ?: strtoupper((string) config('filament-modular-subscriptions.currency_code', 'SAR'));
    $amount = number_format((float) $invoice->remaining_amount, 2);
    $steps = [
        ['icon' => 'M3 10h18M7 15h2m4 0h4M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'label' => __('filament-modular-subscriptions::fms.payments.step_choose_card')],
        ['icon' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z', 'label' => __('filament-modular-subscriptions::fms.payments.step_pay_secure')],
        ['icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => __('filament-modular-subscriptions::fms.payments.step_instant_confirm')],
    ];
@endphp

<style>
    .fms-checkout-summary {
        width: 100%;
        max-width: 34rem;
        margin: 0 auto;
    }

    .fms-checkout-summary-inner {
        position: relative;
        overflow: hidden;
        padding: 1.75rem 1.85rem;
        border-radius: 1.35rem;
        background:
            radial-gradient(120% 120% at 100% 0%, rgba(16, 185, 129, 0.18), transparent 55%),
            linear-gradient(135deg, rgb(15, 23, 42) 0%, rgb(23, 37, 60) 55%, rgb(6, 78, 92) 100%);
        color: white;
        box-shadow: 0 24px 48px -20px rgba(8, 47, 73, 0.65), inset 0 1px 0 rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .fms-checkout-summary-glow {
        position: absolute;
        top: -50%;
        inset-inline-end: -20%;
        width: 24rem;
        height: 24rem;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.22), transparent 62%);
        pointer-events: none;
    }

    .fms-cs-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.6rem;
    }

    .fms-cs-label {
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: rgb(148, 163, 184);
    }

    .fms-cs-ref {
        font-size: 0.74rem;
        font-weight: 600;
        color: rgb(203, 213, 225);
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 9999px;
        padding: 0.25rem 0.8rem;
        white-space: nowrap;
    }

    .fms-cs-amount {
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
        margin-bottom: 1.4rem;
    }

    .fms-cs-amount .value {
        font-size: 2.7rem;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.02em;
    }

    .fms-cs-amount .currency {
        font-family: 'SaudiRiyal', sans-serif;
        font-size: 1.15rem;
        font-weight: 700;
        color: rgb(110, 231, 183);
    }

    .fms-cs-steps {
        display: flex;
        gap: 0.6rem;
        padding: 1rem 0;
        margin: 0.2rem 0 0.4rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .fms-cs-step {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.45rem;
    }

    .fms-cs-step-icon {
        position: relative;
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(16, 185, 129, 0.14);
        border: 1px solid rgba(16, 185, 129, 0.35);
        color: rgb(110, 231, 183);
    }

    .fms-cs-step-icon svg {
        width: 1.2rem;
        height: 1.2rem;
    }

    .fms-cs-step-num {
        position: absolute;
        top: -0.35rem;
        inset-inline-end: -0.35rem;
        width: 1.1rem;
        height: 1.1rem;
        border-radius: 9999px;
        background: rgb(16, 185, 129);
        color: rgb(6, 38, 33);
        font-size: 0.62rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fms-cs-step-label {
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1.4;
        color: rgb(203, 213, 225);
    }

    .fms-cs-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
        padding-top: 1.05rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .fms-cs-secure {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.78rem;
        font-weight: 600;
        color: rgb(110, 231, 183);
    }

    .fms-cs-secure svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }

    .fms-cs-brands {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .fms-cs-brands span {
        font-size: 0.7rem;
        font-weight: 700;
        color: rgb(203, 213, 225);
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 0.45rem;
        padding: 0.22rem 0.55rem;
        letter-spacing: 0.02em;
    }

    @media (max-width: 30rem) {
        .fms-cs-step-label { font-size: 0.66rem; }
        .fms-cs-amount .value { font-size: 2.2rem; }
    }
</style>

<div class="fms-checkout-summary">
    <div class="fms-checkout-summary-inner">
        <div class="fms-checkout-summary-glow"></div>

        <div class="fms-cs-header">
            <p class="fms-cs-label">
                {{ __('filament-modular-subscriptions::fms.payments.amount_due') }}
            </p>
            <span class="fms-cs-ref">
                {{ __('filament-modular-subscriptions::fms.payments.result.invoice_reference', ['id' => $invoice->number]) }}
            </span>
        </div>

        <div class="fms-cs-amount">
            <span class="value">{{ $amount }}</span>
            <span class="currency">{{ $currency }}</span>
        </div>

        <div class="fms-cs-steps">
            @foreach ($steps as $index => $step)
                <div class="fms-cs-step">
                    <span class="fms-cs-step-icon">
                        <span class="fms-cs-step-num">{{ $index + 1 }}</span>
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}" />
                        </svg>
                    </span>
                    <span class="fms-cs-step-label">{{ $step['label'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="fms-cs-footer">
            <span class="fms-cs-secure">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                {{ __('filament-modular-subscriptions::fms.payments.secure_redirect_notice') }}
            </span>

            <span class="fms-cs-brands">
                <span>mada</span>
                <span>VISA</span>
                <span>Mastercard</span>
            </span>
        </div>
    </div>
</div>
