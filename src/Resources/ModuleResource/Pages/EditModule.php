<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources\ModuleResource\Pages;

use HoceineEl\FilamentModularSubscriptions\Resources\ModuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditModule extends EditRecord
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
