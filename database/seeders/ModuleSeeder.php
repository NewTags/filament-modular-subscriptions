<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

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
