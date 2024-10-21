<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use HoceineEl\FilamentModularSubscriptions\ModularSubscription;
use HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource\Pages;

class ModuleUsageResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    public static function getModel(): string
    {
        return config('filament-modular-subscriptions.models.usage');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.name');
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.singular_name');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.menu_group.subscription_management');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('subscription.subscribable.name')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.subscription_id')),
                TextEntry::make('module.name')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.module_id')),
                TextEntry::make('usage')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.usage')),
                TextEntry::make('pricing')
                    ->money(config('filament-modular-subscriptions.main_currency'), locale: 'en')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.pricing')),
                TextEntry::make('calculated_at')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.calculated_at')),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscription.subscribable.name')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.subscriber'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('module.name')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.module_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.usage'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('pricing')
                    ->money(config('filament-modular-subscriptions.main_currency'), locale: 'en')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.pricing'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('calculated_at')
                    ->dateTime()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.calculated_at'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('module_id')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.fields.module_id'))
                    ->relationship('module', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modal(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calculate_usage')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.actions.calculate_usage'))
                    ->color(Color::Indigo)
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->action(function () {
                        ModularSubscription::calculateUsageForAllModules();
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::modular-subscriptions.resources.module_usage.actions.calculate_usage_success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListModuleUsages::route('/'),
        ];
    }
}
