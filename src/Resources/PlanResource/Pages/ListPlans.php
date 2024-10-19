<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\PlanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use HoceineEl\FilamentModularSubscriptions\Resources\PlanResource;

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
