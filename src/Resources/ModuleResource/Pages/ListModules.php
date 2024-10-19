<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\ModuleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use HoceineEl\FilamentModularSubscriptions\Resources\ModuleResource;

class ListModules extends ListRecords
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
