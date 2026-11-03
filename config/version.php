<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Current Application Version & Release Tag
    |--------------------------------------------------------------------------
    |
    | Single source of truth for semantic versioning across the POS system,
    | release packaging, automated overlay patches, and UI badges.
    | Versions strictly increment by 0.0.1 for each release update.
    |
    */
    'version' => '1.0.1',
    'tag' => 'v1.0.1',
    'name' => 'Victorious Enterprise POS',
    'released_at' => '2026-11-03',

    /*
    |--------------------------------------------------------------------------
    | Version Overlays History & Deployment Notes
    |--------------------------------------------------------------------------
    |
    | Detailed record of each version overlay from previous releases.
    | Explains new capabilities, database migrations, and live site instructions.
    |
    */
    'overlays' => [
        'v1.0.1' => [
            'version' => '1.0.1',
            'tag' => 'v1.0.1',
            'title' => 'Admin Reversals Hub, Comprehensive Daily Owner WhatsApp Reconciliation & Live Site Overlay Engine',
            'date' => '2026-11-03',
            'highlights' => [
                'Dedicated Admin Reversals & Voids Center (Tab 10) with 5 sub-consoles: Sales, Debt Recoveries, Stock-In, Adjustments, and Forensic Security Log.',
                'Permanent contra-accounting (SALE_REVERSAL, INVOICE_REVERSAL, PAYMENT_REVERSAL) replacing dangerous database hard deletes.',
                'Strict per-shop isolation across inventory, transactions, customer debts, and reconciliation reports.',
                'Automated 9:00 PM WhatsApp reconciliation dispatching comprehensive metrics to store owners.',
                'Deep Daily System Check: Stock In, Stock Out, In-Transit Transfers, Debts, Pending Deliveries, Reversals, Low Stock & Cash in Hand for Bank Deposit.',
                'Delta Overlay Packager (php artisan app:make-overlay) generating extraction-ready patches for cPanel / live web root.',
                '0.0.1 Semantic Version Increment Engine with in-app version badge and live database migration runner.',
            ],
            'migrations' => [
                '2026_11_03_060000_add_whatsapp_reporting_settings.php',
            ],
            'instructions' => 'Extract overlay-v1.0.1.zip directly over live site folder in cPanel File Manager, then click "Run Pending Migrations & Clear Cache" in Settings or run `php artisan migrate --force`.',
        ],
        'v1.0.0' => [
            'version' => '1.0.0',
            'tag' => 'v1.0.0',
            'title' => 'Core POS, Multi-Warehouse Inventory & Thermal Receipt Engine',
            'date' => '2026-07-27',
            'highlights' => [
                'High-speed Point of Sale checkout counter with barcode lookup.',
                'Multi-branch stock transfers with in-transit buffer and discrepancy handling.',
                'Customer debt ledgers and partial payment recovery.',
                'Thermal 80mm & 58mm receipt printing engine with QR verification.',
            ],
            'migrations' => [
                '2026_07_27_200000_create_business_tables.php',
            ],
            'instructions' => 'Initial installation base release.',
        ],
    ],
];
