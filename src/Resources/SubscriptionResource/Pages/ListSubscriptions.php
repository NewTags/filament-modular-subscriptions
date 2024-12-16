<?php

namespace NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
