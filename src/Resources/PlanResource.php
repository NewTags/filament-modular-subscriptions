<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use Filament\Forms;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use HoceineEl\FilamentModularSubscriptions\Enums\Interval;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use HoceineEl\FilamentModularSubscriptions\Resources\PlanResource\Pages\CreatePlan;
use HoceineEl\FilamentModularSubscriptions\Resources\PlanResource\Pages\EditPlan;
use HoceineEl\FilamentModularSubscriptions\Resources\PlanResource\Pages\ListPlans;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-s-squares-plus';

    public static function getNavigationLabel(): string
    {
        return __('filament-modular-subscriptions.resources.plan.name');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-modular-subscriptions.menu_group.subscription');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Plan Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('Basic Information'))
                            ->icon('heroicon-o-information-circle')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->translatable()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', str($state)->slug()))
                                    ->columnSpanFull()
                                    ->label(__('filament-modular-subscriptions.fields.name')),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->readOnly()
                                    ->unique(ignoreRecord: true)
                                    ->label(__('filament-modular-subscriptions.fields.slug')),
                                Forms\Components\Textarea::make('description')
                                    ->label(__('filament-modular-subscriptions.fields.description'))
                                    ->translatable()
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true)
                                    ->label(__('filament-modular-subscriptions.fields.is_active')),
                            ]),
                        Forms\Components\Tabs\Tab::make(__('Pricing'))
                            ->columns()
                            ->icon('heroicon-o-money-bills')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->required()
                                    ->label(__('filament-modular-subscriptions.fields.price')),
                                Forms\Components\Select::make('currency')
                                    ->options(config('filament-modular-subscriptions.currencies'))
                                    ->default(config('filament-modular-subscriptions.currencies')[0])
                                    ->required()
                                    ->label(__('filament-modular-subscriptions.fields.currency')),
                            ]),
                        Forms\Components\Tabs\Tab::make(__('Billing'))
                            ->icon('heroicon-o-receipt-text')
                            ->columns()
                            ->schema([
                                Forms\Components\TextInput::make('trial_period')
                                    ->numeric()
                                    ->default(0)
                                    ->label(__('filament-modular-subscriptions.fields.trial_period')),
                                Forms\Components\Select::make('trial_interval')
                                    ->options(Interval::class)
                                    ->default(Interval::DAY)
                                    ->label(__('filament-modular-subscriptions.fields.trial_interval')),
                                Forms\Components\TextInput::make('invoice_period')
                                    ->numeric()
                                    ->required()
                                    ->label(__('filament-modular-subscriptions.fields.invoice_period')),
                                Forms\Components\Select::make('invoice_interval')
                                    ->options(Interval::class)
                                    ->default(Interval::MONTH)
                                    ->required()
                                    ->label(__('filament-modular-subscriptions.fields.invoice_interval')),
                                Forms\Components\TextInput::make('grace_period')
                                    ->numeric()
                                    ->default(0)
                                    ->label(__('filament-modular-subscriptions.fields.grace_period')),
                                Forms\Components\Select::make('grace_interval')
                                    ->options(Interval::class)
                                    ->default(Interval::DAY)
                                    ->label(__('filament-modular-subscriptions.fields.grace_interval')),
                            ]),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-modular-subscriptions.fields.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money('currency')
                    ->label(__('filament-modular-subscriptions.fields.price'))
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('filament-modular-subscriptions.fields.is_active')),
                Tables\Columns\TextColumn::make('invoice_period')
                    ->label(__('filament-modular-subscriptions.fields.invoice_period'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_interval')
                    ->label(__('filament-modular-subscriptions.fields.invoice_interval')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->options([
                        true => __('filament-modular-subscriptions.active'),
                        false => __('filament-modular-subscriptions.inactive'),
                    ])
                    ->label(__('filament-modular-subscriptions.fields.is_active')),
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
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
