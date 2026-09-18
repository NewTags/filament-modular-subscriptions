<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Modules\BaseModule;
use NewTags\FilamentModularSubscriptions\Resources\ModuleResource\Pages\CreateModule;
use NewTags\FilamentModularSubscriptions\Resources\ModuleResource\Pages\EditModule;
use NewTags\FilamentModularSubscriptions\Resources\ModuleResource\Pages\ListModules;

class ModuleResource extends Resource
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?Collection $moduleOptions = null;

    public static function getModel(): string
    {
        return config('filament-modular-subscriptions.models.module');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.module.name');
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.module.singular_name');
    }

    public static function getNavigationGroup(): ?string
    {
        return FmsPlugin::get()->getNavigationGroup();
    }

    public static function canDelete(Model $record): bool
    {
        return $record->plans()->count() === 0;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->label(__('filament-modular-subscriptions::fms.resources.module.fields.name')),
                Select::make('class')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->options(fn () => self::getModuleOptions())
                    ->label(__('filament-modular-subscriptions::fms.resources.module.fields.class'))
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                        if ($state && ! $get('name')) {
                            $set('name', self::getModuleOptions()->get($state));
                        }
                    }),
                Toggle::make('is_active')
                    ->default(true)
                    ->label(__('filament-modular-subscriptions::fms.resources.module.fields.is_active')),
                Toggle::make('is_persistent')
                    ->label(__('filament-modular-subscriptions::fms.resources.module.fields.is_persistent'))
                    ->helperText(__('filament-modular-subscriptions::fms.resources.module.fields.is_persistent_help'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-modular-subscriptions::fms.resources.module.fields.name'))
                    ->searchable(),
                TextColumn::make('class')
                    ->formatStateUsing(fn ($state) => self::getModuleOptions()->get($state, $state))
                    ->label(__('filament-modular-subscriptions::fms.resources.module.fields.class')),
                ToggleColumn::make('is_active')
                    ->label(__('filament-modular-subscriptions::fms.resources.module.fields.is_active')),
                IconColumn::make('is_persistent')
                    ->label(__('filament-modular-subscriptions::fms.resources.module.fields.is_persistent'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([
                        '1' => __('filament-modular-subscriptions::fms.active'),
                        '0' => __('filament-modular-subscriptions::fms.inactive'),
                    ])
                    ->label(__('filament-modular-subscriptions::fms.resources.module.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
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
            'index' => ListModules::route('/'),
            'create' => CreateModule::route('/create'),
            'edit' => EditModule::route('/{record}/edit'),
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
