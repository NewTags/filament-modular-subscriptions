<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource;
use HoceineEl\FilamentModularSubscriptions\Services\InvoiceService;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

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
        $activeSubscription = $tenant->subscription;
        $planModel = config('filament-modular-subscriptions.models.plan');

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
            ->modalHeading(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::find($arguments['plan_id']);
                return __('filament-modular-subscriptions::fms.tenant_subscription.confirm_switch_plan', ['plan' => $plan->trans_name]);
            })
            ->action(function (array $arguments) {
                $planId = $arguments['plan_id'];
                $tenant = filament()->getTenant();
                $newPlan = config('filament-modular-subscriptions.models.plan')::findOrFail($planId);
                $oldSubscription = $tenant->subscription;
                $invoiceService = app(InvoiceService::class);

                DB::transaction(function () use ($tenant, $oldSubscription, $newPlan, $invoiceService) {
                    // Handle existing subscription if any
                    if ($oldSubscription) {
                        // Generate final invoice for pay-as-you-go plan
                        if ($oldSubscription->plan->is_pay_as_you_go) {
                            $finalInvoice = $invoiceService->generatePayAsYouGoInvoice($oldSubscription);
                            $oldSubscription->update(['status' => SubscriptionStatus::ON_HOLD]);
                            
                            Notification::make()
                                ->title(__('filament-modular-subscriptions::fms.tenant_subscription.final_invoice_generated'))
                                ->info()
                                ->send();
                        }
                    }

                    // Create new subscription
                    if ($newPlan->is_pay_as_you_go) {
                        // Activate pay-as-you-go subscription immediately
                        $tenant->switchPlan($newPlan->id);
                        
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.tenant_subscription.pay_as_you_go_activated'))
                            ->success()
                            ->send();
                    } else {
                        // Generate initial invoice for limited plan
                        $initialInvoice = $invoiceService->generateInitialPlanInvoice($tenant, $newPlan);
                        
                        // Create subscription in ON_HOLD status
                        $tenant->switchPlan($newPlan->id, SubscriptionStatus::ON_HOLD);
                        
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.tenant_subscription.please_pay_invoice'))
                            ->warning()
                            ->send();
                    }
                });

                $this->redirect(TenantSubscription::getUrl());
            });
    }

    public function table(Table $table): Table
    {
        return (new InvoiceResource)->table($table)->query(
            config('filament-modular-subscriptions.models.invoice')::query()
                ->where('tenant_id', filament()->getTenant()->id)
                ->with(['items', 'subscription.plan'])
        );
    }
}
