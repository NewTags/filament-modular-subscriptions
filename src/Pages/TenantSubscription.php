<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class TenantSubscription extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament.pages.tenant-subscription';


    public ?string $selectedPlanId = null;

    public function mount(): void
    {
        $this->selectedPlanId = $this->currentSubscription?->plan_id;
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.your_subscription_details');
    }
    #[Computed]
    public function currentSubscription(): ?Subscription
    {
        return Filament::getTenant()->activeSubscription();
    }

    #[Computed]
    public function availablePlans()
    {
        return Plan::where('is_active', true)->get();
    }

    public function switchPlan(): void
    {
        $newPlan = Plan::findOrFail($this->selectedPlanId);

        // Implement your plan switching logic here
        // This is a simplified example
        $this->currentSubscription->update([
            'plan_id' => $newPlan->id,
            'ends_at' => now()->add($newPlan->invoice_interval, $newPlan->invoice_period),
        ]);

        Notification::make()
            ->title(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan_switched_success'))
            ->success()
            ->send();

        $this->redirect(TenantSubscription::getUrl());
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedPlanId')
                ->label(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.select_new_plan'))
                ->options($this->availablePlans->pluck('name', 'id'))
                ->required()
                ->live(),
        ];
    }

    protected function getActions(): array
    {
        return [
            Action::make('switchPlan')
                ->label(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.switch_plan_button'))
                ->action('switchPlan')
                ->disabled(fn() => $this->selectedPlanId === $this->currentSubscription?->plan_id)
                ->size(ActionSize::Large)
                ->color('primary'),
        ];
    }
}
