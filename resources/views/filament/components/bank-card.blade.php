@props([
    'bankName' => config('filament-modular-subscriptions.company_bank_name'),
    'accountNumber' => config('filament-modular-subscriptions.company_bank_account'),
    'iban' => config('filament-modular-subscriptions.company_bank_iban'),
    'swift' => config('filament-modular-subscriptions.company_bank_swift'),
])

<div class="w-full max-w-lg mx-auto">
    <div
        class="relative p-8 bg-gradient-to-tr from-gray-900 to-gray-800 rounded-xl text-white shadow-2xl overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0"
                style="background-image: url('data:image/svg+xml,%3Csvg width=\"40\" height=\"40\" viewBox=\"0 0 40 40\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.1\" fill-rule=\"evenodd\"%3E%3Cpath d=\"M0 40L40 0H20L0 20M40 40V20L20 40\"/%3E%3C/g%3E%3C/svg%3E')">
            </div>
        </div>

        <!-- Bank Logo/Icon -->
        <div class="absolute top-4 right-4">
            <svg class="w-10 h-10 text-white/20" fill="currentColor" viewBox="0 0 24 24">
                <path
                    d="M4 10h3v7H4v-7zm6.5-7l-8.5 4v3h17V7l-8.5-4zM13 10h3v7h-3v-7zm6 0h3v7h-3v-7zm-14 9v2h19v-2H5z" />
            </svg>
        </div>

        <!-- Content -->
        <div class="relative space-y-6">
            <div class="space-y-2">
                <p class="text-gray-300 text-xs uppercase tracking-wider">
                    {{ __('filament-modular-subscriptions::fms.resources.payment.bank_name') }}</p>
                <p class="font-semibold text-lg tracking-wide">{{ $bankName }}</p>
            </div>

            <div class="space-y-2">
                <p class="text-gray-300 text-xs uppercase tracking-wider">
                    {{ __('filament-modular-subscriptions::fms.resources.payment.account_number') }}</p>
                <p class="font-mono text-lg tracking-wider">{{ $accountNumber }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <p class="text-gray-300 text-xs uppercase tracking-wider">
                        {{ __('filament-modular-subscriptions::fms.resources.payment.iban') }}</p>
                    <p class="font-mono text-sm tracking-wider">{{ $iban }}</p>
                </div>

                <div class="space-y-2">
                    <p class="text-gray-300 text-xs uppercase tracking-wider">
                        {{ __('filament-modular-subscriptions::fms.resources.payment.swift') }}</p>
                    <p class="font-mono text-sm tracking-wider">{{ $swift }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
