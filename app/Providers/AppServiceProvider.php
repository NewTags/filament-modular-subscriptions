<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Increase max execution time
        ini_set('max_execution_time', env('MAX_EXECUTION_TIME', 60));

        // Optional: Increase memory limit if needed
        ini_set('memory_limit', '256M');

        mb_internal_encoding('UTF-8');
    }
}
