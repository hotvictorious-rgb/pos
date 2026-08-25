# Changelog

All notable changes to this project are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Added
- **Strict 11-Digit Nigerian Phone Number Enforcement**:
  - **Standardized 11-Digit GSM Format**: Mandated exactly 11 digits starting with `0` (e.g. `08031234567`, `09012345678`, `08123456789`) for all customer phone entries across the POS register, checkout validation, debt ledger binding, and server-side controller rules.
  - **Automatic Normalization**: Automatically strips spaces, hyphens, and converts international `+234` format into standard 11-digit local format (`080...`).
  - **Instant Interception**: Rejects inputs that fail the 11-digit rule with clear reason pop-up guidance.
- **SKU-Centric Product Display Architecture**:
  - **SKU as Primary Unique Identifier**: Made the factory SKU (`$product->code`) the dominant, headline title displayed across the Point of Sale catalog, cart, physical receipts, stock inventory hub, transfer manifests, unsupplied pickup list, damaged goods write-offs, and transaction ledgers.
  - **Clean Product Cards & Streamlined Views**: Removed long verbose titles from main labels, allowing fast recognition by factory codes (e.g. `M10DE`, `54X14-18D`, `P1`, `M12CP`) while retaining category and dimensions as subtle subtitles.
- **Pre-Submission Business Rule Validation & Reason Pop-Up Engine**:
  - **Zero-Bypass Client-Side Interceptor (`showActionBlockedModal`)**: Replaced post-submission page reloads and generic alert boxes with instantaneous pre-submission constraint modals across POS checkout, stock transfers, customer debt payments, loss adjustments, and sales returns.
  - **POS Golden Law Stock Verification**: Blocks sale completion if attempting to sell with Handover = `🟢 SUPPLIED` when physical shelf stock is 0 or less than the requested quantity, explaining the exact deficiency and recommending `🟠 NOT SUPPLIED` for delayed pickup.
  - **Credit & Debt Limit Enforcement**: Blocks credit sales if GSM phone number is missing/invalid, if customer is an anonymous "Walk-in", or if new debt exceeds the customer's credit limit with exact overage amounts.
  - **Unsupplied Order Identification**: Prevents delayed pickup orders from being booked anonymously to ensure audit tracking.
  - **Cross-Module Constraint Enforcements**:
    - **Stock Transfers**: Blocks identical source/destination branch dispatches, zero quantities, and missing driver names.
    - **Debt Repayments**: Blocks overpayments exceeding outstanding debt balance, zero/negative amounts, and missing payment modes.
    - **Loss & Damage Adjustments**: Blocks write-offs with missing incident reasons or invalid quantities.
    - **Sales Returns**: Blocks returns exceeding original invoice quantities or missing return reasons.

### Fixed
- **POS Instant Multi-Attribute & SKU Code Search**:
  - **The Root Cause**: The POS product cards were missing the `data-code` HTML attribute, and the client-side JavaScript search function was only comparing queries against the product name (`data-name`), ignoring factory SKU codes, brands, and sizes.
  - **Multi-Attribute Matching**: Enhanced `applyProductFilters()` in `resources/views/pos/index.blade.php` to search across SKU code (`data-code`), commercial name (`data-name`), brand (`data-brand`), size (`data-size`), and category.
  - **Fuzzy & Barcode Scanner Support**: Added symbol-tolerant matching (ignores spaces/hyphens) and instant `Enter` key handling for hardware barcode scanners to automatically select and add products to the cart upon scan.

### Added
- **Production Master Catalog Initialization (Hysam Nwaniba)**:
  - Cleaned all seeded/dummy test sales and transactions for a clean production start.
  - Imported 529 verified SKU mattress, foam, and pillow products with 337 physical opening stock units (₦38.3M asset valuation) into the primary Nwaniba store.
- **Multi-Branch Location Filtering & Sleek Executive Dashboard Redesign**:
  - **Clean Dropdown Controls**: Unified both Branch Location and Time Period controls into matching side-by-side executive dropdown selectors with auto-submit and collapsible custom date range picker.
  - **Branch Location Selector**: Added a top branch dropdown allowing instantaneous switching between `All Branches (Consolidated)` and specific shop/warehouse branches.
  - **Location-Isolated Metrics**: When a specific branch is selected, physical stock valuations, stock inflow/outflow, low stock alerts, cashier cash/POS collections, in-transit transfers, and unsupplied backlog are isolated to that branch.
  - **Multi-Branch Comparison Cards**: Automatically displays side-by-side branch health cards (physical stock units, valuation, low stock alerts) when viewing the consolidated enterprise view.
  - **Modern Luminous UI**: Redesigned the dashboard with rich glassmorphism aesthetics, 4 top hero KPI cards, and 3 organized operational panels (Payment Flow, Stock Flow, Anti-Theft & Loss Radar).
  - **Removed Legacy Action Tiles**: Completely removed the bottom action navigation buttons and clutter for a streamlined, executive command experience.
  - **Feature Test Suite**: Added branch location filtering tests in `DashboardDateFilterTest.php` ($100\%$ pass).
