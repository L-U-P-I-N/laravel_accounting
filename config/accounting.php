<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Accounting System Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration settings for the accounting system,
    | including tax configurations, subscription plans, and other settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'basic' => [
            'name' => 'Basic',
            'name_ar' => 'أساسي',
            'price_usd' => 29.99,
            'price_sar' => 112.00,
            'max_users' => 3,
            'max_invoices' => 100,
            'features' => ['accounting', 'invoicing', 'reports']
        ],
        'professional' => [
            'name' => 'Professional',
            'name_ar' => 'احترافي',
            'price_usd' => 79.99,
            'price_sar' => 300.00,
            'max_users' => 10,
            'max_invoices' => 1000,
            'features' => ['accounting', 'invoicing', 'reports', 'payroll', 'tax', 'inventory']
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'name_ar' => 'مؤسسات',
            'price_usd' => 199.99,
            'price_sar' => 750.00,
            'max_users' => -1,
            'max_invoices' => -1,
            'features' => ['all']
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Configurations
    |--------------------------------------------------------------------------
    */
    'tax_configs' => [
        'SA' => [
            'name' => 'Saudi Arabia',
            'name_ar' => 'المملكة العربية السعودية',
            'currency' => 'SAR',
            'vat_rate' => 15.0,
            'tax_number_label' => 'الرقم الضريبي',
            'tax_number_format' => '/^\d{15}$/',
            'fiscal_year_start' => '01-01',
            'zatca_enabled' => true
        ],
        'AE' => [
            'name' => 'UAE',
            'name_ar' => 'الإمارات العربية المتحدة',
            'currency' => 'AED',
            'vat_rate' => 5.0,
            'tax_number_label' => 'TRN',
            'tax_number_format' => '/^\d{15}$/',
            'fiscal_year_start' => '01-01',
            'zatca_enabled' => false
        ],
        'US' => [
            'name' => 'United States',
            'name_ar' => 'الولايات المتحدة',
            'currency' => 'USD',
            'vat_rate' => 0.0,
            'sales_tax' => true,
            'tax_number_label' => 'EIN',
            'tax_number_format' => '/^\d{2}-\d{7}$/',
            'fiscal_year_start' => '01-01',
            'zatca_enabled' => false
        ],
        'EG' => [
            'name' => 'Egypt',
            'name_ar' => 'مصر',
            'currency' => 'EGP',
            'vat_rate' => 14.0,
            'tax_number_label' => 'الرقم الضريبي',
            'fiscal_year_start' => '01-01',
            'zatca_enabled' => false
        ],
        'JO' => [
            'name' => 'Jordan',
            'name_ar' => 'الأردن',
            'currency' => 'JOD',
            'vat_rate' => 16.0,
            'tax_number_label' => 'الرقم الضريبي',
            'fiscal_year_start' => '01-01',
            'zatca_enabled' => false
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'country_code' => 'SA',
        'currency' => 'SAR',
        'language' => 'ar',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
        'timezone' => 'Asia/Riyadh',
        'fiscal_year_start' => '01-01',
        'decimal_places' => 2,
        'thousands_separator' => ',',
        'decimal_separator' => '.'
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    */
    'invoice' => [
        'auto_numbering' => true,
        'number_format' => 'INV-{year}-{sequence:6}',
        'default_terms' => 'Net 30',
        'default_due_days' => 30,
        'allow_partial_payments' => true,
        'auto_post_to_journal' => true,
        'email_invoice_on_create' => false
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'methods' => ['cash', 'bank_transfer', 'check', 'credit_card'],
        'auto_reconcile' => true,
        'require_reference' => false
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Settings
    |--------------------------------------------------------------------------
    */
    'reports' => [
        'default_period' => 'monthly',
        'include_draft_entries' => false,
        'show_zero_balances' => false
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'require_two_factor' => false,
        'session_timeout' => 24 * 60, // minutes
        'max_login_attempts' => 5,
        'lockout_duration' => 15, // minutes
        'password_min_length' => 8,
        'require_special_chars' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Settings
    |--------------------------------------------------------------------------
    */
    'email' => [
        'from_name' => 'Laravel Accounting System',
        'from_address' => 'noreply@laravel-accounting.com',
        'reply_to' => 'support@laravel-accounting.com',
        'queue_emails' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    */
    'backup' => [
        'enabled' => true,
        'frequency' => 'daily',
        'retention_days' => 30,
        'include_files' => true,
        'compress' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        'stripe' => [
            'enabled' => false,
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET')
        ],
        'quickbooks' => [
            'enabled' => false,
            'api_key' => env('QUICKBOOKS_API_KEY'),
            'api_secret' => env('QUICKBOOKS_API_SECRET')
        ],
        'xero' => [
            'enabled' => false,
            'api_key' => env('XERO_API_KEY'),
            'api_secret' => env('XERO_API_SECRET')
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization Settings
    |--------------------------------------------------------------------------
    */
    'localization' => [
        'supported_locales' => ['en', 'ar'],
        'default_locale' => 'ar',
        'rtl_locales' => ['ar'],
        'date_formats' => [
            'en' => 'M d, Y',
            'ar' => 'd M, Y'
        ],
        'currency_formats' => [
            'SAR' => ['symbol' => 'ر.س', 'position' => 'before'],
            'USD' => ['symbol' => '$', 'position' => 'before'],
            'AED' => ['symbol' => 'د.إ', 'position' => 'before'],
            'EGP' => ['symbol' => 'ج.م', 'position' => 'before'],
            'JOD' => ['symbol' => 'د.أ', 'position' => 'before']
        ]
    ]
];
