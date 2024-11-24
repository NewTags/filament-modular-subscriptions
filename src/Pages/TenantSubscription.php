<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use HoceineEl\FilamentModularSubscriptions\ModularSubscription;
use HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource;
use HoceineEl\FilamentModularSubscriptions\Services\InvoiceService;
use Illuminate\Contracts\Support\Htmlable;

class TenantSubscription extends Page implements HasTable
{
    use InteractsWithTable;
    protected static ?int $navigationSort = 500;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament-modular-subscriptions::filament.pages.tenant-subscription';

    public function getTitle(): string | Htmlable
    {
        return __('filament-modular-subscriptions::fms.tenant_subscription.your_subscription');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-modular-subscriptions::fms.tenant_subscription.your_subscription');
    }

    public static function getNavigationGroup(): string
    {
        return __('filament-modular-subscriptions::fms.tenant_subscription.subscription_navigation_label');
    }

    public function getViewData(): array
    {
        $tenant = filament()->getTenant();
        $activeSubscription = $tenant->activeSubscription();
        $planModel = config('filament-modular-subscriptions.models.plan');

        $invoiceService = app(InvoiceService::class);
        $invoiceService->generateInvoice($tenant->activeSubscription());

        return [
            'tenant' => $tenant,
            'activeSubscription' => $activeSubscription,
            'availablePlans' => $planModel::with('modules')->active()->orderBy('sort_order')->get(),
        ];
    }

    public function switchPlanAction(): Action
    {
        return Action::make('switchPlanAction')
            ->requiresConfirmation()
            ->label(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::find($arguments['plan_id']);

                return $plan->is_pay_as_you_go
                    ? __('filament-modular-subscriptions::fms.tenant_subscription.start_using_pay_as_you_go')
                    : __('filament-modular-subscriptions::fms.tenant_subscription.switch_to_plan');
            })
            ->color(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::find($arguments['plan_id']);

                return $plan->is_pay_as_you_go ? 'success' : 'primary';
            })

            ->action(function (array $arguments) {
                $planId = $arguments['plan_id'];
                $tenant = filament()->getTenant();

                $tenant->switchPlan($planId);

                Notification::make()
                    ->title(__('filament-modular-subscriptions::fms.tenant_subscription.plan_switched_successfully'))
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return (new InvoiceResource)->table($table)->query(config('filament-modular-subscriptions.models.invoice')::query()->where('tenant_id', filament()->getTenant()->id));
    }
}
