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
use Closure;
use HoceineEl\FilamentModularSubscriptions\Enums\InvoiceStatus;
use HoceineEl\FilamentModularSubscriptions\FmsPlugin;

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
        return FmsPlugin::get()->getSubscriptionNavigationLabel();
    }

    public static function getNavigationGroup(): string
    {
        return FmsPlugin::get()->getTenantNavigationGroup();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return cache()->remember(
            'tenant_subscription_nav_' . auth()->id() . '_' . FmsPlugin::getTenant()->id,
            now()->addMinutes(60),
            fn() => FmsPlugin::getTenant()->admins()->where('users.id', auth()->id())->exists()
        );
    }

    public function getViewData(): array
    {
        $tenant = FmsPlugin::getTenant();
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
            ->form(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::find($arguments['plan_id']);
                return [
                    \Filament\Forms\Components\TextInput::make('confirmation')
                        ->label(function () use ($plan) {
                            return __('filament-modular-subscriptions::fms.tenant_subscription.type_to_confirm', [
                                'phrase' => __('filament-modular-subscriptions::fms.tenant_subscription.switch_confirmation_phrase', [
                                    'plan' => $plan->trans_name
                                ])
                            ]);
                        })
                        ->required()
                        ->rules([
                            fn(): Closure => function (string $attribute, $value, Closure $fail) use ($plan) {

                                if (!$plan) {
                                    $fail(__('filament-modular-subscriptions::fms.tenant_subscription.invalid_plan'));
                                    return;
                                }

                                $expectedPhrase = __('filament-modular-subscriptions::fms.tenant_subscription.switch_confirmation_phrase', [
                                    'plan' => $plan->trans_name
                                ]);

                                if ($value !== $expectedPhrase) {
                                    $fail(__('filament-modular-subscriptions::fms.tenant_subscription.confirmation_phrase_mismatch'));
                                }
                            }
                        ])
                ];
            })
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
            ->modalDescription(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::find($arguments['plan_id']);
                $tenant = FmsPlugin::getTenant();
                $currentPlan = $tenant->subscription?->plan;

                if ($currentPlan) {
                    return __('filament-modular-subscriptions::fms.tenant_subscription.switch_plan_warning', [
                        'current_plan' => $currentPlan->trans_name,
                        'new_plan' => $plan->trans_name,
                    ]);
                }

                return __('filament-modular-subscriptions::fms.tenant_subscription.new_subscription_info', [
                    'plan' => $plan->trans_name,
                ]);
            })
            ->action(function (array $arguments) {
                $planId = $arguments['plan_id'];
                $tenant = FmsPlugin::getTenant();
                $newPlan = config('filament-modular-subscriptions.models.plan')::findOrFail($planId);
                $oldPlan = $tenant->subscription?->plan;
                $oldSubscription = $tenant->subscription;
                $invoiceService = app(InvoiceService::class);

                DB::transaction(function () use ($tenant, $oldSubscription, $newPlan, $invoiceService, $oldPlan) {
                    // Handle existing subscription if any
                    if ($oldSubscription->plan->is_pay_as_you_go) {
                        // Generate final invoice for pay-as-you-go plan
                        $finalInvoice = $invoiceService->generatePayAsYouGoInvoice($oldSubscription);

                        $oldSubscription->update(['status' => SubscriptionStatus::ON_HOLD]);

                        // Send notifications for final invoice
                        if ($finalInvoice) {
                            $tenant->notifySuperAdmins('invoice_generated', [
                                'invoice_id' => $finalInvoice->id,
                                'amount' => $finalInvoice->amount,
                                'currency' => $finalInvoice->currency,
                            ]);

                            Notification::make()
                                ->title(__('filament-modular-subscriptions::fms.tenant_subscription.final_invoice_generated'))
                                ->info()
                                ->send();
                        } else {
                            $tenant->notifySuperAdmins('invoice_generation_failed', [
                                'error' => 'Failed to generate final invoice for pay-as-you-go plan',
                            ]);
                        }
                    }

                    if (($oldPlan && $oldPlan->id != $newPlan->id) && !$oldPlan->is_pay_as_you_go) {
                        // Clean up any pending invoices from old subscription
                        $pendingInvoices = $tenant->invoices()
                            ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])
                            ->where('subscription_id', $oldSubscription->id)
                            ->get();

                        foreach ($pendingInvoices as $invoice) {
                            $invoice->update(['status' => InvoiceStatus::CANCELLED]);

                            $tenant->notifySuperAdmins('invoice_cancelled', [
                                'invoice_id' => $invoice->id,
                                'reason' => 'Plan switch',
                            ]);
                        }
                    }

                    // Handle new subscription
                    if ($newPlan->is_pay_as_you_go) {
                        // Create or update subscription with active status
                        if ($oldSubscription) {
                            $tenant->switchPlan($newPlan->id);

                            // Send notifications for plan switch
                            $tenant->notifySubscriptionChange('subscription_switched', [
                                'plan' => $newPlan->trans_name,
                                'type' => 'pay_as_you_go'
                            ]);
                        } else {
                            $this->createSubscription($tenant, $newPlan, SubscriptionStatus::ACTIVE);

                            // Send notifications for new subscription
                            $tenant->notifySubscriptionChange('started', [
                                'plan' => $newPlan->trans_name,
                                'type' => 'pay_as_you_go'
                            ]);
                        }

                        // Send success notification
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.tenant_subscription.pay_as_you_go_activated'))
                            ->success()
                            ->send();
                    } elseif (!$newPlan->is_trial_plan) {

                        // Update existing subscription if any
                        if ($oldSubscription) {
                            $tenant->switchPlan($newPlan->id, SubscriptionStatus::ON_HOLD);
                            $initialInvoice = $invoiceService->generateInitialPlanInvoice($tenant, $newPlan);
                            $tenant->notifySuperAdmins('invoice_generated', [
                                'invoice_id' => $initialInvoice->id,
                                'amount' => $initialInvoice->amount,
                                'currency' => $initialInvoice->currency,
                            ]);

                            // Send notifications for subscription switch
                            $tenant->notifySubscriptionChange('subscription_switched', [
                                'plan' => $newPlan->trans_name,
                                'old_status' => $oldSubscription->status->getLabel(),
                                'new_status' => SubscriptionStatus::ON_HOLD->getLabel(),
                                'date' => now()->format('Y-m-d H:i:s')
                            ]);

                            $tenant->notifySuperAdmins('subscription_switched', [
                                'plan' => $newPlan->trans_name,
                                'end_date' => $oldSubscription->ends_at->format('Y-m-d H:i:s'),
                            ]);
                        }

                        // Send payment reminder notification
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.tenant_subscription.please_pay_invoice'))
                            ->warning()
                            ->send();
                    } elseif ($newPlan->is_trial_plan && !$tenant->canUseTrial()) {
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.notifications.subscription.trial.you_cant_use_trial'))
                            ->danger()
                            ->send();
                    } else {
                        if ($oldSubscription) {
                            $tenant->switchPlan($newPlan->id, SubscriptionStatus::ACTIVE);

                            // Send notifications for subscription switch
                            $tenant->notifySubscriptionChange('subscription_switched', [
                                'plan' => $newPlan->trans_name,
                                'old_status' => $oldSubscription->status->getLabel(),
                                'new_status' => SubscriptionStatus::ON_HOLD->getLabel(),
                                'date' => now()->format('Y-m-d H:i:s')
                            ]);

                            $tenant->notifySuperAdmins('subscription_switched', [
                                'plan' => $newPlan->trans_name,
                                'end_date' => $oldSubscription->ends_at->format('Y-m-d H:i:s'),
                            ]);
                        }
                    }

                    $tenant->invalidateSubscriptionCache();
                });

                $this->redirect(TenantSubscription::getUrl());
            });
    }

    public function newSubscriptionAction(): Action
    {
        return Action::make('newSubscription')
            ->label(__('filament-modular-subscriptions::fms.tenant_subscription.choose_plan'))
            ->requiresConfirmation()
            ->action(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::findOrFail($arguments['plan_id']);
                $tenant = FmsPlugin::getTenant();
                $canUseTrial = $tenant->canUseTrial();

                DB::transaction(function () use ($tenant, $plan, $canUseTrial) {
                    $invoiceService = app(InvoiceService::class);

                    if ($plan->is_pay_as_you_go) {
                        $subscription = $this->createSubscription($tenant, $plan, SubscriptionStatus::ACTIVE);

                        $tenant->notifySubscriptionChange('started', [
                            'plan' => $plan->trans_name,
                            'type' => 'pay_as_you_go',
                            'trial' => $subscription->onTrial(),
                        ]);

                        $this->sendSubscriptionNotification($subscription, true);
                    } else {
                        $subscription = null;
                        if ($plan->is_trial_plan && $canUseTrial) {
                            $subscription = $this->createSubscription($tenant, $plan, SubscriptionStatus::ACTIVE);
                            $this->sendTrialStartedNotification($subscription);
                        } elseif (!$plan->is_trial_plan) {
                            $initialInvoice = $invoiceService->generateInitialPlanInvoice($tenant, $plan);
                            $subscription = $tenant->subscription;
                            $this->sendSubscriptionNotification($subscription, false);

                            Notification::make()
                                ->title(__('filament-modular-subscriptions::fms.notifications.subscription.started.title'))
                                ->body(__('filament-modular-subscriptions::fms.notifications.subscription.started.body', [
                                    'tenant' => $tenant->name,
                                    'plan' => $plan->trans_name,
                                    'end_date' => $this->calculateEndDate($plan)->format('Y-m-d H:i:s')
                                ]))
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('filament-modular-subscriptions::fms.notifications.subscription.trial.you_cant_use_trial'))
                                ->danger()
                                ->send();
                        }
                    }

                    $tenant->invalidateSubscriptionCache();
                });

                $this->redirect(TenantSubscription::getUrl());
            });
    }

    protected function createSubscription($tenant, $plan, SubscriptionStatus $status): Subscription
    {
        $subscriptionModel = config('filament-modular-subscriptions.models.subscription');
        $startDate = now();

        // Check if tenant is eligible for trial
        $canUseTrial = $tenant->canUseTrial();
        $initialStatus = ($plan->trial_period && $canUseTrial) ? SubscriptionStatus::ACTIVE : $status;

        return $subscriptionModel::create([
            'plan_id' => $plan->id,
            'subscribable_id' => $tenant->id,
            'subscribable_type' => get_class($tenant),
            'starts_at' => $startDate,
            'ends_at' => $this->calculateEndDate($plan),
            'trial_ends_at' => $this->calculateTrialEndDate($plan, $startDate, $canUseTrial),
            'status' => $initialStatus,
            'has_used_trial' => $canUseTrial && $plan->trial_period > 0,
        ]);
    }

    protected function calculateTrialEndDate($plan, Carbon $startDate, bool $canUseTrial): ?Carbon
    {
        if (!$canUseTrial || !$plan->trial_period || !$plan->trial_interval) {
            return null;
        }

        return match ($plan->trial_interval->value) {
            'day' => $startDate->copy()->addDays($plan->trial_period),
            'week' => $startDate->copy()->addWeeks($plan->trial_period),
            'month' => $startDate->copy()->addMonths($plan->trial_period),
            'year' => $startDate->copy()->addYears($plan->trial_period),
            default => null,
        };
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
                ->where('tenant_id', FmsPlugin::getTenant()->id)
                ->with(['items', 'subscription.plan'])
        );
    }

    protected function sendSubscriptionNotification(Subscription $subscription, bool $isPayAsYouGo): void
    {
        if ($subscription->onTrial()) {
            $this->sendTrialStartedNotification($subscription);
            return;
        }

        $notificationTitle = $isPayAsYouGo
            ? __('filament-modular-subscriptions::fms.notifications.subscription.starter.title_payg')
            : __('filament-modular-subscriptions::fms.notifications.subscription.starter.title');

        $notificationBody = $isPayAsYouGo
            ? __('filament-modular-subscriptions::fms.notifications.subscription.starter.payg_body', [
                'tenant' => $subscription->subscribable->name,
                'plan' => $subscription->plan->trans_name,
                'end_date' => $subscription->ends_at->format('Y-m-d H:i:s')
            ])
            : __('filament-modular-subscriptions::fms.notifications.subscription.starter.body', [
                'tenant' => $subscription->subscribable->name,
                'plan' => $subscription->plan->trans_name,
                'end_date' => $subscription->ends_at->format('Y-m-d H:i:s')
            ]);

        Notification::make()
            ->title($notificationTitle)
            ->body($notificationBody)
            ->{$isPayAsYouGo ? 'success' : 'warning'}()
            ->send();
    }

    protected function sendTrialStartedNotification(Subscription $subscription): void
    {
        Notification::make()
            ->title(__('filament-modular-subscriptions::fms.notifications.subscription.trial.title'))
            ->body(__('filament-modular-subscriptions::fms.notifications.subscription.trial.body', [
                'tenant' => $subscription->subscribable->name,
                'plan' => $subscription->plan->trans_name,
                'end_date' => $subscription->trial_ends_at->format('Y-m-d H:i:s')
            ]))
            ->success()
            ->send();


        $subscription->subscribable->notifySubscriptionChange('trial', [
            'plan' => $subscription->plan->trans_name,
            'status' => $subscription->status->getLabel(),
            'trial' => $subscription->onTrial(),
            'date' => now()->format('Y-m-d H:i:s')
        ]);
    }
}
