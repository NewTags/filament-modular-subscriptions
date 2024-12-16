<?php

namespace NewTags\FilamentModularSubscriptions;

class ResolvesCustomerInfo
{
    public static function take($tenant): array
    {
        // Check if custom resolver is defined
        $resolver = config('filament-modular-subscriptions.tenant_data_resolver');
        if ($resolver && is_callable($resolver)) {
            return call_user_func($resolver, $tenant);
        }

        // Get field mappings from config
        $fields = config('filament-modular-subscriptions.tenant_fields', [
            'name' => 'name',
            'address' => 'address',
            'vat_number' => 'vat_number',
            'email' => 'email',
        ]);

        // Resolve each field using dot notation
        return [
            'name' => self::resolveField($tenant, $fields['name']),
            'customerInfo' => [
                'address' => self::resolveField($tenant, $fields['address']),
                'vat_no' => self::resolveField($tenant, $fields['vat_number']),
                'email' => self::resolveField($tenant, $fields['email']),
            ],
        ];
    }

    protected static function resolveField($model, string $field)
    {
        return data_get($model, $field, '');
    }
}
