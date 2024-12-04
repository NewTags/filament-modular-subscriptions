<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

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
use HoceineEl\FilamentModularSubscriptions\Components\FileEntry;
use HoceineEl\FilamentModularSubscriptions\Enums\InvoiceStatus;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentMethod;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use HoceineEl\FilamentModularSubscriptions\Resources\PaymentResource\Pages;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use HoceineEl\FilamentModularSubscriptions\FmsPlugin;

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
                    ->money(fn($record) => $record->invoice->subscription->plan->currency, locale: 'en')
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
                            $totalPaid = $invoice->payments()->where('status', PaymentStatus::PAID)->sum('amount');

                            if ($totalPaid >= $invoice->amount) {
                                $invoice->update([
                                    'status' => InvoiceStatus::PAID,
                                    'paid_at' => now(),
                                ]);

                                $invoice->subscription->renew();

                                $invoice->subscription->subscribable->notifySubscriptionChange('payment_received', [
                                    'amount' => $record->amount,
                                    'currency' => $invoice->subscription->plan->currency,
                                    'invoice_id' => $invoice->id,
                                    'status' => PaymentStatus::PAID->getLabel(),
                                    'date' => now()->format('Y-m-d H:i:s')
                                ]);
                            } elseif ($totalPaid > 0) {
                                $invoice->update(['status' => InvoiceStatus::PARTIALLY_PAID]);

                                $invoice->subscription->subscribable->notifySubscriptionChange('payment_partially_approved', [
                                    'amount' => $record->amount,
                                    'total' => $invoice->amount,
                                    'currency' => $invoice->subscription->plan->currency,
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
                            'currency' => $record->invoice->subscription->plan->currency,
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
                                // Revert subscription renewal if this was the payment that triggered it
                                if ($invoice->paid_at && $invoice->paid_at->eq($record->reviewed_at)) {
                                    // Calculate the previous end date before renewal
                                    $previousEndsAt = $subscription->starts_at->addDays($subscription->plan->period);

                                    $subscription->update([
                                        'status' => SubscriptionStatus::ON_HOLD,
                                        'ends_at' => $previousEndsAt,
                                    ]);

                                    // Notify about subscription status change
                                    $subscribable->notifySubscriptionChange('payment_undone', [
                                        'amount' => $record->amount,
                                        'currency' => $subscription->plan->currency,
                                        'previous_end_date' => $previousEndsAt->format('Y-m-d'),
                                    ]);
                                }

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
                                } else {
                                    $invoice->update([
                                        'status' => InvoiceStatus::UNPAID,
                                        'paid_at' => null,
                                    ]);
                                }

                                defer(function () use ($subscribable, $record, $subscription) {
                                    // Notify about payment status change
                                    $subscribable->notifySubscriptionChange('payment_status_changed', [
                                        'previous_status' => PaymentStatus::PAID->getLabel(),
                                        'new_status' => PaymentStatus::PENDING->getLabel(),
                                        'amount' => $record->amount,
                                        'currency' => $subscription->plan->currency,
                                    ]);
                                });
                            }

                            // Reset payment record to pending state
                            $record->update([
                                'status' => PaymentStatus::PENDING,
                                'admin_notes' => null,
                                'reviewed_at' => null,
                                'reviewed_by' => null,
                            ]);

                            // Invalidate all relevant caches
                            $subscribable->invalidateSubscriptionCache();

                            // Send notification
                            Notification::make()
                                ->title(__('filament-modular-subscriptions::fms.payment.undone'))
                                ->success()
                                ->send();

                            // Notify super admins
                            $subscribable->notifySuperAdmins('payment_undone', [
                                'amount' => $record->amount,
                                'currency' => $subscription->plan->currency,
                                'invoice_id' => $invoice->id,
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
                            ->money(fn($record) => $record->invoice->subscription->plan->currency, locale: 'en')
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
