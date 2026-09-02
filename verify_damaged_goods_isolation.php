<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockAdjustment;
use App\Http\Controllers\Web\StockController;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo "   DAMAGED GOODS 2-LEVEL ISOLATION (TENANT & BRANCH) PROOF AUDIT\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);

// Clean up test records
Tenant::whereIn('id', ['dmg-tenant-alpha', 'dmg-tenant-beta'])->delete();
Warehouse::withoutGlobalScopes()->whereIn('tenant_id', ['dmg-tenant-alpha', 'dmg-tenant-beta'])->delete();
User::withoutGlobalScopes()->whereIn('tenant_id', ['dmg-tenant-alpha', 'dmg-tenant-beta'])->delete();
Product::withoutGlobalScopes()->whereIn('tenant_id', ['dmg-tenant-alpha', 'dmg-tenant-beta'])->delete();
StockLevel::withoutGlobalScopes()->whereIn('tenant_id', ['dmg-tenant-alpha', 'dmg-tenant-beta'])->delete();
StockAdjustment::withoutGlobalScopes()->whereIn('tenant_id', ['dmg-tenant-alpha', 'dmg-tenant-beta'])->delete();

// 1. Setup Tenant Alpha with 2 Branches
$tenantA = Tenant::create([
    'id' => 'dmg-tenant-alpha',
    'name' => 'Grace Supermarket Ltd',
    'owner_email' => 'alpha_dmg@test.com',
    'owner_phone' => '08011112222',
    'plan' => 'pro',
    'status' => 'active',
]);

$branchA1 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Lekki Branch',
    'code' => 'DMG-LK-' . Str::random(4),
    'is_active' => true,
]);

$branchA2 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Ikeja Branch',
    'code' => 'DMG-IK-' . Str::random(4),
    'is_active' => true,
]);

$mgrA1 = User::create([
    'id' => 'user-mgr-dmg-a1',
    'tenant_id' => $tenantA->id,
    'name' => 'Manager Lekki Branch',
    'email' => 'mgr_dmg_a1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA1->id,
]);

$mgrA2 = User::create([
    'id' => 'user-mgr-dmg-a2',
    'tenant_id' => $tenantA->id,
    'name' => 'Manager Ikeja Branch',
    'email' => 'mgr_dmg_a2@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA2->id,
]);

$execA = User::create([
    'id' => 'user-exec-dmg-a',
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Executive Owner',
    'email' => 'exec_dmg_a@test.com',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'warehouse_id' => null,
]);

$prodA = Product::create([
    'id' => 'prod-dmg-alpha-01',
    'tenant_id' => $tenantA->id,
    'code' => 'JUICE-01',
    'name' => 'Chivita Juice 1L',
    'category' => 'Beverages',
    'unitPrice' => 1500,
    'currentStock' => 100,
    'minStockLevel' => 5,
    'archived' => false,
    'updatedAt' => now()->toIso8601String(),
]);

$stockA1 = StockLevel::create(['tenant_id' => $tenantA->id, 'product_id' => $prodA->id, 'warehouse_id' => $branchA1->id, 'physical_stock' => 50]);
$stockA2 = StockLevel::create(['tenant_id' => $tenantA->id, 'product_id' => $prodA->id, 'warehouse_id' => $branchA2->id, 'physical_stock' => 50]);

// 2. Setup Tenant Beta
$tenantB = Tenant::create([
    'id' => 'dmg-tenant-beta',
    'name' => 'Alhaji Grain Depot',
    'owner_email' => 'beta_dmg@test.com',
    'owner_phone' => '08033334444',
    'plan' => 'basic',
    'status' => 'active',
]);

$branchB1 = Warehouse::create([
    'tenant_id' => $tenantB->id,
    'name' => 'Alhaji Kano Branch',
    'code' => 'DMG-KN-' . Str::random(4),
    'is_active' => true,
]);

$mgrB1 = User::create([
    'id' => 'user-mgr-dmg-b1',
    'tenant_id' => $tenantB->id,
    'name' => 'Manager Kano Branch',
    'email' => 'mgr_dmg_b1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchB1->id,
]);

$prodB = Product::create([
    'id' => 'prod-dmg-beta-01',
    'tenant_id' => $tenantB->id,
    'code' => 'JUICE-01',
    'name' => 'Chivita Juice 1L',
    'category' => 'Beverages',
    'unitPrice' => 1600,
    'currentStock' => 100,
    'minStockLevel' => 5,
    'archived' => false,
    'updatedAt' => now()->toIso8601String(),
]);

$stockB1 = StockLevel::create(['tenant_id' => $tenantB->id, 'product_id' => $prodB->id, 'warehouse_id' => $branchB1->id, 'physical_stock' => 100]);

$stockController = new StockController(new StockService());

// ─────────────────────────────────────────────────────────
// TEST 1: RECORDING DAMAGED GOODS & TENANT LEVEL ISOLATION
// ─────────────────────────────────────────────────────────
echo "[1/3] Recording Damaged Goods in Tenant A (8 units) & Tenant B (12 units)...\n";

