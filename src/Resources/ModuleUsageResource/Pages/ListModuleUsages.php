<?php

namespace NewTags\FilamentModularSubscriptions\Resources\ModuleUsageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use NewTags\FilamentModularSubscriptions\Resources\ModuleUsageResource;

class ListModuleUsages extends ListRecords
{
    protected static string $resource = ModuleUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
