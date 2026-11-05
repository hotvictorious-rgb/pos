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
    'version' => '1.0.5',
    'tag' => 'v1.0.5',
    'name' => 'Victorious Enterprise POS',
    'released_at' => '2026-11-05',

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
        'v1.0.5' => [
            'version' => '1.0.5',
            'tag' => 'v1.0.5',
            'title' => 'Shopify-Style Full-Screen POS Zen Mode & Auto-Fill Responsive Grid',
            'date' => '2026-11-05',
            'highlights' => [
                'Full-Screen Zen Mode POS Register: Automatically hides standard fixed sidebar on POS screen (/pos), giving 100% full-width workspace to cashier registers.',
                'Responsive Auto-Fill Product Grid: Replaced cramped 2-column fixed grid with repeat(auto-fill, minmax(210px, 1fr)), rendering 3 to 5 responsive product tiles per row on desktop/laptops.',
                'Dedicated Exit POS / Shift Summary Navigation: Added direct 🏠 Exit POS topbar button navigating back to Executive Dashboard (or Shift Summary for cashiers).',
                'Slide-Out Drawer Menu on POS: Cashiers and managers can access full system navigation via drawer toggle (☰) on POS without leaving the active sales register.',
            ],
            'migrations' => [],
            'instructions' => 'Extract overlay-v1.0.5.zip directly over live site folder in cPanel File Manager, then run php artisan optimize:clear.',
        ],
        'v1.0.4' => [
            'version' => '1.0.4',
            'tag' => 'v1.0.4',
            'title' => 'Universal Multi-Branch Isolation & Active-Branch Context Synchronization Across All Screens and Tabs',
            'date' => '2026-11-05',
            'highlights' => [
                'Deep Per-Shop Isolation on Pending Orders (/stock/unsupplied): strictly clamps orders to active branch in session or designated shop for frontline staff.',
                'Multi-Branch Synchronization on Shop Transfers (/stock/transfers): active branch selection automatically defaults to filtering transfers involving that branch, and scopes transfer KPIs accordingly.',
                'Stock Out & Deductions Hub (/stock/adjustments): active branch synchronization pre-selects and filters adjustments by active shop location.',
                'Universal 10-Tab History & Ledgers Hub (/transactions): In-Transit, Incoming Transfers, and Customer Debts now automatically respect active branch context, and total open debt correctly computes for the selected shop.',
                'Reports & Daybook System (/reports, CSV, JSON, PDF): strict branch isolation across Daybook, inventory valuation, pending orders, and all executive export formats.',
                'Catalog Stock Scoping (/products): product shelf count and low/out-of-stock indicators strictly reflect the active branch inventory.',
                'Frontline Immutability: Cashiers and branch employees are hard-locked to their designated location and cannot switch branches.',
            ],
            'migrations' => [],
            'instructions' => 'Extract overlay-v1.0.4.zip directly over live site folder in cPanel File Manager, then run php artisan optimize:clear.',
        ],
        'v1.0.3' => [
            'version' => '1.0.3',
            'tag' => 'v1.0.3',
            'title' => 'Branch Switch Route Hardening, Universal Portal Redirection & Resilient WhatsApp Report Compilation',
            'date' => '2026-11-05',
            'highlights' => [
                'Fixed 405 MethodNotAllowed on /branch/switch: route accepts GET & POST, gracefully redirecting GET requests to the user dashboard.',
                'Sanitized session intended URLs: action and state endpoints are never retained across authentication redirects.',
                'Unified legacy portal routes (/tenant/login, /super-admin/login) to seamlessly redirect to the primary multi-identifier login.',
                'Hardened WhatsApp Daily Report metrics compilation supporting dual timestamp columns (createdAt and created_at).',
            ],
            'migrations' => [],
            'instructions' => 'Extract overlay-v1.0.3.zip directly over live site folder in cPanel File Manager, then run php artisan optimize:clear.',
        ],
        'v1.0.2' => [
            'version' => '1.0.2',
            'tag' => 'v1.0.2',
            'title' => 'Universal Multi-Identifier Authentication, Role & Branch Isolated Dashboards & Interactive WhatsApp USSD Engine',
            'date' => '2026-11-05',
            'highlights' => [
                'Universal Single-Entrypoint Login at /login accepting Work Email, Staff Username, or WhatsApp Phone Number without format blocks.',
                'Strict Role-Based Dashboard Isolation: Cashiers land straight at POS (/pos), Store Owners & Managers land at their isolated Executive Dashboard (/dashboard), and Superadmins land at Platform Command (/saas/admin).',
                'Hard multi-tenant and multi-branch data isolation: cross-tenant access is strictly denied (403/redirect) and branch staff are clamped to their warehouse.',
                'Resolved platform user boundary check ensuring super-admins retain store access without 403 Forbidden roadblocks.',
                'Interactive Multi-Level WhatsApp USSD Navigation: Hierarchical menus with dot/star notation (1.1, 1.2, 2.2, 3.1) and stateful cache navigation.',
                'Enhanced live deployment migration runner ensuring user integrity and default-tenant recovery.',
            ],
            'migrations' => [],
            'instructions' => 'Extract overlay-v1.0.2.zip directly over live site folder in cPanel File Manager, then load https://pos.victoriousmarket.com.ng/run_live_migrations.php?token=vmpos_deploy_2026 to verify database and clear all caches.',
        ],
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
