# Changelog

All notable changes to this project are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

- **System Settings & Configuration Hub** (`/settings`): Business profile manager, customizable receipt headers/footers, currency selector, low-stock threshold rules, branch shop location manager (add/deactivate shops), and database backup snapshot downloads.
- **Staff & Role Management Hub** (`/users`): Visual worker cards with role badges (Auditor, Manager, Storekeeper, Cashier), one-tap account creation, anti-theft instant account lock/disable toggle, and password reset modal.
- **Child-Friendly & Auditor-Grade Inventory & POS System**:
  - **Visual Point of Sale (POS)** (`/pos`): Big touch product cards, search, category chips, `+ / -` steppers, Part-Payment & debt tracking, and physical stock handover toggle ("Did customer take goods away today? [YES/NO]").
  - **Physical Closing Stock Engine**: Decoupled sales from physical dispatch so goods sold but not yet taken away (`UNSUPPLIED`) stay in physical closing stock.
  - **Unsupplied Goods Pickup Hub** (`/stock/unsupplied`): Lists orders on ground with one-click "Handover Goods to Customer" dispatching.
  - **2-Step Anti-Theft Inter-Location Transfers**: Dispatched $\rightarrow$ In-Transit $\rightarrow$ Destination Verification & Count. Automatic discrepancy & theft alert if items go missing.
  - **Auditor Anti-Theft & Control Hub** (`/auditor`): Theft discrepancy radar, multi-shop physical closing stock valuation matrix, Cashier End-of-Day shift drawer balancing (Counted vs Expected Cash), and immutable activity logs.
  - **Customer Debt & Part-Payment Recovery** (`/debts`): Debtors ledger, installment payment modal, and printable payment receipts.
  - **Printable Invoices & Receipts** (`/pos/receipt/{id}`): Shows payment breakdown and physical fulfillment status (Supplied vs Unsupplied).
  - **StockService** (`app/Services/StockService.php`): Core business logic for stock-in, sales, transfers, unsupplied dispatch, and customer payments.
  - **New Models**: `Warehouse`, `StockLevel`, `Customer`, `CustomerLedger`, `Transfer`, `TransferItem`, `CashierShift`, `Supplier`.
  - **New Migration**: `database/migrations/2026_08_20_110000_create_locations_and_transfers_tables.php`.
  - **Database Seeder**: Populated with realistic products, shops, stock counts, and sample customer debts.
- **Web Installer Wizard (CodeCanyon style)**:
  - 6-step interactive installation wizard at `/install` (Welcome, Requirements check, Database config with live PDO connection test, Admin account creation, Migration runner with animated progress, and Completion).
  - `InstallerController` handling installer steps, .env generation, migration execution, and admin account setup.
  - `CheckInstalled` middleware to automatically redirect uninstalled instances to `/install` and lock down the installer after completion.
  - Blade templates for all installer steps and dashboard placeholder (`resources/views/installer/*`, `resources/views/dashboard.blade.php`).
- Business Logic, Audit & Anti-Theft Specification (`docs/business_rules.md`).
- Whogohost cPanel Shared Hosting & Update Guide (`docs/whogohost_cpanel_guide.md`) — complete step-by-step installation walkthrough and safe upgrade protocol.
- AI Agent Engineering Rules (`docs/ai_agent_rules.md`) — mandatory commit & changelog tracking rules for all AI agents.
- Whogohost hosting guide (VPS + Shared Hosting + Web Installer) in README.

### Changed
- Configured Git remote origin to `https://github.com/hotvictorious-rgb/hysam.git`.
- Updated `.gitignore` to ignore archive files (`*.zip`) and installer lock file (`storage/installed`).
- Rewrote `README.md` to fully reflect Laravel 10 / PHP 8.3 stack with Web Installer instructions.
- Cleaned up `composer.json` to remove Node.js/npm setup scripts.
- Updated `.env.example` to remove Gemini keys and reflect correct driver options.
- Updated `bootstrap/app.php` to register `CheckInstalled` middleware globally across web routes.
- Updated `routes/web.php` with 37 registered routes covering POS, Stock, Auditor, Debts, Installer, and Dashboard.

### Removed
- **Gemini AI integration** — removed entirely; system is now fully offline-capable.
- **Node.js / Vite / React** — removed all front-end build tooling.
- `GEMINI_API_KEY` from all configuration and documentation.
- `VITE_APP_NAME` from `.env.example` (no Vite).
- AWS S3 config from `.env.example` (not required for Whogohost).
- Redundant duplicate migration file `2026_08_20_000001_create_products_table.php`.

---

## [0.1.0] – 2026-08-20

### Added
- Initial repository scaffolding (Laravel 10, PHP 8.3).
- Base Eloquent models: `Product`, `Supplier`, `User`, `Backup`, `InventoryLog`, `Sale`, `SaleItem`, `SalesReturn`, `Payment`, `Setting`.
- Initial `README.md` with project overview.
- Initial `CONTRIBUTING.md`.

---

*Hysam Ventures Inventory System – Changelog maintained per project coding standards.*
