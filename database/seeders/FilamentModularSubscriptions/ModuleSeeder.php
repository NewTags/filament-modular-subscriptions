<?php

namespace Database\Seeders\FilamentModularSubscriptions;

use Illuminate\Database\Seeder;
use HoceineEl\FilamentModularSubscriptions\Models\Module;

class ModuleSeeder extends Seeder
{
    public function run()
    {
        $modules = config('filament-modular-subscriptions.modules', []);
        $moduleModel = config('filament-modular-subscriptions.models.module');
        foreach ($modules as $moduleClass) {
            $moduleModel::registerModule($moduleClass);
        }
    }
}
