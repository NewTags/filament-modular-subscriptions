<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\PlanResource\Pages;

use HoceineEl\FilamentModularSubscriptions\Resources\PlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