- **Exportable Filtered Datasets across All 8 Tabs in Universal History**:
  - **Memory-Safe CSV & Structured JSON Streaming**: Added live **"📥 Export Filtered CSV"** and **"📄 Export JSON"** action buttons across all 8 tabs in Universal History (`/transactions`):
    1. `sales`: Sales Invoices History filtered by Date, Payment Status (`PAID`/`PART_PAID`/`NOT_PAID`), Delivery Status, Cashier, Customer search.
    2. `stock_in`: Stock In History filtered by Date, Inflow Category (`Supplier`/`Opening`/`Audit`), Product SKU/Name, Staff.
    3. `stock_out`: Stock Out & Dispatches filtered by Date, Outflow Type (`Pickup`/`Transfer Out`/`Damage`/`Expired`/`Lost`), Product/Notes, Staff.
    4. `in_transit`: In-Transit Buffer filtered by Date, Carrier Driver, Origin/Destination Branch, Dispatched By.
    5. `transfers_in` / `incoming`: Incoming Branch Transfers filtered by Date, Transfer Status (`RECEIVED`/`DISCREPANCY`/`DISPATCHED`), Branch, Carrier.
    6. `returns`: Customer Returns & Shelf Restitutions filtered by Date, Return Reason, Product/Sale Ref, Staff.
    7. `refunds`: Refunds & Financial Reversals filtered by Date, Min/Max Amount, Customer, Sale Ref, Staff.
    8. `debts`: Customer Debts & Repayment Ledgers filtered by Date, Ledger Type (`PAYMENT`/`INVOICE`/`RETURN_CREDIT`), Payment Method, Customer, Reference #.
  - **Shared Query Builder Architecture**: Extracted query building logic in `TransactionController` into dedicated reusable methods ensuring exact parity between UI tables and CSV/JSON export files.
  - **Automated Feature Tests**: Created `tests/Feature/TransactionExportTest.php` verifying CSV streaming and JSON structure across all 8 tabs.
- **Date-Filterable Executive Dashboard**:
  - **Interactive Period Presets**: Added quick filter pills for `Today`, `Yesterday`, `This Week`, `This Month`, `This Year`, `All-Time`, and a custom date range picker (`from_date` to `to_date`).
  - **Comprehensive Live Business KPIs**:
    - **Sales & Cash Inflow**: Gross Sales (₦), Transactions Count, Cash Drawer Collected (₦), POS / Card & Bank Collected (₦), and New Credit / Debt Incurred (₦).
    - **Stock Flow & Inventory Logistics**: Units In (+), Units Out (-), Total Stock Valuation (₦), and Low Stock / Out of Stock SKU Alerts.
    - **Fulfillment & Liabilities**: Unsupplied Orders Count with real-time monetary liability (₦).
    - **Debt Recoveries**: Installment debt payments collected in the selected period (₦), active debtors count, and all-time outstanding debt.
    - **Anti-Theft Radar & Losses**: Real-time Transfer Discrepancy Alerts, In-Transit shipments, and damaged goods write-offs.
    - **Returns & Refunds**: Total returned order counts, units returned, and refund amounts in the period.
  - **Dedicated Dashboard Controller**: Created `App\Http\Controllers\Web\DashboardController` adhering to PSR-12 and modular architecture.
  - **Automated Feature Tests**: Created `tests/Feature/DashboardDateFilterTest.php` verifying default Today view, custom date presets, stock movements, and debt recovery calculations.

### Removed
- **Cashier Shift Balancing Feature**: Completely removed cashier shift balancing workflows, modal forms, routes (`/close-shift`), shift reports table, and JSON shift exports across the entire codebase to streamline closing operations.

### Added
- **Dedicated Returns & Refunds Audit Report Tab & Downloads**:
  - **Tabbed Returns View**: Integrated a dedicated returns tab into the reports page (`reports/index.blade.php`) detailing product name, invoice ID, quantity returned, refunded/credited amount, reasoning, date, and handling staff member.
  - **Summary Metric Card**: Shows the sum of all refunds (`Total Value Refunded/Credited`) dynamically updating with date presets and cashier search filters.
  - **Returns Export Deck**: Supported exporting returned items reports to standard CSV and raw JSON format for AI parsing.
