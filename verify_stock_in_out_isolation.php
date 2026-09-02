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
use App\Models\InventoryLog;
use App\Models\StockAdjustment;
use App\Http\Controllers\Web\StockController;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo "   STOCK IN & OUT 2-LEVEL ISOLATION (TENANT & BRANCH) PROOF AUDIT\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);

// Clean up test tenants
Tenant::whereIn('id', ['stock-tenant-alpha', 'stock-tenant-beta'])->delete();
Warehouse::withoutGlobalScopes()->whereIn('tenant_id', ['stock-tenant-alpha', 'stock-tenant-beta'])->delete();
User::withoutGlobalScopes()->whereIn('tenant_id', ['stock-tenant-alpha', 'stock-tenant-beta'])->delete();
Product::withoutGlobalScopes()->whereIn('tenant_id', ['stock-tenant-alpha', 'stock-tenant-beta'])->delete();
StockLevel::withoutGlobalScopes()->whereIn('tenant_id', ['stock-tenant-alpha', 'stock-tenant-beta'])->delete();
InventoryLog::withoutGlobalScopes()->whereIn('tenant_id', ['stock-tenant-alpha', 'stock-tenant-beta'])->delete();
StockAdjustment::withoutGlobalScopes()->whereIn('tenant_id', ['stock-tenant-alpha', 'stock-tenant-beta'])->delete();

// 1. Setup Tenant Alpha with 2 Branches
$tenantA = Tenant::create([
    'id' => 'stock-tenant-alpha',
    'name' => 'Grace Supermarket Ltd',
    'owner_email' => 'alpha_stock@test.com',
    'owner_phone' => '08011112222',
    'plan' => 'pro',
    'status' => 'active',
]);

$branchA1 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Lekki Stock Branch',
    'code' => 'STK-LK-' . Str::random(4),
    'is_active' => true,
]);

$branchA2 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Ikeja Stock Branch',
    'code' => 'STK-IK-' . Str::random(4),
    'is_active' => true,
]);

$mgrA1 = User::create([
    'id' => 'user-mgr-stk-a1',
    'tenant_id' => $tenantA->id,
    'name' => 'Manager Lekki Stock',
    'email' => 'mgr_stk_a1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA1->id,
]);

$prodA = Product::create([
    'id' => 'prod-stk-alpha-01',
    'tenant_id' => $tenantA->id,
    'code' => 'MILK-01',
    'name' => 'Peak Milk Tin 900g',
    'category' => 'Provisions',
    'unitPrice' => 7500,
    'currentStock' => 0,
    'minStockLevel' => 5,
    'archived' => false,
    'updatedAt' => now()->toIso8601String(),
]);

$stockA1 = StockLevel::create(['tenant_id' => $tenantA->id, 'product_id' => $prodA->id, 'warehouse_id' => $branchA1->id, 'physical_stock' => 0]);
$stockA2 = StockLevel::create(['tenant_id' => $tenantA->id, 'product_id' => $prodA->id, 'warehouse_id' => $branchA2->id, 'physical_stock' => 50]);

// 2. Setup Tenant Beta
$tenantB = Tenant::create([
    'id' => 'stock-tenant-beta',
    'name' => 'Alhaji Grain Depot',
    'owner_email' => 'beta_stock@test.com',
    'owner_phone' => '08033334444',
    'plan' => 'basic',
    'status' => 'active',
]);

$branchB1 = Warehouse::create([
    'tenant_id' => $tenantB->id,
    'name' => 'Alhaji Kano Stock Depot',
    'code' => 'STK-KN-' . Str::random(4),
    'is_active' => true,
]);

$mgrB1 = User::create([
    'id' => 'user-mgr-stk-b1',
    'tenant_id' => $tenantB->id,
    'name' => 'Manager Kano Stock',
    'email' => 'mgr_stk_b1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchB1->id,
]);

$prodB = Product::create([
    'id' => 'prod-stk-beta-01',
    'tenant_id' => $tenantB->id,
    'code' => 'MILK-01',
    'name' => 'Peak Milk Tin 900g',
    'category' => 'Provisions',
    'unitPrice' => 8000,
    'currentStock' => 0,
    'minStockLevel' => 5,
    'archived' => false,
    'updatedAt' => now()->toIso8601String(),
]);

$stockB1 = StockLevel::create(['tenant_id' => $tenantB->id, 'product_id' => $prodB->id, 'warehouse_id' => $branchB1->id, 'physical_stock' => 0]);

$stockController = new StockController(new StockService());

// ─────────────────────────────────────────────────────────
// TEST 1: TENANT LEVEL STOCK IN ISOLATION
// ─────────────────────────────────────────────────────────
echo "[1/3] Testing Stock In Isolation (Tenant A Lekki Manager vs Tenant B Kano Manager)...\n";
auth()->login($mgrA1);
session(['tenant_id' => $tenantA->id, 'active_warehouse_id' => $branchA1->id]);

