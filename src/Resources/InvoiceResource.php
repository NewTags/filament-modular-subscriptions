<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\StaticAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource\Pages;
use Illuminate\Support\Facades\View;

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
        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.subscription');
    }



    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                if (Filament::getTenant()) {
                    $query->where('tenant_id', Filament::getTenant()->id);
                }

                return $query;
            })
            ->columns([

                Tables\Columns\TextColumn::make('subscription.subscriber.name')
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
                ViewAction::make()
                    ->slideOver()
                    ->modalHeading(fn($record) => __('filament-modular-subscriptions::modular-subscriptions.invoice.details_title', ['number' => $record->id]))
                    ->modalContent(function ($record) {
                        $invoice = $record;
                        return View::make('filament-modular-subscriptions::pages.invoice-details', compact('invoice'));
                    })->modalFooterActions([]),
                Action::make('download')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.invoice.download_pdf'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->modalFooterActions(null)
                    ->action(function ($record) {
                        $invoice = $record;
                        $pdf = Pdf::loadView('filament-modular-subscriptions::pages.invoice-pdf', compact('invoice'));

                        // Set PDF options for RTL and Arabic support
                        $pdf->setOption('enable-local-file-access', true);
                        $pdf->setOption('enable-unicode', true);
                        $pdf->setOption('encoding', 'UTF-8');
                        $pdf->setOption('font-family', 'Cairo');
                        $pdf->setOption('margin-top', 10);
                        $pdf->setOption('margin-right', 10);
                        $pdf->setOption('margin-bottom', 10);
                        $pdf->setOption('margin-left', 10);

                        // Set RTL direction
                        $pdf->setOption('direction', 'rtl');

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, "invoice-{$invoice->id}-{$invoice->created_at->format('Y-m-d')}.pdf", [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'attachment',
                            'charset' => 'utf-8',
                        ]);
                    }),
                Action::make('pay')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.actions.pay'))
                    // ->url(fn($record) => route('filament.resources.invoices.pay', $record))
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
        ];
    }
}
