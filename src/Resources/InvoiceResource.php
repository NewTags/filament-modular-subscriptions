<?php

namespace NewTags\FilamentModularSubscriptions\Resources;

use ArPHP\I18N\Arabic;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Fieldset as ComponentsFieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use NewTags\FilamentModularSubscriptions\Components\PeriodBonusFields;
use NewTags\FilamentModularSubscriptions\Enums\InvoiceStatus;
use NewTags\FilamentModularSubscriptions\Enums\PaymentMethod;
use NewTags\FilamentModularSubscriptions\Enums\PaymentStatus;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Models\FmsSetting;
use NewTags\FilamentModularSubscriptions\Pages\TenantSubscription;
use NewTags\FilamentModularSubscriptions\Payments\CheckoutService;
use NewTags\FilamentModularSubscriptions\Payments\Exceptions\CheckoutException;
use NewTags\FilamentModularSubscriptions\Payments\PaymentGatewayManager;
use NewTags\FilamentModularSubscriptions\ResolvesCustomerInfo;
use NewTags\FilamentModularSubscriptions\Resources\InvoiceResource\Pages;
use NewTags\FilamentModularSubscriptions\Services\InvoiceService;
use Salla\ZATCA\GenerateQrCode;
use Salla\ZATCA\Tags\InvoiceDate;
use Salla\ZATCA\Tags\InvoiceTaxAmount;
use Salla\ZATCA\Tags\InvoiceTotalAmount;
use Salla\ZATCA\Tags\Seller;
use Salla\ZATCA\Tags\TaxNumber;

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
        $currency = config('filament-modular-subscriptions.main_currency');

        return $table
            ->modifyQueryUsing(function ($query) {
                // Scope to a tenant only on tenant panels; the admin panel must list every invoice even if the host app resolves a non-null placeholder tenant.
                if (FmsPlugin::get()->isOnTenantPanel()) {
                    $tenant = FmsPlugin::getTenant();
                    if ($tenant) {
                        $query->where('tenant_id', $tenant->id);
                    }
                }

                return $query->with([
                    'subscription.subscriber',
                    'subscription.plan',
                    'items',
                    'tenant',
                ]);
            })
            ->defaultSort('created_at', 'desc')
            ->columns([

                Tables\Columns\TextColumn::make('number')
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.number'))
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->sortable(['id']),

                Tables\Columns\TextColumn::make('subscription.subscriber.name')
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.subscription_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subtotal')
                    ->prefix($currency)
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.subtotal'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax')
                    ->prefix($currency)
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.fields.tax'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->prefix($currency)
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
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
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
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
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
                    ->modalHeading(fn ($record) => __('filament-modular-subscriptions::fms.invoice.details_title', ['number' => $record->number]))
                    ->modalContent(function ($record) {
                        $invoice = $record->loadMissing(['items', 'subscription.plan']);

                        return View::make('filament-modular-subscriptions::pages.invoice-details', compact('invoice'));
                    })
                    ->modalFooterActions([
                        self::downloadAction(),
                    ]),
                Action::make('pay')
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.actions.pay'))
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->modalHeading(fn ($record) => __('filament-modular-subscriptions::fms.invoice.pay_invoice_heading', ['number' => $record->number]))
                    ->modalDescription(__('filament-modular-subscriptions::fms.resources.payment.choose_method'))
                    ->modalSubmitActionLabel(__('filament-modular-subscriptions::fms.payments.proceed_to_payment'))
                    ->modalIcon('heroicon-o-credit-card')
                    ->visible(
                        function ($record) {
                            return FmsPlugin::get()->isOnTenantPanel()
                                && in_array(
                                    $record->status,
                                    [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE]
                                );
                        }
                    )
                    ->fillForm(fn ($record): array => self::paymentFormDefaults($record))
                    ->form(fn ($record): array => self::paymentFormSchema($record))
                    ->action(fn (array $data, $record, $livewire) => self::handlePayment($data, $record, $livewire)),
                Tables\Actions\ActionGroup::make([
                    self::downloadAction(),
                    self::payInvoiceAction(),
                    self::copyPaymentLinkAction(),
                    Action::make('view_payments')
                        ->label(__('filament-modular-subscriptions::fms.invoice.view_payments'))
                        ->icon('heroicon-o-eye')
                        ->slideOver()
                        ->modalWidth('5xl')
                        ->visible(fn ($record) => $record->payments()->exists())
                        ->infolist(function ($record) {
                            $payments = $record->payments;
                            $schema = [];

                            foreach ($payments as $payment) {
                                $schema[] = ComponentsFieldset::make($payment->created_at->translatedFormat('M d, Y'))
                                    ->schema([
                                        TextEntry::make('amount')
                                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount'))
                                            ->prefix(fn ($record) => config('filament-modular-subscriptions.main_currency'))
                                            ->getStateUsing(fn ($record) => $record->amount),
                                        TextEntry::make('payment_method')
                                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.payment_method'))
                                            ->badge()
                                            ->getStateUsing(fn ($record) => $record->payment_method),
                                        TextEntry::make('status')
                                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.status'))
                                            ->badge()
                                            ->getStateUsing(fn ($record) => $record->status),
                                        TextEntry::make('created_at')
                                            ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.created_at'))
                                            ->getStateUsing(fn ($record) => $record->created_at->translatedFormat('M d, Y')),
                                    ]);
                            }

                            return $schema;
                        }),
                ]),
            ])
            ->headerActions([
                self::autoInvoiceGenerationToggleAction(),
                self::createManualInvoiceAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('delete_unpaid')
                    ->label(__('filament-modular-subscriptions::fms.resources.invoice.actions.delete_unpaid'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-modular-subscriptions::fms.resources.invoice.actions.delete_unpaid'))
                    ->modalDescription(__('filament-modular-subscriptions::fms.resources.invoice.delete_unpaid_warning'))
                    ->visible(fn (): bool => ! FmsPlugin::get()->isOnTenantPanel())
                    ->action(function (Collection $records): void {
                        $deleted = 0;
                        $skipped = 0;

                        DB::transaction(function () use ($records, &$deleted, &$skipped): void {
                            foreach ($records as $invoice) {
                                if (! $invoice->isDeletable()) {
                                    $skipped++;

                                    continue;
                                }

                                $invoice->payments()->delete();
                                $invoice->items()->delete();
                                $invoice->delete();
                                $deleted++;
                            }
                        });

                        Notification::make()
                            ->title(__('filament-modular-subscriptions::fms.resources.invoice.invoices_deleted', ['count' => $deleted]))
                            ->body($skipped > 0 ? __('filament-modular-subscriptions::fms.resources.invoice.invoices_skipped', ['count' => $skipped]) : null)
                            ->{$deleted > 0 ? 'success' : 'warning'}()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * Default form state for the unified pay action (shared by the invoice table action
     * and the tenant subscription page "pay now" buttons).
     *
     * @return array{payment_method: string, gateway: string, amount: float}
     */
    public static function paymentFormDefaults($invoice): array
    {
        $manager = app(PaymentGatewayManager::class);
        $onlineAvailable = config('filament-modular-subscriptions.online_payment_enabled', false) && $manager->hasEnabled();

        return [
            'payment_method' => $onlineAvailable ? 'online' : 'local',
            'gateway' => (string) $manager->default(),
            'amount' => (float) $invoice->remaining_amount,
        ];
    }

    /**
     * Single-step pay form: a method picker that reactively reveals the secure online
     * checkout summary or the bank-transfer details for the given invoice.
     *
     * @return array<int, Component>
     */
    public static function paymentFormSchema($invoice): array
    {
        $manager = app(PaymentGatewayManager::class);
        $onlineAvailable = config('filament-modular-subscriptions.online_payment_enabled', false) && $manager->hasEnabled();
        $gatewayOptions = $manager->enabledOptions();

        return [
            Placeholder::make('pending_payment_warning')
                ->label('')
                ->content(fn () => new HtmlString('<div class="rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-400/30 dark:bg-warning-500/10 dark:text-warning-300">' . e(__('filament-modular-subscriptions::fms.resources.payment.pending_payment_warning')) . '</div>'))
                ->columnSpanFull()
                ->visible(fn () => $invoice->payments()->where('status', PaymentStatus::PENDING)->exists()),

            ToggleButtons::make('payment_method')
                ->label(__('filament-modular-subscriptions::fms.resources.payment.choose_method'))
                ->required()
                ->live()
                ->inline()
                ->options(array_filter([
                    'online' => $onlineAvailable
                        ? __('filament-modular-subscriptions::fms.resources.payment.methods.online')
                        : null,
                    'local' => __('filament-modular-subscriptions::fms.resources.payment.methods.local'),
                ]))
                ->icons([
                    'local' => 'heroicon-o-banknotes',
                    'online' => 'heroicon-o-credit-card',
                ])
                ->colors([
                    'local' => 'gray',
                    'online' => 'success',
                ])
                ->columnSpanFull(),

            Group::make([
                Placeholder::make('checkout_summary')
                    ->label('')
                    ->content(fn () => view('filament-modular-subscriptions::filament.components.online-checkout-summary', ['invoice' => $invoice]))
                    ->columnSpanFull(),

                count($gatewayOptions) > 1
                    ? ToggleButtons::make('gateway')
                        ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.gateway'))
                        ->options($gatewayOptions)
                        ->default($manager->default())
                        ->required()
                        ->inline()
                        ->icons(array_map(fn () => 'heroicon-o-credit-card', $gatewayOptions))
                        ->colors(array_map(fn () => 'success', $gatewayOptions))
                        ->columnSpanFull()
                    : Hidden::make('gateway')->default($manager->default()),
            ])
                ->visible(fn (Get $get): bool => $get('payment_method') === 'online')
                ->columnSpanFull(),

            Group::make([
                Placeholder::make('bank_card')
                    ->label('')
                    ->content(fn () => view('filament-modular-subscriptions::filament.components.bank-card'))
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->suffix(fn () => config('filament-modular-subscriptions.main_currency'))
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount'))
                    ->maxValue(fn () => (float) $invoice->remaining_amount)
                    ->minValue(0.01)
                    ->step(0.01)
                    ->columnSpanFull(),
                FileUpload::make('receipt_file')
                    ->required()
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'application/pdf'])
                    ->disk(config('filament-modular-subscriptions.receipts_disk', 'local'))
                    ->visibility('private')
                    ->directory('payment-receipts')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.receipt_file'))
                    ->helperText(__('filament-modular-subscriptions::fms.resources.payment.receipt_help_text'))
                    ->columnSpanFull(),
                TextInput::make('notes')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.notes'))
                    ->columnSpanFull(),
            ])
                ->visible(fn (Get $get): bool => $get('payment_method') === 'local')
                ->columnSpanFull(),
        ];
    }

    /**
     * Shared handler for the unified pay action: starts an online checkout (and
     * redirects to the gateway) or records a pending bank-transfer payment.
     */
    public static function handlePayment(array $data, $invoice, $livewire): void
    {
        if (($data['payment_method'] ?? null) === 'online') {
            $gateway = (string) ($data['gateway'] ?? '');
            if ($gateway === '') {
                $gateway = (string) app(PaymentGatewayManager::class)->default();
            }

            try {
                $checkout = app(CheckoutService::class)->createForInvoice(
                    invoice: $invoice,
                    gateway: $gateway,
                    returnUrl: TenantSubscription::getUrl(),
                    initiator: auth()->user(),
                    source: 'panel',
                );
            } catch (CheckoutException $exception) {
                Notification::make()
                    ->danger()
                    ->title(__('filament-modular-subscriptions::fms.payments.checkout_failed_title'))
                    ->body($exception->getMessage())
                    ->persistent()
                    ->send();

                return;
            }

            $livewire->redirect($checkout->checkout_url);

            return;
        }

        $invoice->payments()->create([
            'amount' => $data['amount'],
            'receipt_file' => $data['receipt_file'],
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'status' => PaymentStatus::PENDING,
            'transaction_id' => 'PAY-' . (string) Str::uuid(),
            'metadata' => [
                'notes' => $data['notes'] ?? null,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ],
        ]);

        $subscribable = $invoice->subscription->subscribable;
        $subscribable->notifySuperAdmins('payment_pending', [
            'amount' => $data['amount'],
            'currency' => config('filament-modular-subscriptions.main_currency'),
            'invoice_id' => $invoice->id,
            'tenant' => $subscribable->name,
            'date' => now()->format('Y-m-d H:i:s'),
        ]);

        Notification::make()
            ->title(__('filament-modular-subscriptions::fms.invoice.payment_pending'))
            ->success()
            ->send();
    }

    /**
     * Shareable online-payment link for an invoice: a temporary signed URL that
     * starts a hosted checkout without requiring a panel login. Useful for
     * sending to the academy over WhatsApp or email.
     */
    public static function copyPaymentLinkAction(): Action
    {
        return Action::make('payment_link')
            ->label(__('filament-modular-subscriptions::fms.payments.payment_link'))
            ->icon('heroicon-o-link')
            ->color('info')
            ->visible(fn ($record): bool => config('filament-modular-subscriptions.online_payment_enabled', false)
                && app(PaymentGatewayManager::class)->hasEnabled()
                && in_array($record->status, [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE], true))
            ->modalHeading(__('filament-modular-subscriptions::fms.payments.payment_link'))
            ->modalDescription(fn ($record): string => __('filament-modular-subscriptions::fms.payments.payment_link_help', [
                'id' => $record->number,
                'days' => (int) config('filament-modular-subscriptions.payments.link_ttl_days', 30),
            ]))
            ->modalIcon('heroicon-o-link')
            ->modalWidth('lg')
            ->infolist(fn ($record) => [
                TextEntry::make('payment_link')
                    ->label(__('filament-modular-subscriptions::fms.payments.click_to_copy'))
                    ->state(self::paymentLinkFor($record))
                    ->copyable()
                    ->copyMessage(__('filament-modular-subscriptions::fms.payments.link_copied'))
                    ->copyMessageDuration(2000)
                    ->icon('heroicon-o-clipboard-document')
                    ->iconColor('info')
                    ->extraAttributes(['class' => 'break-all [&_.fi-in-text-item]:font-mono [&_.fi-in-text-item]:text-xs [&_.fi-in-text-item]:cursor-pointer']),
            ])
            ->modalSubmitAction(false);
    }

    public static function paymentLinkFor($record): string
    {
        return URL::temporarySignedRoute(
            'fms.pay',
            now()->addDays((int) config('filament-modular-subscriptions.payments.link_ttl_days', 30)),
            ['invoice' => $record->getKey()],
        );
    }

    /**
     * Admin-side action: record a confirmed (offline) payment against an invoice and
     * immediately settle it — marking the invoice paid/partially paid and renewing the
     * subscription via the shared, idempotent InvoiceService::settleInvoice().
     */
    public static function payInvoiceAction(): Action
    {
        return Action::make('pay_invoice')
            ->label(__('filament-modular-subscriptions::fms.resources.invoice.actions.record_payment'))
            ->icon('heroicon-o-banknotes')
            ->color('gray')
            ->visible(fn ($record): bool => ! FmsPlugin::get()->isOnTenantPanel()
                && in_array($record->status, [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE], true))
            ->modalHeading(fn ($record): string => __('filament-modular-subscriptions::fms.resources.invoice.record_payment_heading', ['number' => $record->number]))
            ->modalWidth('xl')
            ->modalSubmitActionLabel(__('filament-modular-subscriptions::fms.resources.invoice.actions.record_payment'))
            ->fillForm(fn ($record): array => [
                'amount' => (float) $record->remaining_amount,
                'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            ])
            ->form([
                Placeholder::make('remaining_amount')
                    ->label(__('filament-modular-subscriptions::fms.invoice.remaining_amount'))
                    ->content(fn ($record): string => number_format((float) $record->remaining_amount, 2) . ' ' . config('filament-modular-subscriptions.main_currency')),
                TextInput::make('amount')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.amount'))
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(fn ($record): float => (float) $record->remaining_amount)
                    ->step(0.01)
                    ->suffix(config('filament-modular-subscriptions.main_currency')),
                Select::make('payment_method')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.payment_method'))
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => $method->getLabel()])->all())
                    ->default(PaymentMethod::BANK_TRANSFER->value)
                    ->required()
                    ->native(false),
                FileUpload::make('receipt_file')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.receipt_file'))
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'application/pdf'])
                    ->disk(config('filament-modular-subscriptions.receipts_disk', 'local'))
                    ->visibility('private')
                    ->directory('payment-receipts'),
                Textarea::make('notes')
                    ->label(__('filament-modular-subscriptions::fms.resources.payment.fields.notes'))
                    ->rows(2),
            ])
            ->action(function (array $data, $record): void {
                DB::transaction(function () use ($data, $record): void {
                    $payment = $record->payments()->create([
                        'amount' => round((float) $data['amount'], 2),
                        'payment_method' => $data['payment_method'],
                        'status' => PaymentStatus::PAID,
                        'receipt_file' => $data['receipt_file'] ?? null,
                        'transaction_id' => 'PAY-' . (string) Str::uuid(),
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->id(),
                        'admin_notes' => $data['notes'] ?? null,
                        'metadata' => [
                            'recorded_by' => auth()->id(),
                            'recorded_at' => now()->format('Y-m-d H:i:s'),
                            'notes' => $data['notes'] ?? null,
                        ],
                    ]);

                    app(InvoiceService::class)->settleInvoice($record, $payment);
                });

                Notification::make()
                    ->title(__('filament-modular-subscriptions::fms.resources.invoice.payment_recorded'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Super-admin switch for the scheduled invoice generator (fms:schedule-invoices):
     * when off, the daily run exits without billing anyone.
     */
    public static function autoInvoiceGenerationToggleAction(): Action
    {
        return Action::make('auto_invoice_generation')
            ->visible(fn (): bool => ! FmsPlugin::get()->isOnTenantPanel())
            ->label(fn (): string => FmsSetting::autoInvoiceGenerationEnabled()
                ? __('filament-modular-subscriptions::fms.invoice.auto_generation_enabled')
                : __('filament-modular-subscriptions::fms.invoice.auto_generation_disabled'))
            ->icon(fn (): string => FmsSetting::autoInvoiceGenerationEnabled() ? 'heroicon-o-bolt' : 'heroicon-o-bolt-slash')
            ->color(fn (): string => FmsSetting::autoInvoiceGenerationEnabled() ? 'success' : 'gray')
            ->requiresConfirmation()
            ->modalIcon(fn (): string => FmsSetting::autoInvoiceGenerationEnabled() ? 'heroicon-o-bolt-slash' : 'heroicon-o-bolt')
            ->modalHeading(fn (): string => FmsSetting::autoInvoiceGenerationEnabled()
                ? __('filament-modular-subscriptions::fms.invoice.auto_generation_disable_heading')
                : __('filament-modular-subscriptions::fms.invoice.auto_generation_enable_heading'))
            ->modalDescription(fn (): string => FmsSetting::autoInvoiceGenerationEnabled()
                ? __('filament-modular-subscriptions::fms.invoice.auto_generation_disable_description')
                : __('filament-modular-subscriptions::fms.invoice.auto_generation_enable_description'))
            ->action(function (): void {
                $enabled = ! FmsSetting::autoInvoiceGenerationEnabled();
                FmsSetting::set(FmsSetting::AUTO_INVOICE_GENERATION, $enabled ? '1' : '0');

                Notification::make()
                    ->success()
                    ->title($enabled
                        ? __('filament-modular-subscriptions::fms.invoice.auto_generation_enabled_notification')
                        : __('filament-modular-subscriptions::fms.invoice.auto_generation_disabled_notification'))
                    ->send();
            });
    }

    public static function createManualInvoiceAction(): Action
    {
        return Action::make('create_manual_invoice')
            ->label(__('filament-modular-subscriptions::fms.invoice.create_manual_invoice'))
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->modalWidth('4xl')
            ->modalHeading(__('filament-modular-subscriptions::fms.invoice.create_manual_invoice'))
            ->visible(fn (): bool => ! FmsPlugin::get()->isOnTenantPanel())
            ->form([
                Grid::make(2)->schema([
                    Select::make('tenant_id')
                        ->label(__('filament-modular-subscriptions::fms.invoice.subscriber'))
                        ->options(fn (): array => config('filament-modular-subscriptions.tenant_model')::query()
                            ->orderBy(config('filament-modular-subscriptions.tenant_attribute'))
                            ->pluck(config('filament-modular-subscriptions.tenant_attribute'), 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $latest = $state
                                ? config('filament-modular-subscriptions.models.subscription')::query()
                                    ->where('subscribable_id', $state)
                                    ->latest()
                                    ->first()
                                : null;

                            $set('subscription_id', $latest?->id);
                            self::fillManualInvoiceItems($latest?->id, $set);
                        }),
                    Select::make('subscription_id')
                        ->label(__('filament-modular-subscriptions::fms.invoice.subscription'))
                        ->options(function (Get $get): array {
                            if (! $get('tenant_id')) {
                                return [];
                            }

                            return config('filament-modular-subscriptions.models.subscription')::query()
                                ->where('subscribable_id', $get('tenant_id'))
                                ->with('plan')
                                ->latest()
                                ->get()
                                ->mapWithKeys(fn ($subscription): array => [
                                    $subscription->id => ($subscription->plan?->trans_name ?? __('filament-modular-subscriptions::fms.invoice.subscription')) . ' — #' . $subscription->id,
                                ])
                                ->all();
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set) => self::fillManualInvoiceItems($state, $set))
                        ->disabled(fn (Get $get): bool => ! $get('tenant_id'))
                        ->helperText(__('filament-modular-subscriptions::fms.invoice.manual_invoice_subscription_hint')),
                ]),
                Placeholder::make('usage_summary')
                    ->label(__('filament-modular-subscriptions::fms.invoice.current_usage'))
                    ->visible(fn (Get $get): bool => filled($get('subscription_id')))
                    ->content(function (Get $get): HtmlString {
                        $subscription = config('filament-modular-subscriptions.models.subscription')::query()
                            ->with('plan.modules')
                            ->find($get('subscription_id'));

                        if (! $subscription?->plan?->is_pay_as_you_go) {
                            return new HtmlString('<span class="text-sm text-gray-500">—</span>');
                        }

                        $rows = [];
                        foreach ($subscription->plan->modules as $module) {
                            $instance = $module->getInstance();
                            $rows[] = e($instance->getLabel()) . ': <strong>' . (int) $instance->calculateUsage($subscription) . '</strong>';
                        }

                        return new HtmlString(
                            $rows === []
                                ? '<span class="text-sm text-gray-500">—</span>'
                                : '<span class="text-sm">' . implode(' &nbsp;•&nbsp; ', $rows) . '</span>'
                        );
                    }),
                Repeater::make('items')
                    ->label(__('filament-modular-subscriptions::fms.invoice.items'))
                    ->schema([
                        TextInput::make('description')
                            ->label(__('filament-modular-subscriptions::fms.invoice.description'))
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('quantity')
                            ->label(__('filament-modular-subscriptions::fms.invoice.quantity'))
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('unit_price')
                            ->label(__('filament-modular-subscriptions::fms.invoice.unit_price'))
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->live(onBlur: true)
                            ->suffix(config('filament-modular-subscriptions.main_currency')),
                    ])
                    ->columns(4)
                    ->defaultItems(1)
                    ->minItems(1)
                    ->required()
                    ->addActionLabel(__('filament-modular-subscriptions::fms.invoice.add_item')),
                Section::make(__('filament-modular-subscriptions::fms.period_bonus.section_title'))
                    ->description(__('filament-modular-subscriptions::fms.period_bonus.invoice_section_description'))
                    ->icon('heroicon-o-gift')
                    ->compact()
                    ->visible(fn (Get $get): bool => filled($get('subscription_id')))
                    ->schema(PeriodBonusFields::make(
                        resolvePlan: fn (Get $get) => config('filament-modular-subscriptions.models.subscription')::query()
                            ->with('plan')
                            ->find($get('subscription_id'))
                            ?->plan,
                        resolveAmount: function (Get $get): float {
                            $subtotal = collect($get('items') ?? [])
                                ->sum(fn ($item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));

                            return round($subtotal * (1 + ((float) $get('tax_percentage')) / 100), 2);
                        },
                    )),
                Grid::make(2)->schema([
                    TextInput::make('tax_percentage')
                        ->label(__('filament-modular-subscriptions::fms.invoice.tax_percentage'))
                        ->numeric()
                        ->default(config('filament-modular-subscriptions.tax_percentage', 15))
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->live(onBlur: true)
                        ->required(),
                    DatePicker::make('due_date')
                        ->label(__('filament-modular-subscriptions::fms.invoice.due_date'))
                        ->default(now()->addDays(7))
                        ->required(),
                ]),
                Placeholder::make('manual_invoice_total')
                    ->label(__('filament-modular-subscriptions::fms.invoice.total_with_tax'))
                    ->content(function (Get $get): HtmlString {
                        $subtotal = collect($get('items') ?? [])
                            ->sum(fn ($item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));
                        $tax = round($subtotal * ((float) $get('tax_percentage') / 100), 2);
                        $currency = config('filament-modular-subscriptions.main_currency');

                        return new HtmlString(
                            '<span class="text-base font-semibold">' . number_format($subtotal + $tax, 2) . ' ' . e($currency) . '</span>'
                            . '<span class="text-gray-500 text-sm"> (' . __('filament-modular-subscriptions::fms.invoice.subtotal') . ': ' . number_format($subtotal, 2) . ' + ' . __('filament-modular-subscriptions::fms.invoice.vat') . ': ' . number_format($tax, 2) . ')</span>'
                        );
                    }),
            ])
            ->action(function (array $data): void {
                $subscription = config('filament-modular-subscriptions.models.subscription')::query()
                    ->whereKey($data['subscription_id'])
                    ->first();

                if (! $subscription) {
                    Notification::make()
                        ->danger()
                        ->title(__('filament-modular-subscriptions::fms.invoice.manual_invoice_subscription_required'))
                        ->send();

                    return;
                }

                $invoice = app(InvoiceService::class)->createManualInvoice(
                    $subscription,
                    $data['items'],
                    (float) $data['tax_percentage'],
                    filled($data['due_date']) ? Carbon::parse($data['due_date']) : null,
                    max(0, (int) ($data['bonus_days'] ?? 0)),
                );

                Notification::make()
                    ->success()
                    ->title(__('filament-modular-subscriptions::fms.invoice.manual_invoice_created', ['id' => $invoice->id]))
                    ->send();
            });
    }

    protected static function fillManualInvoiceItems($subscriptionId, Set $set): void
    {
        if (! $subscriptionId) {
            return;
        }

        $subscription = config('filament-modular-subscriptions.models.subscription')::query()
            ->with('plan.modules')
            ->find($subscriptionId);

        if (! $subscription) {
            return;
        }

        $items = app(InvoiceService::class)
            ->previewInvoiceItems($subscription);

        $set('items', $items !== [] ? $items : [['description' => null, 'quantity' => 1, 'unit_price' => null]]);
    }

    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label(__('filament-modular-subscriptions::fms.invoice.download_pdf'))
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function ($record) {
                // Calculate tax amounts correctly
                $taxPercentage = config('filament-modular-subscriptions.tax_percentage', 15);
                $totalBeforeTax = $record->subtotal;
                $taxAmount = $record->tax;

                // Generate QR code with correct amounts
                $QrCode = GenerateQrCode::fromArray([
                    new Seller(config('filament-modular-subscriptions.company_name')),
                    new TaxNumber(config('filament-modular-subscriptions.tax_number')),
                    new InvoiceDate($record->created_at->utc()->format('Y-m-d\TH:i:s\Z')),
                    new InvoiceTotalAmount(number_format((float) $record->amount, 2, '.', '')), // Total with tax
                    new InvoiceTaxAmount(number_format((float) $record->tax, 2, '.', '')),
                ])->render();

                // Resolve the company logo to a base64 data URI (config may hold an
                // absolute path or one relative to public/); embedding avoids mPDF path issues.
                $logoSource = (string) config('filament-modular-subscriptions.company_logo');
                $logoPath = is_file($logoSource) ? $logoSource : public_path($logoSource);
                $companyLogo = is_file($logoPath)
                    ? 'data:' . ((function_exists('mime_content_type') ? mime_content_type($logoPath) : null) ?: 'image/png') . ';base64,' . base64_encode((string) file_get_contents($logoPath))
                    : null;

                // Get view data with additional tax information
                $data = [
                    'invoice' => $record,
                    'QrCode' => $QrCode,
                    'user' => ResolvesCustomerInfo::take($record->tenant),
                    'company_logo' => $companyLogo,
                    'tax_percentage' => $taxPercentage,
                    'total_before_tax' => $totalBeforeTax,
                    'tax_amount' => $taxAmount,
                ];

                // Configure mPDF with better Arabic support
                $defaultConfig = (new ConfigVariables)->getDefaults();
                $fontDirs = $defaultConfig['fontDir'];

                $defaultFontConfig = (new FontVariables)->getDefaults();
                $fontData = $defaultFontConfig['fontdata'];

                $mpdf = new Mpdf([
                    'fontDir' => array_merge($fontDirs, [
                        config('filament-modular-subscriptions.font_path'),
                        __DIR__ . '/../../public/fonts/saudi_riyal',
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
                        'riyal' => [
                            'R' => 'saudi_riyal.ttf',
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
                $mpdf->SetTitle('Invoice ' . $record->number);
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
                    }, $record->number . '_' . $record->created_at->format('Y-m-d') . '.pdf');
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
