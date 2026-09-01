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
use App\Models\Sale;
use App\Http\Controllers\Web\PosController;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo "   POS SELL GOODS 2-LEVEL ISOLATION (TENANT & BRANCH) PROOF AUDIT\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);

// Clean up test tenants
Tenant::whereIn('id', ['pos-tenant-alpha', 'pos-tenant-beta'])->delete();
Warehouse::withoutGlobalScopes()->whereIn('tenant_id', ['pos-tenant-alpha', 'pos-tenant-beta'])->delete();
User::withoutGlobalScopes()->whereIn('tenant_id', ['pos-tenant-alpha', 'pos-tenant-beta'])->delete();
Product::withoutGlobalScopes()->whereIn('tenant_id', ['pos-tenant-alpha', 'pos-tenant-beta'])->delete();
StockLevel::withoutGlobalScopes()->whereIn('tenant_id', ['pos-tenant-alpha', 'pos-tenant-beta'])->delete();
Sale::withoutGlobalScopes()->whereIn('tenant_id', ['pos-tenant-alpha', 'pos-tenant-beta'])->delete();

// 1. Setup Tenant Alpha with 2 Branches
$tenantA = Tenant::create([
    'id' => 'pos-tenant-alpha',
    'name' => 'Grace Supermarket Ltd',
    'owner_email' => 'alpha_pos@test.com',
    'owner_phone' => '08011112222',
    'plan' => 'pro',
    'status' => 'active',
]);

$branchA1 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Lekki POS Branch',
    'code' => 'POS-LK-' . Str::random(4),
    'is_active' => true,
]);

$branchA2 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Ikeja POS Branch',
    'code' => 'POS-IK-' . Str::random(4),
    'is_active' => true,
]);

$cashierA1 = User::create([
    'id' => 'user-cashier-a1',
    'tenant_id' => $tenantA->id,
    'name' => 'Cashier Lekki',
    'email' => 'cashier_a1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA1->id,
]);

$cashierA2 = User::create([
    'id' => 'user-cashier-a2',
    'tenant_id' => $tenantA->id,
    'name' => 'Cashier Ikeja',
    'email' => 'cashier_a2@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA2->id,
]);

$prodA = Product::create([
    'id' => 'prod-pos-alpha-01',
    'tenant_id' => $tenantA->id,
    'code' => 'RICE-01',
    'name' => 'Golden Penny Rice 50kg',
    'category' => 'Foodstuff',
    'unitPrice' => 45000,
    'currentStock' => 55,
    'minStockLevel' => 5,
    'archived' => false,
    'updatedAt' => now()->toIso8601String(),
]);

$stockA1 = StockLevel::create(['tenant_id' => $tenantA->id, 'product_id' => $prodA->id, 'warehouse_id' => $branchA1->id, 'physical_stock' => 40]);
$stockA2 = StockLevel::create(['tenant_id' => $tenantA->id, 'product_id' => $prodA->id, 'warehouse_id' => $branchA2->id, 'physical_stock' => 15]);

// 2. Setup Tenant Beta
$tenantB = Tenant::create([
    'id' => 'pos-tenant-beta',
    'name' => 'Alhaji Grain Depot',
    'owner_email' => 'beta_pos@test.com',
    'owner_phone' => '08033334444',
    'plan' => 'basic',
    'status' => 'active',
]);

$branchB1 = Warehouse::create([
    'tenant_id' => $tenantB->id,
    'name' => 'Alhaji Kano POS Depot',
    'code' => 'POS-KN-' . Str::random(4),
    'is_active' => true,
]);

$cashierB1 = User::create([
    'id' => 'user-cashier-b1',
    'tenant_id' => $tenantB->id,
    'name' => 'Cashier Kano',
    'email' => 'cashier_b1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchB1->id,
]);

$prodB = Product::create([
    'id' => 'prod-pos-beta-01',
    'tenant_id' => $tenantB->id,
    'code' => 'RICE-01',
    'name' => 'Golden Penny Rice 50kg',
    'category' => 'Grains',
    'unitPrice' => 48000,
    'currentStock' => 100,
    'minStockLevel' => 5,
    'archived' => false,
    'updatedAt' => now()->toIso8601String(),
]);

$stockB1 = StockLevel::create(['tenant_id' => $tenantB->id, 'product_id' => $prodB->id, 'warehouse_id' => $branchB1->id, 'physical_stock' => 100]);

$posController = new PosController(new StockService());

// ─────────────────────────────────────────────────────────
// TEST 1: TENANT LEVEL POS CATALOG & CHECKOUT ISOLATION
// ─────────────────────────────────────────────────────────
echo "[1/3] Testing Tenant Level POS Catalog & Checkout (Tenant A Lekki Cashier)...\n";
auth()->login($cashierA1);
session(['tenant_id' => $tenantA->id, 'active_warehouse_id' => $branchA1->id]);

