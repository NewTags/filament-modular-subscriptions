<?php

return [
    'resources' => [
        'plan' => [
            'name' => 'الخطة',
            'fields' => [
                'name' => 'الاسم',
                'slug' => 'الاسم المختصر',
                'description' => 'الوصف',
                'is_active' => 'نشط',
                'price' => 'السعر',
                'currency' => 'العملة',
                'trial_period' => 'فترة التجربة',
                'trial_interval' => 'فاصل التجربة',
                'invoice_period' => 'فترة الفاتورة',
                'invoice_interval' => 'فاصل الفاتورة',
                'grace_period' => 'فترة السماح',
                'grace_interval' => 'فاصل السماح',
            ],
        ],
        'subscription' => [
            'name' => 'الاشتراك',
            'fields' => [
                'plan_id' => 'الخطة',
                'subscribable_type' => 'نوع المشترك',
                'subscribable_id' => 'معرف المشترك',
                'starts_at' => 'يبدأ في',
                'ends_at' => 'ينتهي في',
                'trial_ends_at' => 'تنتهي التجربة في',
                'status' => 'الحالة',
            ],
            'tabs' => [
                'billing' => 'الفوترة',
            ],
        ],
        'module' => [
            'name' => 'الوحدة',
            'fields' => [
                'name' => 'الاسم',
                'class' => 'الفئة',
                'is_active' => 'نشط',
            ],
        ],
        'module_usage' => [
            'name' => 'استخدام الوحدة',
            'fields' => [
                'subscription_id' => 'الاشتراك',
                'module_id' => 'الوحدة',
                'usage' => 'الاستخدام',
                'pricing' => 'التسعير',
                'calculated_at' => 'تم الحساب في',
            ],
        ],
    ],
];
