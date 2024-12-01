<?php

namespace HoceineEl\FilamentModularSubscriptions\View\Components;

use Illuminate\View\Component;

class SubscriptionAlerts extends Component
{
    public $alerts = [];

    public function __construct(array $alerts)
    {
        $this->alerts = $alerts;
    }

    public function render()
    {
        return view('filament-modular-subscriptions::components.subscription-alerts');
    }
}
