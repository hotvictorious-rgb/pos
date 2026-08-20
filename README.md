# Hysam Ventures – Inventory Management System

<div align="center">
  <img width="1200" height="475" alt="Hysam Ventures Banner" src="https://ai.google.dev/static/site-assets/images/share-ais-513315318.png" />
</div>

<div align="center">

[![Laravel](https://img.shields.io/badge/Laravel-10.x-red?logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-blue?logo=php)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-orange?logo=mysql)](https://www.mysql.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![CI](https://img.shields.io/badge/CI-GitHub_Actions-black?logo=github)](https://github.com/features/actions)

</div>

---

## Overview

**Hysam Ventures** is a production-ready inventory management system built entirely on **Laravel 10 (PHP 8.3)**. Inspired by the Vmarket backend architecture, it delivers an API-first design with role-based access control, backup/restore, an admin panel rendered via Blade templates, and a complete Docker-based LEMP stack—**with no Node.js, Vite, or React dependencies**.

The application can run **fully offline** (no external APIs required) and is designed to be hosted on **Whogohost VPS** or any standard PHP/MySQL hosting environment.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Framework** | Laravel 10 |
| **Language** | PHP 8.3 |
| **Database** | MySQL 8.0 / MariaDB (Eloquent ORM) |
| **Cache & Queues** | Redis (VPS) or File driver (Shared Hosting) |
| **Authentication** | Laravel Sanctum – SPA tokens + session cookies |
| **Front-end** | Blade templates only (no React/Vite/Node.js) |
| **Containerisation** | Docker + Docker Compose (LEMP stack) |
| **Testing** | PHPUnit, Pest, ≥ 80 % coverage, PSR-12 |
| **CI/CD** | GitHub Actions |

---

## Core Features

| Category | Feature | Description |
|----------|---------|-------------|
| **Inventory** | Product Catalog | CRUD for products: SKU, name, description, price, category. |
| | Stock Management | Track quantity per warehouse, automatic low-stock alerts. |
| | Warehouse Locations | Multiple warehouses, bin-level tracking, transfers. |
| **Suppliers** | Supplier Management | Supplier details, contact info, lead-time tracking. |
| **Backup & Restore** | DB Snapshots | Create, download, upload, and restore database backups. Artisan commands: `backup:run` / `backup:restore`. |
| **Security** | RBAC | Admin, Manager, Staff roles with granular Gate/Policy permissions. |
| | Activity Log | Full audit trail for all CRUD operations on inventory data. |
| **API** | RESTful v1 Endpoints | All features exposed under `/api/v1/*` with versioned routing. |
| **Admin UI** | Blade Admin Panel | Clean, responsive Blade-based admin dashboard. |
| **Reports** | CSV / PDF Export | Inventory valuation, stock movement, supplier performance reports. |

---

## Architecture Overview

```
hysam/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/          # API controllers (Products, Suppliers, Stock, Backups)
│   │   │   └── Web/          # Blade admin controllers
│   │   ├── Middleware/       # Auth, RBAC, throttle middleware
│   │   └── Requests/         # Form Request validation classes
│   ├── Models/               # Eloquent models (Product, Supplier, Warehouse, StockLevel…)
│   ├── Policies/             # Gate/Policy RBAC definitions
│   └── Services/             # Business logic services (BackupService, etc.)
├── config/                   # Laravel config files
├── database/
│   ├── migrations/           # Schema migrations
│   └── seeders/              # Realistic seed data
├── docs/                     # Project documentation & AI agent rules
├── resources/views/          # Blade templates (admin panel UI)
├── routes/
│   ├── api.php               # /api/v1/* routes
│   └── web.php               # Blade admin routes
├── tests/
│   ├── Feature/              # Feature tests (HTTP, auth, CRUD)
│   └── Unit/                 # Unit tests (services, models)
├── Dockerfile                # PHP 8.3-FPM image
└── docker-compose.yml        # LEMP stack (PHP, MySQL, Redis, Nginx)
```

---

## API Reference (v1)

All routes are prefixed `/api/v1/` and require a Sanctum Bearer token (except `/login`).

| Method | Endpoint | Description |
|--------|---------|-------------|
| `POST` | `/api/v1/login` | Authenticate user, return token. |
| `POST` | `/api/v1/logout` | Invalidate token. |
| `GET` | `/api/v1/products` | List all products (paginated). |
| `POST` | `/api/v1/products` | Create a product. |
| `GET` | `/api/v1/products/{id}` | Get product details. |
| `PUT` | `/api/v1/products/{id}` | Update a product. |
| `DELETE` | `/api/v1/products/{id}` | Soft-delete a product. |
| `GET` | `/api/v1/stock` | Current stock levels per warehouse. |
| `POST` | `/api/v1/stock/transfer` | Transfer stock between warehouses. |
| `GET` | `/api/v1/suppliers` | List suppliers. |
| `POST` | `/api/v1/suppliers` | Create a supplier. |
| `GET` | `/api/v1/backups` | List backup snapshots. |
| `POST` | `/api/v1/backups` | Create a new backup. |
| `POST` | `/api/v1/backups/{id}/restore` | Restore from a backup. |

---

## Setup & Development

### Prerequisites

- PHP 8.3 or higher
- Composer
- MySQL 8.0 or MariaDB
- Redis (for VPS/Docker) — optional, falls back to file driver
- Docker & Docker Compose (optional)

### Local Installation

```bash
# 1. Clone the repository
git clone <repo-url>
cd hysam

# 2. Install PHP dependencies
composer install

# 3. Set up environment file
cp .env.example .env

# 4. Generate app key
php artisan key:generate

# 5. Configure your database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=hysam
# DB_USERNAME=root
# DB_PASSWORD=

# 6. Run migrations and seed sample data
php artisan migrate --seed

# 7. Start the development server
php artisan serve --host=127.0.0.1 --port=8000
```

Visit `http://127.0.0.1:8000` to access the Blade admin dashboard.

### Using Docker (Recommended)

```bash
docker-compose up -d        # Start PHP, MySQL, Redis, Nginx
docker-compose exec app php artisan migrate --seed
```

---

## Hosting on Whogohost

Hysam Ventures is designed to run on **Whogohost** hosting. Choose the right plan:

### Option A – Whogohost Shared Hosting (Basic)

> Suitable for testing or low-traffic deployments.

| Feature | Status |
|---------|--------|
| PHP 8.3 + Laravel 10 | ✅ Supported |
| MySQL | ✅ Supported |
| Redis | ❌ Use `file` driver instead |
| Queue Workers | ❌ Use `sync` driver instead |
| SSH / Artisan Access | ⚠️ Limited – request SSH from Whogohost support |
| Docker | ❌ Not supported |

**Required `.env` changes for shared hosting:**

```dotenv
APP_ENV=production
APP_DEBUG=false
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
```

**Deployment steps (shared hosting):**

1. Upload all files to your domain's public_html folder via FTP/File Manager.
2. Move contents of the `public/` folder into `public_html/` and update `index.php` paths.
3. Create your database via cPanel and import your SQL dump.
4. Set environment variables via cPanel → PHP Config or upload your `.env`.
5. Run `php artisan config:cache && php artisan route:cache` via SSH (or cPanel Terminal).

---

### Option B – Whogohost VPS / Cloud (Recommended ✅)

> Full control, all features enabled, production-grade.

| Feature | Status |
|---------|--------|
| PHP 8.3 + Laravel 10 | ✅ Full support |
| MySQL 8.0 | ✅ Full support |
| Redis | ✅ Install via `apt install redis-server` |
| Queue Workers | ✅ Run with Supervisor |
| SSH / Artisan | ✅ Full root access |
| Docker | ✅ Fully supported |
| Scheduler (`cron`) | ✅ `php artisan schedule:run` |

**VPS deployment steps:**

```bash
# 1. SSH into your VPS
ssh root@your-vps-ip

# 2. Install dependencies (Ubuntu/Debian)
apt update && apt install -y php8.3-fpm php8.3-mysql php8.3-redis \
  php8.3-xml php8.3-mbstring mysql-server redis-server nginx composer git

# 3. Clone the project
git clone <repo-url> /var/www/hysam
cd /var/www/hysam

# 4. Install PHP dependencies
composer install --no-dev --optimize-autoloader

# 5. Set up environment and run migrations
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# 6. Set permissions
chown -R www-data:www-data /var/www/hysam/storage /var/www/hysam/bootstrap/cache

# 7. Configure Nginx to point document root to /var/www/hysam/public
# 8. Set up Supervisor to run: php artisan queue:work
# 9. Add cron: * * * * * php /var/www/hysam/artisan schedule:run >> /dev/null 2>&1
```

---

## Testing

```bash
# Run full test suite
composer test

# Run PHPUnit directly
php artisan test

# Run with coverage report
php artisan test --coverage --min=80
```

---

## CI/CD (GitHub Actions)

The `.github/workflows/ci.yml` workflow runs on every push and pull request:

1. Install PHP 8.3 + Composer dependencies
2. Run PSR-12 linting (`phpcs`)
3. Execute full test suite (`composer test`)
4. Build and validate Docker image

---

## Contributing

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Follow the engineering standards in [`docs/ai_agent_rules.md`](docs/ai_agent_rules.md).
4. Ensure all tests pass: `composer test`.
5. Submit a pull request with a clear description.

---

## License

This project is licensed under the **MIT License** – see the [LICENSE](LICENSE) file for details.

---

*Hysam Ventures Inventory System – built with Laravel, designed for reliability, hosted on Whogohost.*
