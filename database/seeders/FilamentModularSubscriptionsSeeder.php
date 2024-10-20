<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\FilamentModularSubscriptions\PlanSeeder;
use Database\Seeders\FilamentModularSubscriptions\ModuleSeeder;
use Database\Seeders\FilamentModularSubscriptions\SubscriptionSeeder;

class FilamentModularSubscriptionsSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            ModuleSeeder::class,
            PlanSeeder::class,
            SubscriptionSeeder::class,
        ]);
    }
}
