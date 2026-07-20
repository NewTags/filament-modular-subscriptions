<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Models\SubscriptionLog;
use NewTags\FilamentModularSubscriptions\Resources\SubscriptionLogResource\Pages\ListSubscriptionLogs;

class SubscriptionLogResource extends Resource
{
    protected static ?string $model = SubscriptionLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

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
            ->modifyQueryUsing(fn ($query) => $query->with('subscription.subscribable'))
            ->columns([
                TextColumn::make('subscription.subscribable.name')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.subscription_id'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.event'))
                    ->formatStateUsing(fn ($state) => self::resolveEventLabel($state))
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('old_status')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.old_status'))
                    ->formatStateUsing(fn ($state) => self::resolveStatusLabel($state))
                    ->sortable(),
                TextColumn::make('new_status')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.new_status'))
                    ->formatStateUsing(fn ($state) => self::resolveStatusLabel($state))
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
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('5xl'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('subscription.subscribable.name')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.subscription_id')),
                        TextEntry::make('event')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.event'))
                            ->formatStateUsing(fn ($state) => self::resolveEventLabel($state)),
                        TextEntry::make('description')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.description'))
                            ->columnSpanFull(),
                        TextEntry::make('old_status')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.old_status'))
                            ->formatStateUsing(fn ($state) => self::resolveStatusLabel($state)),
                        TextEntry::make('new_status')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.new_status'))
                            ->formatStateUsing(fn ($state) => self::resolveStatusLabel($state)),
                        TextEntry::make('metadata')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.metadata'))
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT)),
                        TextEntry::make('created_at')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription_log.fields.created_at'))
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Translate a stored event for display. Handles raw event keys (new rows),
     * fully-qualified translation keys (legacy rows written before the key existed)
     * and already-translated legacy values, falling back to the raw value for unknown
     * events so nothing ever renders as a bare translation key.
     */
    public static function resolveEventLabel(?string $state): ?string
    {
        if (blank($state)) {
            return $state;
        }

        if (str_starts_with($state, 'filament-modular-subscriptions::')) {
            return __($state);
        }

        $key = 'filament-modular-subscriptions::fms.logs.events.' . $state;
        $translated = __($key);

        return $translated === $key ? $state : $translated;
    }

    public static function resolveStatusLabel(?string $state): ?string
    {
        if (blank($state)) {
            return $state;
        }

        return SubscriptionStatus::tryFrom($state)?->getLabel() ?? $state;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionLogs::route('/'),
        ];
    }
}
