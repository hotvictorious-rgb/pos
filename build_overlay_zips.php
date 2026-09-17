<?php

function createZip(string $zipName, array $files): void
{
    $zipPath = __DIR__ . '/' . $zipName;
    if (file_exists($zipPath)) {
        unlink($zipPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Cannot create {$zipPath}\n");
    }

    echo "=====================================================\n";
    echo "Building: {$zipName}\n";
    echo "=====================================================\n";

    $addedCount = 0;
    foreach ($files as $file) {
        $fullPath = __DIR__ . '/' . $file;
        if (file_exists($fullPath)) {
            // Add file preserving exact folder structure
            $zip->addFile($fullPath, $file);
            $size = filesize($fullPath);
            echo " [+] {$file} ({$size} bytes)\n";
            $addedCount++;
        } else {
            echo " [!] WARNING: File not found: {$file}\n";
        }
    }

    $zip->close();
    $totalSize = filesize($zipPath);
    echo "\n--> Successfully created {$zipName}: {$addedCount} files, " . number_format($totalSize / 1024, 2) . " KB\n\n";
}

// 1. Audit System Upgrade Overlay (Files modified for the Audit Logging & Row Details Modal)
$auditFiles = [
    'app/Http/Controllers/AuthController.php',
    'app/Http/Controllers/Web/AuditorController.php',
    'app/Http/Controllers/Web/PosController.php',
    'app/Http/Controllers/Web/ProductController.php',
    'app/Models/Activity.php',
    'resources/views/auditor/index.blade.php',
    'tests/Feature/AuditTrailCoverageAndDetailsTest.php',
];

createZip('vmpos_audit_upgrade_overlay.zip', $auditFiles);

// 2. Comprehensive Branch `vic` Live Overlay (All enhancements, POS engine, UI, reports, audits & voiding)
$allVicFiles = [
    'app/Http/Controllers/AuthController.php',
    'app/Http/Controllers/Web/AuditorController.php',
    'app/Http/Controllers/Web/DashboardController.php',
    'app/Http/Controllers/Web/PosController.php',
    'app/Http/Controllers/Web/ProductController.php',
    'app/Http/Controllers/Web/StockController.php',
    'app/Http/Controllers/Web/TransactionController.php',
    'app/Models/Activity.php',
    'app/Services/StockService.php',
    'app/Services/TransactionVoidService.php',
    'app/Services/Accounting/AccountingReportService.php',
    'public/js/core-ui.js',
    'public/js/pos-engine.js',
    'resources/views/auditor/index.blade.php',
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
    'tests/Feature/AuditTrailCoverageAndDetailsTest.php',
    'tests/Feature/NigerianMarket100ScenariosStressTest.php',
    'tests/Feature/TransactionVoidAndReturnEnhancementTest.php',
    'tests/Feature/TransactionVoidIntegrityGuardTest.php',
    'tests/Feature/UnsuppliedReturnAndSearchableInvoiceTest.php',
];

createZip('vmpos_complete_vic_overlay.zip', $allVicFiles);
