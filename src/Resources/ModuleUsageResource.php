<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\ModularSubscription;
use NewTags\FilamentModularSubscriptions\Resources\ModuleUsageResource\Pages\ListModuleUsages;

class ModuleUsageResource extends Resource
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    public static function getModel(): string
    {
        return config('filament-modular-subscriptions.models.usage');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.module_usage.name');
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.module_usage.singular_name');
    }

    public static function getNavigationGroup(): ?string
    {
        return FmsPlugin::get()->getNavigationGroup();
    }

    public static function infolist(Schema $schema): Schema
    {
        $currency = config('filament-modular-subscriptions.main_currency');

        return $schema
            ->components([
                TextEntry::make('subscription.subscribable.name')
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.subscription_id')),
                TextEntry::make('module.name')
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.module_id')),
                TextEntry::make('usage')
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.usage')),
                TextEntry::make('pricing')
                    ->prefix(config('filament-modular-subscriptions.main_currency'))
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.pricing')),
                TextEntry::make('calculated_at')
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.calculated_at')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading(__('filament-modular-subscriptions::fms.resources.module_usage.name'))
            ->modelLabel(__('filament-modular-subscriptions::fms.resources.module_usage.singular_name'))
            ->pluralModelLabel(__('filament-modular-subscriptions::fms.resources.module_usage.name'))
            ->modifyQueryUsing(fn ($query) => $query->with(['subscription.plan', 'module']))
            ->columns([
                TextColumn::make('subscription.subscribable.name')
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.subscriber'))
                    ->sortable(),
                TextColumn::make('subscription.plan.name')
                    ->getStateUsing(fn ($record) => $record->subscription->plan?->trans_name ?? __('filament-modular-subscriptions::fms.tenant_subscription.no_plan'))
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.plan'))
                    ->sortable(),
                TextColumn::make('module.name')
                    ->getStateUsing(fn ($record) => $record->module->getLabel())
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.module_id'))
                    ->sortable(),
                TextColumn::make('usage')
                    ->getStateUsing(function ($record) {
                        $module = $record->module;
                        $moduleInstance = $module->getInstance();
                        $usage = $moduleInstance->calculateUsage($record->subscription);

                        return $usage;
                    })
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.usage'))
                    ->sortable(),
                TextColumn::make('pricing')
                    ->prefix(config('filament-modular-subscriptions.main_currency'))
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.pricing'))
                    ->getStateUsing(function ($record) {
                        $module = $record->module;
                        $moduleInstance = $module->getInstance();
                        $usage = $moduleInstance->calculateUsage($record->subscription);
                        $pricing = $moduleInstance->getPrice($record->subscription);

                        return $usage * $pricing;
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('module_id')
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.module_id'))
                    ->relationship('module', 'name'),
                SelectFilter::make('subscription')
                    ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.subscriber'))
                    ->relationship('subscription.subscriber', 'name'),
                Filter::make('usage')
                    ->schema([
                        TextInput::make('usage_from')
                            ->numeric()
                            ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.usage_from')),
                        TextInput::make('usage_to')
                            ->numeric()
                            ->label(__('filament-modular-subscriptions::fms.resources.module_usage.fields.usage_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['usage_from'],
                                fn (Builder $query, $usage): Builder => $query->where('usage', '>=', $usage),
                            )
                            ->when(
                                $data['usage_to'],
                                fn (Builder $query, $usage): Builder => $query->where('usage', '<=', $usage),
                            );
                    }),
                Filter::make('calculated_at')
                    ->schema([
                        DatePicker::make('calculated_from')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_from')),
                        DatePicker::make('calculated_until')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['calculated_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('calculated_at', '>=', $date),
                            )
                            ->when(
                                $data['calculated_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('calculated_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modal(),
            ])
            ->headerActions([
                // Tables\Actions\Action::make('calculate_usage')
                //     ->label(__('filament-modular-subscriptions::fms.resources.module_usage.actions.calculate_usage'))
                //     ->color(Color::Indigo)
                //     ->icon('heroicon-o-arrow-path-rounded-square')
                //     ->action(function () {
                //         ModularSubscription::calculateUsageForAllModules();
                //         Notification::make()
                //             ->title(__('filament-modular-subscriptions::fms.resources.module_usage.actions.calculate_usage_success'))
                //             ->success()
                //             ->send();
                //     }),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModuleUsages::route('/'),
        ];
    }
}