- **10+ Year Enterprise Data Scalability & Database Indexing**:
  - **High-Performance Query Indexes**: Added composite database indexes across high-volume transaction tables (`sales(createdAt, deliveryStatus, userId, customerName)`, `sale_items(productId)`, `inventory_logs(timestamp, type)`, and `activities(timestamp, type)`) to maintain sub-10ms query execution across millions of records.
  - **Memory-Safe Export Streaming**: Converted all CSV export generators in `ReportController.php` to use Laravel Eloquent `cursor()` PHP Generators, ensuring reports stream directly to output in constant $O(1)$ RAM (<15MB memory) regardless of whether the dataset contains 10,000 or 10,000,000 sales invoices.
- **Cross-Application Action Confirmation Popups (What Will Happen)**:
  - Implemented descriptive pre-execution confirmation popups and modals across every interactive action in the application to prevent human errors and explain operational impacts:
    - **POS Checkout Modal** (`pos/index.blade.php`): Full breakdown popup detailing Customer, Total Bill, Amount Paying Now, Debt Ledger impact, and Shelf Stock deduction vs Unsupplied buffer retention before completing sale.
    - **Customer Debt Repayment** (`debts/index.blade.php`): Confirmation popup detailing customer name, payment amount, previous debt, and exact new remaining balance.
    - **Stock In / Arrived Goods** (`stock/index.blade.php`): Confirmation popup confirming physical stock increment and audit logging.
    - **Inter-Branch Transfer Dispatch & Receiving** (`stock/index.blade.php`, `stock/transfers.blade.php`): Confirmation popups detailing origin stock deduction, in-transit buffer, driver details, physical count verification, and discrepancy alerts.
    - **Unsupplied Order Dispatch** (`stock/unsupplied.blade.php`): Confirmation popup detailing customer name, invoice #, and items being released from shop.
    - **Sales Returns & Refunds** (`pos/returns.blade.php`): Confirmation popup detailing items returned to shelf stock and cash refund / debt reduction amount.
    - **Damaged Stock Adjustments** (`stock/adjustments.blade.php`): Confirmation popup detailing product write-off loss and physical closing stock deduction.
    - **Product Catalog Management** (`products/index.blade.php`): Confirmation popups for product creation, catalog edits, and bulk CSV imports.
    - **User Administration** (`users/index.blade.php`): Confirmation popups for worker account creation, locking/unlocking worker access, and password resets.
    - **Settings & Branch Locations** (`settings/index.blade.php`): Confirmation popups for business profile updates, warehouse activation/deactivation, new branch creation, and instant DB backups.
- **Standardized Payment & Fulfillment Terminology Matrix**:
  - Implemented explicit terminology across the entire application:
    - Payment States: **Paid** (full settlement), **Part-Paid** (deposit with remaining debt balance), and **Not Paid** (100% credit sale).
    - Fulfillment States: **Supplied** (goods physically collected/taken away) and **Not Supplied** (goods retained in shop buffer awaiting pickup).
    - 4-State & Composite Badges: **Paid & Supplied**, **Paid & Not Supplied**, **Part-Paid & Supplied**, **Part-Paid & Not Supplied**, **Not Paid & Supplied**, and **Not Paid & Not Supplied**.
  - **In-App FAQs and Workflow Documentation** (`/help`):
    - Added comprehensive explanations and accordion FAQs for Cashiers, Storekeepers, Managers, and Auditors detailing how Part Payments are mathematically calculated, ledgered, and recovered in installments.
    - Added visual workflow guide illustrating the 4 key combinations and why physical closing stock is strictly preserved until goods are marked as *Supplied*.
- **Receipt Handover & Payment Composite Badges** (`pos/receipt.blade.php`):
  - Updated receipts to prominently print clear status badges: `PAID & SUPPLIED`, `PAID & NOT SUPPLIED`, `PART-PAID & SUPPLIED`, `PART-PAID & NOT SUPPLIED`, `NOT PAID & SUPPLIED`, and `NOT PAID & NOT SUPPLIED`.
  - Added line item showing `Amount Paid: ₦... (PAID / PART-PAID / NOT PAID)`, remaining `Debt Balance (PART-PAID): ₦...`, and `Goods Status: ✓ SUPPLIED / ⏳ NOT SUPPLIED`.

### Changed
- **POS Checkout Sidebar Form** (`pos/index.blade.php`):
  - Standardized handover toggle labels to `🟢 SUPPLIED (Customer took goods away - Deduct stock)` and `🟠 NOT SUPPLIED (Goods stay in shop for pickup - Keep in stock)`.
  - Updated payment mode tabs to `Paid (Cash)`, `Paid (POS/Bank)`, and `Part-Paid / Not Paid`.
