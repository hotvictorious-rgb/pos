# Whogohost cPanel Shared Hosting & Update Guide for Hysam Ventures

> **Target Platform:** Whogohost Shared Hosting (cPanel)  
> **PHP Version Required:** PHP 8.2 or 8.3  
> **Application Version:** 1.0.0 (Pure Laravel 10)  

---

## 🌟 PART 1: Initial Step-by-Step Installation

### Step 1: Select PHP Version in cPanel
1. Log in to your **Whogohost cPanel**.
2. Under the **Software** section, click on **Select PHP Version** (or **MultiPHP Manager**).
3. Set your domain's PHP version to **PHP 8.2** or **PHP 8.3**.
4. Ensure the following extensions are checked/enabled:
   - `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `curl`, `bcmath`, `fileinfo`, `zip`.

---

### Step 2: Create MySQL Database & User in cPanel
1. In cPanel, go to **Databases** ➔ **MySQL Databases**.
2. **Create New Database:** e.g., `whogo_hysam`. Click *Create Database*.
3. **Create New User:** Scroll down to *MySQL Users*. Enter username (e.g., `whogo_user`) and a strong password. Click *Create User*.
4. **Add User to Database:** Scroll to *Add User To Database*, select your user and database, and click *Add*.
5. Select **ALL PRIVILEGES** and click *Make Changes*.
6. 📝 *Note down:*
   - Database Name: `cpaneluser_hysam`
   - Database User: `cpaneluser_user`
   - Database Password: `YourPassword123`
   - Host: `localhost` (or `127.0.0.1`)

---

### Step 3: Prepare & Upload Files

#### Recommended Secure Directory Structure on cPanel:
```
/home/yourusername/
├── hysam/                 <-- ALL project files (app, bootstrap, config, vendor, etc.)
└── public_html/           <-- ONLY the files inside the public/ folder
```

#### Steps to Upload:
1. On your computer, open `c:\Users\USER\Downloads\hysam`.
2. Zip the contents of the project (excluding `node_modules` and `.git`).
3. In cPanel, open **File Manager**.
4. Go to your home directory `/home/yourusername/` (one level above `public_html`).
5. Create a folder named `hysam` and upload your zip file there.
6. Extract the zip inside `/home/yourusername/hysam/`.
7. Move all files from inside `/home/yourusername/hysam/public/` directly into `/home/yourusername/public_html/`.
8. Open `/home/yourusername/public_html/index.php` in the cPanel code editor and change:
   ```php
   // Replace lines 34 and 47 to point to your hysam folder:
   require __DIR__.'/../hysam/vendor/autoload.php';
   $app = require_once __DIR__.'/../hysam/bootstrap/app.php';
   ```

---

### Step 4: Set File Permissions
In cPanel File Manager:
- Go to `/home/yourusername/hysam/storage` ➔ Right click ➔ **Change Permissions** ➔ Set to `775` (or `777`).
- Go to `/home/yourusername/hysam/bootstrap/cache` ➔ Right click ➔ **Change Permissions** ➔ Set to `775` (or `777`).

---

### Step 5: Run the Web Installation Wizard
1. Open your browser and visit:
   ```
   https://yourdomain.com/install
   ```
2. **Step 1 – Welcome:** Tap *Get Started*.
3. **Step 2 – Requirements Check:** The system automatically verifies your PHP version and extensions. Tap *Continue*.
4. **Step 3 – Database Setup:**
   - Host: `127.0.0.1` (or `localhost`)
   - Port: `3306`
   - Database Name: `cpaneluser_hysam`
   - Database Username: `cpaneluser_user`
   - Database Password: `YourPassword123`
   - Tap *Test & Continue*. (The wizard automatically tests the connection and writes your `.env`!).
5. **Step 4 – Admin Account:** Enter Super Admin name, email, and password. Tap *Install Now*.
6. **Step 5 & 6 – Automated Setup:** The wizard creates all tables, seeds default shops/warehouses, locks the installer for security, and directs you to the Dashboard!

---

### Step 6: Set up Daily Automated Cron Job
To automate low-stock alerts, backups, and shift summaries:
1. In cPanel, search for **Cron Jobs**.
2. Under *Common Settings*, select **Once Per Minute (* * * * *)**.
3. In the command box, enter:
   ```bash
   /usr/local/bin/php /home/yourusername/hysam/artisan schedule:run >> /dev/null 2>&1
   ```
4. Click *Add New Cron Job*.

---

## 🔄 PART 2: How to Deploy Future Updates (Zero Downtime & Zero Data Loss)

When you receive new code updates, features, or bug fixes for Hysam Ventures, follow these 4 simple steps to update your live site without touching your existing sales or database records:

### 1. Take a Safety Backup First
- Go to cPanel ➔ **phpMyAdmin** ➔ Select your database ➔ Click **Export** ➔ Click **Go** (saves SQL backup to your computer).
- In Hysam Ventures dashboard, you can also click **Backup Database** to download a snapshot.

### 2. Upload Updated Files
Upload and replace only the updated folders:
- `/app/`
- `/resources/`
- `/routes/`
- `/database/migrations/`
- `/vendor/` (only if new PHP packages were added)

> ⚠️ **NEVER OVERWRITE:**
> - Do **NOT** overwrite `.env` (this contains your live database connection).
> - Do **NOT** overwrite `storage/` (this contains your receipts, logs, and uploads).

### 3. Run New Migrations (if database changed)
If the update includes new tables or columns:
- **Option A (Via cPanel Terminal):**
  ```bash
  cd /home/yourusername/hysam
  php artisan migrate --force
  php artisan config:clear
  php artisan view:clear
  ```
- **Option B (Without Terminal):**
  Contact Whogohost live support or import the new migration SQL script via phpMyAdmin.

### 4. Clear Caches
Via cPanel Terminal:
```bash
php artisan optimize:clear
```
Your live system is now updated with all existing stock, sales, and customer debts intact!

---

*Hysam Ventures – Official Deployment Guide for Whogohost Shared Hosting.*
