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
                    $record->subscribable->invalidateSubscriptionCache();
                }),
        ];
    }

    public function afterSave(): void
    {
        $this->record->load('subscribable');
        $this->getRecord()->subscribable->invalidateSubscriptionCache();
    }
}
