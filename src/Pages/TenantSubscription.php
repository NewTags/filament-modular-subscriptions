<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\ActionSize;
use Illuminate\Contracts\Support\Htmlable;
use HoceineEl\FilamentModularSubscriptions\Widgets\CurrentSubscriptionWidget;
use HoceineEl\FilamentModularSubscriptions\Widgets\SubscriptionDaysLeftWidget;
use HoceineEl\FilamentModularSubscriptions\Widgets\AvailablePlansWidget;

class TenantSubscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament-modular-subscriptions::filament.pages.tenant-subscription';

    public function getTitle(): string|Htmlable
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.your_subscription');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CurrentSubscriptionWidget::class,
            SubscriptionDaysLeftWidget::class,
            AvailablePlansWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('switchPlan')
                ->label(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.switch_plan_button'))
                ->action('switchPlan')
                ->size(ActionSize::Large)
                ->color('primary'),
        ];
    }

    public function switchPlan()
    {
        // Implement the logic for switching plans here
    }
}
