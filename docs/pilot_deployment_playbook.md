# Pilot Merchant Deployment Playbook (v1.0.0-rc.1)

## Overview

- **Release Tag**: `v1.0.0-rc.1`
- **Base Commit**: `9e69aee`
- **Target Environments**: WhoGoHost cPanel Shared Hosting / Linux VPS
- **Target Audience**: Pilot Merchants & Retail Operations Team

---

## 1. Pre-Deployment Configuration Checklist

Before serving traffic to live merchants, verify the following `.env` settings:

```env
APP_NAME="Hysam Ventures POS"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_32_CHAR_KEY
APP_DEBUG=false
APP_URL=https://pos.yourdomain.com

# Lock Web Installer in Production
APP_INSTALLED=true
APP_INSTALLER_ENABLED=false

# Session Security (Enforces HTTPS cookie transport)
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Database Configuration (MySQL / MariaDB)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_cpanel_db
DB_USERNAME=your_cpanel_user
DB_PASSWORD="your_strong_db_password"

# SaaS Multi-Tenancy Engine
SAAS_ENABLED=true
SAAS_PLATFORM_NAME="Hysam Ventures POS"
SUPER_ADMIN_EMAIL=admin@yourdomain.com
SUPER_ADMIN_PASSWORD="your_strong_super_admin_password"
```

---

## 2. Server Document Root Configuration

### cPanel Shared Hosting (WhoGoHost)
1. In cPanel, navigate to **Domains** $\rightarrow$ **Manage Domain**.
2. Set the **Document Root** to point strictly to the `public/` subdirectory:
   - **Correct**: `/home/cpaneluser/public_html/public` or `/home/cpaneluser/repositories/pos/public`
   - **Incorrect**: `/home/cpaneluser/public_html` *(Exposes `.env`, `composer.json`, and `storage/`)*
3. In File Manager, ensure file permissions:
   - `.env`: `0600` or `0640`
   - `storage/` and `bootstrap/cache/`: `0755` (writable by web server process)

### VPS (Nginx / Apache)
Ensure the virtual host directive sets `root` to `/path/to/pos/public`:
```nginx
server {
    listen 443 ssl http2;
    server_name pos.yourdomain.com;
    root /var/www/pos/public;
    index index.php;
    
    # Block access to hidden files (.env, .git)
    location ~ /\. {
        deny all;
    }
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

---

## 3. Production Deployment Execution Steps

Run the following commands during deployment:

```bash
# 1. Pull the immutable release tag
git checkout tags/v1.0.0-rc.1

# 2. Install production dependencies (no dev dependencies)
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Run database schema migrations
php artisan migrate --force

# 4. Compile and cache configurations, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Ensure storage marker is set
touch storage/installed
```

---

## 4. Onboarding the First Pilot Merchant

1. **Access the Super-Admin Portal**:
   - Navigate to `https://pos.yourdomain.com/super-admin/login`.
   - Log in with `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD`.
2. **Provision Tenant**:
   - Create the tenant business (e.g., *Alaba Power Solutions Ltd*).
   - Set plan limits: max branches (e.g. 2), max users (e.g. 5).
3. **Branch & Staff Setup**:
   - Create initial shop branches (e.g. *Main Shop*, *Warehouse*).
   - Create the tenant administrator user and cashier accounts.
   - Hand off credentials via the Tenant Portal (`/tenant/login`) and Employee Portal (`/tenant-employee/login`).

---

## 5. Live Operational Telemetry & Monitoring

During the pilot run, periodically inspect the database logs:

| Metric / Event | Table / Command | What to Look For |
| :--- | :--- | :--- |
| **Worker Audits** | `SELECT * FROM activities ORDER BY id DESC LIMIT 50;` | Verify client IP, tenant ID, and actor roles are consistently logged. |
| **Inventory Invariants** | `SELECT * FROM inventory_logs ORDER BY id DESC LIMIT 50;` | Confirm all SALE, STOCK_IN, and TRANSFER operations match physical goods. |
| **Authentication Abuse** | Server error log / `storage/logs/laravel.log` | Check for repeated `429 Too Many Attempts` indicating brute-force or lockout events. |
| **Idempotency Replays** | `SELECT * FROM idempotency_records ORDER BY created_at DESC;` | Confirm double-clicks on POS checkout are captured and safely replayed. |
