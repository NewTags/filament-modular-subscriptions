<?php

namespace NewTags\FilamentModularSubscriptions\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use NewTags\FilamentModularSubscriptions\Resources\InvoiceResource;
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Resources\ModuleUsageResource;

class TenantSubscription extends Page implements HasTable
{
    use InteractsWithTable;
    use InteractsWithActions;

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

    public function newSubscriptionAction(): Action
    {
        return Action::make('newSubscription')
            ->label(function () {
                $tenant = FmsPlugin::getTenant();
                if ($tenant->unpaidInvoices()->exists()) {
                    return __('filament-modular-subscriptions::fms.notifications.subscription.pay_all_invoices_to_activate.title');
                }
                return __('filament-modular-subscriptions::fms.tenant_subscription.choose_plan');
            })
            ->requiresConfirmation()
            ->visible(function () {
                $tenant = FmsPlugin::getTenant();

                if ($tenant->unpaidInvoices()->exists()) {
                    return false;
                }

                // Show subscription option if:
                // - No subscription exists
                // - Subscription is cancelled 
                // - No plan is assigned
                if (
                    !$tenant->subscription ||
                    $tenant->subscription->status === SubscriptionStatus::CANCELLED ||
                    !$tenant->subscription->plan
                ) {
                    return true;
                }

                return true;
            })
            ->action(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::findOrFail($arguments['plan_id']);
                $tenant = FmsPlugin::getTenant();

                if ($tenant->unpaidInvoices()->exists()) {
                    Notification::make()
                        ->title(__('filament-modular-subscriptions::fms.notifications.subscription.pay_all_invoices_to_activate.title'))
                        ->body(__('filament-modular-subscriptions::fms.notifications.subscription.pay_all_invoices_to_activate.body', [
                            'total' => $tenant->unpaidInvoices()->sum('amount'),
                            'currency' => config('filament-modular-subscriptions.main_currency')
                        ]))
                        ->warning()
                        ->send();
                    return;
                }

                $subscription = $tenant->subscribe($plan);

                if ($subscription) {
                    // Show appropriate notification based on plan type
                    if ($plan->is_pay_as_you_go) {
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.tenant_subscription.pay_as_you_go_activated'))
                            ->success()
                            ->send();
                    } elseif (!$plan->is_trial_plan) {
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.tenant_subscription.please_pay_invoice'))
                            ->warning()
                            ->send();
                    }
                }

                $this->redirect(TenantSubscription::getUrl());
            });
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
                            if (!$plan) {
                                return __('filament-modular-subscriptions::fms.tenant_subscription.invalid_plan');
                            }
                            return __('filament-modular-subscriptions::fms.tenant_subscription.type_to_confirm', [
                                'phrase' => __('filament-modular-subscriptions::fms.tenant_subscription.switch_confirmation_phrase', [
                                    'plan' => $plan?->trans_name
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
                                    'plan' => $plan?->trans_name
                                ]);

                                if ($value !== $expectedPhrase) {
                                    $fail(__('filament-modular-subscriptions::fms.tenant_subscription.confirmation_phrase_mismatch'));
                                }
                            }
                        ])
                ];
            })
            ->visible(function () {
                $tenant = FmsPlugin::getTenant();
                if (!$tenant->subscription) {
                    return false;
                }

                $isPayAsYouGo = $tenant->subscription->plan?->is_pay_as_you_go ?? false;
                $payAsYouGoInvoice = $isPayAsYouGo && $tenant->unpaidInvoices()?->exists();
                return !$payAsYouGoInvoice;
            })
            ->label(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::find($arguments['plan_id']);
                if (!$plan) {
                    return __('filament-modular-subscriptions::fms.tenant_subscription.select_plan');
                }
                return $plan->is_pay_as_you_go
                    ? __('filament-modular-subscriptions::fms.tenant_subscription.start_using_pay_as_you_go')
                    : __('filament-modular-subscriptions::fms.tenant_subscription.subscribe_to_plan');
            })
            ->modalHeading(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::find($arguments['plan_id']);
                if (!$plan) {
                    return __('filament-modular-subscriptions::fms.tenant_subscription.invalid_plan');
                }
                return __('filament-modular-subscriptions::fms.tenant_subscription.confirm_subscription', ['plan' => $plan?->trans_name]);
            })
            ->modalDescription(function ($arguments) {
                $plan = config('filament-modular-subscriptions.models.plan')::find($arguments['plan_id']);
                if (!$plan) {
                    return __('filament-modular-subscriptions::fms.tenant_subscription.invalid_plan_description');
                }

                $tenant = FmsPlugin::getTenant();
                $currentPlan = $tenant->subscription?->plan;

                if ($currentPlan) {
                    return __('filament-modular-subscriptions::fms.tenant_subscription.switch_plan_warning', [
                        'current_plan' => $currentPlan?->trans_name,
                        'new_plan' => $plan?->trans_name,
                    ]);
                }

                return __('filament-modular-subscriptions::fms.tenant_subscription.new_subscription_info', [
                    'plan' => $plan?->trans_name,
                ]);
            })
            ->action(function (array $arguments) {
                $planId = $arguments['plan_id'];
                $tenant = FmsPlugin::getTenant();
                $newPlan = config('filament-modular-subscriptions.models.plan')::findOrFail($planId);

                if ($newPlan->is_trial_plan && !$tenant->canUseTrial()) {
                    Notification::make()
                        ->title(__('filament-modular-subscriptions::fms.notifications.subscription.trial.you_cant_use_trial'))
                        ->danger()
                        ->send();
                    return;
                }

                $status = match (true) {
                    $newPlan->is_pay_as_you_go => SubscriptionStatus::ACTIVE,
                    $newPlan->is_trial_plan => SubscriptionStatus::ACTIVE,
                    default => SubscriptionStatus::ON_HOLD,
                };

                $tenant->switchPlan($planId, $status);

                if ($newPlan->is_pay_as_you_go) {
                    Notification::make()
                        ->title(__('filament-modular-subscriptions::fms.tenant_subscription.pay_as_you_go_activated'))
                        ->success()
                        ->send();
                } elseif (!$newPlan->is_trial_plan) {
                    Notification::make()
                        ->title(__('filament-modular-subscriptions::fms.tenant_subscription.please_pay_invoice'))
                        ->warning()
                        ->send();
                }

                $this->redirect(TenantSubscription::getUrl());
            });
    }

    public function cancelSubscriptionAction(): Action
    {
        return Action::make('cancelSubscription')
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->label(__('filament-modular-subscriptions::fms.tenant_subscription.cancel_subscription'))
            ->requiresConfirmation()
            ->modalHeading(__('filament-modular-subscriptions::fms.tenant_subscription.confirm_cancellation'))
            ->modalDescription(__('filament-modular-subscriptions::fms.tenant_subscription.cancel_subscription_warning'))
            ->form([
                \Filament\Forms\Components\TextInput::make('confirmation')
                    ->label(__('filament-modular-subscriptions::fms.tenant_subscription.type_to_confirm_cancel'))
                    ->required()
                    ->rules([
                        fn(): Closure => function (string $attribute, $value, Closure $fail) {
                            $expectedPhrase = __('filament-modular-subscriptions::fms.tenant_subscription.cancel_confirmation_phrase');

                            if ($value !== $expectedPhrase) {
                                $fail(__('filament-modular-subscriptions::fms.tenant_subscription.confirmation_phrase_mismatch'));
                            }
                        }
                    ])
            ])
            ->visible(function () {
                $tenant = FmsPlugin::getTenant();
                $subscription = $tenant->subscription;

                if (!$subscription) {
                    return false;
                }
                if ($subscription->status === SubscriptionStatus::CANCELLED) {
                    return false;
                }
                // Check if there are any unpaid pay-as-you-go invoices
                if ($subscription->plan && $subscription->plan->is_pay_as_you_go) {
                    return !$tenant->unpaidInvoices()->exists();
                }

                return true;
            })
            ->action(function () {
                $tenant = FmsPlugin::getTenant();

                if ($tenant->cancel()) {
                    Notification::make()
                        ->title(__('filament-modular-subscriptions::fms.notifications.subscription.cancelled.title'))
                        ->body(__('filament-modular-subscriptions::fms.notifications.subscription.cancelled.body'))
                        ->success()
                        ->send();

                    $tenant->notifySubscriptionChange('cancelled', [
                        'plan' => $tenant->subscription->plan?->trans_name,
                        'date' => now()->format('Y-m-d H:i:s')
                    ]);

                    $this->redirect(TenantSubscription::getUrl());
                }
            });
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
            ? __('filament-modular-subscriptions::fms.notifications.subscription.started.title_payg')
            : __('filament-modular-subscriptions::fms.notifications.subscription.started.title');

        $notificationBody = $isPayAsYouGo
            ? __('filament-modular-subscriptions::fms.notifications.subscription.started.payg_body', [
                'tenant' => $subscription->subscribable->name,
                'plan' => $subscription->plan?->trans_name,
                'end_date' => $subscription->ends_at->format('Y-m-d H:i:s')
            ])
            : __('filament-modular-subscriptions::fms.notifications.subscription.started.body', [
                'tenant' => $subscription->subscribable->name,
                'plan' => $subscription->plan?->trans_name,
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
                'plan' => $subscription->plan?->trans_name,
                'end_date' => $subscription->trial_ends_at->format('Y-m-d H:i:s')
            ]))
            ->success()
            ->send();


        $subscription->subscribable->notifySubscriptionChange('trial', [
            'plan' => $subscription->plan?->trans_name,
            'status' => $subscription->status->getLabel(),
            'trial' => $subscription->onTrial(),
            'date' => now()->format('Y-m-d H:i:s')
        ]);
    }
}
