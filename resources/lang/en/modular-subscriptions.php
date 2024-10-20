<?php

return [
    'resources' => [
        'plan' => [
            'name' => 'Plan',
            'singular_name' => 'Plan',
            'fields' => [
                'name' => 'Name',
                'slug' => 'Slug',
                'description' => 'Description',
                'is_active' => 'Is Active',
                'price' => 'Price',
                'currency' => 'Currency',
                'trial_period' => 'Trial Period',
                'trial_interval' => 'Trial Interval',
                'invoice_period' => 'Invoice Period',
                'invoice_interval' => 'Invoice Interval',
                'grace_period' => 'Grace Period',
                'grace_interval' => 'Grace Interval',
                'module' => 'Module',
                'module_limit' => 'Usage Limit',
                'module_settings' => 'Module Settings',
                'setting_key' => 'Setting Key',
                'setting_value' => 'Setting Value',
                'modules_count' => 'Modules Count',
            ],
            'hints' => [
                'module_limit' => 'Leave empty for unlimited usage',
            ],
            'placeholders' => [
                'setting_key' => 'Enter setting key',
                'setting_value' => 'Enter setting value',
            ],
            'actions' => [
                'add_module' => 'Add Module',
                'collapse_all_modules' => 'Collapse All Modules',
            ],
            'tabs' => [
                'details' => 'Details',
                'billing' => 'Billing',
                'usage' => 'Usage',
            ],
        ],
        'subscription' => [
            'name' => 'Subscription',
            'singular_name' => 'Subscription',
            'fields' => [
                'plan_id' => 'Plan',
                'subscribable_type' => 'Subscriber Type',
                'subscribable_id' => 'Subscriber',
                'starts_at' => 'Starts At',
                'ends_at' => 'Ends At',
                'trial_ends_at' => 'Trial Ends At',
                'status' => 'Status',
            ],
        ],
        'module' => [
            'name' => 'Module',
            'singular_name' => 'Module',
            'fields' => [
                'name' => 'Name',
                'class' => 'Class',
                'is_active' => 'Is Active',
            ],
        ],
        'module_usage' => [
            'name' => 'Module Usage',
            'singular_name' => 'Module Usage',
            'fields' => [
                'subscription_id' => 'Subscription',
                'module_id' => 'Module',
                'usage' => 'Usage',
                'pricing' => 'Pricing',
                'calculated_at' => 'Calculated At',
            ],
        ],
    ],
    'menu_group' => [
        'subscription_management' => 'Subscription Management',
    ],
    'interval' => [
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'year' => 'Year',
    ],
    'status' => [
        'active' => 'Active',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'pending' => 'Pending',
    ],
];
