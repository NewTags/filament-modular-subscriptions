<?php

use NewTags\FilamentModularSubscriptions\FmsPlugin;
use Illuminate\Support\Facades\Cache;
if (! function_exists('clear_fms_cache')) {
    function clear_fms_cache(): bool
    {
        try {
            $tenant = FmsPlugin::getTenant();

            if (! $tenant) {
                return false;
            }

            $tenant->clearFmsCache();

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}

if (! function_exists('clear_fms_module_cache')) {
    function clear_fms_module_cache(string $moduleClass): bool
    {
        try {
            $tenant = FmsPlugin::getTenant();

            if (! $tenant) {
                return false;
            }

            Cache::forget($tenant->getCacheKey($moduleClass));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
