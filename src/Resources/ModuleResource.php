<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use HoceineEl\FilamentModularSubscriptions\Modules\BaseModule;
use HoceineEl\FilamentModularSubscriptions\Resources\ModuleResource\Pages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ModuleResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?Collection $moduleOptions = null;

    public static function getModel(): string
    {
        return config('filament-modular-subscriptions.models.module');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.resources.module.name');
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.resources.module.singular_name');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.menu_group.subscription_management');
    }

    public static function canDelete(Model $record): bool
    {
        return $record->subscriptions()->count() === 0;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module.fields.name')),
                Forms\Components\Select::make('class')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->options(fn () => self::getModuleOptions())
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module.fields.class'))
                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                        if ($state && ! $get('name')) {
                            $set('name', self::getModuleOptions()->get($state));
                        }
                    }),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module.fields.is_active')),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module.fields.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('class')
                    ->formatStateUsing(fn ($state) => self::getModuleOptions()->get($state, $state))
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module.fields.class')),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module.fields.is_active')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->options([
                        '1' => __('filament-modular-subscriptions::modular-subscriptions.active'),
                        '0' => __('filament-modular-subscriptions::modular-subscriptions.inactive'),
                    ])
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.module.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'edit' => Pages\EditModule::route('/{record}/edit'),
        ];
    }

    protected static function getModuleOptions(): Collection
    {
        if (self::$moduleOptions === null) {
            self::$moduleOptions = collect(config('filament-modular-subscriptions.modules'))
                ->filter(fn ($module) => is_subclass_of($module, BaseModule::class))
                ->mapWithKeys(fn ($module) => [$module => (new $module)->getName()]);
        }

        return self::$moduleOptions;
    }
}
