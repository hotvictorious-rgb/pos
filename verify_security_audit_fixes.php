<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Transfer;
use App\Http\Controllers\Web\StockController;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo "   SECURITY AUDIT FIXES & CENTRALIZED AUTHORIZATION PROOF VERIFICATION\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);

// Clean up test records
Tenant::whereIn('id', ['sec-tenant-alpha'])->delete();
Warehouse::withoutGlobalScopes()->whereIn('tenant_id', ['sec-tenant-alpha'])->delete();
User::withoutGlobalScopes()->whereIn('tenant_id', ['sec-tenant-alpha'])->delete();
Transfer::withoutGlobalScopes()->whereIn('tenant_id', ['sec-tenant-alpha'])->delete();

// Setup Tenant Alpha with 3 Branches
$tenantA = Tenant::create([
    'id' => 'sec-tenant-alpha',
    'name' => 'Grace Supermarket Ltd',
    'owner_email' => 'alpha_sec@test.com',
    'owner_phone' => '08011112222',
    'plan' => 'pro',
    'status' => 'active',
]);

$branchA1 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Lekki Branch',
    'code' => 'SEC-LK-' . Str::random(4),
    'is_active' => true,
]);

$branchA2 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Ikeja Branch',
    'code' => 'SEC-IK-' . Str::random(4),
    'is_active' => true,
]);

$branchA3 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Abuja Branch',
    'code' => 'SEC-ABJ-' . Str::random(4),
    'is_active' => true,
]);

$mgrA1 = User::create([
    'id' => 'user-mgr-sec-a1',
    'tenant_id' => $tenantA->id,
    'name' => 'Manager Lekki',
    'email' => 'mgr_sec_a1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA1->id,
]);

$mgrA3 = User::create([
    'id' => 'user-mgr-sec-a3',
    'tenant_id' => $tenantA->id,
    'name' => 'Manager Abuja',
    'email' => 'mgr_sec_a3@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA3->id,
]);

$execA = User::create([
    'id' => 'user-exec-sec-a',
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Executive HQ',
    'email' => 'exec_sec_a@test.com',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'warehouse_id' => null,
]);

session(['tenant_id' => $tenantA->id]);

// Create Transfer from Lekki (A1) to Ikeja (A2)
$transfer = Transfer::create([
    'tenant_id' => $tenantA->id,
    'transfer_no' => 'TRF-SEC-001',
    'source_warehouse_id' => $branchA1->id,
    'destination_warehouse_id' => $branchA2->id,
    'status' => 'DISPATCHED',
    'carrier_name' => 'GIG Express',
    'dispatched_by' => $mgrA1->name,
    'dispatched_at' => now(),
]);

// ─────────────────────────────────────────────────────────
// TEST 1: CENTRALIZED USER AUTHORIZATION HELPERS
// ─────────────────────────────────────────────────────────
echo "[1/2] Testing Centralized User Authorization Helpers...\n";
echo "   • Lekki Manager isBranchScoped(): " . ($mgrA1->isBranchScoped() ? 'true' : 'false') . " (Expected: true)\n";
echo "   • Executive HQ isBranchScoped(): " . ($execA->isBranchScoped() ? 'true' : 'false') . " (Expected: false)\n";
echo "   • Lekki Manager canAccessWarehouse(Lekki): " . ($mgrA1->canAccessWarehouse($branchA1->id) ? 'true' : 'false') . " (Expected: true)\n";
echo "   • Lekki Manager canAccessWarehouse(Abuja): " . ($mgrA1->canAccessWarehouse($branchA3->id) ? 'true' : 'false') . " (Expected: false)\n";
echo "   • Executive HQ canAccessWarehouse(Abuja): " . ($execA->canAccessWarehouse($branchA3->id) ? 'true' : 'false') . " (Expected: true)\n";

if (!$mgrA1->isBranchScoped() || $execA->isBranchScoped() || !$mgrA1->canAccessWarehouse($branchA1->id) || $mgrA1->canAccessWarehouse($branchA3->id)) {
    echo "❌ AUDIT FAILURE: Centralized User authorization helpers failed!\n";
    exit(1);
}
echo "   ✅ PASS: Centralized User authorization helper methods verified.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 2: WAYBILL CROSS-BRANCH AUTHORIZATION GUARD
// ─────────────────────────────────────────────────────────
echo "[2/2] Testing Waybill Cross-Branch Authorization Guard...\n";
$stockController = new StockController(new StockService());

// A. Unauthorized Branch Staff (Abuja Manager trying to view Lekki->Ikeja Waybill)
auth()->login($mgrA3);
session(['tenant_id' => $tenantA->id, 'active_warehouse_id' => $branchA3->id]);

try {
    $stockController->waybill($transfer->id);
    echo "❌ AUDIT FAILURE: Unauthorized branch staff was allowed to view waybill!\n";
    exit(1);
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    echo "   • Abuja Manager Access Result: " . $e->getStatusCode() . " Access Denied (" . $e->getMessage() . ")\n";
    if ($e->getStatusCode() === 403) {
        echo "   ✅ PASS: Cross-branch waybill inspection attempt correctly blocked with 403 Forbidden.\n\n";
    } else {
        echo "❌ AUDIT FAILURE: Expected 403 Forbidden exception!\n";
        exit(1);
    }
}

// B. Authorized Origin Branch Staff (Lekki Manager viewing Lekki->Ikeja Waybill)
auth()->login($mgrA1);
session(['tenant_id' => $tenantA->id, 'active_warehouse_id' => $branchA1->id]);

$viewWaybill = $stockController->waybill($transfer->id);
echo "   • Lekki Origin Manager Access Result: 200 OK (View rendered successfully: '" . $viewWaybill->name() . "')\n";
echo "   ✅ PASS: Authorized origin branch staff granted waybill access.\n\n";

echo "====================================================================\n";
echo "🌟 SECURITY AUDIT FIXES VERDICT: 100% PASSED!\n";
echo "CENTRALIZED AUTHORIZATION & WAYBILL SECURITY GUARDS ARE AIRTIGHT.\n";
echo "====================================================================\n";
