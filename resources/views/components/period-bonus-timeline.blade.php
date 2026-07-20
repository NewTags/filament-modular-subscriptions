@php
    use NewTags\FilamentModularSubscriptions\Components\PeriodBonusFields;

    $totalDays = $paidDays + $bonusDays;
    $paidPercent = round(($paidDays / $totalDays) * 100, 2);
    $bonusPercent = 100 - $paidPercent;
    $priceFormatted = number_format((float) $plan->price, 2, '.', ',');
    $giftFormatted = number_format($giftValue, 2, '.', ',');
@endphp

<div style="display: flex; flex-direction: column; gap: 0.75rem; padding: 1rem; border-radius: 0.75rem; border: 1px solid rgba(128, 128, 128, 0.2); background: rgba(128, 128, 128, 0.04);">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
        <span class="text-sm font-semibold">
            {{ __('filament-modular-subscriptions::fms.period_bonus.coverage_title') }}
        </span>
        <span class="text-sm font-bold" style="color: rgb(var(--primary-600));">
            {{ PeriodBonusFields::formatDays($totalDays) }}
        </span>
    </div>

    <div style="display: flex; height: 1.75rem; border-radius: 9999px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.08);">
        <div style="width: {{ $paidPercent }}%; background: linear-gradient(90deg, rgb(var(--primary-500)), rgb(var(--primary-600))); display: flex; align-items: center; justify-content: center; min-width: 3rem;">
            <span style="color: white; font-size: 0.7rem; font-weight: 600; white-space: nowrap; padding: 0 0.5rem; overflow: hidden; text-overflow: ellipsis;">
                {{ __('filament-modular-subscriptions::fms.period_bonus.paid_segment', ['period' => PeriodBonusFields::formatDays($paidDays)]) }}
            </span>
        </div>
        @if ($bonusDays > 0)
            <div style="width: {{ $bonusPercent }}%; background: repeating-linear-gradient(45deg, #f59e0b, #f59e0b 8px, #fbbf24 8px, #fbbf24 16px); display: flex; align-items: center; justify-content: center; min-width: 2.5rem;">
                <span style="color: #78350f; font-size: 0.7rem; font-weight: 700; white-space: nowrap; padding: 0 0.5rem;">
                    🎁 {{ PeriodBonusFields::formatDays($bonusDays) }}
                </span>
            </div>
        @endif
    </div>

    <div class="text-xs" style="display: flex; justify-content: space-between; gap: 0.5rem; color: rgba(128, 128, 128, 0.9);">
        <span>{{ $start->translatedFormat('d M Y') }}</span>
        @if ($bonusDays > 0)
            <span>{{ $paidEnd->translatedFormat('d M Y') }}</span>
        @endif
        <span class="font-semibold" style="color: rgb(var(--primary-600));">{{ $finalEnd->translatedFormat('d M Y') }}</span>
    </div>

    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <span class="text-xs font-medium" style="padding: 0.25rem 0.625rem; border-radius: 9999px; background: rgba(var(--primary-500), 0.12); color: rgb(var(--primary-600));">
            {{ __('filament-modular-subscriptions::fms.period_bonus.pays_chip', ['amount' => $priceFormatted, 'currency' => $currency]) }}
        </span>
        <span class="text-xs font-medium" style="padding: 0.25rem 0.625rem; border-radius: 9999px; background: rgba(34, 197, 94, 0.12); color: #16a34a;">
            {{ __('filament-modular-subscriptions::fms.period_bonus.gets_chip', ['period' => PeriodBonusFields::formatDays($totalDays), 'date' => $finalEnd->translatedFormat('Y-m-d')]) }}
        </span>
        @if ($bonusDays > 0)
            <span class="text-xs font-bold" style="padding: 0.25rem 0.625rem; border-radius: 9999px; background: rgba(245, 158, 11, 0.15); color: #b45309;">
                🎁 {{ __('filament-modular-subscriptions::fms.period_bonus.gift_value_chip', ['amount' => $giftFormatted, 'currency' => $currency]) }}
            </span>
        @endif
    </div>
</div>
