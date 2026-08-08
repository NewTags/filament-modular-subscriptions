<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Enums\SubscriptionStatus;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Resources\PaymentResource\Pages\ListPayments;
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;

class PaymentResource extends Resource
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';

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
                TextColumn::make('invoice.subscription.subscribable.name')
                    ->sortable()
                    ->searchable()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.subscriber')),
                TextColumn::make('amount')
                    ->prefix(fn ($record) => config('filament-modular-subscriptions.main_currency'))
                    ->sortable()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount')),
                TextColumn::make('payment_method')
                    ->searchable()
                    ->badge()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.payment_method')),
                TextColumn::make('transaction_id')
                    ->searchable()
                    ->toggledHiddenByDefault()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.transaction_id')),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.status')),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_at')),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggledHiddenByDefault()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.reviewed_at')),
                TextColumn::make('reviewer.name')
                    ->toggledHiddenByDefault()
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.reviewed_by')),
            ])

            ->filters([
                SelectFilter::make('status')
                    ->options(PaymentStatus::class)
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.status')),
                Filter::make('created_at')
                    ->columns(2)
                    ->schema([
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
                Filter::make('amount')
                    ->schema([
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
            ->recordActions([
                ViewAction::make(),
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
                    ->schema([
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

                            $record->update([
                                'status' => PaymentStatus::PAID,
                                'admin_notes' => $data['admin_notes'],
                                'reviewed_at' => now(),
                                'reviewed_by' => auth()->id(),
                            ]);

                            app(InvoiceService::class)->settleInvoice($record->invoice, $record);

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
                    ->schema([
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-modular-subscriptions::fms.resources.payment.sections.payment_details'))
                    ->schema([
                        TextEntry::make('invoice.subscription.subscriber.name')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.subscriber')),
                        TextEntry::make('amount')
                            ->prefix(fn ($record) => config('filament-modular-subscriptions.main_currency'))
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount')),
                        TextEntry::make('payment_method')
                            ->badge()
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.payment_method')),
                        TextEntry::make('transaction_id')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.transaction_id')),
                        TextEntry::make('status')
                            ->badge()
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.status')),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_at')),
                    ])->columns(),
                ViewEntry::make('receipt_file')
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
            'index' => ListPayments::route('/'),
        ];
    }
}
