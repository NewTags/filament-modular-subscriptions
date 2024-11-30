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
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
                    : __('filament-modular-subscriptions::fms.tenant_subscription.subscribe_to_plan');
            })
            ->modalHeading(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::find($arguments['plan_id']);
                return __('filament-modular-subscriptions::fms.tenant_subscription.confirm_subscription', ['plan' => $plan->trans_name]);
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
                        if ($oldSubscription->plan->is_pay_as_you_go) {
                            // Generate final invoice for pay-as-you-go plan
                            $finalInvoice = $invoiceService->generatePayAsYouGoInvoice($oldSubscription);
                            $oldSubscription->update(['status' => SubscriptionStatus::ON_HOLD]);

                            Notification::make()
                                ->title(__('filament-modular-subscriptions::fms.tenant_subscription.final_invoice_generated'))
                                ->info()
                                ->send();
                        }
                    }

                    // Handle new subscription
                    if ($newPlan->is_pay_as_you_go) {
                        // Create or update subscription with active status
                        if ($oldSubscription) {
                            $tenant->switchPlan($newPlan->id);
                        } else {
                            $this->createSubscription($tenant, $newPlan, SubscriptionStatus::ACTIVE);
                        }

                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.tenant_subscription.pay_as_you_go_activated'))
                            ->success()
                            ->send();
                    } else {
                        // Create or update subscription with on-hold status
                        if ($oldSubscription) {
                            $tenant->switchPlan($newPlan->id, SubscriptionStatus::ON_HOLD);
                        } else {
                            $subscription = $this->createSubscription($tenant, $newPlan, SubscriptionStatus::ON_HOLD);
                        }

                        // Generate initial invoice
                        $initialInvoice = $invoiceService->generateInitialPlanInvoice($tenant, $newPlan);

                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.tenant_subscription.please_pay_invoice'))
                            ->warning()
                            ->send();
                    }
                });

                $this->redirect(TenantSubscription::getUrl());
            });
    }

    protected function createSubscription($tenant, $plan, SubscriptionStatus $status): Subscription
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        
        return $subscriptionModel::create([
            'plan_id' => $plan->id,
            'subscribable_id' => $tenant->id,
            'subscribable_type' => get_class($tenant),
            'starts_at' => now(),
            'ends_at' => $this->calculateEndDate($plan),
            'trial_ends_at' => $plan->trial_period ? now()->addDays($plan->trial_period) : null,
            'status' => $status,
        ]);
    }

    protected function calculateEndDate($plan): Carbon
    {
        return match ($plan->invoice_interval) {
            'day' => now()->addDays($plan->invoice_period),
            'week' => now()->addWeeks($plan->invoice_period),
            'month' => now()->addMonths($plan->invoice_period),
            'year' => now()->addYears($plan->invoice_period),
            default => now()->addMonth(),
        };
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
