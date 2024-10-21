<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Filament\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use HoceineEl\FilamentModularSubscriptions\Models\Invoice;

class InvoiceDetails extends Page
{
    use InteractsWithRecord;

    public ?Invoice $invoice = null;

    protected static string $view = 'filament-modular-subscriptions::pages.invoice-details';

    public function mount($record): void
    {
        $this->invoice = $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return __('filament-modular-subscriptions::modular-subscriptions.invoice.details_title', ['number' => $this->invoice->id]);
    }
}
