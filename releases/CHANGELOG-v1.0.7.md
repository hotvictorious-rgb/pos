================================================================================
VICTORIOUS ENTERPRISE POS - LIVE SITE OVERLAY DEPLOYMENT PACKAGE
Release Tag: v1.0.7
Base Revision: v1.0.0-rc.1
Packaged Date: 2026-11-06 11:44:05
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
Step 2: Upload `overlay-v1.0.7.zip` directly into the site root folder.
Step 3: Right-click `overlay-v1.0.7.zip` and select "Extract" (Extract Here).
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
Step 5: Delete `overlay-v1.0.7.zip` and `overlay-runner.php` from your server.

4. FILES INCLUDED IN THIS OVERLAY:
--------------------------------------------------------------------------------
  - VERSION
  - app/Console/Commands/MakeVersionOverlayCommand.php
  - app/Console/Commands/SaasProvisionRootCommand.php
  - app/Console/Commands/SendDailyWhatsAppReportCommand.php
  - app/Exceptions/InsufficientStockException.php
  - app/Exceptions/SecurityException.php
  - app/Http/Controllers/AuthController.php
  - app/Http/Controllers/BackupController.php
  - app/Http/Controllers/Controller.php
  - app/Http/Controllers/DataController.php
  - app/Http/Controllers/SaaS/SaaSController.php
  - app/Http/Controllers/Web/AuditorController.php
  - app/Http/Controllers/Web/BranchSwitchController.php
  - app/Http/Controllers/Web/DashboardController.php
  - app/Http/Controllers/Web/DebtController.php
  - app/Http/Controllers/Web/LandingController.php
  - app/Http/Controllers/Web/PosController.php
  - app/Http/Controllers/Web/ProductController.php
  - app/Http/Controllers/Web/ReportController.php
  - app/Http/Controllers/Web/SettingController.php
  - app/Http/Controllers/Web/StockController.php
  - app/Http/Controllers/Web/SubscriptionController.php
  - app/Http/Controllers/Web/TransactionController.php
  - app/Http/Controllers/Web/UserController.php
  - app/Http/Controllers/Web/WhatsAppWebhookController.php
  - app/Http/Middleware/BlockReadOnlyMutations.php
  - app/Http/Middleware/CheckWebAuth.php
  - app/Http/Middleware/CronFreeWhatsAppReportMiddleware.php
  - app/Http/Middleware/EnsureTenantActive.php
  - app/Http/Middleware/RequireAdmin.php
  - app/Http/Middleware/RequireCapability.php
  - app/Http/Middleware/RequirePlatformUser.php
  - app/Http/Middleware/RequireSuperAdmin.php
  - app/Http/Middleware/SecurityHeaders.php
  - app/Models/Activity.php
  - app/Models/Backup.php
  - app/Models/CashierShift.php
  - app/Models/CustomRole.php
  - app/Models/Customer.php
  - app/Models/CustomerLedger.php
  - app/Models/IdempotencyRecord.php
  - app/Models/InventoryLog.php
  - app/Models/Payment.php
  - app/Models/Product.php
  - app/Models/SaaSSetting.php
  - app/Models/Sale.php
  - app/Models/SaleItem.php
  - app/Models/SalesReturn.php
  - app/Models/Scopes/TenantScope.php
  - app/Models/Setting.php
  - app/Models/StockAdjustment.php
  - app/Models/StockLevel.php
  - app/Models/StockReservation.php
  - app/Models/Supplier.php
  - app/Models/Tenant.php
  - app/Models/Transfer.php
  - app/Models/TransferItem.php
  - app/Models/User.php
  - app/Models/Warehouse.php
  - app/Providers/AppServiceProvider.php
  - app/Rules/PasswordPolicy.php
  - app/Services/Accounting/AccountingReportService.php
  - app/Services/Auth/CapabilityService.php
  - app/Services/IdempotencyService.php
  - app/Services/Notification/WhatsAppService.php
  - app/Services/StockService.php
  - app/Services/TransactionVoidService.php
  - app/Traits/BelongsToTenant.php
  - bootstrap/app.php
  - bootstrap/providers.php
  - config/version.php
  - config/app.php
  - config/auth.php
  - config/cache.php
  - config/database.php
  - config/filesystems.php
  - config/logging.php
  - config/mail.php
  - config/queue.php
  - config/saas.php
  - config/services.php
  - config/session.php
  - config/version.php
  - config/whatsapp.php
  - database/migrations/0001_01_01_000000_create_users_table.php
  - database/migrations/0001_01_01_000001_create_cache_table.php
  - database/migrations/0001_01_01_000002_create_jobs_table.php
  - database/migrations/2026_07_27_200000_create_business_tables.php
  - database/migrations/2026_07_27_300000_create_backups_table.php
  - database/migrations/2026_07_27_400000_create_custom_roles_table.php
  - database/migrations/2026_08_20_074727_create_suppliers_table.php
  - database/migrations/2026_08_20_110000_create_locations_and_transfers_tables.php
  - database/migrations/2026_08_20_130000_create_adjustments_and_assign_users_table.php
  - database/migrations/2026_08_20_210000_add_performance_indexes_for_scale.php
  - database/migrations/2026_08_24_190000_add_customer_code_to_customers_table.php
  - database/migrations/2026_08_25_080000_add_sale_type_to_sales_table.php
  - database/migrations/2026_08_25_081000_add_customer_id_to_sales_table.php
  - database/migrations/2026_09_01_000000_create_saas_tenants_table.php
  - database/migrations/2026_09_01_000001_add_tenant_id_to_business_tables.php
  - database/migrations/2026_09_01_000002_create_saas_settings_table.php
  - database/migrations/2026_09_01_000003_add_tenant_id_to_settings_table.php
  - database/migrations/2026_09_01_000004_fix_warehouse_code_tenant_unique_index.php
  - database/migrations/2026_09_01_000005_add_tenant_id_to_inventory_logs_table.php
  - database/migrations/2026_09_01_000006_add_tenant_id_to_activities_and_ledgers.php
  - database/migrations/2026_09_03_233000_create_idempotency_records_table.php
  - database/migrations/2026_09_03_235500_add_warehouse_and_change_to_sales_table.php
  - database/migrations/2026_09_04_002500_add_tenant_id_to_all_financial_tables.php
  - database/migrations/2026_09_04_120000_add_warehouse_id_to_inventory_logs_table.php
  - database/migrations/2026_09_04_121000_create_stock_reservations_table.php
  - database/migrations/2026_09_05_000000_add_tenant_id_to_backups_table.php
  - database/migrations/2026_09_05_020000_fix_stock_reservations_product_id_type.php
  - database/migrations/2026_09_05_040000_add_returned_fulfilled_qty_to_stock_reservations.php
  - database/migrations/2026_09_05_080000_add_warehouse_id_to_customer_ledgers_table.php
  - database/migrations/2026_09_05_090000_backfill_customer_ledgers_warehouse_id.php
  - database/migrations/2026_09_05_160000_add_scale_indexes_for_financial_closure.php
  - database/migrations/2026_09_10_060000_fix_settings_primary_key_and_add_sales_customer_phone.php
  - database/migrations/2026_09_16_160000_fix_stock_reservations_status_enum_to_varchar.php
  - database/migrations/2026_09_17_130000_add_receipt_ref_to_sales_table.php
  - database/migrations/2026_11_03_060000_add_whatsapp_reporting_settings.php
  - database/migrations/2026_11_04_100000_add_whatsapp_fields_to_users_table.php
  - public/favicon.ico
  - public/icons/apple-touch-icon.png
  - public/icons/icon-192x192.png
  - public/icons/icon-512x512.png
  - public/icons/maskable-icon.png
  - public/index.php
  - public/js/core-ui.js
  - public/js/pos-engine.js
  - public/manifest.json
  - public/robots.txt
  - public/sw.js
  - resources/css/app.css
  - resources/js/ARCHIVED_LEGACY_SPA_NOTICE.md
  - resources/js/App.tsx
  - resources/js/app.js
  - resources/js/components/ActivityLog.tsx
  - resources/js/components/CalculatorDropdown.tsx
  - resources/js/components/Dashboard.tsx
  - resources/js/components/Deliveries.tsx
  - resources/js/components/Inventory.tsx
  - resources/js/components/Login.tsx
  - resources/js/components/Payments.tsx
  - resources/js/components/ReceiptModal.tsx
  - resources/js/components/Sales.tsx
  - resources/js/components/Settings.tsx
  - resources/js/components/Users.tsx
  - resources/js/index.css
  - resources/js/lib/dateUtils.ts
  - resources/js/lib/rbac.ts
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
  - resources/views/saas/suspended.blade.php
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
  - resources/views/welcome.blade.php
  - routes/console.php
  - routes/web.php

================================================================================
Generated by: php artisan app:make-overlay
Victorious Enterprise POS Architecture
================================================================================