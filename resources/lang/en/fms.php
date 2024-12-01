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
                'is_persistent' => 'Persistent Usage',
                'is_persistent_help' => 'If enabled, usage data will be preserved when subscription renews',
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
        'unit' => 'unit',
        'units' => 'units',
        'usage_information' => 'Usage Pricing',
        'billed_monthly' => 'Billed monthly based on actual usage',
        'no_minimum_commitment' => 'No minimum commitment required',
        'usage_tracked_realtime' => 'Usage tracked in real-time',
        'subscribe_to_plan' => 'Subscribe to this plan',
        'confirm_subscription' => 'Confirm Subscription to :plan',
        'subscription_created' => 'Subscription created successfully',
        'subscription_created_pending' => 'Subscription created. Please pay the invoice to activate.',
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
    'notifications' => [
        'subscription' => [
            // ... existing subscription notifications ...
            'invoice_generated' => [
                'title' => 'New Invoice Generated',
                'body' => 'New invoice #:invoice_id has been generated for :amount :currency, due on :due_date'
            ],
            'invoice_generation_failed' => [
                'title' => 'Invoice Generation Failed',
                'body' => 'Failed to generate invoice: :error'
            ],
            'invoice_overdue' => [
                'title' => 'Invoice Overdue',
                'body' => 'Invoice #:invoice_id is overdue by :days days. Amount: :amount :currency'
            ],
            'usage_limit_warning' => [
                'title' => 'Usage Limit Warning',
                'body' => 'You are approaching the usage limit for :module (:current of :limit)'
            ],
            'usage_limit_exceeded' => [
                'title' => 'Usage Limit Exceeded',
                'body' => 'You have exceeded the usage limit for :module (:current of :limit)'
            ],
            'subscription_status_changed' => [
                'title' => 'Subscription Status Changed',
                'body' => 'Your subscription status has changed from :old_status to :new_status'
            ],
            'subscription_near_expiry' => [
                'title' => 'Subscription Near Expiry',
                'body' => 'Your subscription will expire in :days days on :expiry_date'
            ],
            'subscription_grace_period' => [
                'title' => 'Subscription in Grace Period',
                'body' => 'Your subscription is in grace period and will be suspended on :grace_end_date (:days days remaining)'
            ],
            'payment_pending' => [
                'title' => 'Payment Pending Review',
                'body' => 'New payment of :amount :currency is pending review'
            ],
            'payment_approved' => [
                'title' => 'Payment Approved',
                'body' => 'Payment of :amount has been approved for :tenant'
            ],
            'payment_partially_approved' => [
                'title' => 'Partial Payment Approved',
                'body' => 'Partial payment of :amount out of :total approved for :tenant'
            ],
            'payment_cancelled' => [
                'title' => 'Payment Cancelled',
                'body' => 'Payment of :amount has been cancelled for :tenant'
            ],
            'payment_undone' => [
                'title' => 'Payment Undone',
                'body' => 'Payment of :amount has been undone for :tenant'
            ],
        ],
        'admin_message' => [
            'invoice_generated' => [
                'title' => 'New Invoice Generated',
                'body' => 'New invoice #:invoice_id generated for :tenant for :amount :currency'
            ],
            'invoice_generation_failed' => [
                'title' => 'Invoice Generation Failed',
                'body' => 'Failed to generate invoice for :tenant. Error: :error'
            ],
            'invoice_overdue' => [
                'title' => 'Invoice Overdue',
                'body' => 'Invoice #:invoice_id for :tenant is overdue by :days days. Amount: :amount :currency'
            ],
            'subscription_near_expiry' => [
                'title' => 'Subscription Near Expiry',
                'body' => ':tenant\'s subscription (:plan) will expire in :days days on :expiry_date'
            ],
            'subscription_grace_period' => [
                'title' => 'Subscription in Grace Period',
                'body' => ':tenant\'s subscription is in grace period. Will be suspended on :grace_end_date (:days days remaining)'
            ],
            'expired' => [
                'title' => 'Subscription Expired',
                'body' => ':tenant\'s subscription has expired on :date'
            ],
            'suspended' => [
                'title' => 'Subscription Suspended',
                'body' => ':tenant\'s subscription has been suspended on :date'
            ],
            'cancelled' => [
                'title' => 'Subscription Cancelled',
                'body' => ':tenant\'s subscription has been cancelled on :date'
            ],
            'payment_received' => [
                'title' => 'Payment Received',
                'body' => 'Payment of :amount :currency received from :tenant on :date'
            ],
            'payment_rejected' => [
                'title' => 'Payment Rejected',
                'body' => 'Payment of :amount :currency from :tenant was rejected on :date'
            ],
            'payment_overdue' => [
                'title' => 'Payment Overdue',
                'body' => 'Payment of :amount :currency from :tenant is overdue by :days days'
            ],
            'payment_pending' => [
                'title' => 'New Payment Pending Review',
                'body' => 'New payment of :amount :currency is pending review from :tenant for invoice #:invoice_id'
            ],
            'payment_partially_approved' => [
                'title' => 'Partial Payment Approved',
                'body' => 'Partial payment of :amount :currency out of :total :currency has been approved for :tenant'
            ],
            'usage_limit_warning' => [
                'title' => 'Usage Limit Warning',
                'body' => ':tenant is approaching the usage limit for :module (:current of :limit)'
            ],
            'usage_limit_exceeded' => [
                'title' => 'Usage Limit Exceeded',
                'body' => ':tenant has exceeded the usage limit for :module (:current of :limit)'
            ],
            'subscription_status_changed' => [
                'title' => 'Subscription Status Changed',
                'body' => ':tenant\'s subscription status changed from :old_status to :new_status'
            ],
        ]
    ]
];
