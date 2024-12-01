<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use ArPHP\I18N\Arabic;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Enums\FiltersLayout;
use HoceineEl\FilamentModularSubscriptions\Enums\InvoiceStatus;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentMethod;
use HoceineEl\FilamentModularSubscriptions\Enums\PaymentStatus;
use HoceineEl\FilamentModularSubscriptions\Models\Plan;
use HoceineEl\FilamentModularSubscriptions\ResolvesCustomerInfo;
use HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource\Pages;
use Illuminate\Contracts\Database\Eloquent\Builder;
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
        return __('filament-modular-subscriptions::fms.resources.invoice.singular_name');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-modular-subscriptions::fms.resources.invoice.name');
    }

    public static function getNavigationGroup(): ?string
    {
        if (filament()->getTenant()) {
            return __('filament-modular-subscriptions::fms.tenant_subscription.subscription_navigation_label');
        }

        return __('filament-modular-subscriptions::fms.menu_group.subscription_management');
    }
    public static function getNavigationBadge(): ?string
    {
        return self::getModel()::where('status', InvoiceStatus::UNPAID)->orWhere('status', InvoiceStatus::PARTIALLY_PAID)->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        $currency = Plan::first()->currency ?? config('filament-modular-subscriptions.main_currency');

        return $table
            ->modifyQueryUsing(function ($query) {
                if (filament()->getTenant()) {
                    $query->where('tenant_id', filament()->getTenant()->id);
                }

                return $query->with([
                    'subscription.subscriber',
                    'subscription.plan',
                    'items',
                    'tenant',
                ]);
            })
            ->columns([

                Tables\Columns\TextColumn::make('subscription.subscriber.name')
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.subscription_id'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money($currency)
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.amount'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax')
                    ->money($currency)
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.tax'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->money($currency)
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.total'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.status'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.due_date'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.paid_at'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InvoiceStatus::class)
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.status')),
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
                Tables\Filters\Filter::make('date')
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
            ], FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->modelLabel(__('filament-modular-subscriptions::fms.resources.invoice.singular_name'))
            ->pluralModelLabel(__('filament-modular-subscriptions::fms.resources.invoice.name'))
            ->actions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->modalHeading(fn($record) => __('filament-modular-subscriptions::fms.invoice.details_title', ['number' => $record->id]))
                    ->modalContent(function ($record) {
                        $invoice = $record->load(['items', 'subscription.plan']); // Eager load relationships

                        return View::make('filament-modular-subscriptions::pages.invoice-details', compact('invoice'));
                    })
                    ->modalFooterActions([]),
                Action::make('download')
                    ->label(__('filament-modular-subscriptions::fms.invoice.download_pdf'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($record) {
                        // Calculate tax amounts correctly
                        $taxPercentage = config('filament-modular-subscriptions.tax_percentage', 15);
                        $totalBeforeTax = $record->amount;
                        $taxAmount = $record->tax;

                        // Generate QR code with correct amounts
                        $QrCode = \Salla\ZATCA\GenerateQrCode::fromArray([
                            new \Salla\ZATCA\Tags\Seller(config('filament-modular-subscriptions.company_name')),
                            new \Salla\ZATCA\Tags\TaxNumber(config('filament-modular-subscriptions.tax_number')),
                            new \Salla\ZATCA\Tags\InvoiceDate($record->created_at),
                            new \Salla\ZATCA\Tags\InvoiceTotalAmount($totalBeforeTax + $taxAmount), // Total with tax
                            new \Salla\ZATCA\Tags\InvoiceTaxAmount($taxAmount),
                        ])->render();

                        // Get view data with additional tax information
                        $data = [
                            'invoice' => $record,
                            'QrCode' => $QrCode,
                            'user' => ResolvesCustomerInfo::take($record->tenant),
                            'company_logo' => public_path(config('filament-modular-subscriptions.company_logo')),
                            'tax_percentage' => $taxPercentage,
                            'total_before_tax' => $totalBeforeTax,
                            'tax_amount' => $taxAmount,
                        ];

                        // Configure mPDF with better Arabic support
                        $defaultConfig = (new \Mpdf\Config\ConfigVariables)->getDefaults();
                        $fontDirs = $defaultConfig['fontDir'];

                        $defaultFontConfig = (new \Mpdf\Config\FontVariables)->getDefaults();
                        $fontData = $defaultFontConfig['fontdata'];

                        $mpdf = new \Mpdf\Mpdf([
                            'fontDir' => array_merge($fontDirs, [
                                config('filament-modular-subscriptions.font_path'),
                            ]),
                            'fontdata' => array_merge($fontData, [
                                'Cairo' => [
                                    'R' => 'Cairo-Bold.ttf',
                                    'B' => 'Cairo-Bold.ttf',
                                ],
                            ]),
                            'default_font' => 'Cairo',
                            'mode' => 'utf-8',
                            'format' => 'A4',
                            'tempDir' => storage_path('app/pdf-fonts'),
                            'orientation' => 'P',
                            'margin_left' => 10,
                            'margin_right' => 10,
                            'margin_top' => 10,
                            'margin_bottom' => 10,
                        ]);

                        // Add better error handling
                        try {
                            $view = view('filament-modular-subscriptions::pages.invoice-pdf', $data);
                            $html = $view->render();
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, 'invoice_' . $record->id . '_' . $record->created_at->format('Y-m-d') . '.pdf');
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title(__('filament-modular-subscriptions::fms.invoice.pdf_generation_error'))
                                ->danger()
                                ->send();

                            report($e);

                            return null;
                        }
                    }),
                Action::make('pay')
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.actions.pay'))
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn($record) => filament()->getTenant() && in_array($record->status, [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID]))
                    ->form([
                        TextInput::make('amount')
                            ->default(fn($record) => $record->remaining_amount)
                            ->numeric()
                            ->required()
                            ->suffix(fn($record) => $record->subscription->plan->currency)
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount'))
                            ->maxValue(fn($record) => $record->remaining_amount)
                            ->minValue(1),
                        FileUpload::make('receipt_file')
                            ->required()
                            ->maxSize(5120) // 5MB
                            ->directory('payment-receipts')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.receipt_file'))
                            ->helperText(__('filament-modular-subscriptions::fms.resources.payment.receipt_help_text')),
                        TextInput::make('notes')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.notes')),
                    ])
                    ->action(function (array $data, $record) {
                        $record->payments()->create([
                            'amount' => $data['amount'],
                            'receipt_file' => $data['receipt_file'],
                            'payment_method' => PaymentMethod::BANK_TRANSFER,
                            'status' => PaymentStatus::PENDING,
                            'transaction_id' => 'PAY-' . (string) uuid_create(),
                            'metadata' => [
                                'notes' => $data['notes'] ?? null,
                                'submitted_by' => auth()->id(),
                                'submitted_at' => now(),
                            ],
                        ]);

                        // Notify super admins about new pending payment
                        $record->subscription->subscribable->notifySuperAdmins('payment_pending', [
                            'amount' => $data['amount'],
                            'currency' => $record->subscription->plan->currency,
                            'invoice_id' => $record->id
                        ]);

                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.invoice.payment_pending'))
                            ->success()
                            ->send();
                    }),
                Action::make('view_payments')
                    ->label(__('filament-modular-subscriptions::fms.invoice.view_payments'))
                    ->icon('heroicon-o-eye')
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->visible(fn($record) => $record->payments()->exists())
                    ->infolist(function ($record) {
                        $payments = $record->payments;
                        $schema = [];

                        foreach ($payments as $payment) {
                            $schema[] = Fieldset::make($payment->created_at->translatedFormat('M d, Y'))
                                ->schema([
                                    TextEntry::make('amount')
                                        ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount'))
                                        ->money(fn($record) => $record->subscription->plan->currency, locale: 'en')
                                        ->getStateUsing(fn($record) => $record->amount),
                                    TextEntry::make('payment_method')
                                        ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.payment_method'))
                                        ->badge()
                                        ->getStateUsing(fn($record) => $record->payment_method),
                                    TextEntry::make('status')
                                        ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.status'))
                                        ->badge()
                                        ->getStateUsing(fn($record) => $record->status),
                                    TextEntry::make('created_at')
                                        ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_at'))
                                        ->getStateUsing(fn($record) => $record->created_at->translatedFormat('M d, Y')),
                                ]);
                        }

                        return $schema;
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
