<?php

namespace HoceineEl\FilamentModularSubscriptions\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CurrentSubscriptionWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        $subscription = $subscriptionModel::where('subscribable_id', $tenant->id)
            ->where('subscribable_type', config('filament-modular-subscriptions.tenant_model'))
            ->active()
            ->first();

        return [
            Stat::make(__('filament-modular-subscriptions::modular-subscriptions.widgets.current_plan'), $subscription?->plan->trans_name ?? __('filament-modular-subscriptions::modular-subscriptions.widgets.no_active_subscription'))
                ->description($subscription ? __('filament-modular-subscriptions::modular-subscriptions.widgets.subscribed_on', ['date' => $subscription->created_at->format('M d, Y')]) : __('filament-modular-subscriptions::modular-subscriptions.widgets.subscribe_to_plan'))
                ->color($subscription ? 'success' : 'danger'),
        ];
    }
}
