<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Models\Plan;
use NewTags\FilamentModularSubscriptions\Models\Subscription;
use NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;
use Illuminate\Database\Eloquent\Builder;
use NewTags\FilamentModularSubscriptions\FmsPlugin;

class SubscriptionResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $slug = 'subscriptions-management';

    public static function getModel(): string
    {
        return config('filament-modular-subscriptions.models.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.subscription.name');
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.subscription.singular_name');
    }

    public static function getNavigationGroup(): ?string
    {
        return FmsPlugin::get()->getNavigationGroup();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('subscribable_id')
                    ->relationship('subscriber', function () {
                        $tenantAttribute = config('filament-modular-subscriptions.tenant_attribute');
                        if (! $tenantAttribute) {
                            throw new \Exception('Tenant attribute not set in config/filament-modular-subscriptions.php');
                        }

                        return $tenantAttribute;
                    }, modifyQueryUsing: function (Builder $query, $record) {
                        // Get subscribers with active subscriptions except current record
                        $subscribersWithActiveSubscriptions = Subscription::query()
                            ->where('status', SubscriptionStatus::ACTIVE)
                            ->when($record, function ($query) use ($record) {
                                $query->where('subscribable_id', '!=', $record->subscribable_id);
                            })
                            ->pluck('subscribable_id');

                        // Return current subscriber and those without active subscriptions
                        return $query->where(function ($query) use ($record, $subscribersWithActiveSubscriptions) {
                            $query->whereNotIn('id', $subscribersWithActiveSubscriptions)
                                ->when($record, function ($query) use ($record) {
                                    $query->orWhere('id', $record->subscribable_id);
                                });
                        });
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.subscribable_id')),

                Forms\Components\Select::make('plan_id')
                    ->options(fn() => Plan::all()->mapWithKeys(function ($plan) {
                        return [$plan->id => $plan->trans_name . ' - ' . $plan->price . ' ' . $plan->currency];
                    }))
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $plan = Plan::find($state);
                        if ($plan) {
                            $startDate = now();
                            $set('starts_at', $startDate->format('Y-m-d H:i:s'));
                            $set('ends_at', $startDate->copy()->addDays($plan->period)->format('Y-m-d H:i:s'));
                            $set('status', SubscriptionStatus::ACTIVE);
                            $set('trial_ends_at', $startDate->copy()->addDays($plan->period_trial)->format('Y-m-d H:i:s'));
                        }
                    })
                    ->required()
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.plan_id')),
                Fieldset::make(__('filament-modular-subscriptions::fms.details'))
                    ->columns()
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->required()
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.starts_at')),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.ends_at')),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->hidden(fn($get) => Plan::find($get('plan_id'))->is_trial_plan)
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.trial_ends_at')),
                        Forms\Components\Select::make('status')
                            ->options(SubscriptionStatus::class)
                            ->required()
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.status')),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan.trans_name')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.plan_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscribable.name')
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.subscribable_id')),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.starts_at'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.ends_at'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.status')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(SubscriptionStatus::class)
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.status')),
                Tables\Filters\SelectFilter::make('plan_id')
                    ->options(fn() => Plan::all()->pluck('name', 'id'))
                    ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.plan_id')),
                Filter::make('dates')
                    ->form([
                        DatePicker::make('starts_at')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.starts_at')),
                        DatePicker::make('ends_at')
                            ->label(__('filament-modular-subscriptions::fms.resources.subscription.fields.ends_at')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['starts_at']) {
                            $query->where('starts_at', '>=', $data['starts_at']);
                        }
                        if ($data['ends_at']) {
                            $query->where('ends_at', '<=', $data['ends_at']);
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        $record->subscribable->clearFmsCache();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function () {
                            $subscriptions = config('filament-modular-subscriptions.models.subscription');
                            foreach ($subscriptions::all() as $subscription) {
                                $subscription->subscribable->clearFmsCache();
                            }
                        }),
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
