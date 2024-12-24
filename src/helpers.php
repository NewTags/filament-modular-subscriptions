<?php

use NewTags\FilamentModularSubscriptions\FmsPlugin;
use Illuminate\Support\Facades\Cache;
if (! function_exists('clear_fms_cache')) {
    function clear_fms_cache(): bool
    {
        try {
            FmsPlugin::getTenant()->clearFmsCache();

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }
}

if (! function_exists('clear_fms_module_cache')) {
    function clear_fms_module_cache(string $moduleClass): bool
    {
        try {
            Cache::forget(FmsPlugin::getTenant()->getCacheKey($moduleClass));

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }
}
