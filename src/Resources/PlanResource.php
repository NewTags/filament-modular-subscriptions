<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use NewTags\FilamentModularSubscriptions\Enums\Interval;
use NewTags\FilamentModularSubscriptions\Resources\PlanResource\Pages\CreatePlan;
use NewTags\FilamentModularSubscriptions\Resources\PlanResource\Pages\EditPlan;
use NewTags\FilamentModularSubscriptions\Resources\PlanResource\Pages\ListPlans;
use Illuminate\Database\Eloquent\Model;
use NewTags\FilamentModularSubscriptions\FmsPlugin;

class PlanResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-s-squares-plus';

    public static function getModel(): string
    {
        return config('filament-modular-subscriptions.models.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.plan.name');
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.plan.singular_name');
    }

    public static function getNavigationGroup(): ?string
    {
        return FmsPlugin::get()->getNavigationGroup();
    }

    public static function canDelete(Model $record): bool
    {
        return $record->subscriptions()->count() === 0;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Plan Details')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('filament-modular-subscriptions::fms.resources.plan.tabs.details'))
                            ->icon('heroicon-o-information-circle')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->translatable(true, config('filament-modular-subscriptions.locales'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Set $set, $state) => $set('slug', str($state['name'][config('filament-modular-subscriptions.locales')[0] ?? app()->getLocale()])->slug()))
                                    ->columnSpanFull()
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.name')),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.slug')),
                                Forms\Components\Textarea::make('description')
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.description'))
                                    ->translatable(true, config('filament-modular-subscriptions.locales'))
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true)
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_active')),
                                Forms\Components\Toggle::make('is_pay_as_you_go')
                                    ->default(false)
                                    ->hidden(fn(Forms\Get $get) => $get('is_trial_plan'))
                                    ->live()
                                    ->helperText(__('filament-modular-subscriptions::fms.resources.plan.hints.is_pay_as_you_go'))
                                    ->label(__('filament-modular-subscriptions::fms.pay_as_you_go')),
                                Forms\Components\Toggle::make('is_trial_plan')
                                    ->default(false)
                                    ->live()
                                    ->helperText(__('filament-modular-subscriptions::fms.resources.plan.hints.is_trial_plan'))
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $set('price', 0);
                                            $set('is_pay_as_you_go', false);
                                        }
                                    })
                                    ->hidden(fn(Forms\Get $get) => $get('is_pay_as_you_go'))
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_trial_plan')),
                            ]),
                        Forms\Components\Tabs\Tab::make(__('filament-modular-subscriptions::fms.resources.plan.tabs.pricing'))
                            ->columns()
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->required()
                                    ->hidden(fn(Forms\Get $get) => $get('is_trial_plan') || $get('is_pay_as_you_go'))
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.price')),
                                Forms\Components\TextInput::make('setup_fee')
                                    ->numeric()
                                    ->helperText(__('filament-modular-subscriptions::fms.resources.plan.hints.setup_fee'))
                                    ->hidden(fn(Forms\Get $get) => $get('is_trial_plan'))
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.setup_fee')),
                                Forms\Components\Select::make('currency')
                                    ->options(config('filament-modular-subscriptions.currencies'))
                                    ->default(config('filament-modular-subscriptions.main_currency'))
                                    ->required()
                                    ->hidden()
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.currency')),
                            ]),
                        Forms\Components\Tabs\Tab::make(__('filament-modular-subscriptions::fms.resources.plan.tabs.billing'))
                            ->columns()
                            ->schema([
                                Forms\Components\Select::make('fixed_invoice_day')
                                    ->options(fn() => collect(range(1, 28))->mapWithKeys(fn($day) => [$day => $day]))
                                    ->default(1)
                                    ->columnSpanFull()
                                    ->helperText(__('filament-modular-subscriptions::fms.resources.plan.hints.fixed_invoice_day'))
                                    ->hidden(fn(Forms\Get $get) => $get('is_trial_plan'))
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.fixed_invoice_day')),
                                Fieldset::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('trial_period')
                                            ->numeric()
                                            ->default(0)
                                            ->hidden(fn(Forms\Get $get) => !$get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.trial_period')),
                                        Forms\Components\Select::make('trial_interval')
                                            ->options(Interval::class)
                                            ->default(Interval::DAY)
                                            ->hidden(fn(Forms\Get $get) => !$get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.trial_interval')),
                                        Forms\Components\TextInput::make('invoice_period')
                                            ->numeric()
                                            ->required()
                                            ->hidden(fn(Forms\Get $get) => $get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.invoice_period')),
                                        Forms\Components\Select::make('invoice_interval')
                                            ->options(Interval::class)
                                            ->default(Interval::MONTH)
                                            ->required()
                                            ->hidden(fn(Forms\Get $get) => $get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.invoice_interval')),
                                        Forms\Components\TextInput::make('grace_period')
                                            ->numeric()
                                            ->default(0)
                                            ->hidden(fn(Forms\Get $get) => !$get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.grace_period')),
                                        Forms\Components\Select::make('grace_interval')
                                            ->options(Interval::class)
                                            ->default(Interval::DAY)
                                            ->hidden(fn(Forms\Get $get) => !$get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.grace_interval')),
                                    ]),
                            ]),
                        Forms\Components\Tabs\Tab::make(__('filament-modular-subscriptions::fms.resources.plan.fields.modules'))
                            ->icon('heroicon-o-puzzle-piece')
                            ->schema([
                                Repeater::make('planModules')
                                    ->label('')
                                    ->relationship()
                                    ->columns(3)
                                    ->schema([
                                        Select::make('module_id')
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.module'))
                                            ->options(function () {
                                                $modules = config('filament-modular-subscriptions.models.module')::all()->mapWithKeys(function ($module) {
                                                    return [$module->id => $module->getLabel()];
                                                });

                                                return $modules;
                                            })
                                            ->required()
                                            ->searchable(),
                                        TextInput::make('limit')
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.module_limit'))
                                            ->numeric()
                                            ->nullable()
                                            ->hint(__('filament-modular-subscriptions::fms.resources.plan.hints.module_limit')),
                                        Forms\Components\TextInput::make('price')
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.module_price'))
                                            ->numeric()
                                            ->default(0)
                                            ->hidden(fn(Forms\Get $get) => $get('is_trial_plan'))
                                            ->suffix(config('filament-modular-subscriptions.main_currency'))
                                            ->nullable(),
                                    ])
                                    ->itemLabel(fn(array $state): ?string => config('filament-modular-subscriptions.models.module')::find($state['module_id'])?->getLabel() ?? null)
                                    ->collapsible()
                                    ->addActionLabel(__('filament-modular-subscriptions::fms.resources.plan.actions.add_module')),
                            ]),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('trans_name')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.price'))
                    ->getStateUsing(fn($record) => $record->is_pay_as_you_go ? __('filament-modular-subscriptions::fms.pay_as_you_go') : $record->price . ' ' . config('filament-modular-subscriptions.main_currency'))
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_active')),
                Tables\Columns\TextColumn::make('invoice_period')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.invoice_period'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_interval')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.invoice_interval')),
                Tables\Columns\TextColumn::make('modules_count')
                    ->counts('modules')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.modules_count')),
                Tables\Columns\IconColumn::make('is_trial_plan')
                    ->boolean()
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_trial_plan'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->options([
                        true => __('filament-modular-subscriptions::fms.active'),
                        false => __('filament-modular-subscriptions::fms.inactive'),
                    ])
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_active')),
                Tables\Filters\SelectFilter::make('is_pay_as_you_go')
                    ->options([
                        true => __('filament-modular-subscriptions::fms.pay_as_you_go'),
                        false => __('filament-modular-subscriptions::fms.subscription'),
                    ])
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_pay_as_you_go')),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
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
