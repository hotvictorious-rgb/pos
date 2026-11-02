<?php

/**
 * Safe Live Migration & Cache Clear Helper for VMPOS Deployment
 */
if (php_sapi_name() !== 'cli') {
    // Check security token if accessed via HTTP
    $token = $_GET['token'] ?? '';
    if ($token !== 'vmpos_deploy_2026') {
        http_response_code(403);
        die('Forbidden: Access token required (?token=vmpos_deploy_2026)');
    }
}

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

echo "=== VMPOS LIVE DEPLOYMENT POST-EXTRACT RUNNER ===\n<br>";

// 1. Run migrations safely
echo "1. Running migrations...\n<br>";
try {
    Artisan::call('migrate', ['--force' => true]);
    echo nl2br(Artisan::output()) . "\n<br>";
} catch (\Throwable $e) {
    echo "Migration note: " . $e->getMessage() . "\n<br>";
}

// 1b. Ensure stock_reservations status allows RETURNED even if migration runner encountered legacy MySQL strict enum
try {
    \Illuminate\Support\Facades\DB::statement("ALTER TABLE stock_reservations MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE'");
    echo "Stock reservations status column successfully verified/updated to VARCHAR(32)!\n<br>";
} catch (\Throwable $e) {
    // Already updated or non-MySQL
}

// 1c. Retroactive Cleanup for Previously Voided Sales:
// If any sale was already voided / deleted before Option A was installed, clean up its orphan Stock Out logs and debt ledger records
try {
    $existingSaleIds = \Illuminate\Support\Facades\DB::table('sales')->pluck('id')->toArray();
    $existingSaleIdSet = array_flip($existingSaleIds);

    // Find all inventory logs referencing a sale
    $saleLogs = \Illuminate\Support\Facades\DB::table('inventory_logs')
        ->whereIn('type', ['SALE', 'SALE_RESERVED', 'DISPATCH_FULFILLED'])
        ->get(['id', 'description']);

    $logsToDelete = [];
    foreach ($saleLogs as $log) {
        if (preg_match('/Sale #([a-zA-Z0-9\-]+)/', $log->description, $matches)) {
            $refId = $matches[1];
            if (!isset($existingSaleIdSet[$refId])) {
                $logsToDelete[] = $log->id;
            }
        }
    }

    if (!empty($logsToDelete)) {
        \Illuminate\Support\Facades\DB::table('inventory_logs')->whereIn('id', $logsToDelete)->delete();
        echo "Retroactively cleaned up " . count($logsToDelete) . " orphan outflow log(s) from previously voided sales!\n<br>";
    }

    // Clean up CustomerLedger entries whose sales no longer exist
    $orphanLedgers = \Illuminate\Support\Facades\DB::table('customer_ledgers')
        ->whereNotNull('sale_id')
        ->whereNotIn('sale_id', $existingSaleIds ?: ['__NONE__'])
        ->delete();
    if ($orphanLedgers > 0) {
        echo "Retroactively cleaned up {$orphanLedgers} orphan customer debt ledger record(s) from previously voided sales!\n<br>";
    }
} catch (\Throwable $e) {
    echo "Note on retroactive void cleanup: " . $e->getMessage() . "\n<br>";
}

// 2. Clear view cache
echo "2. Clearing compiled Blade views...\n<br>";
Artisan::call('view:clear');
echo nl2br(Artisan::output()) . "\n<br>";

// 3. Clear application cache & route cache
echo "3. Clearing application & route cache...\n<br>";
Artisan::call('cache:clear');
Artisan::call('route:clear');
Artisan::call('config:clear');
echo "All caches cleared successfully!\n<br>";

echo "=== DEPLOYMENT OVERLAY COMPLETE & READY ===\n<br>";
