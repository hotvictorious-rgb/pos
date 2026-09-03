<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\Installer\InstallerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PosController;
use App\Http\Controllers\Web\StockController;
use App\Http\Controllers\Web\AuditorController;
use App\Http\Controllers\Web\DebtController;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Transfer;

// ─────────────────────────────────────────────────────────
// INSTALLER ROUTES (accessible before the app is installed)
// ─────────────────────────────────────────────────────────
Route::prefix('install')->name('installer.')->group(function () {
    Route::get('/',            [InstallerController::class, 'welcome'])->name('welcome');
    Route::get('/requirements',[InstallerController::class, 'requirements'])->name('requirements');
    Route::get('/database',    [InstallerController::class, 'database'])->name('database');
    Route::post('/database',   [InstallerController::class, 'databaseSave'])->name('database.save');
    Route::get('/admin',       [InstallerController::class, 'admin'])->name('admin');
    Route::post('/admin',      [InstallerController::class, 'install'])->name('install');
    Route::post('/run',        [InstallerController::class, 'run'])->name('run');
    Route::get('/complete',    [InstallerController::class, 'complete'])->name('complete');
});

// ─────────────────────────────────────────────────────────
// PUBLIC MARKETING & LANDING PAGE (Nigerian Retail, Supermarkets & Wholesalers)
// ─────────────────────────────────────────────────────────
Route::get('/landing', [\App\Http\Controllers\Web\LandingController::class, 'index'])->name('landing');
Route::get('/welcome', [\App\Http\Controllers\Web\LandingController::class, 'index'])->name('welcome');

// ─────────────────────────────────────────────────────────
// AUTHENTICATION & SESSIONS
// ─────────────────────────────────────────────────────────
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'webLogin'])->name('login.post');
Route::match(['get', 'post'], '/logout', [AuthController::class, 'webLogout'])->name('logout');

// ─────────────────────────────────────────────────────────
// FOUR PORTAL AUTHENTICATION
// ─────────────────────────────────────────────────────────
// 1. Tenant Portal (Business Owners & Administrators)
Route::get('/tenant/login',                      [AuthController::class, 'showPortalLogin'])->defaults('portal', 'tenant')->name('portal.tenant.login');
Route::post('/tenant/login',                     [AuthController::class, 'portalLogin'])->defaults('portal', 'tenant')->name('portal.tenant.login.post');
Route::match(['get', 'post'], '/tenant/logout', [AuthController::class, 'portalLogout'])->defaults('portal', 'tenant')->name('portal.tenant.logout');

// 2. Tenant Employee Portal (Cashiers, Managers, Storekeepers)
Route::get('/tenant-employee/login',                      [AuthController::class, 'showPortalLogin'])->defaults('portal', 'tenant-employee')->name('portal.tenant_employee.login');
Route::post('/tenant-employee/login',                     [AuthController::class, 'portalLogin'])->defaults('portal', 'tenant-employee')->name('portal.tenant_employee.login.post');
Route::match(['get', 'post'], '/tenant-employee/logout', [AuthController::class, 'portalLogout'])->defaults('portal', 'tenant-employee')->name('portal.tenant_employee.logout');

// 3. Super Admin Portal (Platform Super-Administrators)
Route::get('/super-admin/login',                      [AuthController::class, 'showPortalLogin'])->defaults('portal', 'super-admin')->name('portal.super_admin.login');
Route::post('/super-admin/login',                     [AuthController::class, 'portalLogin'])->defaults('portal', 'super-admin')->name('portal.super_admin.login.post');
Route::match(['get', 'post'], '/super-admin/logout', [AuthController::class, 'portalLogout'])->defaults('portal', 'super-admin')->name('portal.super_admin.logout');

// 4. Super-Admin Employee Portal (Platform Staff & Auditors)
Route::get('/super-admin-employee/login',                      [AuthController::class, 'showPortalLogin'])->defaults('portal', 'super-admin-employee')->name('portal.super_admin_employee.login');
Route::post('/super-admin-employee/login',                     [AuthController::class, 'portalLogin'])->defaults('portal', 'super-admin-employee')->name('portal.super_admin_employee.login.post');
Route::match(['get', 'post'], '/super-admin-employee/logout', [AuthController::class, 'portalLogout'])->defaults('portal', 'super-admin-employee')->name('portal.super_admin_employee.logout');

