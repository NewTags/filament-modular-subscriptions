<?php

namespace NewTags\FilamentModularSubscriptions\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use NewTags\FilamentModularSubscriptions\FmsPlugin;
use NewTags\FilamentModularSubscriptions\Resources\ModuleUsageResource;

class ModuleUsageWidget extends BaseWidget
{

    protected static ?int $sort = 10;
    protected static ?string $pollingInterval = null;
    protected int | string | array $columnSpan = 'full';
    public static function canView(): bool
    {
        return config('filament-modular-subscriptions.widgets.enable_module_usage', false);
    }
    public function table(Table $table): Table
    {
        return (new ModuleUsageResource)->table($table)
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->searchable(false)
            ->query(
                config('filament-modular-subscriptions.models.usage')::query()
                    ->where('subscription_id', FmsPlugin::getTenant()->subscription?->id)
                    ->with(['module', 'subscription.plan'])
            );
    }
}
