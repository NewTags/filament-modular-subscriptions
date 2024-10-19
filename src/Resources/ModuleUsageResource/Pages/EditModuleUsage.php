<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource\Pages;

use HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditModuleUsage extends EditRecord
{
    protected static string $resource = ModuleUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
