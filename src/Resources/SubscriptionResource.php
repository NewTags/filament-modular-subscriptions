<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use HoceineEl\FilamentModularSubscriptions\Models\Subscription;
use HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.resources.subscription.name');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Subscriptions Management');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('subscribable_id')
                    ->numeric()
                    ->required()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.subscribable_id')),
                Forms\Components\Select::make('plan_id')
                    ->options(fn() => Plan::all()->mapWithKeys(function ($plan) {
                        return [$plan->id => $plan->name . ' - ' . $plan->price . ' ' . $plan->currency];
                    }))
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $plan = Plan::find($state);
                        $startDate = now();
                        $set('starts_at', $startDate);
                        $set('ends_at', $startDate->copy()->add($plan->invoice_interval, $plan->invoice_period));
                        $set('status', SubscriptionStatus::ACTIVE);
                        $set('trial_ends_at', $startDate->copy()->add($plan->trial_interval, $plan->trial_period));
                    })
                    ->required()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.plan_id')),
                Fieldset::make(__('Details'))
                    ->columns()
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->required()
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.starts_at')),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.ends_at')),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.trial_ends_at')),
                        Forms\Components\Select::make('status')
                            ->options(SubscriptionStatus::class)
                            ->required()
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.status')),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan.name')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.plan_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscribable_type')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.subscribable_type')),
                Tables\Columns\TextColumn::make('subscribable_id')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.subscribable_id')),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.starts_at'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.ends_at'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.status')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(SubscriptionStatus::class)
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.status')),
                Tables\Filters\SelectFilter::make('plan_id')
                    ->options(fn() => Plan::all()->pluck('name', 'id'))
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.plan_id')),
                Filter::make('dates')
                    ->form([
                        DatePicker::make('starts_at')
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.starts_at')),
                        DatePicker::make('ends_at')
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.fields.ends_at')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->whereBetween('starts_at', [$data['starts_at'], $data['ends_at']]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
