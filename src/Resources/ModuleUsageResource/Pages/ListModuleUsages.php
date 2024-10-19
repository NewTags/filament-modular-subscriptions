<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource;

class ListModuleUsages extends ListRecords
{
    protected static string $resource = ModuleUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