// Tenant A Lekki Manager records 8 damaged units
auth()->login($mgrA1);
session(['tenant_id' => $tenantA->id, 'active_warehouse_id' => $branchA1->id]);
$reqAdjA1 = Request::create('/stock/adjustments', 'POST', [
    'warehouse_id' => $branchA1->id,
    'product_id' => $prodA->id,
    'type' => 'DAMAGE',
    'quantity' => 8,
    'reason' => 'Leaking carton during transit',
]);
$stockController->recordAdjustment($reqAdjA1);

// Tenant A Ikeja Manager records 5 expired units
auth()->login($mgrA2);
session(['tenant_id' => $tenantA->id, 'active_warehouse_id' => $branchA2->id]);
$reqAdjA2 = Request::create('/stock/adjustments', 'POST', [
    'warehouse_id' => $branchA2->id,
    'product_id' => $prodA->id,
    'type' => 'EXPIRED',
    'quantity' => 5,
    'reason' => 'Past expiration date on shelf',
]);
$stockController->recordAdjustment($reqAdjA2);

// Tenant B Kano Manager records 12 lost units
auth()->login($mgrB1);
session(['tenant_id' => $tenantB->id, 'active_warehouse_id' => $branchB1->id]);
$reqAdjB1 = Request::create('/stock/adjustments', 'POST', [
    'warehouse_id' => $branchB1->id,
    'product_id' => $prodB->id,
    'type' => 'LOST',
    'quantity' => 12,
    'reason' => 'Missing during audit count',
]);
$stockController->recordAdjustment($reqAdjB1);

// Verify Tenant B view
$viewB = $stockController->adjustments(Request::create('/stock/adjustments', 'GET'));
$dataB = $viewB->getData();

echo "   • Tenant B Adjustments Count: " . $dataB['totalAdjustmentsCount'] . " (Expected: 1)\n";
echo "   • Tenant B Total Units Lost: " . $dataB['totalUnitsLost'] . " units (Expected: 12)\n";

if ($dataB['totalAdjustmentsCount'] !== 1 || $dataB['totalUnitsLost'] !== 12) {
    echo "❌ AUDIT FAILURE: Tenant B damaged goods view exposed Tenant A entries!\n";
    exit(1);
}
echo "   ✅ PASS: Level 1 Tenant Damaged Goods Isolation verified 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 2: BRANCH LEVEL DAMAGED GOODS ISOLATION (Lekki Manager)
// ─────────────────────────────────────────────────────────
echo "[2/3] Testing Level 2 Branch Damaged Goods Isolation (Lekki Branch Manager)...\n";
auth()->login($mgrA1);
session(['tenant_id' => $tenantA->id, 'active_warehouse_id' => $branchA1->id]);

$viewA1 = $stockController->adjustments(Request::create('/stock/adjustments', 'GET'));
$dataA1 = $viewA1->getData();

echo "   • Lekki Branch Adjustments Count: " . $dataA1['totalAdjustmentsCount'] . " (Expected: 1)\n";
echo "   • Lekki Branch Total Units Lost: " . $dataA1['totalUnitsLost'] . " units (Expected: 8)\n";

if ($dataA1['totalAdjustmentsCount'] !== 1 || $dataA1['totalUnitsLost'] !== 8) {
    echo "❌ AUDIT FAILURE: Lekki Manager exposed to Ikeja damaged goods entries!\n";
    exit(1);
}
echo "   ✅ PASS: Level 2 Branch Damaged Goods Isolation verified 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 3: HQ EXECUTIVE CONSOLIDATED DAMAGED GOODS AUDIT VIEW
// ─────────────────────────────────────────────────────────
echo "[3/3] Testing Tenant Executive HQ Consolidated Damaged Goods Audit View...\n";
auth()->login($execA);
session(['tenant_id' => $tenantA->id]);

$viewExec = $stockController->adjustments(Request::create('/stock/adjustments', 'GET'));
$dataExec = $viewExec->getData();

echo "   • Executive HQ Consolidated Adjustments Count: " . $dataExec['totalAdjustmentsCount'] . " (Expected: 2)\n";
echo "   • Executive HQ Consolidated Units Lost: " . $dataExec['totalUnitsLost'] . " units (Expected: 13)\n";

if ($dataExec['totalAdjustmentsCount'] !== 2 || $dataExec['totalUnitsLost'] !== 13) {
    echo "❌ AUDIT FAILURE: Executive HQ failed to see consolidated multi-branch damaged goods audit!\n";
    exit(1);
}
echo "   ✅ PASS: Executive HQ consolidated multi-branch damaged goods oversight verified.\n\n";

echo "====================================================================\n";
echo "🌟 DAMAGED GOODS ISOLATION VERDICT: 100% PASSED!\n";
echo "DAMAGED GOODS, EXPIRED WRITE-OFFS, & LOSS ADJUSTMENTS ARE AIRTIGHT ISOLATED.\n";
echo "====================================================================\n";
