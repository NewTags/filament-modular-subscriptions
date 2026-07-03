<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-s-squares-plus';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Plan Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('filament-modular-subscriptions::fms.resources.plan.tabs.details'))
                            ->icon('heroicon-o-information-circle')
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->translatable(true, config('filament-modular-subscriptions.locales'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Set $set, $state) => $set('slug', str($state['name'][config('filament-modular-subscriptions.locales')[0] ?? app()->getLocale()])->slug()))
                                    ->columnSpanFull()
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.name')),
                                TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.slug')),
                                Textarea::make('description')
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.description'))
                                    ->translatable(true, config('filament-modular-subscriptions.locales'))
                                    ->columnSpanFull(),
                                Toggle::make('is_active')
                                    ->default(true)
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_active')),
                                Toggle::make('is_pay_as_you_go')
                                    ->default(false)
                                    ->hidden(fn(Get $get) => $get('is_trial_plan'))
                                    ->live()
                                    ->helperText(__('filament-modular-subscriptions::fms.resources.plan.hints.is_pay_as_you_go'))
                                    ->label(__('filament-modular-subscriptions::fms.pay_as_you_go')),
                                Toggle::make('is_trial_plan')
                                    ->default(false)
                                    ->live()
                                    ->helperText(__('filament-modular-subscriptions::fms.resources.plan.hints.is_trial_plan'))
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if ($state) {
                                            $set('price', 0);
                                            $set('is_pay_as_you_go', false);
                                        }
                                    })
                                    ->hidden(fn(Get $get) => $get('is_pay_as_you_go'))
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_trial_plan')),
                            ]),
                        Tab::make(__('filament-modular-subscriptions::fms.resources.plan.tabs.pricing'))
                            ->columns()
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                TextInput::make('price')
                                    ->numeric()
                                    ->required()
                                    ->hidden(fn(Get $get) => $get('is_trial_plan') || $get('is_pay_as_you_go'))
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.price')),
                                TextInput::make('setup_fee')
                                    ->numeric()
                                    ->helperText(__('filament-modular-subscriptions::fms.resources.plan.hints.setup_fee'))
                                    ->hidden(fn(Get $get) => $get('is_trial_plan'))
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.setup_fee')),
                                Select::make('currency')
                                    ->options(config('filament-modular-subscriptions.currencies'))
                                    ->default(config('filament-modular-subscriptions.main_currency'))
                                    ->required()
                                    ->hidden()
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.currency')),
                            ]),
                        Tab::make(__('filament-modular-subscriptions::fms.resources.plan.tabs.billing'))
                            ->columns()
                            ->schema([
                                Select::make('fixed_invoice_day')
                                    ->options(fn() => collect(range(1, 28))->mapWithKeys(fn($day) => [$day => $day]))
                                    ->default(1)
                                    ->columnSpanFull()
                                    ->helperText(__('filament-modular-subscriptions::fms.resources.plan.hints.fixed_invoice_day'))
                                    ->hidden(fn(Get $get) => $get('is_trial_plan'))
                                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.fixed_invoice_day')),
                                Fieldset::make()
                                    ->schema([
                                        TextInput::make('trial_period')
                                            ->numeric()
                                            ->default(0)
                                            ->hidden(fn(Get $get) => !$get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.trial_period')),
                                        Select::make('trial_interval')
                                            ->options(Interval::class)
                                            ->default(Interval::DAY)
                                            ->hidden(fn(Get $get) => !$get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.trial_interval')),
                                        TextInput::make('invoice_period')
                                            ->numeric()
                                            ->required()
                                            ->hidden(fn(Get $get) => $get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.invoice_period')),
                                        Select::make('invoice_interval')
                                            ->options(Interval::class)
                                            ->default(Interval::MONTH)
                                            ->required()
                                            ->hidden(fn(Get $get) => $get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.invoice_interval')),
                                        TextInput::make('grace_period')
                                            ->numeric()
                                            ->default(0)
                                            ->hidden(fn(Get $get) => $get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.grace_period')),
                                        Select::make('grace_interval')
                                            ->options(Interval::class)
                                            ->default(Interval::DAY)
                                            ->hidden(fn(Get $get) => $get('is_trial_plan'))
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.grace_interval')),
                                    ]),
                            ]),
                        Tab::make(__('filament-modular-subscriptions::fms.resources.plan.fields.modules'))
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
                                        TextInput::make('price')
                                            ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.module_price'))
                                            ->numeric()
                                            ->default(0)
                                            ->hidden(fn(Get $get) => $get('is_trial_plan'))
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('trans_name')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.name'))
                    ->searchable(),
                TextColumn::make('price')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.price'))
                    ->getStateUsing(fn($record) => $record->is_pay_as_you_go ? __('filament-modular-subscriptions::fms.pay_as_you_go') : $record->price . ' ' . config('filament-modular-subscriptions.main_currency'))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_active')),
                TextColumn::make('invoice_period')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.invoice_period'))
                    ->sortable(),
                TextColumn::make('invoice_interval')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.invoice_interval')),
                TextColumn::make('modules_count')
                    ->counts('modules')
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.modules_count')),
                IconColumn::make('is_trial_plan')
                    ->boolean()
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_trial_plan'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([
                        true => __('filament-modular-subscriptions::fms.active'),
                        false => __('filament-modular-subscriptions::fms.inactive'),
                    ])
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_active')),
                SelectFilter::make('is_pay_as_you_go')
                    ->options([
                        true => __('filament-modular-subscriptions::fms.pay_as_you_go'),
                        false => __('filament-modular-subscriptions::fms.subscription'),
                    ])
                    ->label(__('filament-modular-subscriptions::fms.resources.plan.fields.is_pay_as_you_go')),

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
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
