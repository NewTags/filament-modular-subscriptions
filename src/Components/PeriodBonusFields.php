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
 * Shared "period + bonus" builder: optional paid-period quick picks, gift
 * quick picks, live coverage timeline and gift-value summary. Reused by the
 * subscription form and the manual invoice modal. Dehydrates `bonus_days`
 * (and `period_days` when the paid-period selector is enabled).
 */
class PeriodBonusFields
{
    /**
     * @param  Closure(Get): ?Plan  $resolvePlan
     * @param  Closure(Get): ?Carbon  $resolveStart
     * @param  Closure(Get, Set): void  $onBonusUpdated
     * @param  bool  $withPaidPeriod  Show a paid-period selector (manual invoice quotes) instead of always using the plan period.
     * @param  Closure(Get): float  $resolveAmount  What the customer actually pays; defaults to the plan price.
     * @return array<int, Component>
     */
    public static function make(
        Closure $resolvePlan,
        ?Closure $resolveStart = null,
        ?Closure $onBonusUpdated = null,
        bool $withPaidPeriod = false,
        ?Closure $resolveAmount = null,
    ): array {
        $resolveStart ??= fn (Get $get): Carbon => now();
        $resolveAmount ??= fn (Get $get): float => (float) ($resolvePlan($get)?->price ?? 0);
        $resolvePaidDays = function (Get $get) use ($resolvePlan, $withPaidPeriod): int {
            $planDays = max(1, (int) ($resolvePlan($get)?->period ?? 30));
            if (! $withPaidPeriod) {
                return $planDays;
            }
            $selected = (int) $get('period_days');

            return $selected > 0 ? $selected : $planDays;
        };
        $applyBonus = function (Get $get, Set $set) use ($onBonusUpdated): void {
            $set('bonus_days', self::resolveBonusDays($get));
            if ($onBonusUpdated) {
                $onBonusUpdated($get, $set);
            }
        };
        $applyPaidPeriod = function (Get $get, Set $set) use ($onBonusUpdated): void {
            $set('period_days', self::resolvePaidPeriodDays($get));
            if ($onBonusUpdated) {
                $onBonusUpdated($get, $set);
            }
        };

        $paidPeriodFields = ! $withPaidPeriod ? [] : [
            Hidden::make('period_days')
                ->default(0)
                ->dehydrateStateUsing(fn ($state): int => max(0, (int) $state)),
            ToggleButtons::make('paid_period_preset')
                ->label(__('filament-modular-subscriptions::fms.period_bonus.paid_period'))
                ->inline()
                ->live()
                ->dehydrated(false)
                ->default('plan')
                ->options([
                    'plan' => __('filament-modular-subscriptions::fms.period_bonus.plan_period'),
                    '30' => __('filament-modular-subscriptions::fms.period_bonus.one_month'),
                    '90' => __('filament-modular-subscriptions::fms.period_bonus.three_months'),
                    '180' => __('filament-modular-subscriptions::fms.period_bonus.six_months'),
                    '365' => __('filament-modular-subscriptions::fms.period_bonus.one_year'),
                    'custom' => __('filament-modular-subscriptions::fms.period_bonus.custom'),
                ])
                ->colors([
                    'plan' => 'gray',
                    '30' => 'primary',
                    '90' => 'primary',
                    '180' => 'primary',
                    '365' => 'primary',
                    'custom' => 'info',
                ])
                ->afterStateUpdated($applyPaidPeriod),
            Grid::make(2)
                ->visible(fn (Get $get): bool => $get('paid_period_preset') === 'custom')
                ->schema([
                    TextInput::make('paid_custom_value')
                        ->label(__('filament-modular-subscriptions::fms.period_bonus.duration'))
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->live(onBlur: true)
                        ->dehydrated(false)
                        ->afterStateUpdated($applyPaidPeriod),
                    Select::make('paid_custom_unit')
                        ->label(__('filament-modular-subscriptions::fms.period_bonus.unit'))
                        ->options([
                            'day' => __('filament-modular-subscriptions::fms.interval.day'),
                            'month' => __('filament-modular-subscriptions::fms.interval.month'),
                            'year' => __('filament-modular-subscriptions::fms.interval.year'),
                        ])
                        ->default('month')
                        ->selectablePlaceholder(false)
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated($applyPaidPeriod),
                ]),
        ];

        return [
            ...$paidPeriodFields,
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
                ->content(function (Get $get) use ($resolvePlan, $resolveStart, $resolvePaidDays, $resolveAmount): HtmlString {
                    $plan = $resolvePlan($get);
                    $start = $resolveStart($get) ?? now();
                    $start = $start instanceof Carbon ? $start : Carbon::parse($start);
                    $bonusDays = self::resolveBonusDays($get);
                    $paidDays = $resolvePaidDays($get);
                    $amount = round($resolveAmount($get), 2);

                    $paidEnd = $start->copy()->addDays($paidDays);
                    $finalEnd = $paidEnd->copy()->addDays($bonusDays);
                    $giftValue = round(($amount / $paidDays) * $bonusDays, 2);

                    return new HtmlString(view('filament-modular-subscriptions::components.period-bonus-timeline', [
                        'plan' => $plan,
                        'start' => $start,
                        'paidDays' => $paidDays,
                        'bonusDays' => $bonusDays,
                        'paidEnd' => $paidEnd,
                        'finalEnd' => $finalEnd,
                        'amount' => $amount,
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

    public static function resolvePaidPeriodDays(Get $get): int
    {
        $preset = $get('paid_period_preset') ?? 'plan';

        if ($preset === 'plan') {
            return 0;
        }

        if ($preset === 'custom') {
            $value = max(0, (int) $get('paid_custom_value'));

            return match ($get('paid_custom_unit')) {
                'day' => $value,
                'year' => $value * 365,
                default => $value * 30,
            };
        }

        return max(0, (int) $preset);
    }

    public static function formatDays(int $days): string
    {
        if ($days <= 0) {
            return trans_choice('filament-modular-subscriptions::fms.period_bonus.days_count', 0, ['count' => 0]);
        }

        $years = intdiv($days, 365);
        $remainder = $days % 365;
        $months = intdiv($remainder, 30);
        $remainderDays = $remainder % 30;

        $parts = [];
        if ($years > 0) {
            $parts[] = trans_choice('filament-modular-subscriptions::fms.period_bonus.years_count', $years, ['count' => $years]);
        }
        if ($months > 0) {
            $parts[] = trans_choice('filament-modular-subscriptions::fms.period_bonus.months_count', $months, ['count' => $months]);
        }
        if ($remainderDays > 0 || $parts === []) {
            $parts[] = trans_choice('filament-modular-subscriptions::fms.period_bonus.days_count', $remainderDays, ['count' => $remainderDays]);
        }

        return implode(__('filament-modular-subscriptions::fms.period_bonus.and'), $parts);
    }

    public static function giftLineDescription(int $days): string
    {
        return __('filament-modular-subscriptions::fms.period_bonus.gift_line', ['period' => self::formatDays($days)]);
    }
}
