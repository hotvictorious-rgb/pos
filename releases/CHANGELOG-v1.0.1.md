================================================================================
VICTORIOUS ENTERPRISE POS - LIVE SITE OVERLAY DEPLOYMENT PACKAGE
Release Tag: v1.0.1
Base Revision: v1.0.0-rc.1
Packaged Date: 2026-11-04 21:28:18
================================================================================

1. WHAT IS THIS OVERLAY?
--------------------------------------------------------------------------------
This zip package contains the exact updated codebase files for Victorious POS
to overlay on a live production site (e.g. pos.victoriousmarket.com.ng) without
touching your production `.env`, uploaded customer files, or database storage.

2. WHAT WAS UPDATED IN THIS VERSION:
--------------------------------------------------------------------------------
  * General system enhancements and performance updates.

3. HOW TO APPLY THIS OVERLAY ON LIVE CPANEL / LIVE HOST:
--------------------------------------------------------------------------------
Step 1: In your cPanel File Manager, navigate to your live site root directory.
Step 2: Upload `overlay-v1.0.1.zip` directly into the site root folder.
Step 3: Right-click `overlay-v1.0.1.zip` and select "Extract" (Extract Here).
        All updated files in `app/`, `resources/`, `routes/`, `config/`, etc.
        will be updated seamlessly.
Step 4: Execute database migrations and clear caches:
        - METHOD A (Recommended): Log in to the POS System as Admin -> Go to
          System Settings -> Click [ ⚡ Run Pending Migrations & Clear Cache ].
        - METHOD B (CLI / SSH): Run the following commands:
          php artisan migrate --force
          php artisan optimize:clear
        - METHOD C (Web Script): Visit `https://yourdomain.com/overlay-runner.php`
          in your browser and enter your app key to run migrations automatically.
Step 5: Delete `overlay-v1.0.1.zip` and `overlay-runner.php` from your server.

