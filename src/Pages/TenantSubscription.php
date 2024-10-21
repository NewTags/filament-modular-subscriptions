<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class TenantSubscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament-modular-subscriptions::filament.pages.tenant-subscription';

    public function getTitle(): string|Htmlable
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.your_subscription');
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
}
