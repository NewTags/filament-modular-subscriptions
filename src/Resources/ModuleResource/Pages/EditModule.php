<?php

namespace NewTags\FilamentModularSubscriptions\Resources\ModuleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use NewTags\FilamentModularSubscriptions\Resources\ModuleResource;

class EditModule extends EditRecord
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn($record) => $record->whereHas('plans')->count() === 0),
        ];
    }
}