4. FILES INCLUDED IN THIS OVERLAY:
--------------------------------------------------------------------------------
  - CHANGELOG.md
  - VERSION
  - app/Console/Commands/MakeVersionOverlayCommand.php
  - app/Console/Commands/SaasProvisionRootCommand.php
  - app/Console/Commands/SendDailyWhatsAppReportCommand.php
  - app/Http/Controllers/AuthController.php
  - app/Http/Controllers/BackupController.php
  - app/Http/Controllers/DataController.php
  - app/Http/Controllers/SaaS/SaaSController.php
  - app/Http/Controllers/Web/AuditorController.php
  - app/Http/Controllers/Web/BranchSwitchController.php
  - app/Http/Controllers/Web/DashboardController.php
  - app/Http/Controllers/Web/DebtController.php
  - app/Http/Controllers/Web/PosController.php
  - app/Http/Controllers/Web/ProductController.php
  - app/Http/Controllers/Web/ReportController.php
  - app/Http/Controllers/Web/SettingController.php
  - app/Http/Controllers/Web/StockController.php
  - app/Http/Controllers/Web/SubscriptionController.php
  - app/Http/Controllers/Web/TransactionController.php
  - app/Http/Controllers/Web/UserController.php
  - app/Http/Controllers/Web/WhatsAppWebhookController.php
  - app/Http/Middleware/CheckWebAuth.php
  - app/Http/Middleware/CronFreeWhatsAppReportMiddleware.php
  - app/Http/Middleware/EnsureTenantActive.php
  - app/Http/Middleware/RequirePlatformUser.php
  - app/Http/Middleware/RequireSuperAdmin.php
  - app/Models/Activity.php
  - app/Models/InventoryLog.php
  - app/Models/Sale.php
  - app/Models/SaleItem.php
  - app/Models/Scopes/TenantScope.php
  - app/Models/Setting.php
  - app/Models/User.php
  - app/Models/Warehouse.php
  - app/Rules/PasswordPolicy.php
  - app/Services/Accounting/AccountingReportService.php
  - app/Services/Auth/CapabilityService.php
  - app/Services/IdempotencyService.php
  - app/Services/Notification/WhatsAppService.php
  - app/Services/StockService.php
  - app/Services/TransactionVoidService.php
  - app/Traits/BelongsToTenant.php
  - bootstrap/app.php
  - config/version.php
  - config/app.php
  - config/mail.php
  - config/saas.php
  - config/version.php
  - config/whatsapp.php
  - database/migrations/2026_09_05_160000_add_scale_indexes_for_financial_closure.php
  - database/migrations/2026_09_10_060000_fix_settings_primary_key_and_add_sales_customer_phone.php
  - database/migrations/2026_09_16_160000_fix_stock_reservations_status_enum_to_varchar.php
  - database/migrations/2026_09_17_130000_add_receipt_ref_to_sales_table.php
  - database/migrations/2026_11_03_060000_add_whatsapp_reporting_settings.php
  - database/migrations/2026_11_04_100000_add_whatsapp_fields_to_users_table.php
  - database/seeders/DatabaseSeeder.php
  - database/seeders/LocalTestSeeder.php
  - docs/V_MARKET_SECURITY_CONTRACT.md
  - docs/pilot_deployment_playbook.md
  - hysam_products_import_nwaniba.csv
  - hysam_products_import_nwaniba_part1.csv
  - hysam_products_import_nwaniba_part2.csv
  - package.json
  - phpunit.mysql.standalone.xml
  - phpunit.mysql.xml
  - public/.htaccess
  - public/icons/apple-touch-icon.png
  - public/icons/icon-192x192.png
  - public/icons/icon-512x512.png
  - public/icons/maskable-icon.png
  - public/js/core-ui.js
  - public/js/pos-engine.js
  - public/manifest.json
  - public/sw.js
  - resources/js/ARCHIVED_LEGACY_SPA_NOTICE.md
  - resources/js/App.tsx
  - resources/js/lib/storage.ts
  - resources/js/main.tsx
  - resources/js/types.ts
  - resources/views/app.blade.php
  - resources/views/auditor/index.blade.php
  - resources/views/auth/change-password.blade.php
  - resources/views/auth/forgot-password.blade.php
  - resources/views/auth/login.blade.php
  - resources/views/auth/portal-login.blade.php
  - resources/views/auth/reset-password.blade.php
  - resources/views/components/searchable-product-picker.blade.php
  - resources/views/dashboard.blade.php
  - resources/views/debts/index.blade.php
  - resources/views/help/index.blade.php
  - resources/views/landing.blade.php
  - resources/views/layouts/app.blade.php
  - resources/views/pos/index.blade.php
  - resources/views/pos/receipt.blade.php
  - resources/views/pos/returns.blade.php
  - resources/views/products/index.blade.php
  - resources/views/reports/index.blade.php
  - resources/views/reports/pdf/daily_closing.blade.php
  - resources/views/reports/pdf/day_book.blade.php
  - resources/views/reports/pdf/debtors.blade.php
  - resources/views/reports/pdf/exchanges.blade.php
  - resources/views/reports/pdf/inventory.blade.php
  - resources/views/reports/pdf/layout.blade.php
  - resources/views/reports/pdf/pending_orders.blade.php
  - resources/views/reports/pdf/returns.blade.php
  - resources/views/reports/pdf/sales.blade.php
  - resources/views/reports/pdf/stock_out.blade.php
  - resources/views/reports/pdf/transfers.blade.php
  - resources/views/saas/admin/index.blade.php
  - resources/views/saas/register.blade.php
  - resources/views/settings/index.blade.php
  - resources/views/stock/adjustments.blade.php
  - resources/views/stock/index.blade.php
  - resources/views/stock/transfers.blade.php
  - resources/views/stock/unsupplied.blade.php
  - resources/views/stock/waybill.blade.php
  - resources/views/subscription/index.blade.php
  - resources/views/transactions/index.blade.php
  - resources/views/transactions/partials/panes.blade.php
  - resources/views/users/index.blade.php
  - routes/console.php
  - routes/web.php
  - run_live_migrations.php
  - test_worker_forms.php

================================================================================
Generated by: php artisan app:make-overlay
Victorious Enterprise POS Architecture
================================================================================