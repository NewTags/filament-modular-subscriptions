<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use NewTags\FilamentModularSubscriptions\Models\SubscriptionLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use NewTags\FilamentModularSubscriptions\Resources\SubscriptionLogResource\Pages\ListSubscriptionLogs;
use NewTags\FilamentModularSubscriptions\FmsPlugin;

class SubscriptionLogResource extends Resource
{
    protected static ?string $model = SubscriptionLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getNavigationGroup(): ?string
    {
        return FmsPlugin::get()->getNavigationGroup();
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.subscription_log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.subscription_log.plural_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subscription.subscribable.name')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.subscription_id'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.event'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('old_status')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.old_status'))
                    ->sortable(),
                TextColumn::make('new_status')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.new_status'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->modalWidth('5xl'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make()
                    ->schema([
                        TextEntry::make('subscription.subscribable.name')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.subscription_id')),
                        TextEntry::make('event')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.event')),
                        TextEntry::make('description')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.description'))
                            ->columnSpanFull(),
                        TextEntry::make('old_status')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.old_status')),
                        TextEntry::make('new_status')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.new_status')),
                        TextEntry::make('metadata')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.metadata'))
                            ->columnSpanFull()
                            ->formatStateUsing(fn($state) => json_encode($state, JSON_PRETTY_PRINT)),
                        TextEntry::make('created_at')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.created_at'))
                            ->dateTime(),
                    ])
                    ->columns(2)
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionLogs::route('/'),
        ];
    }
}