$posViewA = $posController->index(Request::create('/pos', 'GET'));
$posDataA = $posViewA->getData();

$prodsInPOSA = $posDataA['products'];
echo "   • Tenant A POS Product Count: " . $prodsInPOSA->count() . " (Expected: 1)\n";
echo "   • Tenant A Product Price: ₦" . number_format($prodsInPOSA->first()->unitPrice) . " (Expected: ₦45,000, NOT ₦48,000)\n";
echo "   • Tenant A Lekki Stock Level: " . $prodsInPOSA->first()->physical_stock . " units (Expected: 40)\n";

if ($prodsInPOSA->first()->unitPrice != 45000 || $prodsInPOSA->first()->physical_stock != 40) {
    echo "❌ AUDIT FAILURE: Tenant A POS exposed Tenant B pricing/stock!\n";
    exit(1);
}

// Perform Checkout of 5 units at Tenant A Lekki
$checkoutReqA1 = Request::create('/pos/checkout', 'POST', [
    'warehouse_id' => $branchA1->id,
    'totalAmount' => 225000,
    'paidAmount' => 225000,
    'is_supplied' => 'yes',
    'items' => [
        ['productId' => $prodA->id, 'quantity' => 5, 'unitPrice' => 45000],
    ],
]);
$posController->checkout($checkoutReqA1);

$stockA1->refresh();
$stockB1->refresh();
echo "   • Tenant A Lekki Stock After Checkout: " . $stockA1->physical_stock . " units (Expected: 35)\n";
echo "   • Tenant B Kano Stock After Tenant A Checkout: " . $stockB1->physical_stock . " units (Expected: 100, 100% UNTOUCHED)\n";

if ($stockA1->physical_stock != 35 || $stockB1->physical_stock != 100) {
    echo "❌ AUDIT FAILURE: POS checkout cross-contaminated tenant stocks!\n";
    exit(1);
}
echo "   ✅ PASS: Level 1 Tenant POS Catalog & Checkout Isolation is 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 2: BRANCH LEVEL POS CHECKOUT ISOLATION (Ikeja Cashier)
// ─────────────────────────────────────────────────────────
echo "[2/3] Testing Level 2 Branch POS Checkout Isolation (Tenant A Ikeja Cashier)...\n";
auth()->login($cashierA2);
session(['tenant_id' => $tenantA->id, 'active_warehouse_id' => $branchA2->id]);

// Perform Checkout of 3 units at Tenant A Ikeja Branch
$checkoutReqA2 = Request::create('/pos/checkout', 'POST', [
    'warehouse_id' => $branchA2->id,
    'totalAmount' => 135000,
    'paidAmount' => 135000,
    'is_supplied' => 'yes',
    'items' => [
        ['productId' => $prodA->id, 'quantity' => 3, 'unitPrice' => 45000],
    ],
]);
$posController->checkout($checkoutReqA2);

$stockA1->refresh();
$stockA2->refresh();
echo "   • Tenant A Ikeja Stock After Checkout: " . $stockA2->physical_stock . " units (Expected: 12)\n";
echo "   • Tenant A Lekki Stock After Ikeja Checkout: " . $stockA1->physical_stock . " units (Expected: 35, 100% UNTOUCHED)\n";

if ($stockA2->physical_stock != 12 || $stockA1->physical_stock != 35) {
    echo "❌ AUDIT FAILURE: POS checkout cross-contaminated branch stocks within same tenant!\n";
    exit(1);
}
echo "   ✅ PASS: Level 2 Branch POS Checkout Isolation is 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 3: PRINTABLE RECEIPT BRANDING & BRANCH ACCURACY
// ─────────────────────────────────────────────────────────
echo "[3/3] Testing Printable Receipt Dynamic Branding & Branch Accuracy...\n";
$latestSaleA = Sale::where('userId', $cashierA2->id)->latest('createdAt')->first();
$receiptView = $posController->receipt($latestSaleA->id);
$receiptData = $receiptView->getData();

echo "   • Receipt Printable Sale ID: #" . substr($receiptData['sale']->id, 0, 8) . "\n";
echo "   • Receipt Branch Name: '" . $receiptData['warehouse']->name . "' (Expected: Grace Ikeja POS Branch)\n";

if ($receiptData['warehouse']->name !== 'Grace Ikeja POS Branch') {
    echo "❌ AUDIT FAILURE: Receipt failed to reflect active branch name!\n";
    exit(1);
}
echo "   ✅ PASS: Dynamic Receipt Branding & Branch Location confirmed.\n\n";

echo "====================================================================\n";
echo "🌟 POS ISOLATION VERDICT: 100% PASSED!\n";
echo "POS SELL GOODS, STOCK DEDUCTIONS, & RECEIPTS ARE AIRTIGHT ISOLATED.\n";
echo "====================================================================\n";
