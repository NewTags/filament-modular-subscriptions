<?php

return [
    'resources' => [
        'plan' => [
            'name' => 'الخطط',
            'singular_name' => 'خطة',
            'fields' => [
                'name' => 'الاسم',
                'slug' => 'الاسم المختصر',
                'description' => 'الوصف',
                'is_active' => 'نشط',
                'price' => 'السعر',
                'currency' => 'العملة',
                'trial_period' => 'مدة التجربة',
                'trial_interval' => 'وحدة مدة التجربة',
                'invoice_period' => 'مدة الفوترة',
                'invoice_interval' => 'وحدة مدة الفوترة',
                'grace_period' => 'فترة السماح',
                'grace_interval' => 'وحدة فترة السماح',
                'module' => 'الوحدة',
                'modules' => 'الوحدات',
                'module_limit' => 'حدود الاستخدام',
                'module_settings' => 'إعدادات الوحدة',
                'setting_key' => 'مفتاح الإعداد',
                'setting_value' => 'قيمة الإعداد',
                'module_price' => 'سعر الوحدة',
                'modules_count' => 'عدد الوحدات',
            ],
            'hints' => [
                'module_limit' => 'أتركه فارغا للاستخدام الغير محدود',
            ],
            'placeholders' => [
                'setting_key' => 'أدخل مفتاح الإعداد',
                'setting_value' => 'أدخل قيمة الإعداد',
            ],
            'actions' => [
                'add_module' => 'إضافة الوحدة',
                'collapse_all_modules' => 'إغلاق جميع الوحدات',
            ],
            'tabs' => [
                'details' => 'التفاصيل',
                'billing' => 'الفوترة',
                'usage' => 'الاستخدام',
                'pricing' => 'التسعير',
                'modules' => 'الوحدات',
            ],
        ],
        'subscription' => [
            'name' => 'الاشتراكات',
            'singular_name' => 'الاشتراك',
            'fields' => [
                'plan_id' => 'الخطة',
                'subscribable_type' => 'نوع المشترك',
                'subscribable_id' => 'المشترك',
                'starts_at' => 'تاريخ البدء',
                'ends_at' => 'تاريخ الانتهاء',
                'trial_ends_at' => 'تاريخ انتهاء التجربة',
                'status' => 'الحالة',
            ],
            'tabs' => [
                'billing' => 'الفوترة',
            ],
        ],
        'module' => [
            'name' => 'الوحدات',
            'singular_name' => 'الوحدة',
            'fields' => [
                'name' => 'الاسم',
                'class' => 'الفئة',
                'is_active' => 'نشط',
            ],
        ],
        'module_usage' => [
            'name' => 'استخدامات الوحدات',
            'singular_name' => 'استخدام الوحدة',
            'fields' => [
                'subscription_id' => 'الاشتراك',
                'module_id' => 'الوحدة',
                'usage' => 'الاستخدام',
                'pricing' => 'التسعير',
                'calculated_at' => 'تاريخ الحساب',
                'subscriber' => 'المشترك',
            ],
            'actions' => [
                'calculate_usage' => 'حساب الاستخدام',
                'calculate_usage_success' => 'تم حساب الاستخدام بنجاح',
            ],
        ],
    ],
    'menu_group' => [
        'subscription_management' => 'إدارة الاشتراكات',
    ],
    'interval' => [
        'day' => 'يوم',
        'week' => 'أسبوع',
        'month' => 'شهر',
        'year' => 'سنة',
    ],
    'status' => [
        'active' => 'نشط',
        'cancelled' => 'ملغي',
        'expired' => 'منتهي',
        'pending' => 'قيد الانتظار',
    ],
    'your_subscription_details' => 'تفاصيل الاشتراك',
];
