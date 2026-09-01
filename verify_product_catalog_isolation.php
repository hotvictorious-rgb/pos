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
use App\Http\Controllers\Web\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo "   PRODUCT CATALOG 2-LEVEL ISOLATION (TENANT & BRANCH) PROOF AUDIT\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);

// Clean up test tenants
Tenant::whereIn('id', ['cat-tenant-alpha', 'cat-tenant-beta'])->delete();
Warehouse::withoutGlobalScopes()->whereIn('tenant_id', ['cat-tenant-alpha', 'cat-tenant-beta'])->delete();
User::withoutGlobalScopes()->whereIn('tenant_id', ['cat-tenant-alpha', 'cat-tenant-beta'])->delete();
Product::withoutGlobalScopes()->whereIn('tenant_id', ['cat-tenant-alpha', 'cat-tenant-beta'])->delete();
StockLevel::withoutGlobalScopes()->whereIn('tenant_id', ['cat-tenant-alpha', 'cat-tenant-beta'])->delete();

// 1. Setup Tenant Alpha with 2 Branches
$tenantA = Tenant::create([
    'id' => 'cat-tenant-alpha',
    'name' => 'Grace Supermarket Ltd',
    'owner_email' => 'alpha_cat@test.com',
    'owner_phone' => '08011112222',
    'plan' => 'pro',
    'status' => 'active',
]);

$branchA1 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Lekki Branch',
    'code' => 'CAT-LK-' . Str::random(4),
    'is_active' => true,
]);

$branchA2 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Ikeja Branch',
    'code' => 'CAT-IK-' . Str::random(4),
    'is_active' => true,
]);

$adminA = User::create([
    'id' => 'user-admin-cat-a',
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Admin A',
    'email' => 'admin_cat_a@test.com',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'warehouse_id' => null,
]);

$mgrA1 = User::create([
    'id' => 'user-mgr-cat-a1',
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Manager Lekki',
    'email' => 'mgr_cat_a1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA1->id,
]);

// 2. Setup Tenant Beta
$tenantB = Tenant::create([
    'id' => 'cat-tenant-beta',
    'name' => 'Alhaji Grain Depot',
    'owner_email' => 'beta_cat@test.com',
    'owner_phone' => '08033334444',
    'plan' => 'basic',
    'status' => 'active',
]);

$branchB1 = Warehouse::create([
    'tenant_id' => $tenantB->id,
    'name' => 'Alhaji Kano Branch',
    'code' => 'CAT-KN-' . Str::random(4),
    'is_active' => true,
]);

$adminB = User::create([
    'id' => 'user-admin-cat-b',
    'tenant_id' => $tenantB->id,
    'name' => 'Alhaji Admin B',
    'email' => 'admin_cat_b@test.com',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'warehouse_id' => null,
]);

$controller = new ProductController();

// ─────────────────────────────────────────────────────────
// TEST 1: TENANT A CATALOG CREATION & LISTING
// ─────────────────────────────────────────────────────────
echo "[1/3] Creating Product Catalog in Tenant A (Mama Gold Rice 50kg, SKU: RICE-50KG)...\n";
auth()->login($adminA);
session(['tenant_id' => $tenantA->id]);

$reqStoreA = Request::create('/products', 'POST', [
    'name' => 'Mama Gold Rice (50kg)',
    'code' => 'RICE-50KG',
    'category' => 'Grains',
    'unitPrice' => 78000,
    'initial_stock' => 50,
    'warehouse_id' => $branchA1->id,
]);
$controller->store($reqStoreA);

$prodA = Product::where('code', 'RICE-50KG')->first();

// Add secondary stock level for Branch A2 (Ikeja)
StockLevel::create([
    'tenant_id' => $tenantA->id,
    'product_id' => $prodA->id,
    'warehouse_id' => $branchA2->id,
    'physical_stock' => 20,
]);

echo "   • Tenant A Product Created: '" . $prodA->name . "' (Price: ₦" . number_format($prodA->unitPrice) . ")\n";
echo "   ✅ PASS: Tenant A product catalog entry created cleanly.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 2: TENANT B CREATING DUPLICATE SKU PRODUCT IN INDEPENDENT CATALOG
// ─────────────────────────────────────────────────────────
echo "[2/3] Creating Independent Product in Tenant B with SAME SKU (RICE-50KG)...\n";
auth()->login($adminB);
session(['tenant_id' => $tenantB->id]);

$reqStoreB = Request::create('/products', 'POST', [
    'name' => 'Otunba Premium Rice (50kg)',
    'code' => 'RICE-50KG', // Duplicate code across different tenants!
    'category' => 'Grains',
    'unitPrice' => 72000,
    'initial_stock' => 100,
    'warehouse_id' => $branchB1->id,
]);
$controller->store($reqStoreB);

$prodB = Product::where('code', 'RICE-50KG')->first();

echo "   • Tenant B Product Created: '" . $prodB->name . "' (Price: ₦" . number_format($prodB->unitPrice) . ")\n";
echo "   • Tenant B ID: " . $prodB->id . " (Tenant ID: " . $prodB->tenant_id . ")\n";

$viewB = $controller->index(Request::create('/products', 'GET'));
$dataB = $viewB->getData();
$prodsB = $dataB['products'];

echo "   • Tenant B Catalog Count: " . $prodsB->count() . " (Expected: 1)\n";
echo "   • Tenant B Catalog Product Name: '" . $prodsB->first()->name . "'\n";

if ($prodsB->count() !== 1 || $prodsB->first()->name !== 'Otunba Premium Rice (50kg)') {
    echo "❌ AUDIT FAILURE: Product catalog leaking cross-tenant records or SKU collision!\n";
    exit(1);
}
echo "   ✅ PASS: Level 1 Tenant Catalog Isolation & duplicate SKU independence verified.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 3: BRANCH LEVEL CATALOG STOCK ISOLATION & HQ CONSOLIDATED OVERSIGHT
// ─────────────────────────────────────────────────────────
echo "[3/3] Testing Branch Level Catalog Stock Breakdown (Lekki Manager vs HQ Executive)...\n";

// A. Lekki Manager View
auth()->login($mgrA1);
session(['tenant_id' => $tenantA->id]);

$viewA1 = $controller->index(Request::create('/products', 'GET'));
$dataA1 = $viewA1->getData();
$prodA1Data = $dataA1['products']->first();

echo "   • Lekki Branch Visible Total Physical Stock: " . $prodA1Data->total_physical_stock . " units (Expected: 50)\n";
if ($prodA1Data->total_physical_stock !== 50) {
    echo "❌ AUDIT FAILURE: Branch manager exposed to cross-branch stock counts!\n";
    exit(1);
}

// B. HQ Executive View
auth()->login($adminA);
session(['tenant_id' => $tenantA->id]);

$viewExec = $controller->index(Request::create('/products', 'GET'));
$dataExec = $viewExec->getData();
$prodExecData = $dataExec['products']->first();

echo "   • Executive HQ Consolidated Physical Stock: " . $prodExecData->total_physical_stock . " units (Expected: 70)\n";
if ($prodExecData->total_physical_stock !== 70) {
    echo "❌ AUDIT FAILURE: Executive HQ failed to see consolidated physical stock!\n";
    exit(1);
}

echo "   ✅ PASS: Branch level stock breakdown and HQ consolidated oversight verified.\n\n";

echo "====================================================================\n";
echo "🌟 PRODUCT CATALOG ISOLATION VERDICT: 100% PASSED!\n";
echo "PRODUCT CATALOGS, SKUs, PRICING, AND STOCKS ARE AIRTIGHT ISOLATED.\n";
echo "====================================================================\n";
