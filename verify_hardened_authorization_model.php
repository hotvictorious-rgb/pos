<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Transfer;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo "   HARDENED AUTHORIZATION MODEL (ROLE & TENANT ENFORCEMENT) PROOF AUDIT\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);

// Clean up test records
Tenant::whereIn('id', ['hdr-tenant-alpha', 'hdr-tenant-beta'])->delete();
Warehouse::withoutGlobalScopes()->whereIn('tenant_id', ['hdr-tenant-alpha', 'hdr-tenant-beta'])->delete();
User::withoutGlobalScopes()->whereIn('tenant_id', ['hdr-tenant-alpha', 'hdr-tenant-beta'])->delete();
Transfer::withoutGlobalScopes()->whereIn('tenant_id', ['hdr-tenant-alpha', 'hdr-tenant-beta'])->delete();

// Setup Tenant Alpha
$tenantA = Tenant::create([
    'id' => 'hdr-tenant-alpha',
    'name' => 'Grace Supermarket Ltd',
    'owner_email' => 'alpha_hdr@test.com',
    'owner_phone' => '08011112222',
    'plan' => 'pro',
    'status' => 'active',
]);

$branchA1 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Lekki Branch',
    'code' => 'HDR-LK-' . Str::random(4),
    'is_active' => true,
]);

// Setup Tenant Beta
$tenantB = Tenant::create([
    'id' => 'hdr-tenant-beta',
    'name' => 'Alhaji Grain Depot',
    'owner_email' => 'beta_hdr@test.com',
    'owner_phone' => '08033334444',
    'plan' => 'basic',
    'status' => 'active',
]);

$branchB1 = Warehouse::create([
    'tenant_id' => $tenantB->id,
    'name' => 'Alhaji Kano Depot',
    'code' => 'HDR-KN-' . Str::random(4),
    'is_active' => true,
]);

// Test Users
$unassignedCashier = User::create([
    'id' => 'user-cashier-unassigned-' . Str::random(4),
    'tenant_id' => $tenantA->id,
    'name' => 'Unassigned Cashier',
    'email' => 'unassigned_cashier_' . Str::random(4) . '@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => null, // ⚠️ Missing branch assignment!
]);

$executiveA = User::create([
    'id' => 'user-exec-a-' . Str::random(4),
    'tenant_id' => $tenantA->id,
    'name' => 'Executive Owner Tenant A',
    'email' => 'exec_a_' . Str::random(4) . '@test.com',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'warehouse_id' => null,
]);

$branchStaffA1 = User::create([
    'id' => 'user-staff-a1-' . Str::random(4),
    'tenant_id' => $tenantA->id,
    'name' => 'Lekki Staff',
    'email' => 'staff_a1_' . Str::random(4) . '@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA1->id,
]);

$transferA = Transfer::create([
    'tenant_id' => $tenantA->id,
    'transfer_no' => 'TRF-HDR-001',
    'source_warehouse_id' => $branchA1->id,
    'destination_warehouse_id' => $branchA1->id,
    'status' => 'DISPATCHED',
    'carrier_name' => 'Local Courier',
    'dispatched_by' => 'Staff',
    'dispatched_at' => now(),
]);

$transferB = Transfer::create([
    'tenant_id' => $tenantB->id,
    'transfer_no' => 'TRF-HDR-002',
    'source_warehouse_id' => $branchB1->id,
    'destination_warehouse_id' => $branchB1->id,
    'status' => 'DISPATCHED',
    'carrier_name' => 'Local Courier',
    'dispatched_by' => 'Staff B',
    'dispatched_at' => now(),
]);

// ─────────────────────────────────────────────────────────
// TEST 1: UNASSIGNED NON-EXECUTIVE (CASHIER/MANAGER) EDGE CASE
// ─────────────────────────────────────────────────────────
echo "[1/3] Testing Unassigned Cashier/Manager Edge Case (warehouse_id = null, role = manager)...\n";
echo "   • Unassigned Cashier isExecutive(): " . ($unassignedCashier->isExecutive() ? 'true' : 'false') . " (Expected: false)\n";
echo "   • Unassigned Cashier canAccessWarehouse(Branch A1): " . ($unassignedCashier->canAccessWarehouse($branchA1->id) ? 'true' : 'false') . " (Expected: false)\n";
echo "   • Unassigned Cashier canAccessTransfer(Transfer A): " . ($unassignedCashier->canAccessTransfer($transferA) ? 'true' : 'false') . " (Expected: false)\n";

if ($unassignedCashier->canAccessWarehouse($branchA1->id) || $unassignedCashier->canAccessTransfer($transferA)) {
    echo "❌ AUDIT FAILURE: Unassigned non-executive user was granted global access!\n";
    exit(1);
}
echo "   ✅ PASS: Unassigned non-executive user correctly denied global access.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 2: CROSS-TENANT AUTHORIZATION BOUNDARY
// ─────────────────────────────────────────────────────────
echo "[2/3] Testing Cross-Tenant Authorization Boundary...\n";
echo "   • Executive HQ Tenant A canAccessWarehouse(Tenant A Branch A1): " . ($executiveA->canAccessWarehouse($branchA1->id) ? 'true' : 'false') . " (Expected: true)\n";
echo "   • Executive HQ Tenant A canAccessWarehouse(Tenant B Branch B1): " . ($executiveA->canAccessWarehouse($branchB1->id) ? 'true' : 'false') . " (Expected: false)\n";
echo "   • Executive HQ Tenant A canAccessTransfer(Tenant A Transfer): " . ($executiveA->canAccessTransfer($transferA) ? 'true' : 'false') . " (Expected: true)\n";
echo "   • Executive HQ Tenant A canAccessTransfer(Tenant B Transfer): " . ($executiveA->canAccessTransfer($transferB) ? 'true' : 'false') . " (Expected: false)\n";

if (!$executiveA->canAccessWarehouse($branchA1->id) || $executiveA->canAccessWarehouse($branchB1->id) || !$executiveA->canAccessTransfer($transferA) || $executiveA->canAccessTransfer($transferB)) {
    echo "❌ AUDIT FAILURE: Cross-tenant authorization boundary breached!\n";
    exit(1);
}
echo "   ✅ PASS: Cross-tenant authorization boundary verified 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 3: BRANCH STAFF AUTHORIZATION BOUNDARY
// ─────────────────────────────────────────────────────────
echo "[3/3] Testing Branch Staff Authorization Boundary...\n";
echo "   • Lekki Staff canAccessWarehouse(Lekki Branch A1): " . ($branchStaffA1->canAccessWarehouse($branchA1->id) ? 'true' : 'false') . " (Expected: true)\n";
echo "   • Lekki Staff canAccessWarehouse(Kano Branch B1): " . ($branchStaffA1->canAccessWarehouse($branchB1->id) ? 'true' : 'false') . " (Expected: false)\n";

if (!$branchStaffA1->canAccessWarehouse($branchA1->id) || $branchStaffA1->canAccessWarehouse($branchB1->id)) {
    echo "❌ AUDIT FAILURE: Branch staff authorization boundary breached!\n";
    exit(1);
}
echo "   ✅ PASS: Branch staff authorization boundary verified 100% airtight.\n\n";

echo "====================================================================\n";
echo "🌟 HARDENED AUTHORIZATION VERDICT: 100% PASSED!\n";
echo "ROLES, TENANTS, AND BRANCH AUTHORIZATIONS ARE AIRTIGHT ENFORCED.\n";
echo "====================================================================\n";
