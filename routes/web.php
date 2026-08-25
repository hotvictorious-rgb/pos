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
// AUTHENTICATION & SESSIONS
// ─────────────────────────────────────────────────────────
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'webLogin'])->name('login.post');
Route::match(['get', 'post'], '/logout', [AuthController::class, 'webLogout'])->name('logout');

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

// 7. Dedicated Wholesale Operations & Office Pricing Hub
Route::prefix('wholesale')->name('wholesale.')->group(function () {
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




