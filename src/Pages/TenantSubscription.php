<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource;
use Illuminate\Contracts\Support\Htmlable;

class TenantSubscription extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament-modular-subscriptions::filament.pages.tenant-subscription';

    public function getTitle(): string | Htmlable
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.your_subscription');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.your_subscription');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.subscription_navigation_label');
    }

    public function getViewData(): array
    {
        $tenant = Filament::getTenant();
        $activeSubscription = $tenant->activeSubscription();
        $planModel = config('filament-modular-subscriptions.models.plan');

        return [
            'tenant' => $tenant,
            'activeSubscription' => $activeSubscription,
            'availablePlans' => $planModel::with('modules')->active()->orderBy('sort_order')->get(),
        ];
    }

    public function switchPlan(int $planId): void
    {
        $tenant = Filament::getTenant();

        $tenant->switchPlan($planId);

        Notification::make()
            ->title(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan_switched_successfully'))
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return (new InvoiceResource)->table($table)->query(config('filament-modular-subscriptions.models.invoice')::query()->where('tenant_id', Filament::getTenant()->id));
    }
}
