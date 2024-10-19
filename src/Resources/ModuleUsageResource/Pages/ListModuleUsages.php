<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource\Pages;

use HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

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
