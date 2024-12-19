<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use ArPHP\I18N\Arabic;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Enums\FiltersLayout;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentMethod;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\Models\Plan;
use NewTags\FilamentModularSubscriptions\ResolvesCustomerInfo;
use NewTags\FilamentModularSubscriptions\Resources\InvoiceResource\Pages;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\Fieldset as ComponentsFieldset;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use Illuminate\Support\HtmlString;

class InvoiceResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static ?string $slug = 'ms-invoices';

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
        if (FmsPlugin::get()->isOnTenantPanel()) {
            return FmsPlugin::get()->getTenantNavigationGroup();
        }

        return FmsPlugin::get()->getNavigationGroup();
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
                $tenant = FmsPlugin::getTenant();
                if ($tenant) {
                    $query->where('tenant_id', $tenant->id);
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

                Tables\Columns\TextColumn::make('subtotal')
                    ->money($currency)
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.subtotal'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax')
                    ->money($currency)
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.tax'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
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
                    ->toggledHiddenByDefault()
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
            ], FiltersLayout::Modal)
            ->filtersFormColumns(3)
            ->modelLabel(__('filament-modular-subscriptions::fms.resources.invoice.singular_name'))
            ->pluralModelLabel(__('filament-modular-subscriptions::fms.resources.invoice.name'))
            ->actions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->modalHeading(fn($record) => __('filament-modular-subscriptions::fms.invoice.details_title', ['number' => $record->id]))
                    ->modalContent(function ($record) {
                        $invoice = $record->load(['items', 'subscription.plan']);

                        return View::make('filament-modular-subscriptions::pages.invoice-details', compact('invoice'));
                    })
                    ->modalFooterActions([
                        self::downloadAction(),
                    ]),
                self::downloadAction(),
                Action::make('pay')
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.actions.pay'))
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(
                        function ($record) {
                            return FmsPlugin::get()->isOnTenantPanel()
                                && in_array(
                                    $record->status,
                                    [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID]
                                );
                        }
                    )
                    ->steps([
                        Step::make('payment_method')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.payment_method'))
                            ->description(__('filament-modular-subscriptions::fms.resources.payment.choose_method'))
                            ->schema([
                                Grid::make()
                                    ->schema([
                                        Placeholder::make('pending_payment_warning')
                                            ->label('')
                                            ->content(fn() => new HtmlString('<div class="text-warning-500 font-semibold">' . __('filament-modular-subscriptions::fms.resources.payment.pending_payment_warning') . '</div>'))
                                            ->columnSpan('full')
                                            ->visible(fn($record) => $record->payments()->where('status', PaymentStatus::PENDING)->exists()),

                                        ToggleButtons::make('payment_method')
                                            ->label(__('filament-modular-subscriptions::fms.resources.payment.payment_method'))
                                            ->required()
                                            ->inline()
                                            ->options([
                                                'online' => __('filament-modular-subscriptions::fms.resources.payment.methods.online'),
                                                'local' => __('filament-modular-subscriptions::fms.resources.payment.methods.local'),
                                            ])
                                            ->default('local')
                                            ->icons([
                                                'local' => 'heroicon-o-banknotes',
                                                'online' => 'heroicon-o-credit-card',
                                            ])
                                            ->colors([
                                                'local' => 'warning',
                                                'online' => 'success',
                                            ])
                                    ])
                            ]),

                        Step::make('payment_details')
                            ->label(__('filament-modular-subscriptions::fms.resources.payment.payment_details'))
                            ->description(__('filament-modular-subscriptions::fms.resources.payment.enter_details'))
                            ->schema(function ($get) {
                                if ($get('payment_method') === 'online') {
                                    return [
                                        Fieldset::make('payment_details')
                                            ->label('')
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('amount')
                                                            ->default(fn($record) => $record->remaining_amount)
                                                            ->disabled()
                                                            ->numeric()
                                                            ->required()
                                                            ->suffix(fn($record) => $record->subscription->plan->currency)
                                                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount'))
                                                            ->extraAttributes(['class' => 'text-lg font-semibold']),

                                                        ToggleButtons::make('payment_provider')
                                                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.provider'))
                                                            ->options([
                                                                'stripe' => 'Credit Card (Stripe)',
                                                                'paypal' => 'PayPal',
                                                            ])
                                                            ->default('stripe')
                                                            ->colors([
                                                                'stripe' => 'success',
                                                                'paypal' => 'info',
                                                            ])
                                                            ->icons([
                                                                'stripe' => 'heroicon-o-credit-card',
                                                                'paypal' => 'heroicon-o-currency-dollar'
                                                            ])
                                                            ->required()
                                                            ->inline()
                                                            ->live(),
                                                    ]),

                                                // Credit Card Details Section
                                                Fieldset::make('credit_card_details')
                                                    ->label('')
                                                    ->schema([
                                                        TextInput::make('card_number')
                                                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.card_number'))
                                                            ->placeholder('4242 4242 4242 4242')
                                                            ->mask('9999 9999 9999 9999')
                                                            ->prefixIcon('heroicon-o-credit-card')
                                                            ->extraAttributes(['class' => 'font-mono']),

                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('expiry')
                                                                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.expiry'))
                                                                    ->placeholder('MM/YY')
                                                                    ->mask('99/99')
                                                                    ->prefixIcon('heroicon-o-calendar'),

                                                                TextInput::make('cvc')
                                                                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.cvc'))
                                                                    ->placeholder('123')
                                                                    ->mask('999')
                                                                    ->prefixIcon('heroicon-o-lock-closed')
                                                                    ->password(),
                                                            ]),
                                                    ])
                                                    ->visible(fn($get) => $get('payment_provider') === 'stripe'),

                                                //@todo : use Filament Shout
                                                Placeholder::make('paypal_message')
                                                    ->label('')
                                                    ->visible(fn($get) => $get('payment_provider') === 'paypal')
                                                    ->columnSpanFull()
                                                    ->content(new HtmlString('
                                                                <div class="p-6 space-y-4 bg-gradient-to-br from-primary-50 to-primary-100 rounded-xl border border-primary-200 shadow-sm">
                                                                    <div class="flex items-center gap-4">
                                                                        <div class="flex-shrink-0">
                                                                            <svg class="w-10 h-10 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                            </svg>
                                                                        </div>
                                                                        <div class="flex flex-col gap-2">
                                                                            <span class="text-xl font-semibold text-primary-900">
                                                                                ' . __('filament-modular-subscriptions::fms.resources.payment.paypal_message') . '
                                                                            </span>
                                                                            <span class="text-sm text-primary-700">
                                                                                ' . __('filament-modular-subscriptions::fms.resources.payment.redirect_message') . '
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            '))
                                            ]),

                                    ];
                                }

                                // Bank transfer form
                                return [

                                    Placeholder::make('bank_card')
                                        ->label('')
                                        ->content(fn($record) => view('filament-modular-subscriptions::filament.components.bank-card'))
                                        ->columnSpanFull(),
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
                                        ->maxSize(5120)
                                        ->directory('payment-receipts')
                                        ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.receipt_file'))
                                        ->helperText(__('filament-modular-subscriptions::fms.resources.payment.receipt_help_text')),
                                    TextInput::make('notes')
                                        ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.notes')),
                                ];
                            })
                    ])
                    ->action(function (array $data, $record) {
                        if ($data['payment_method'] === 'online') {
                            Notification::make()
                                ->warning()
                                ->title(__('filament-modular-subscriptions::fms.resources.payment.online_payment_coming_soon'))
                                ->body(__('filament-modular-subscriptions::fms.resources.payment.please_use_bank_transfer'))
                                ->persistent()
                                ->send();
                            return;
                        }

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
                        $subscribable = $record->subscription->subscribable;
                        // Notify super admins about new pending payment
                        $subscribable->notifySuperAdmins('payment_pending', [
                            'amount' => $data['amount'],
                            'currency' => $record->subscription->plan->currency,
                            'invoice_id' => $record->id,
                            'tenant' => $subscribable->name,
                            'date' => now()->format('Y-m-d H:i:s')
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
                            $schema[] = ComponentsFieldset::make($payment->created_at->translatedFormat('M d, Y'))
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

    public static function downloadAction(): Action
    {
        return   Action::make('download')
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
                    new \Salla\ZATCA\Tags\InvoiceTotalAmount($record->amount), // Total with tax
                    new \Salla\ZATCA\Tags\InvoiceTaxAmount($record->tax),
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
                $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
                $fontDirs = $defaultConfig['fontDir'];

                $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
                $fontData = $defaultFontConfig['fontdata'];

                $mpdf = new \Mpdf\Mpdf([
                    'fontDir' => array_merge($fontDirs, [
                        config('filament-modular-subscriptions.font_path'),
                    ]),
                    'fontdata' => $fontData + [
                        'dinnextltarabic-medium' => [
                            'R' => 'dinnextltarabic_medium_normal_ab9f5a2326967c69e338559eaff07d99.ttf',
                            'B' => 'DINNextLTArabic-Medium.ttf',
                            'I' => 'DINNextLTArabic-Medium.ttf',
                            'BI' => 'DINNextLTArabic-Medium.ttf',
                            'useOTL' => 0xFF,
                            'useKashida' => 75,
                            'unAGlyphs' => true,
                        ],
                    ],
                    'default_font' => 'dinnextltarabic-medium',
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'tempDir' => storage_path('app/pdf_fonts'),
                    'orientation' => 'P',
                    'margin_left' => 10,
                    'margin_right' => 10,
                    'margin_top' => 10,
                    'margin_bottom' => 10,
                    'direction' => 'rtl',
                    'autoScriptToLang' => true,
                    'autoLangToFont' => true,
                    'useSubstitutions' => true,
                    'biDirectional' => true,
                    'text_input_as_HTML' => true,
                ]);
                $mpdf->SetTitle('Invoice #' . $record->id);
                $mpdf->SetAuthor(config('filament-modular-subscriptions.company_name'));
                $mpdf->SetCreator(config('filament-modular-subscriptions.company_name'));
                $mpdf->SetDirectionality('rtl');
                $mpdf->SetFont('dinnextltarabic-medium', '', 14);
                $view = view('filament-modular-subscriptions::pages.invoice-pdf', $data);
                $html = $view->render();
                $mpdf->WriteHTML($html);

                try {
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
            });
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
