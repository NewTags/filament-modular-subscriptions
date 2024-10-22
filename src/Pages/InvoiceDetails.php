<?php

namespace HoceineEl\FilamentModularSubscriptions\Pages;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
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

    public function getDownloadAction(): Action
    {
        return Action::make('download')
            ->label(__('filament-modular-subscriptions::modular-subscriptions.invoice.download_pdf'))
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function ($arguments) {
                $invoice = $arguments['invoice'];
                $pdf = Pdf::loadView('filament-modular-subscriptions::pages.invoice-pdf', compact('invoice'));

                return response()->streamDownload(function () use ($pdf) {
                    echo $pdf->output();
                }, "invoice-{$invoice->id}.pdf");
            });
    }
}
