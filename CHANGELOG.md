# Changelog

All notable changes to this project are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Added
- **Web Installer Wizard (CodeCanyon style)**:
  - 6-step interactive installation wizard at `/install` (Welcome, Requirements check, Database config with live PDO connection test, Admin account creation, Migration runner with animated progress, and Completion).
  - `InstallerController` handling installer steps, .env generation, migration execution, and admin account setup.
  - `CheckInstalled` middleware to automatically redirect uninstalled instances to `/install` and lock down the installer after completion.
  - Blade templates for all installer steps and dashboard placeholder (`resources/views/installer/*`, `resources/views/dashboard.blade.php`).
- Core inventory management schema migrations: `products` and `suppliers` tables.
- Role-Based Access Control (RBAC) via Laravel Gates and Policies (Admin, Manager, Staff).
- Backup & Restore module with Artisan commands (`backup:run`, `backup:restore`).
- Activity audit log for all CRUD operations.
- Blade-based admin panel (no Node.js/Vite/React).
- API versioned routes under `/api/v1/*` protected by Laravel Sanctum.
- CSV/PDF export for inventory valuation, stock movement, and supplier performance.
- Database migrations: `products`, `suppliers`, `warehouses`, `stock_levels`, `backup_logs`.
- Docker + Docker Compose LEMP stack (PHP 8.3-FPM, MySQL, Redis, Nginx).
- GitHub Actions CI workflow (lint, test, build).
- AI Agent Engineering Rules (`docs/ai_agent_rules.md`) — mandatory commit & changelog tracking rules for all AI agents.
- Business Logic, Audit & Anti-Theft Specification (`docs/business_rules.md`) — complete domain specification covering physical closing stock, two-step inter-location transfers, supplied vs. unsupplied fulfillment states, customer debt/part-payments, and auditor reconciliation controls.
- Whogohost hosting guide (VPS + Shared Hosting + Web Installer) in README.

### Changed
- Configured Git remote origin to `https://github.com/hotvictorious-rgb/hysam.git`.
- Updated `.gitignore` to ignore archive files (`*.zip`) and installer lock file (`storage/installed`).
- Rewrote `README.md` to fully reflect Laravel 10 / PHP 8.3 stack with Web Installer instructions.
- Cleaned up `composer.json` to remove Node.js/npm setup scripts.
- Updated `.env.example` to remove Gemini keys and reflect correct driver options.
- Updated `bootstrap/app.php` to register `CheckInstalled` middleware globally across web routes.
- Updated `routes/web.php` with 8 installer routes and Blade dashboard route.

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
