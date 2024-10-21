<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use Illuminate\Database\Eloquent\Collection;

class TenantSubscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament-modular-subscriptions::filament.pages.tenant-subscription';

    public function getTitle(): string|Htmlable
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.your_subscription');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.your_subscription');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.subscription');
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('switchPlan')
                ->label(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.switch_plan_button'))
                ->form([
                    Select::make('plan_id')
                        ->label(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.select_plan'))
                        ->options(function () {
                            $planModel = config('filament-modular-subscriptions.models.plan');

                            return $planModel::active()->get()
                                ->mapWithKeys(function ($plan) {
                                    $invoiceInterval = $plan->invoice_interval->value;
                                    $period = $plan->invoice_period;
                                    $name = $plan->trans_name;
                                    $price = $plan->price;
                                    $currency = $plan->currency;
                                    $per = __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.per');
                                    $interval = __('filament-modular-subscriptions::modular-subscriptions.intervals.' . $invoiceInterval);

                                    return [
                                        $plan->id => $name . ' (' . $price . ' ' . $currency . ') ' . $per . ' ' . $period . ' ' . $interval
                                    ];
                                });
                        })
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();
                    $success = $tenant->switchPlan($data['plan_id']);

                    if ($success) {
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan_switched_successfully'))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan_switch_failed'))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function switchPlan($planId)
    {
        $tenant = Filament::getTenant();
        $success = $tenant->switchPlan($planId);

        if ($success) {
            Notification::make()
                ->title(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan_switched_successfully'))
                ->success()
                ->send();

            $this->redirect(TenantSubscription::getUrl());
        } else {
            Notification::make()
                ->title(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan_switch_failed'))
                ->danger()
                ->send();
        }
    }
}
