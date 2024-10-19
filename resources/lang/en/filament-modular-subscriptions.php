<?php

return [
    'resources' => [
        'plan' => [
            'name' => 'Plan',
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
            ],
            'tabs' => [
                'details' => 'Details',
                'billing' => 'Billing',
                'usage' => 'Usage',
            ],
        ],
        'subscription' => [
            'name' => 'Subscription',
            'fields' => [
                'plan_id' => 'Plan',
                'subscribable_type' => 'Subscribable Type',
                'subscribable_id' => 'Subscribable ID',
                'starts_at' => 'Starts At',
                'ends_at' => 'Ends At',
                'trial_ends_at' => 'Trial Ends At',
                'status' => 'Status',
            ],
        ],
        'module' => [
            'name' => 'Module',
            'fields' => [
                'name' => 'Name',
                'class' => 'Class',
                'is_active' => 'Is Active',
            ],
        ],
        'module_usage' => [
            'name' => 'Module Usage',
            'fields' => [
                'subscription_id' => 'Subscription',
                'module_id' => 'Module',
                'usage' => 'Usage',
                'pricing' => 'Pricing',
                'calculated_at' => 'Calculated At',
            ],
        ],
    ],
];
