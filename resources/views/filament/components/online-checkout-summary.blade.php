@props([
    'invoice',
])

@php
    $currency = strtoupper((string) config('filament-modular-subscriptions.currency_code', 'SAR'));
    $amount = number_format((float) $invoice->remaining_amount, 2);
@endphp

<style>
    .fms-checkout-summary {
        width: 100%;
        max-width: 32rem;
        margin: 0 auto;
    }

    .fms-checkout-summary-inner {
        position: relative;
        overflow: hidden;
        padding: 1.75rem 2rem;
        border-radius: 1rem;
        background: linear-gradient(135deg, rgb(15, 23, 42) 0%, rgb(30, 41, 59) 60%, rgb(5, 81, 96) 100%);
        color: white;
        box-shadow: 0 20px 40px -16px rgba(15, 23, 42, 0.45);
    }

    .fms-checkout-summary-glow {
        position: absolute;
        top: -45%;
        inset-inline-end: -25%;
        width: 22rem;
        height: 22rem;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.25), transparent 65%);
        pointer-events: none;
    }

    .fms-checkout-summary-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .fms-checkout-summary-label {
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: rgb(148, 163, 184);
    }

    .fms-checkout-summary-ref {
        font-size: 0.75rem;
        font-weight: 600;
        color: rgb(203, 213, 225);
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 9999px;
        padding: 0.25rem 0.8rem;
        white-space: nowrap;
    }

    .fms-checkout-summary-amount {
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .fms-checkout-summary-amount .value {
        font-size: 2.4rem;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.02em;
    }

    .fms-checkout-summary-amount .currency {
        font-size: 0.95rem;
        font-weight: 700;
        color: rgb(110, 231, 183);
    }

    .fms-checkout-summary-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
        padding-top: 1.1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .fms-checkout-summary-secure {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.78rem;
        font-weight: 600;
        color: rgb(110, 231, 183);
    }

    .fms-checkout-summary-secure svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }

    .fms-checkout-summary-brands {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .fms-checkout-summary-brands span {
        font-size: 0.7rem;
        font-weight: 700;
        color: rgb(203, 213, 225);
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 0.45rem;
        padding: 0.22rem 0.55rem;
        letter-spacing: 0.02em;
    }
</style>

<div class="fms-checkout-summary">
    <div class="fms-checkout-summary-inner">
        <div class="fms-checkout-summary-glow"></div>

        <div class="fms-checkout-summary-header">
            <p class="fms-checkout-summary-label">
                {{ __('filament-modular-subscriptions::fms.payments.amount_due') }}
            </p>
            <span class="fms-checkout-summary-ref">
                {{ __('filament-modular-subscriptions::fms.payments.result.invoice_reference', ['id' => $invoice->id]) }}
            </span>
        </div>

        <div class="fms-checkout-summary-amount">
            <span class="value">{{ $amount }}</span>
            <span class="currency">{{ $currency }}</span>
        </div>

        <div class="fms-checkout-summary-footer">
            <span class="fms-checkout-summary-secure">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                {{ __('filament-modular-subscriptions::fms.payments.secure_redirect_notice') }}
            </span>

            <span class="fms-checkout-summary-brands">
                <span>mada</span>
                <span>VISA</span>
                <span>Mastercard</span>
            </span>
        </div>
    </div>
</div>
