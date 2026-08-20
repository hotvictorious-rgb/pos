<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\Installer\InstallerController;
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

// 1. Dashboard (Main Visual Menu)
Route::get('/', function () {
    $totalProducts = Product::where('archived', false)->count();
    $totalWarehouses = Warehouse::where('is_active', true)->count();
    $todaySalesCount = Sale::whereDate('created_at', today())->count();
    $todaySalesAmount = Sale::whereDate('created_at', today())->sum('totalAmount');
    $unsuppliedCount = Sale::where('deliveryStatus', 'UNSUPPLIED')->count();
    $totalDebt = Customer::sum('total_debt');
    $discrepancyCount = Transfer::where('status', 'DISCREPANCY')->count();

    return view('dashboard', compact(
        'totalProducts',
        'totalWarehouses',
        'todaySalesCount',
        'todaySalesAmount',
        'unsuppliedCount',
        'totalDebt',
        'discrepancyCount'
    ));
})->name('dashboard');

// 2. Visual Point of Sale (POS)
Route::prefix('pos')->name('pos.')->group(function () {
    Route::get('/',             [PosController::class, 'index'])->name('index');
    Route::post('/checkout',    [PosController::class, 'checkout'])->name('checkout');
    Route::get('/receipt/{id}', [PosController::class, 'receipt'])->name('receipt');
});

// 3. Stock Hub (Goods In, Transfers, Dispatch)
Route::prefix('stock')->name('stock.')->group(function () {
    Route::get('/',                     [StockController::class, 'index'])->name('index');
    Route::post('/in',                  [StockController::class, 'stockIn'])->name('in');
    Route::post('/transfer-out',        [StockController::class, 'transferOut'])->name('transfer.out');
    Route::post('/transfer-in/{id}',    [StockController::class, 'transferIn'])->name('transfer.in');
    Route::get('/unsupplied',           [StockController::class, 'unsuppliedList'])->name('unsupplied');
    Route::post('/dispatch/{saleId}',   [StockController::class, 'dispatchConfirm'])->name('dispatch');
});

// 4. Auditor Anti-Theft & Reconciliation Hub
Route::prefix('auditor')->name('auditor.')->group(function () {
    Route::get('/',             [AuditorController::class, 'index'])->name('index');
    Route::post('/close-shift', [AuditorController::class, 'closeShift'])->name('close.shift');
});

// 5. Debt & Part-Payment Recovery Hub
Route::prefix('debts')->name('debts.')->group(function () {
    Route::get('/',             [DebtController::class, 'index'])->name('index');
    Route::post('/pay/{id}',    [DebtController::class, 'recordPayment'])->name('pay');
});

// 6. Workers & Role Permissions Hub
Route::prefix('users')->name('users.')->group(function () {
    Route::get('/',                       [\App\Http\Controllers\Web\UserController::class, 'index'])->name('index');
    Route::post('/',                      [\App\Http\Controllers\Web\UserController::class, 'store'])->name('store');
    Route::post('/toggle/{id}',           [\App\Http\Controllers\Web\UserController::class, 'toggleStatus'])->name('toggle');
    Route::post('/reset-password/{id}',   [\App\Http\Controllers\Web\UserController::class, 'resetPassword'])->name('reset.password');
});

// 7. System Settings Hub
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/',                       [\App\Http\Controllers\Web\SettingController::class, 'index'])->name('index');
    Route::post('/',                      [\App\Http\Controllers\Web\SettingController::class, 'update'])->name('update');
    Route::post('/warehouse',             [\App\Http\Controllers\Web\SettingController::class, 'storeWarehouse'])->name('warehouse.store');
    Route::post('/warehouse/toggle/{id}', [\App\Http\Controllers\Web\SettingController::class, 'toggleWarehouse'])->name('warehouse.toggle');
});


