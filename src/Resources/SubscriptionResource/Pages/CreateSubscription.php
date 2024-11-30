<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource;
use HoceineEl\FilamentModularSubscriptions\Services\InvoiceService;
use Illuminate\Database\Eloquent\Model;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['subscribable_type'] = config('filament-modular-subscriptions.tenant_model');

        $record = new ($this->getModel())($data);



        $record->save();


        

        return $record;
    }
    protected function afterCreate(): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoiceService->generateInvoice($this->record);
    }
}