- **Transactions History & Sales Reports Filter Engines** (`transactions/index.blade.php`, `reports/index.blade.php`, `TransactionController.php`, `ReportController.php`):
  - Added filter options for `PAID`, `PART_PAID`, `NOT_PAID`, `SUPPLIED`, `NOT_SUPPLIED`, and composite filters (`PAID_SUPPLIED`, `PAID_NOT_SUPPLIED`, `PART_PAID_SUPPLIED`, `PART_PAID_NOT_SUPPLIED`).
  - Standardized table badge columns to show clear `Paid`, `Part-Paid`, `Not Paid` alongside `Supplied`, `Not Supplied`.
- **Stock Pickup & Dashboard Tiles** (`stock/unsupplied.blade.php`, `stock/index.blade.php`, `dashboard.blade.php`, `auditor/index.blade.php`):
  - Renamed unsupplied orders references to `Goods Sold & Not Supplied (Awaiting Pickup)` and dispatch button to `📦 Mark as Supplied (Handover Goods)`.

### Testing & Verification
- **Receipt Verification Suite** (`verify_receipt_handover.php`): Verified all 4 payment and handover combinations across 18 automated assertions ($100\%$ pass).
- **PHPUnit Feature Suite** (`tests/Feature/SystemIntegrityAuditTest.php`): 6/6 tests passing (50 assertions) verifying composite badges, debt generation, and unsupplied buffer transitions.
- **7-Tier Business Logic & Mathematical Audit** (`verify_system_suite.php`): All 7 tiers verified ($100\%$ pass across all 29 routes).

### Security & Governance
- **Central Catalog Authority Enforcement**: Restricted product creation, master price editing, CSV bulk imports, and archiving strictly to `admin` (Auditor / Super Admin). Branch Managers and Sales & Stock staff can add stock quantities (`Stock In`) for catalog items while keeping product definitions centralized and tamper-proof.

### Added
- **Products Catalog Multi-Filter Deck & Multi-Format Exports** (`/products`):
  - **Stock Health & Criteria Filters**: Filter products by Stock Health pills (`🟢 In Stock`, `🟡 Low Stock ≤ 5`, `🔴 Out of Stock 0`), Category, Min/Max Price (₦), and keyword search.
  - **Multi-Format Exports**: Export catalog to **CSV (for Excel / Sheets)**, **JSON (for AI Prompting)**, and printable Master Price List.
- **Bulk CSV Product Import & Sample Template** (`/products/import/csv` & `/products/template/csv`):
  - Added bulk product import engine supporting automatic SKU generation, category/brand mapping, price updating, and initial physical stock distribution to branches.
  - Added one-click downloadable sample CSV template with standard columns: `name, code, category, brand, size, unitPrice, minStockLevel, initial_stock`.
- **Executive Owner (View-Only / Silent Auditor) Role (`viewer`)**:
  - Implemented read-only executive role for business owners and investors to monitor multi-branch sales, inventory valuations, debt levels, transfer logistics, and reports from any device without write permissions.
  - Added gold badge styling in Workers hub and dedicated training track in `/help`.
- **Combined Sales & Stock Role (`sales_stock`)**:
  - Implemented single-staff branch attendant role combining POS selling, price bargaining, customer debts, supplier goods receipt, accepting transfer shipments, and damaged stock logging.
  - Updated Workers Management modal with `💼 Sales & Stock Officer (Combined)` option.
  - Added dedicated training track in `/help` for solo shop attendants.
- **Printable Inter-Branch Transfer Waybills** (`/stock/waybill/{id}`): Official printable delivery manifest with sender/destination branch details, product breakdown, and physical signature boxes for dispatch officer, carrier driver, and storekeeper.
- **Enriched Executive Business Intelligence & Reports Hub** (`/reports`):
  - **Global Multi-Criteria Filter Deck**: Quick date presets (Today, Yesterday, This Week, This Month, This Year, All Time), custom date ranges, Cashier/Staff picker, Payment status (Paid vs Debt), and Handover status (Delivered vs Pickup).
  - **6-Card Executive KPI Deck**: Total Filtered Revenue, Cash Realized, New Debts Created, Physical Stock Asset Value, Transfer Discrepancy Units, and Damaged Goods Losses.
  - **Rankings & Insights Deck**: Top 5 best-selling products by revenue/volume and top staff by sales volume.
  - **7 Tabbed Report Tables**: Sales & Invoices, Multi-Branch Stock & Valuation Health, Transfers & Waybills, Debtors Aging (0-7d, 8-30d, 30+d critical), Damaged Stock, Cashier Shift Balancing, and AI Export Cards.
  - **Structured CSV and JSON Data Exports**: Formatted datasets for Excel, PowerBI, and AI prompt analysis.
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