// ─────────────────────────────────────────────────────────
// SAAS MULTI-TENANT & SUPER ADMIN ROUTES
// ─────────────────────────────────────────────────────────
Route::prefix('saas')->name('saas.')->group(function () {
    Route::get('/register',      [\App\Http\Controllers\SaaS\SaaSController::class, 'registerForm'])->name('register');
    Route::post('/register',     [\App\Http\Controllers\SaaS\SaaSController::class, 'processRegister'])->name('register.post');
    Route::get('/suspended',     [\App\Http\Controllers\SaaS\SaaSController::class, 'suspended'])->name('suspended');

    // Super Admin Master SaaS Control Panel
    Route::prefix('admin')->name('admin.')->middleware([\App\Http\Middleware\RequireSuperAdmin::class])->group(function () {
        Route::get('/',                 [\App\Http\Controllers\SaaS\SaaSController::class, 'adminIndex'])->name('index');
        Route::post('/settings',        [\App\Http\Controllers\SaaS\SaaSController::class, 'updateSettings'])->name('settings');
        Route::post('/tenant',          [\App\Http\Controllers\SaaS\SaaSController::class, 'storeTenant'])->name('tenant.store');
        Route::post('/toggle/{id}',     [\App\Http\Controllers\SaaS\SaaSController::class, 'toggleStatus'])->name('toggle');
        Route::post('/limits/{id}',     [\App\Http\Controllers\SaaS\SaaSController::class, 'updateTenantLimits'])->name('limits');
        Route::get('/impersonate/{id}', [\App\Http\Controllers\SaaS\SaaSController::class, 'impersonateTenant'])->name('impersonate');
        Route::get('/stop-impersonate', [\App\Http\Controllers\SaaS\SaaSController::class, 'stopImpersonation'])->name('stop_impersonate');
        Route::post('/delete/{id}',     [\App\Http\Controllers\SaaS\SaaSController::class, 'deleteTenant'])->name('delete');
    });
});

// ─────────────────────────────────────────────────────────
// API ROUTES (session-aware, CSRF exempt)
// ─────────────────────────────────────────────────────────
Route::prefix('api')->group(function () {
    Route::post('/login',  [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get('/data',   [DataController::class, 'get']);
    Route::post('/data',  [DataController::class, 'post']);
    Route::post('/reset', [DataController::class, 'reset']);

    // Backup & Restore
    Route::get('/backups',               [BackupController::class, 'index']);
    Route::post('/backups',              [BackupController::class, 'create']);
    Route::get('/backups/{id}/download', [BackupController::class, 'download']);
    Route::post('/backups/{id}/restore', [BackupController::class, 'restore']);
    Route::post('/backups/upload',       [BackupController::class, 'upload']);
    Route::delete('/backups/{id}',       [BackupController::class, 'destroy']);
});

// ─────────────────────────────────────────────────────────
// INTERACTIVE USER-FRIENDLY BLADE WEB APP
// ─────────────────────────────────────────────────────────

// 1. Executive Dashboard (Date Filterable)
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// 2. Visual Point of Sale (POS)
Route::prefix('pos')->name('pos.')->group(function () {
    Route::get('/',                     [PosController::class, 'index'])->name('index');
    Route::post('/checkout',            [PosController::class, 'checkout'])->name('checkout');
    Route::post('/customer/quick-register', [PosController::class, 'quickRegisterCustomer'])->name('customer.quick_register');
    Route::get('/receipt/{id}',         [PosController::class, 'receipt'])->name('receipt');
    Route::get('/returns',              [PosController::class, 'returns'])->name('returns');
    Route::post('/returns',             [PosController::class, 'processReturn'])->name('returns.process');
});

// 3. Products Catalog Management
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/',                     [\App\Http\Controllers\Web\ProductController::class, 'index'])->name('index');
    Route::get('/template/csv',         [\App\Http\Controllers\Web\ProductController::class, 'downloadCsvTemplate'])->name('template.csv');
    Route::get('/export/csv',           [\App\Http\Controllers\Web\ProductController::class, 'exportCsv'])->name('export.csv');
    Route::get('/export/json',          [\App\Http\Controllers\Web\ProductController::class, 'exportJson'])->name('export.json');
    Route::post('/import/csv',          [\App\Http\Controllers\Web\ProductController::class, 'importCsv'])->name('import.csv');
    Route::post('/',                    [\App\Http\Controllers\Web\ProductController::class, 'store'])->name('store');
    Route::post('/{id}',                [\App\Http\Controllers\Web\ProductController::class, 'update'])->name('update');
    Route::post('/{id}/delete',         [\App\Http\Controllers\Web\ProductController::class, 'destroy'])->name('destroy');
});

