<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;

use HoceineEl\FilamentModularSubscriptions\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
