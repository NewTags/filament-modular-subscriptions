<?php

namespace HoceineEl\FilamentModularSubscriptions\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionDaysLeftWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        $subscription = $subscriptionModel::where('subscribable_id', $tenant->id)
            ->where('subscribable_type', config('filament-modular-subscriptions.tenant_model'))
            ->active()
            ->first();

        $daysLeft = $subscription ? now()->diffInDays($subscription->ends_at, false) : 0;

        return [
            Stat::make(__('filament-modular-subscriptions::modular-subscriptions.widgets.days_left'), $daysLeft)
                ->description($subscription
                    ? __('filament-modular-subscriptions::modular-subscriptions.widgets.expires_on', ['date' => $subscription->ends_at->format('M d, Y')])
                    : __('filament-modular-subscriptions::modular-subscriptions.widgets.no_active_subscription'))
                ->color($daysLeft > 7 ? 'success' : ($daysLeft > 0 ? 'warning' : 'danger')),
        ];
    }
}