// 4. Stock Hub (Goods In, Transfers, Dispatch, Adjustments)
Route::prefix('stock')->name('stock.')->group(function () {
    Route::get('/',                     [StockController::class, 'index'])->name('index');
    Route::get('/transfers',            [StockController::class, 'transfersList'])->name('transfers');
    Route::get('/waybill/{id}',         [StockController::class, 'waybill'])->name('waybill');
    Route::post('/in',                  [StockController::class, 'stockIn'])->name('in');
    Route::post('/transfer-out',        [StockController::class, 'transferOut'])->name('transfer.out');
    Route::post('/transfer-in/{id}',    [StockController::class, 'transferIn'])->name('transfer.in');
    Route::post('/transfers/{id}/receive', [StockController::class, 'transferIn'])->name('transfers.receive');
    Route::post('/transfer-recall/{id}',[StockController::class, 'recallTransfer'])->name('transfer.recall');
    Route::get('/unsupplied',           [StockController::class, 'unsuppliedList'])->name('unsupplied');
    Route::post('/dispatch/{saleId}',   [StockController::class, 'dispatchConfirm'])->name('dispatch');
    Route::get('/adjustments',          [StockController::class, 'adjustments'])->name('adjustments');
    Route::post('/adjustments',         [StockController::class, 'recordAdjustment'])->name('adjustments.record');
});

// 5. Reports & AI Data Export Hub
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/',                             [\App\Http\Controllers\Web\ReportController::class, 'index'])->name('index');
    Route::get('/export-csv/{type}',            [\App\Http\Controllers\Web\ReportController::class, 'exportCsv'])->name('export.csv');
    Route::get('/export-json/{type}',           [\App\Http\Controllers\Web\ReportController::class, 'exportJson'])->name('export.json');
});


// 5. Auditor Anti-Theft & Reconciliation Hub
Route::prefix('auditor')->name('auditor.')->middleware([\App\Http\Middleware\RequireAdmin::class])->group(function () {
    Route::get('/',             [AuditorController::class, 'index'])->name('index');
});

// 6. Debt & Part-Payment Recovery Hub
Route::prefix('debts')->name('debts.')->group(function () {
    Route::get('/',             [DebtController::class, 'index'])->name('index');
    Route::post('/pay/{id}',    [DebtController::class, 'recordPayment'])->name('pay');
});

// 7. Dedicated Wholesale Operations & Office Pricing Hub (Admin/Executive Only)
Route::prefix('wholesale')->name('wholesale.')->middleware([\App\Http\Middleware\RequireAdmin::class])->group(function () {
    Route::get('/',                     [\App\Http\Controllers\Web\WholesaleController::class, 'index'])->name('index');
    Route::post('/price/{id}',          [\App\Http\Controllers\Web\WholesaleController::class, 'priceOrder'])->name('price');
    Route::get('/invoice/{id}',         [\App\Http\Controllers\Web\WholesaleController::class, 'commercialInvoice'])->name('invoice');
});

// 7. Transactions History & Audit Trail (Exportable)
Route::prefix('transactions')->name('transactions.')->group(function () {
    Route::get('/',                  [\App\Http\Controllers\Web\TransactionController::class, 'index'])->name('index');
    Route::get('/export-csv/{tab}',  [\App\Http\Controllers\Web\TransactionController::class, 'exportCsv'])->name('export.csv');
    Route::get('/export-json/{tab}', [\App\Http\Controllers\Web\TransactionController::class, 'exportJson'])->name('export.json');
});

// 8. Workers & Role Permissions Hub
Route::prefix('users')->name('users.')->middleware([\App\Http\Middleware\RequireAdmin::class])->group(function () {
    Route::get('/',                       [\App\Http\Controllers\Web\UserController::class, 'index'])->name('index');
    Route::post('/',                      [\App\Http\Controllers\Web\UserController::class, 'store'])->name('store');
    Route::post('/update/{id}',           [\App\Http\Controllers\Web\UserController::class, 'update'])->name('update');
    Route::post('/toggle/{id}',           [\App\Http\Controllers\Web\UserController::class, 'toggleStatus'])->name('toggle');
    Route::post('/reset-password/{id}',   [\App\Http\Controllers\Web\UserController::class, 'resetPassword'])->name('reset.password');
});

// 9. System Settings Hub
Route::prefix('settings')->name('settings.')->middleware([\App\Http\Middleware\RequireAdmin::class])->group(function () {
    Route::get('/',                       [\App\Http\Controllers\Web\SettingController::class, 'index'])->name('index');
    Route::post('/',                      [\App\Http\Controllers\Web\SettingController::class, 'update'])->name('update');
    Route::post('/warehouse',             [\App\Http\Controllers\Web\SettingController::class, 'storeWarehouse'])->name('warehouse.store');
    Route::post('/warehouse/update/{id}', [\App\Http\Controllers\Web\SettingController::class, 'updateWarehouse'])->name('warehouse.update');
    Route::post('/warehouse/toggle/{id}', [\App\Http\Controllers\Web\SettingController::class, 'toggleWarehouse'])->name('warehouse.toggle');
});

// 9. User Guide & Training Center
Route::get('/help', function () {
    return view('help.index');
})->name('help.index');




