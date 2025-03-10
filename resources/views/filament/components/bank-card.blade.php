@props([
    'bankName' => config('filament-modular-subscriptions.company_bank_name'),
    'accountNumber' => config('filament-modular-subscriptions.company_bank_account'),
    'iban' => config('filament-modular-subscriptions.company_bank_iban'),
    'swift' => config('filament-modular-subscriptions.company_bank_swift'),
    'companyName' => config('filament-modular-subscriptions.company_name'),
])

<style>
    .bank-card {
        width: 100%;
        max-width: 32rem;
        margin: 0 auto;
    }

    .bank-card-inner {
        position: relative;
        padding: 2rem;
        background: linear-gradient(to top right, rgb(17, 24, 39), rgb(31, 41, 55));
        border-radius: 0.75rem;
        color: white;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
    }

    .bank-card-pattern {
        position: absolute;
        inset: 0;
        opacity: 0.1;
        background-image: url('data:image/svg+xml,%3Csvg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="%23ffffff" fill-opacity="0.1" fill-rule="evenodd"%3E%3Cpath d="M0 40L40 0H20L0 20M40 40V20L20 40"/%3E%3C/g%3E%3C/svg%3E');
    }

    .bank-card-logo {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 2.5rem;
        height: 2.5rem;
        color: rgba(255, 255, 255, 0.2);
    }

    .bank-card-content {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .bank-card-field {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .bank-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }

    .bank-card-label {
        color: rgb(209, 213, 219);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .bank-card-value {
        font-weight: 600;
        font-size: 1.125rem;
        letter-spacing: 0.025em;
    }

    .bank-card-value.mono {
        font-family: monospace;
    }

    .bank-card-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
</style>

<div class="bank-card">
    <div class="bank-card-inner">
        <div class="bank-card-pattern"></div>
    
        <div class="bank-card-logo">
            <svg fill="currentColor" viewBox="0 0 24 24">
                <path
                    d="M4 10h3v7H4v-7zm6.5-7l-8.5 4v3h17V7l-8.5-4zM13 10h3v7h-3v-7zm6 0h3v7h-3v-7zm-14 9v2h19v-2H5z" />
            </svg>
        </div>

        <div class="bank-card-content">
            <div class="bank-card-header">
                <div class="bank-card-field">
                    <p class="bank-card-label">{{ __('filament-modular-subscriptions::fms.resources.payment.company_name') }}</p>
                    <p class="bank-card-value">{{ $companyName }}</p>
                </div>

                <div class="bank-card-field">
                    <p class="bank-card-label">{{ __('filament-modular-subscriptions::fms.resources.payment.bank_name') }}</p>
                    <p class="bank-card-value">{{ $bankName }}</p>
                </div>
            </div>

            <div class="bank-card-field">
                <p class="bank-card-label">{{ __('filament-modular-subscriptions::fms.resources.payment.account_number') }}</p>
                <p class="bank-card-value mono">{{ $accountNumber }}</p>
            </div>

            <div class="bank-card-grid">
                <div class="bank-card-field">
                    <p class="bank-card-label">{{ __('IBAN') }}</p>
                    <p class="bank-card-value mono">{{ $iban }}</p>
                </div>

                <div class="bank-card-field">
                    <p class="bank-card-label">{{ __('Swift Code') }}</p>
                    <p class="bank-card-value mono">{{ $swift }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
