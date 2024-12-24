<?php

namespace NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use NewTags\FilamentModularSubscriptions\Resources\SubscriptionResource;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(function ($record) {
                    $record->subscribable->clearFmsCache();
                }),
        ];
    }

    public function afterSave(): void
    {
        $this->record->loadMissing('subscribable');
        $this->getRecord()->subscribable->clearFmsCache();
    }
}
