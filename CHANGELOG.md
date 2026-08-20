# Changelog

All notable changes to this project are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and [Semantic Versioning](https://semver.org/).

---

### Fixed
- **Transfer Dispatch & Sales Item Key Resolution**: Fixed `Undefined array key "productId"` in `StockService` by gracefully resolving both camelCase `productId` and snake_case `product_id` keys in `initiateTransfer`, `recordSale`, and `recordSaleReturn`.

### Added
- **Combined Sales & Stock Role (`sales_stock`)**:
  - Implemented single-staff branch attendant role combining POS selling, price bargaining, customer debts, supplier goods receipt, accepting transfer shipments, and damaged stock logging.
  - Updated Workers Management modal with `💼 Sales & Stock Officer (Combined)` option.
  - Added dedicated training track in `/help` for solo shop attendants.
- **Printable Inter-Branch Transfer Waybills** (`/stock/waybill/{id}`): Official printable delivery manifest with sender/destination branch details, product breakdown, and physical signature boxes for dispatch officer, carrier driver, and storekeeper.
- **Reports & AI Data Export Hub** (`/reports`):
  - Multi-tab reports dashboard: Sales & Revenue, Physical Stock & Valuations, Transfers & Logistical Discrepancies, Debtors Ledger, Damaged Stock Write-offs, and Activity Audit Logs.
  - One-click **CSV (Excel/Google Sheets)** and structured **JSON (for AI Prompt/Script Analysis)** exports across all operational data.
- **Role-Based Training Center & User Guides** (`/help`):
  - Organized user guides into 4 tailored role tracks: **💰 Cashier / Sales Officer**, **📦 Storekeeper / Inventory Lead**, **🏢 Branch Manager**, and **🛡️ Auditor / Super Admin**.
  - Interactive top tabs allowing workers to filter and view duties, operational steps, and FAQs specific to their job role.
- **Mandatory In-App User Guide & FAQ Sync Rule**: Added Section 2.5 to `docs/ai_agent_rules.md` requiring all AI agents to read and synchronize `/help` FAQs with every newly added feature or workflow.
- **Transactions & Sales History Hub** (`/transactions`):
  - Advanced multi-criteria filter engine: quick date presets (Today, Yesterday, This Week, This Month), custom date range, payment status (Fully Paid vs Part-Payment Debt), delivery/handover status (Supplied vs Unsupplied), and staff/cashier dropdown.
  - Aggregated real-time metrics: Total Invoices, Gross Sales, Cash/POS Collected, and Outstanding Debts.
  - Detailed line-item modal drawer and direct printable receipt shortcuts.
- **Header Enhancements**:
  - **Live Digital Date & Clock**: Real-time ticking calendar and digital clock in Nigerian local time format (`Thu, 20 Aug 2026 | 12:35:10 PM`).
  - **Interactive Quick POS Calculator**: One-click topbar calculator widget with standard arithmetic (`+`, `-`, `*`, `/`, `%`, `00`), error handling, and high-visibility digital display for quick counter tallying.
- **Nigerian Wholesale/Retail Workflow Enhancements (Nwaniba Market Spec)**:
  - **Modern Sidebar Navigation**: Fixed high-contrast collapsible sidebar with distinct operational groups, active route markers, and quick POS access.
  - **Products & Pricing Catalog Hub** (`/products`): Full CRUD catalog manager with category filters, pack/brand specs, SKU generator, and live stock visibility across all shop locations.
  - **Editable Unit Price at POS**: Real-time price negotiation and bulk discount adjustments right inside the POS cart drawer with instant subtotal and debt recalculations.
  - **Sales Returns & Customer Refunds** (`/pos/returns`): Invoice picker, line item return selection, cash refund or customer debt balance reduction, and automatic restocking into **Physical Closing Stock**.
  - **Damaged & Expired Stock Write-offs** (`/stock/adjustments`): Formal write-off hub for damaged, expired, or lost goods on ground with mandatory staff attribution and immutable Auditor logging.
  - **Multi-Location Staff Assignment**: Tie workers (Cashiers, Storekeepers, Managers) to specific branches (`Shop 1`, `Shop 2`, `Nwaniba Branch`), with centralized oversight under the Super Admin.
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
