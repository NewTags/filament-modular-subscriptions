<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\PlanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use HoceineEl\FilamentModularSubscriptions\Resources\PlanResource;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
