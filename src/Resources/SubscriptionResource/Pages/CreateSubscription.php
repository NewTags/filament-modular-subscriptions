<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource;
use HoceineEl\FilamentModularSubscriptions\Services\InvoiceService;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function afterCreate(): void
    {
        // Generate invoice for the new subscription
        $invoiceService = app(InvoiceService::class);
        $invoiceService->generateInvoice($this->record);
    }
}
