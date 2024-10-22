<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use HoceineEl\FilamentModularSubscriptions\Services\InvoiceService;
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
        return $table
            ->query(config('filament-modular-subscriptions.models.invoice')::query()->where('tenant_id', Filament::getTenant()->id))
            ->columns([
                TextColumn::make('id')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.invoice.number'))
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.invoice.amount'))
                    ->money(fn($record) => $record->subscription->plan->currency, locale: 'en')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.invoice.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.invoice.due_date'))
                    ->date()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make('view')
                    ->slideOver()
                    ->modalHeading(fn($record) => __('filament-modular-subscriptions::modular-subscriptions.invoice.details_title', ['number' => $record->id]))
                    ->modalContent(function ($record) {
                        $invoice = $record;
                        return view('filament-modular-subscriptions::pages.invoice-details', compact('invoice'));
                    })->modalFooterActions([]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