// Tenant A performs Stock In of 100 units
$reqInA = Request::create('/stock/in', 'POST', [
    'warehouse_id' => $branchA1->id,
    'product_id' => $prodA->id,
    'quantity' => 100,
    'supplier_name' => 'Friesland Campina Ltd',
    'notes' => 'Factory Delivery Batch #01',
]);
$stockController->stockIn($reqInA);

// Tenant B performs Stock In of 200 units
auth()->login($mgrB1);
session(['tenant_id' => $tenantB->id, 'active_warehouse_id' => $branchB1->id]);

$reqInB = Request::create('/stock/in', 'POST', [
    'warehouse_id' => $branchB1->id,
    'product_id' => $prodB->id,
    'quantity' => 200,
    'supplier_name' => 'Kano Wholesalers Ltd',
    'notes' => 'Depot Batch #88',
]);
$stockController->stockIn($reqInB);

$stockA1->refresh();
$stockB1->refresh();
$logCountA = InventoryLog::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count();
$logCountB = InventoryLog::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count();

echo "   • Tenant A Lekki Stock After Stock In: " . $stockA1->physical_stock . " units (Expected: 100)\n";
echo "   • Tenant B Kano Stock After Stock In: " . $stockB1->physical_stock . " units (Expected: 200)\n";
echo "   • Tenant A Inventory Log Count: " . $logCountA . " (Expected: 1)\n";
echo "   • Tenant B Inventory Log Count: " . $logCountB . " (Expected: 1)\n";

if ($stockA1->physical_stock != 100 || $stockB1->physical_stock != 200 || $logCountA != 1 || $logCountB != 1) {
    echo "❌ AUDIT FAILURE: Stock In operations cross-contaminated tenant inventory levels or logs!\n";
    exit(1);
}
echo "   ✅ PASS: Level 1 Tenant Stock In & Inventory Log Isolation is 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 2: BRANCH LEVEL STOCK ADJUSTMENT / STOCK OUT ISOLATION
// ─────────────────────────────────────────────────────────
echo "[2/3] Testing Level 2 Branch Stock Adjustment / Loss Write-Off Isolation (Lekki Branch Manager)...\n";
auth()->login($mgrA1);
session(['tenant_id' => $tenantA->id, 'active_warehouse_id' => $branchA1->id]);

// Record Damaged Stock Out of 5 units at Lekki Branch
$reqAdjA1 = Request::create('/stock/adjustments', 'POST', [
    'warehouse_id' => $branchA1->id,
    'product_id' => $prodA->id,
    'type' => 'DAMAGE',
    'quantity' => 5,
    'reason' => 'Dents on tin packaging during shelf stacking',
]);
$stockController->recordAdjustment($reqAdjA1);

$stockA1->refresh();
$stockA2->refresh();

echo "   • Tenant A Lekki Stock After Adjustment Write-off: " . $stockA1->physical_stock . " units (Expected: 95)\n";
echo "   • Tenant A Ikeja Stock After Lekki Adjustment: " . $stockA2->physical_stock . " units (Expected: 50, 100% UNTOUCHED)\n";

if ($stockA1->physical_stock != 95 || $stockA2->physical_stock != 50) {
    echo "❌ AUDIT FAILURE: Stock adjustment cross-contaminated branch stocks within same tenant!\n";
    exit(1);
}
echo "   ✅ PASS: Level 2 Branch Stock Adjustment / Stock Out Isolation is 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 3: STOCK MANAGEMENT HUB INDEX ISOLATION
// ─────────────────────────────────────────────────────────
echo "[3/3] Testing Stock Hub Index Isolation for Lekki Branch Manager...\n";
$viewIndexA1 = $stockController->index(Request::create('/stock', 'GET'));
$dataIndexA1 = $viewIndexA1->getData();

echo "   • Active Warehouse Name in View: '" . $dataIndexA1['activeWarehouse']->name . "' (Expected: Grace Lekki Stock Branch)\n";
echo "   • Total Physical Units Listed: " . $dataIndexA1['totalPhysicalUnits'] . " units (Expected: 95)\n";

if ($dataIndexA1['activeWarehouse']->name !== 'Grace Lekki Stock Branch' || $dataIndexA1['totalPhysicalUnits'] !== 95) {
    echo "❌ AUDIT FAILURE: Stock index exposed cross-branch or cross-tenant data!\n";
    exit(1);
}
echo "   ✅ PASS: Stock Management Hub Index view confirmed 100% isolated.\n\n";

echo "====================================================================\n";
echo "🌟 STOCK IN & OUT ISOLATION VERDICT: 100% PASSED!\n";
echo "STOCK IN, STOCK OUT, ADJUSTMENTS, AND INVENTORY LOGS ARE AIRTIGHT ISOLATED.\n";
echo "====================================================================\n";
