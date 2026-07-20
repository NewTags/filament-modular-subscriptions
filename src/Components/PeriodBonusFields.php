<?php

namespace NewTags\FilamentModularSubscriptions\Components;

use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use NewTags\FilamentModularSubscriptions\Models\Plan;

/**
 * Shared "bonus period" builder: quick-pick gift months, live coverage timeline
 * and gift-value summary. Reused by the subscription form and the manual
 * invoice modal. Dehydrates a single `bonus_days` integer.
 */
class PeriodBonusFields
{
    /**
     * @param  Closure(Get): ?Plan  $resolvePlan
     * @param  Closure(Get): ?Carbon  $resolveStart
     * @param  Closure(Get, Set): void  $onBonusUpdated
     * @return array<int, Component>
     */
    public static function make(Closure $resolvePlan, ?Closure $resolveStart = null, ?Closure $onBonusUpdated = null): array
    {
        $resolveStart ??= fn (Get $get): Carbon => now();
        $applyBonus = function (Get $get, Set $set) use ($onBonusUpdated): void {
            $set('bonus_days', self::resolveBonusDays($get));
            if ($onBonusUpdated) {
                $onBonusUpdated($get, $set);
            }
        };

        return [
            Hidden::make('bonus_days')
                ->default(0)
                ->dehydrateStateUsing(fn ($state): int => max(0, (int) $state)),
            ToggleButtons::make('bonus_preset')
                ->label(__('filament-modular-subscriptions::fms.period_bonus.gift_period'))
                ->inline()
                ->live()
                ->dehydrated(false)
                ->default('0')
                ->options([
                    '0' => __('filament-modular-subscriptions::fms.period_bonus.no_gift'),
                    '30' => __('filament-modular-subscriptions::fms.period_bonus.plus_one_month'),
                    '60' => __('filament-modular-subscriptions::fms.period_bonus.plus_two_months'),
                    '90' => __('filament-modular-subscriptions::fms.period_bonus.plus_three_months'),
                    'custom' => __('filament-modular-subscriptions::fms.period_bonus.custom'),
                ])
                ->icons([
                    '30' => 'heroicon-o-gift',
                    '60' => 'heroicon-o-gift',
                    '90' => 'heroicon-o-gift',
                    'custom' => 'heroicon-o-adjustments-horizontal',
                ])
                ->colors([
                    '0' => 'gray',
                    '30' => 'warning',
                    '60' => 'warning',
                    '90' => 'warning',
                    'custom' => 'info',
                ])
                ->afterStateHydrated(function (ToggleButtons $component, Set $set, ?string $state, $record): void {
                    $bonusDays = (int) ($record?->bonus_days ?? 0);
                    if ($bonusDays <= 0) {
                        return;
                    }

                    $preset = in_array($bonusDays, [30, 60, 90], true) ? (string) $bonusDays : 'custom';
                    $component->state($preset);
                    $set('bonus_days', $bonusDays);
                    if ($preset === 'custom') {
                        $isWholeMonths = $bonusDays % 30 === 0;
                        $set('bonus_custom_value', $isWholeMonths ? intdiv($bonusDays, 30) : $bonusDays);
                        $set('bonus_custom_unit', $isWholeMonths ? 'month' : 'day');
                    }
                })
                ->afterStateUpdated($applyBonus),
            Grid::make(2)
                ->visible(fn (Get $get): bool => $get('bonus_preset') === 'custom')
                ->schema([
                    TextInput::make('bonus_custom_value')
                        ->label(__('filament-modular-subscriptions::fms.period_bonus.duration'))
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->live(onBlur: true)
                        ->dehydrated(false)
                        ->afterStateUpdated($applyBonus),
                    Select::make('bonus_custom_unit')
                        ->label(__('filament-modular-subscriptions::fms.period_bonus.unit'))
                        ->options([
                            'day' => __('filament-modular-subscriptions::fms.interval.day'),
                            'month' => __('filament-modular-subscriptions::fms.interval.month'),
                        ])
                        ->default('month')
                        ->selectablePlaceholder(false)
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated($applyBonus),
                ]),
            Placeholder::make('period_bonus_timeline')
                ->label('')
                ->visible(fn (Get $get): bool => $resolvePlan($get) !== null)
                ->content(function (Get $get) use ($resolvePlan, $resolveStart): HtmlString {
                    $plan = $resolvePlan($get);
                    $start = $resolveStart($get) ?? now();
                    $start = $start instanceof Carbon ? $start : Carbon::parse($start);
                    $bonusDays = self::resolveBonusDays($get);
                    $paidDays = max(1, (int) $plan->period);

                    $paidEnd = $start->copy()->addDays($paidDays);
                    $finalEnd = $paidEnd->copy()->addDays($bonusDays);
                    $giftValue = round(((float) $plan->price / $paidDays) * $bonusDays, 2);

                    return new HtmlString(view('filament-modular-subscriptions::components.period-bonus-timeline', [
                        'plan' => $plan,
                        'start' => $start,
                        'paidDays' => $paidDays,
                        'bonusDays' => $bonusDays,
                        'paidEnd' => $paidEnd,
                        'finalEnd' => $finalEnd,
                        'giftValue' => $giftValue,
                        'currency' => $plan->currency ?? config('filament-modular-subscriptions.main_currency'),
                    ])->render());
                }),
        ];
    }

    public static function resolveBonusDays(Get $get): int
    {
        $preset = $get('bonus_preset') ?? '0';

        if ($preset === 'custom') {
            $value = max(0, (int) $get('bonus_custom_value'));

            return $get('bonus_custom_unit') === 'day' ? $value : $value * 30;
        }

        return max(0, (int) $preset);
    }

    public static function formatDays(int $days): string
    {
        if ($days > 0 && $days % 30 === 0) {
            $months = intdiv($days, 30);

            return trans_choice('filament-modular-subscriptions::fms.period_bonus.months_count', $months, ['count' => $months]);
        }

        return trans_choice('filament-modular-subscriptions::fms.period_bonus.days_count', $days, ['count' => $days]);
    }

    public static function giftLineDescription(int $days): string
    {
        return __('filament-modular-subscriptions::fms.period_bonus.gift_line', ['period' => self::formatDays($days)]);
    }
}
