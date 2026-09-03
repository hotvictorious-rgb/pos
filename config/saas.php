<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SaaS Multi-Tenancy Toggle
    |--------------------------------------------------------------------------
    |
    | When set to false, the application runs as a traditional standalone single-
    | tenant system for a single business. Global tenant query scopes are bypassed.
    | When set to true, strict multi-tenant data isolation is enforced.
    |
    */

    'enabled' => env('SAAS_ENABLED', false),
    'platform_name' => env('SAAS_PLATFORM_NAME', 'VMARKET POS'),
    'super_admin_email' => env('SUPER_ADMIN_EMAIL', 'admin@hysamventures.com'),

    /*
    |--------------------------------------------------------------------------
    | Subscription Plan Tiers & Branch Limits
    |--------------------------------------------------------------------------
    */

    'plans' => [
        'basic' => [
            'name' => 'Starter Plan',
            'max_branches' => 1,
            'max_users' => 3,
            'price_monthly' => 15000,
        ],
        'pro' => [
            'name' => 'Professional Growth',
            'max_branches' => 5,
            'max_users' => 15,
            'price_monthly' => 35000,
        ],
        'enterprise' => [
            'name' => 'Enterprise Multi-Branch',
            'max_branches' => 999,
            'max_users' => 999,
            'price_monthly' => 75000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Free Trial Length (in days)
    |--------------------------------------------------------------------------
    */
    'trial_days' => 14,
];
