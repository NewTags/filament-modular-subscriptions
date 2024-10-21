<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use HoceineEl\FilamentModularSubscriptions\Resources\InvoiceResource;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
