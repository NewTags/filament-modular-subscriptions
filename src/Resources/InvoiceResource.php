<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentMethod;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource\Pages;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;

class InvoiceResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static ?string $slug = 'ms-nvoices';

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
        if (Filament::getTenant()) {
            return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.subscription_navigation_label');
        }

        return __('filament-modular-subscriptions::modular-subscriptions.tenant_subscription.subscription');
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                if (Filament::getTenant()) {
                    $query->where('tenant_id', Filament::getTenant()->id);
                }

                return $query->with('subscription.subscriber', 'subscription.plan');
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
            //@todo : to fix this unhandled state
            // ->filters([
            //     Tables\Filters\SelectFilter::make('status')
            //         ->options(PaymentStatus::class)
            //         ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.fields.status')),
            // ])
            ->modelLabel(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.singular_name'))
            ->pluralModelLabel(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.name'))
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
                    ->action(function ($record) {
                        $invoice = $record;
                        $html = view('filament-modular-subscriptions::pages.invoice-pdf', compact('invoice'))->render();

                        // Configure mPDF with RTL support
                        $mpdf = new Mpdf([
                            'mode' => 'utf-8',
                            'format' => 'A4',
                            'orientation' => 'P',
                            'margin_left' => 0,
                            'margin_right' => 0,
                            'margin_top' => 0,
                            'margin_bottom' => 0,
                            'default_font' => 'dejavusans',
                            'tempDir' => storage_path('tmp'),
                        ]);

                        $mpdf->SetDirectionality('rtl');
                        $mpdf->autoScriptToLang = true;
                        $mpdf->autoLangToFont = true;

                        $mpdf->WriteHTML($html);

                        return response()->streamDownload(function () use ($mpdf) {
                            echo $mpdf->Output('', 'S');
                        }, "invoice_{$record->id}-{$record->created_at->format('Y-m-d H-i-s')}.pdf", [
                            'Content-Type' => 'application/pdf',
                        ]);
                    }),
                Action::make('pay')
                    ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.invoice.actions.pay'))
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->visible(fn($record) => Filament::getTenant() && $record->notPaid())
                    ->form(function ($record) {
                        return [
                            TextInput::make('amount')
                                ->default(fn() => $record->amount)
                                ->numeric()
                                ->required()
                                ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fileds.amount')),
                            FileUpload::make('receipt_file')
                                ->required()
                                ->label(__('filament-modular-subscriptions::modular-subscriptions.resources.payment.fileds.receipt_file'))
                        ];
                    })
                    ->action(function (array $data, $record) {
                        $record->payments()->create([
                            'amount' => $data['amount'],
                            'receipt_file' => $data['receipt_file'],
                            'payment_method' => PaymentMethod::BANK_TRANSFER,
                            'status' => PaymentStatus::PENDING,
                            'transaction_id' => 'PAY-' . (string) uuid_create(),
                        ]);

                        Notification::make()
                            ->title(__('filament-modular-subscriptions::modular-subscriptions.invoice.payment_pending'))
                            ->success()
                            ->send();
                    }),
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
