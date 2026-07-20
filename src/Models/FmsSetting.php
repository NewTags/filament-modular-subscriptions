<?php

namespace NewTags\FilamentModularSubscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-level key/value settings for the subscriptions engine (super admin
 * scope, not tenant scoped). Values are cached forever and busted on write.
 */
class FmsSetting extends Model
{
    public const AUTO_INVOICE_GENERATION = 'auto_invoice_generation';

    protected $fillable = ['key', 'value'];

    public function getTable()
    {
        return config('filament-modular-subscriptions.tables.setting', 'fms_settings');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            "fms_setting.{$key}",
            fn () => static::query()->where('key', $key)->value('value') ?? $default,
        );
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("fms_setting.{$key}");
    }

    public static function autoInvoiceGenerationEnabled(): bool
    {
        return filter_var(static::get(self::AUTO_INVOICE_GENERATION, false), FILTER_VALIDATE_BOOL);
    }
}
