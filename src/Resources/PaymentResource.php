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
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Resources\PaymentResource\Pages;

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
            ->modifyQueryUsing(fn ($query) => $query->with(['invoice.subscription.subscribable', 'invoice.subscription.plan', 'reviewer']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice.subscription.subscribable.name')
                    ->sortable()
                    ->searchable()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.subscriber')),
                Tables\Columns\TextColumn::make('amount')
                    ->prefix(fn ($record) => config('filament-modular-subscriptions.main_currency'))
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
                Tables\Filters\SelectFilter::make('status')
                    ->options(PaymentStatus::class)
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.status')),
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
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
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
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),

            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('download_receipt')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.actions.download_receipt'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn ($record) => filled($record->receipt_file))
                    ->action(function ($record) {
                        $disk = self::receiptDiskFor($record->receipt_file);

                        if (! $disk) {
                            Notification::make()
                                ->warning()
                                ->title(__('filament-modular-subscriptions::fms.resources.payment.receipt_missing'))
                                ->send();

                            return null;
                        }

                        return Storage::disk($disk)->download($record->receipt_file);
                    }),
                Action::make('approve')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.actions.approve'))
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->status === PaymentStatus::PENDING)
                    ->form([
                        TextInput::make('admin_notes')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.admin_notes')),
                    ])
                    ->action(function ($record, array $data) {
                        $processed = DB::transaction(function () use ($record, $data) {
                            // Lock the payment and re-assert it is still pending so a replayed
                            // or concurrent approval cannot apply the payment (and renewal) twice.
                            $record = $record->newQuery()->lockForUpdate()->find($record->getKey());

                            if (! $record || $record->status !== PaymentStatus::PENDING) {
                                return false;
                            }

                            $invoice = $record->invoice()->lockForUpdate()->first();
                            $invoiceWasPaid = $invoice->status === InvoiceStatus::PAID;

                            $record->update([
                                'status' => PaymentStatus::PAID,
                                'admin_notes' => $data['admin_notes'],
                                'reviewed_at' => now(),
                                'reviewed_by' => auth()->id(),
                            ]);

                            $subscription = $invoice->subscription;
                            $totalPaid = $invoice->payments()
                                ->where('status', PaymentStatus::PAID)
                                ->sum('amount');

                            if ($totalPaid >= $invoice->amount) {
                                $invoice->update([
                                    'status' => InvoiceStatus::PAID,
                                    'paid_at' => $invoice->paid_at ?? now(),
                                ]);

                                // Only apply the paid-side effects (renewal, callbacks, notification)
                                // the first time the invoice becomes paid, never on a later approval.
                                if ($invoiceWasPaid) {
                                    return true;
                                }

                                // Run custom after invoice paid callback
                                FmsPlugin::runAfterInvoicePaid($invoice);

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
                                    'date' => now()->format('Y-m-d H:i:s'),
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
                                    'date' => now()->format('Y-m-d H:i:s'),
                                ]);
                            }

                            return true;
                        });

                        if (! $processed) {
                            Notification::make()
                                ->title(__('filament-modular-subscriptions::fms.payment.already_processed'))
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.payment.approved'))
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.actions.reject'))
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn ($record) => $record->status === PaymentStatus::PENDING)
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('admin_notes')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.admin_notes'))
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => PaymentStatus::REJECTED,
                            'admin_notes' => $data['admin_notes'],
                            'reviewed_at' => now(),
                            'reviewed_by' => auth()->id(),
                        ]);

                        $record->invoice->subscription->subscribable->notifySubscriptionChange('payment_rejected', [
                            'amount' => $record->amount,
                            'currency' => config('filament-modular-subscriptions.main_currency'),
                            'reason' => $data['admin_notes'],
                            'date' => now()->format('Y-m-d H:i:s'),
                            'invoice_id' => $record->invoice->id,
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
                    ->visible(fn ($record) => in_array($record->status, [PaymentStatus::PAID, PaymentStatus::CANCELLED, PaymentStatus::REJECTED]))
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
                            ->prefix(fn ($record) => config('filament-modular-subscriptions.main_currency'))
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
                Infolists\Components\ViewEntry::make('receipt_file')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.receipt_file'))
                    ->view('filament-modular-subscriptions::filament.components.receipt-preview')
                    ->visible(fn ($record) => filled($record->receipt_file)),
            ]);
    }

    public static function receiptDiskFor(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $disk = config('filament-modular-subscriptions.receipts_disk', 'local');

        if (Storage::disk($disk)->exists($path)) {
            return $disk;
        }

        // Fall back to the public disk for receipts uploaded before private storage was enabled.
        if ($disk !== 'public' && Storage::disk('public')->exists($path)) {
            return 'public';
        }

        return null;
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
