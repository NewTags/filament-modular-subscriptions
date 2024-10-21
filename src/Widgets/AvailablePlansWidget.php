<?php

namespace HoceineEl\FilamentModularSubscriptions\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class AvailablePlansWidget extends Widget
{
    protected static string $view = 'filament-modular-subscriptions::widgets.available-plans-widget';

    public function getPlans(): Collection
    {
        $planModel = config('filament-modular-subscriptions.models.plan');

        return $planModel::active()->orderBy('sort_order')->get();
    }
}
