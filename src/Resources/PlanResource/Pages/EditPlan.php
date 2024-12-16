<?php

namespace NewTags\FilamentModularSubscriptions\Resources\PlanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use NewTags\FilamentModularSubscriptions\Resources\PlanResource;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn($record) => $record->subscriptions()->exists()),
        ];
    }

    public function afterSave(): void
    {
        $this->record->subscriptions->each(function ($subscription) {
            $subscription->load('subscribable');
            $subscription->subscribable->invalidateSubscriptionCache();
        });
    }
}
