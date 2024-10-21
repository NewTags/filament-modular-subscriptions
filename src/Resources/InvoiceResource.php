<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;
use HoceineEl\FilamentModularSubscriptions\Pages\InvoiceDetails;
use HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource\Pages;

class InvoiceResource extends Resource
{

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static ?string $slug = 'your-invoices';


    public static function getModel(): string
    {
        return config('filament-modular-subscriptions.models.invoice');
    }

    public static function getModelLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.resources.invoice.singular_name');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.resources.invoice.name');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.menu_group.subscription_management');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('subscription.subscriber.name')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.subscription_id')),
                TextEntry::make('amount')
                    ->money(fn($record) => $record->subscription->plan->currency, locale: 'en')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.amount')),
                TextEntry::make('status')
                    ->badge()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.status')),
                TextEntry::make('due_date')
                    ->date()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.due_date')),
                TextEntry::make('paid_at')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.paid_at')),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.invoice_number'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription.id')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.subscription_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn($record) => $record->subscription->plan->currency)
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.amount'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.status'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.due_date'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.paid_at'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PaymentStatus::class)
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.status')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('pay')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.actions.pay'))
                    ->url(fn($record) => InvoiceDetails::getUrl(['record' => $record]))
                    ->openUrlInNewTab(),
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
            'index' => Pages\ListInvoices::route('/'),
            'view' => InvoiceDetails::route('/{record}'),
        ];
    }
}
