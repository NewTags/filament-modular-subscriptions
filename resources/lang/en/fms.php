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
        'payment' => [
            'name' => 'Payments',
            'singular_name' => 'Payment',
            'fields' => [
                'invoice_id' => 'Invoice ID',
                'amount' => 'Amount',
                'payment_method' => 'Payment Method',
                'transaction_id' => 'Transaction ID',
                'status' => 'Status',
                'created_at' => 'Created At',
            ],
            'sections' => [
                'payment_details' => 'Payment Details',
            ],
            'select_method' => 'Select Payment Method',
            'select_method_description' => 'Choose your preferred payment method to complete the transaction.',
            'method' => 'Payment Method',
            'success' => 'Payment completed successfully!',
            'error' => 'Payment failed. Please try again.',
            'cancelled' => 'Payment was cancelled.',
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
        'paid' => 'Paid',
        'unpaid' => 'Unpaid',
        'partially_paid' => 'Partially Paid',
    ],
    'tenant_subscription' => [
        'your_subscription' => 'Your Subscription',
        'current_subscription' => 'Current Subscription',
        'plan' => 'Plan',
        'status' => 'Status',
        'started_on' => 'Started On',
        'ends_on' => 'Ends On',
        'subscription_details' => 'Subscription Details',
        'days_left' => 'Days Left',
        'on_trial' => 'On Trial',
        'yes' => 'Yes',
        'no' => 'No',
        'trial_ends_at' => 'Trial Ends At',
        'no_active_subscription' => 'No Active Subscription',
        'no_subscription_message' => 'You currently don\'t have an active subscription. Please choose a plan to subscribe.',
        'available_plans' => 'Available Plans',
        'per' => 'per',
        'switch_plan_button' => 'Switch Plan',
        'select_plan' => 'Select Plan',
        'plan_switched_successfully' => 'Plan switched successfully',
        'plan_switch_failed' => 'Failed to switch plan',
        'statuses' => [
            'active' => 'Active',
            'canceled' => 'Canceled',
            'expired' => 'Expired',
        ],
    ],
    'intervals' => [
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'year' => 'Year',
    ],
    'invoice' => [
        'number' => 'Invoice Number',
        'amount' => 'Amount',
        'status' => 'Status',
        'due_date' => 'Due Date',
        'view' => 'View',
        'details_title' => 'Invoice #:number Details',
        'invoice_number' => 'Invoice #:number',
        'billing_to' => 'Billing To',
        'invoice_details' => 'Invoice Details',
        'date' => 'Date',
        'description' => 'Description',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit Price',
        'total' => 'Total',
        'subscription_fee' => 'Subscription Fee for :plan',
        'module_usage' => 'Usage for :module',
        'email_subject' => 'Invoice #:number for Your Subscription',
        'email_greeting' => 'Dear Valued Customer,',
        'email_body' => 'Please find attached the invoice #:number for your subscription.',
        'email_amount' => 'The total amount due is :amount :currency.',
        'email_due_date' => 'Please ensure payment is made by :date.',
        'email_closing' => 'Thank you for your business.',
    ],
    'navigation' => [
        'group' => 'Subscriptions',
    ],
];