<?php

namespace HoceineEl\LaravelModularSubscriptions\Models;

use HoceineEl\LaravelModularSubscriptions\Modules\BaseModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = ['name', 'class', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function moduleUsages(): HasMany
    {
        return $this->hasMany(config('filament-modular-subscriptions.models.usage'));
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public static function registerModule(string $moduleClass): self
    {
        $module = new $moduleClass();

        return self::updateOrCreate(
            ['name' => $module->getName()],
            [
                'class' => $moduleClass,
                'is_active' => true,
            ]
        );
    }

    public function getInstance(): BaseModule
    {
        return new $this->class;
    }
}
