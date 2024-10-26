<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Resources\PaymentResource\Pages;
use Illuminate\Support\HtmlString;

class PaymentResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function getModel(): string
    {
        return config('filament-modular-subscriptions.models.payment');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.menu_group.subscription_management');
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.resources.payment.singular_name');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.resources.payment.name');
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice.subscription.subscribable.name')
                    ->sortable()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.subscriber')),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn($record) => $record->invoice->subscription->plan->currency, locale: 'en')
                    ->sortable()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.amount')),
                Tables\Columns\TextColumn::make('payment_method')
                    ->searchable()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.payment_method')),
                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.transaction_id')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.status')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.created_at')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('approve')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.actions.approve'))
                    ->visible(fn($record) => $record->status === PaymentStatus::PENDING)
                    ->action(function ($record) {
                        $record->update(['status' => PaymentStatus::PAID]);

                        $invoice = $record->invoice;
                        if ($invoice->status === PaymentStatus::PARTIALLY_PAID && $invoice->payments()->sum('amount') >= $invoice->amount) {
                            $invoice->update(['status' => PaymentStatus::PAID]);

                            $invoice->subscription->renew();

                            Notification::make()
                                ->title(__('filament-modular-subscriptions::modular-subscriptions.payment.subscription_renewed'))
                                ->success()
                                ->send();
                        } elseif ($invoice->notPaid() && $invoice->payments()->sum('amount') < $invoice->amount) {
                            $invoice->update(['status' => PaymentStatus::PARTIALLY_PAID]);

                            Notification::make()
                                ->title(__('filament-modular-subscriptions::modular-subscriptions.payment.partially_paid'))
                                ->success()
                                ->send();
                        }
                        Notification::make()
                            ->title(__('filament-modular-subscriptions::modular-subscriptions.payment.approved'))
                            ->success()
                            ->send();
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
                Infolists\Components\Section::make(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.sections.payment_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice.subscription.subscriber.name')
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.subscriber')),
                        Infolists\Components\TextEntry::make('amount')
                            ->money(fn($record) => $record->invoice->subscription->plan->currency)
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.amount')),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.payment_method')),
                        Infolists\Components\TextEntry::make('transaction_id')
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.transaction_id')),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.status')),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime()
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.created_at')),

                    ]),
                Infolists\Components\Section::make(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.sections.receipt'))
                    ->schema([
                        Infolists\Components\Entry::make('receipt_file')
                            ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.receipt_file'))
                            ->formatStateUsing(fn($state) => $state ? new HtmlString('<a href="' . $state . '" target="_blank">' . __('filament-modular-subscriptions::modular-subscriptions.resources.payment.fields.receipt_file') . '</a>') : null),
                    ])
                    ->visible(fn($record) => $record->receipt_file)
                    ->columns(2),
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
