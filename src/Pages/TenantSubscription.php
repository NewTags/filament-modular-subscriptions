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


    public ?string $selectedPlanId = null;

    public function mount(): void
    {
        $this->selectedPlanId = $this->currentSubscription?->plan_id;
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.your_subscription');
    }
    #[Computed]
    public function currentSubscription(): ?Subscription
    {
        return Filament::getTenant()->activeSubscription();
    }

    #[Computed]
    public function availablePlans(): Collection
    {
        $planModel = config('filament-modular-subscriptions.models.plan');

        return $planModel::where('is_active', true)->get();
    }

    public function switchPlan(): void
    {
        $planModel = config('filament-modular-subscriptions.models.plan');
        $newPlan = $planModel::findOrFail($this->selectedPlanId);

        // Implement your plan switching logic here
        // This is a simplified example
        $this->currentSubscription->subscriber()->switchPlan($newPlan);

        Notification::make()
            ->title(__('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.plan_switched_success'))
            ->success()
            ->send();

        redirect()->back();
    }



    protected function getHeaderActions(): array
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
