<?php

$zipPath = __DIR__ . '/vmpos_update_void_and_returns.zip';
if (file_exists($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Cannot open {$zipPath}\n");
}

$files = [
    'app/Http/Controllers/Web/PosController.php',
    'app/Http/Controllers/Web/StockController.php',
    'app/Http/Controllers/Web/TransactionController.php',
    'app/Http/Controllers/Web/DashboardController.php',
    'app/Services/StockService.php',
    'app/Services/TransactionVoidService.php',
    'app/Services/Accounting/AccountingReportService.php',
    'public/js/core-ui.js',
    'public/js/pos-engine.js',
    'resources/views/layouts/app.blade.php',
    'resources/views/help/index.blade.php',
    'resources/views/pos/index.blade.php',
    'resources/views/pos/receipt.blade.php',
    'resources/views/pos/returns.blade.php',
    'resources/views/products/index.blade.php',
    'resources/views/stock/adjustments.blade.php',
    'resources/views/stock/index.blade.php',
    'resources/views/stock/transfers.blade.php',
    'resources/views/stock/unsupplied.blade.php',
    'resources/views/transactions/index.blade.php',
    'resources/views/transactions/partials/panes.blade.php',
    'resources/views/debts/index.blade.php',
    'resources/views/users/index.blade.php',
    'resources/views/settings/index.blade.php',
    'routes/web.php',
    'run_live_migrations.php',
    'database/migrations/2026_09_16_160000_fix_stock_reservations_status_enum_to_varchar.php',
    'tests/Feature/TransactionVoidAndReturnEnhancementTest.php',
    'tests/Feature/UnsuppliedReturnAndSearchableInvoiceTest.php',
    'tests/Feature/TransactionVoidIntegrityGuardTest.php',
    'tests/Feature/NigerianMarket100ScenariosStressTest.php',
];

foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $zip->addFile($fullPath, $file);
        echo "Added: {$file} (" . filesize($fullPath) . " bytes)\n";
    } else {
        echo "WARNING: Missing {$file}\n";
    }
}

$zip->close();

echo "\nSUCCESS: Created " . basename($zipPath) . " (" . filesize($zipPath) . " bytes)\n";
