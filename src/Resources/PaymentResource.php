<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use NewTags\FilamentModularSubscriptions\Components\FileEntry;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentMethod;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\Resources\PaymentResource\Pages;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use NewTags\FilamentModularSubscriptions\FmsPlugin;

class PaymentResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function getModel(): string
    {
        return config('filament-modular-subscriptions.models.payment');
    }

    public static function getNavigationGroup(): ?string
    {
        return FmsPlugin::get()->getNavigationGroup();
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.payment.singular_name');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.payment.name');
    }

    public static function getNavigationBadge(): ?string
    {
        return self::getModel()::where('status', PaymentStatus::PENDING)->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice.subscription.subscribable.name')
                    ->sortable()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.subscriber')),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn($record) =>  config('filament-modular-subscriptions.main_currency'), locale: 'en')
                    ->sortable()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount')),
                Tables\Columns\TextColumn::make('payment_method')
                    ->searchable()
                    ->badge()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.payment_method')),
                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable()
                    ->toggledHiddenByDefault()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.transaction_id')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.status')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_at')),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggledHiddenByDefault()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.reviewed_at')),
                Tables\Columns\TextColumn::make('reviewer.name')
                    ->toggledHiddenByDefault()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.reviewed_by')),
            ])

            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->columns(2)
                    ->form([
                        DatePicker::make('created_from')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_from')),
                        DatePicker::make('created_until')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('amount')
                    ->form([
                        TextInput::make('amount_from')
                            ->numeric()
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount_from')),
                        TextInput::make('amount_to')
                            ->numeric()
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),

            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('approve')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.actions.approve'))
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn($record) => $record->status === PaymentStatus::PENDING)
                    ->form([
                        TextInput::make('admin_notes')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.admin_notes')),
                    ])
                    ->action(function ($record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $record->update([
                                'status' => PaymentStatus::PAID,
                                'admin_notes' => $data['admin_notes'],
                                'reviewed_at' => now(),
                                'reviewed_by' => auth()->id(),
                            ]);

                            $invoice = $record->invoice;
                            $subscription = $invoice->subscription;
                            $totalPaid = $invoice->payments()
                                ->where('status', PaymentStatus::PAID)
                                ->sum('amount');

                            if ($totalPaid >= $invoice->amount) {
                                $invoice->update([
                                    'status' => InvoiceStatus::PAID,
                                    'paid_at' => now(),
                                ]);

                                // Only handle subscription renewal if there's a plan
                                if ($subscription->plan_id) {
                                    // Store the old plan ID before renewal
                                    $oldPlanId = $subscription->plan_id;

                                    // Renew the subscription
                                    $subscription->renew();

                                    // Determine the type of subscription change
                                    if ($subscription->plan_id !== $oldPlanId) {
                                        // Plan was switched
                                        $subscription->subscribable->notifySubscriptionChange('subscription_switched', [
                                            'old_plan' => $oldPlanId,
                                            'new_plan' => $subscription->plan_id,
                                            'start_date' => $subscription->starts_at->format('Y-m-d'),
                                            'end_date' => $subscription->ends_at->format('Y-m-d'),
                                            'currency' => config('filament-modular-subscriptions.main_currency'),
                                            'amount' => $invoice->amount,
                                        ]);
                                    } elseif ($subscription->wasRecentlyCreated) {
                                        // New subscription
                                        $subscription->subscribable->notifySubscriptionChange('subscription_activated', [
                                            'plan' => $subscription->plan_id,
                                            'start_date' => $subscription->starts_at->format('Y-m-d'),
                                            'end_date' => $subscription->ends_at->format('Y-m-d'),
                                            'currency' => config('filament-modular-subscriptions.main_currency'),
                                            'amount' => $invoice->amount,
                                        ]);
                                    } else {
                                        // Regular renewal
                                        $subscription->subscribable->notifySubscriptionChange('subscription_renewed', [
                                            'plan' => $subscription->plan_id,
                                            'start_date' => $subscription->starts_at->format('Y-m-d'),
                                            'end_date' => $subscription->ends_at->format('Y-m-d'),
                                            'currency' => config('filament-modular-subscriptions.main_currency'),
                                            'amount' => $invoice->amount,
                                        ]);
                                    }
                                }

                                // Payment received notification
                                $invoice->subscription->subscribable->notifySubscriptionChange('payment_received', [
                                    'amount' => $record->amount,
                                    'subtotal' => $invoice->subtotal,
                                    'tax' => $invoice->tax,
                                    'total' => $invoice->amount,
                                    'currency' => config('filament-modular-subscriptions.main_currency'),
                                    'invoice_id' => $invoice->id,
                                    'status' => PaymentStatus::PAID->getLabel(),
                                    'date' => now()->format('Y-m-d H:i:s')
                                ]);
                            } elseif ($totalPaid > 0) {
                                $invoice->update(['status' => InvoiceStatus::PARTIALLY_PAID]);

                                $invoice->subscription->subscribable->notifySubscriptionChange('payment_partially_approved', [
                                    'amount' => $record->amount,
                                    'remaining' => $invoice->amount - $totalPaid,
                                    'subtotal' => $invoice->subtotal,
                                    'tax' => $invoice->tax,
                                    'total' => $invoice->amount,
                                    'currency' => config('filament-modular-subscriptions.main_currency'),
                                    'status' => PaymentStatus::PARTIALLY_PAID->getLabel(),
                                    'date' => now()->format('Y-m-d H:i:s')
                                ]);
                            }
                        });

                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.payment.approved'))
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.actions.reject'))
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn($record) => $record->status === PaymentStatus::PENDING)
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('admin_notes')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.admin_notes'))
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => PaymentStatus::CANCELLED,
                            'admin_notes' => $data['admin_notes'],
                            'reviewed_at' => now(),
                            'reviewed_by' => auth()->id(),
                        ]);

                        $record->invoice->subscription->subscribable->notifySubscriptionChange('payment_rejected', [
                            'amount' => $record->amount,
                            'currency' => config('filament-modular-subscriptions.main_currency'),
                            'reason' => $data['admin_notes'],
                            'date' => now()->format('Y-m-d H:i:s'),
                            'invoice_id' => $record->invoice->id
                        ]);

                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.payment.rejected'))
                            ->danger()
                            ->send();
                    }),
                Action::make('undo')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.actions.undo'))
                    ->color('warning')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn($record) => in_array($record->status, [PaymentStatus::PAID, PaymentStatus::CANCELLED]))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            $invoice = $record->invoice;
                            $subscription = $invoice->subscription;
                            $subscribable = $subscription->subscribable;

                            if ($record->status === PaymentStatus::PAID) {
                                // Recalculate total paid amount excluding this payment
                                $totalPaid = $invoice->payments()
                                    ->where('status', PaymentStatus::PAID)
                                    ->where('id', '!=', $record->id)
                                    ->sum('amount');

                                // Update invoice status based on remaining paid amount
                                if ($totalPaid >= $invoice->amount) {
                                    $invoice->update(['status' => InvoiceStatus::PAID]);
                                } elseif ($totalPaid > 0) {
                                    $invoice->update([
                                        'status' => InvoiceStatus::PARTIALLY_PAID,
                                        'paid_at' => null,
                                    ]);

                                    $invoice->subscription->update([
                                        'status' => SubscriptionStatus::ON_HOLD,
                                    ]);
                                } else {
                                    $invoice->update([
                                        'status' => InvoiceStatus::UNPAID,
                                        'paid_at' => null,
                                    ]);

                                    $invoice->subscription->update([
                                        'status' => SubscriptionStatus::ON_HOLD,
                                    ]);
                                }

                                // Notify about payment status change
                                $subscribable->notifySubscriptionChange('payment_status_changed', [
                                    'previous_status' => PaymentStatus::PAID->getLabel(),
                                    'new_status' => PaymentStatus::PENDING->getLabel(),
                                    'amount' => $record->amount,
                                    'remaining' => $invoice->amount - $totalPaid,
                                    'subtotal' => $invoice->subtotal,
                                    'tax' => $invoice->tax,
                                    'total' => $invoice->amount,
                                    'currency' => config('filament-modular-subscriptions.main_currency'),
                                ]);
                            }

                            // Reset payment record to pending state
                            $record->update([
                                'status' => PaymentStatus::PENDING,
                                'admin_notes' => null,
                                'reviewed_at' => null,
                                'reviewed_by' => null,
                            ]);
                        });
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('filament-modular-subscriptions::fms.resources.payment.sections.payment_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice.subscription.subscriber.name')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.subscriber')),
                        Infolists\Components\TextEntry::make('amount')
                            ->money(fn($record) =>  config('filament-modular-subscriptions.main_currency'), locale: 'en')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount')),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->badge()
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.payment_method')),
                        Infolists\Components\TextEntry::make('transaction_id')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.transaction_id')),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.status')),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime()
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_at')),
                    ])->columns(),
                FileEntry::make('receipt_file')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.receipt_file'))
                    ->getStateUsing(fn($record) => $record->receipt_file ? Storage::url($record->receipt_file) : null)
                    ->visible(fn($record) => $record->receipt_file),
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
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
