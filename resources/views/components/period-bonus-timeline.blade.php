@php
    use NewTags\FilamentModularSubscriptions\Components\PeriodBonusFields;

    $totalDays = $paidDays + $bonusDays;
    $paidPercent = round(($paidDays / $totalDays) * 100, 2);
    $bonusPercent = 100 - $paidPercent;
    $amountFormatted = number_format($amount, 2, '.', ',');
    $giftFormatted = number_format($giftValue, 2, '.', ',');
@endphp

<div style="display: flex; flex-direction: column; gap: 0.875rem; padding: 1.125rem; border-radius: 0.875rem; border: 1px solid rgba(128, 128, 128, 0.18); background: linear-gradient(180deg, rgba(128,128,128,0.05), rgba(128,128,128,0.02));">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
        <span class="text-sm font-semibold" style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem; opacity: 0.7;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
            {{ __('filament-modular-subscriptions::fms.period_bonus.coverage_title') }}
        </span>
        <span class="text-base font-bold" style="color: rgb(var(--primary-600));">
            {{ PeriodBonusFields::formatDays($totalDays) }}
        </span>
    </div>

    <div style="display: flex; height: 2rem; border-radius: 9999px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
        <div style="width: {{ $paidPercent }}%; background: linear-gradient(90deg, rgb(var(--primary-500)), rgb(var(--primary-600))); display: flex; align-items: center; justify-content: center; min-width: 3.5rem;">
            <span style="color: white; font-size: 0.72rem; font-weight: 600; white-space: nowrap; padding: 0 0.625rem; overflow: hidden; text-overflow: ellipsis;">
                {{ __('filament-modular-subscriptions::fms.period_bonus.paid_segment', ['period' => PeriodBonusFields::formatDays($paidDays)]) }}
            </span>
        </div>
        @if ($bonusDays > 0)
            <div style="width: {{ $bonusPercent }}%; background: repeating-linear-gradient(45deg, #f59e0b, #f59e0b 8px, #fbbf24 8px, #fbbf24 16px); display: flex; align-items: center; justify-content: center; min-width: 3rem;">
                <span style="color: #78350f; font-size: 0.72rem; font-weight: 700; white-space: nowrap; padding: 0 0.625rem;">
                    🎁 {{ PeriodBonusFields::formatDays($bonusDays) }}
                </span>
            </div>
        @endif
    </div>

    <div class="text-xs" style="display: flex; justify-content: space-between; gap: 0.5rem; color: rgba(128, 128, 128, 0.95);">
        <span style="display: inline-flex; flex-direction: column; gap: 0.125rem;">
            <span style="opacity: 0.75;">{{ __('filament-modular-subscriptions::fms.period_bonus.starts_label') }}</span>
            <span class="font-medium">{{ $start->translatedFormat('d M Y') }}</span>
        </span>
        @if ($bonusDays > 0)
            <span style="display: inline-flex; flex-direction: column; gap: 0.125rem; text-align: center;">
                <span style="opacity: 0.75;">{{ __('filament-modular-subscriptions::fms.period_bonus.paid_until_label') }}</span>
                <span class="font-medium">{{ $paidEnd->translatedFormat('d M Y') }}</span>
            </span>
        @endif
        <span style="display: inline-flex; flex-direction: column; gap: 0.125rem; text-align: end;">
            <span style="opacity: 0.75;">{{ __('filament-modular-subscriptions::fms.period_bonus.ends_label') }}</span>
            <span class="font-semibold" style="color: rgb(var(--primary-600));">{{ $finalEnd->translatedFormat('d M Y') }}</span>
        </span>
    </div>

    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        @if ($amount > 0)
            <span class="text-xs font-semibold" style="padding: 0.3rem 0.75rem; border-radius: 9999px; background: rgba(var(--primary-500), 0.12); color: rgb(var(--primary-600));">
                {{ __('filament-modular-subscriptions::fms.period_bonus.pays_chip', ['amount' => $amountFormatted, 'currency' => $currency]) }}
            </span>
        @endif
        <span class="text-xs font-semibold" style="padding: 0.3rem 0.75rem; border-radius: 9999px; background: rgba(34, 197, 94, 0.12); color: #16a34a;">
            {{ __('filament-modular-subscriptions::fms.period_bonus.gets_chip', ['period' => PeriodBonusFields::formatDays($totalDays), 'date' => $finalEnd->translatedFormat('d M Y')]) }}
        </span>
        @if ($bonusDays > 0 && $giftValue > 0)
            <span class="text-xs font-bold" style="padding: 0.3rem 0.75rem; border-radius: 9999px; background: rgba(245, 158, 11, 0.15); color: #b45309;">
                🎁 {{ __('filament-modular-subscriptions::fms.period_bonus.gift_value_chip', ['amount' => $giftFormatted, 'currency' => $currency]) }}
            </span>
        @endif
    </div>
</div>
