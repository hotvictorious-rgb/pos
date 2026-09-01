<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Sale;
use App\Http\Controllers\Web\DebtController;
use App\Http\Controllers\Web\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo "   CUSTOMER DEBT 2-LEVEL ISOLATION (TENANT & BRANCH) PROOF AUDIT\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);

// Clean up test tenants
Tenant::whereIn('id', ['debt-tenant-alpha', 'debt-tenant-beta'])->delete();
Warehouse::withoutGlobalScopes()->whereIn('tenant_id', ['debt-tenant-alpha', 'debt-tenant-beta'])->delete();
User::withoutGlobalScopes()->whereIn('tenant_id', ['debt-tenant-alpha', 'debt-tenant-beta'])->delete();
Customer::withoutGlobalScopes()->whereIn('tenant_id', ['debt-tenant-alpha', 'debt-tenant-beta'])->delete();
Sale::withoutGlobalScopes()->whereIn('tenant_id', ['debt-tenant-alpha', 'debt-tenant-beta'])->delete();

// 1. Setup Tenant Alpha (Grace Supermarket) with 2 Branches
$tenantA = Tenant::create([
    'id' => 'debt-tenant-alpha',
    'name' => 'Grace Supermarket Ltd',
    'owner_email' => 'alpha_debt@test.com',
    'owner_phone' => '08011112222',
    'plan' => 'pro',
    'status' => 'active',
]);

$branchA1 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Lekki HQ',
    'code' => 'GRC-LK-' . Str::random(4),
    'is_active' => true,
]);

$branchA2 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Ikeja Shop',
    'code' => 'GRC-IK-' . Str::random(4),
    'is_active' => true,
]);

$mgrA1 = User::create([
    'id' => 'user-mgr-a1',
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Manager Lekki',
    'email' => 'mgr_a1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA1->id,
]);

$mgrA2 = User::create([
    'id' => 'user-mgr-a2',
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Manager Ikeja',
    'email' => 'mgr_a2@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA2->id,
]);

$execA = User::create([
    'id' => 'user-exec-a',
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Executive Owner',
    'email' => 'exec_a@test.com',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'warehouse_id' => null,
]);

// 2. Setup Tenant Beta (Alhaji Grain Depot)
$tenantB = Tenant::create([
    'id' => 'debt-tenant-beta',
    'name' => 'Alhaji Grain Depot',
    'owner_email' => 'beta_debt@test.com',
    'owner_phone' => '08033334444',
    'plan' => 'basic',
    'status' => 'active',
]);

$mgrB1 = User::create([
    'id' => 'user-mgr-b1',
    'tenant_id' => $tenantB->id,
    'name' => 'Alhaji Manager Kano',
    'email' => 'mgr_b1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => null,
]);

// 3. Seed Debts & Transactions in Tenant A
session(['tenant_id' => $tenantA->id]);

// Customer 1 at Branch A1 (Lekki)
$custA1 = Customer::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Chief Okonkwo (Lekki)',
    'phone' => '08099990000',
    'total_debt' => 150000,
]);

Sale::create([
    'id' => 'sale-debt-a1',
    'tenant_id' => $tenantA->id,
    'userId' => $mgrA1->id,
    'userName' => $mgrA1->name,
    'customerName' => $custA1->name,
    'totalAmount' => 200000,
    'paidAmount' => 50000,
    'cashAmount' => 50000,
    'posAmount' => 0,
    'status' => 'PARTIAL',
    'deliveryStatus' => 'DELIVERED',
    'createdAt' => now()->toIso8601String(),
]);

CustomerLedger::create([
    'customer_id' => $custA1->id,
    'type' => 'PAYMENT',
    'amount' => 50000,
    'balance_after' => 150000,
    'payment_method' => 'TRANSFER',
    'recorded_by' => $mgrA1->name,
    'created_at' => now(),
]);

// Customer 2 at Branch A2 (Ikeja)
$custA2 = Customer::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Madam Bisi (Ikeja)',
    'phone' => '08088881111',
    'total_debt' => 30000,
]);

Sale::create([
    'id' => 'sale-debt-a2',
    'tenant_id' => $tenantA->id,
    'userId' => $mgrA2->id,
    'userName' => $mgrA2->name,
    'customerName' => $custA2->name,
    'totalAmount' => 50000,
    'paidAmount' => 20000,
    'cashAmount' => 20000,
    'posAmount' => 0,
    'status' => 'PARTIAL',
    'deliveryStatus' => 'DELIVERED',
    'createdAt' => now()->toIso8601String(),
]);

// 4. Seed Debts in Tenant B
session(['tenant_id' => $tenantB->id]);
$custB1 = Customer::create([
    'tenant_id' => $tenantB->id,
    'name' => 'Malam Garba (Kano)',
    'phone' => '08077772222',
    'total_debt' => 80000,
]);

// ─────────────────────────────────────────────────────────
// TEST 1: TENANT LEVEL DEBT ISOLATION (Tenant B vs Tenant A)
// ─────────────────────────────────────────────────────────
echo "[1/3] Testing Level 1 Tenant Debt Isolation (Tenant B trying to view Tenant A debts)...\n";
auth()->login($mgrB1);
session(['tenant_id' => $tenantB->id]);

$debtController = new DebtController(new \App\Services\StockService());
$debtViewB = $debtController->index(Request::create('/debts', 'GET'));
$debtDataB = $debtViewB->getData();

echo "   • Tenant B Total Debtors Count: " . $debtDataB['totalDebtorsCount'] . " (Expected: 1)\n";
echo "   • Tenant B Total Outstanding Debt: ₦" . number_format($debtDataB['totalOutstandingDebt']) . " (Expected: ₦80,000)\n";

$debtorNamesB = $debtDataB['debtors']->pluck('name')->toArray();
echo "   • Tenant B Debtor Names: " . implode(', ', $debtorNamesB) . "\n";

if ($debtDataB['totalOutstandingDebt'] != 80000 || in_array('Chief Okonkwo (Lekki)', $debtorNamesB)) {
    echo "❌ AUDIT FAILURE: Tenant B can see Tenant A's customer debt data!\n";
    exit(1);
}
echo "   ✅ PASS: Level 1 Tenant Debt Isolation is 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 2: BRANCH LEVEL DEBT ISOLATION (Lekki Manager vs Ikeja Manager)
// ─────────────────────────────────────────────────────────
echo "[2/3] Testing Level 2 Branch Debt Isolation (Lekki Manager in Tenant A)...\n";
auth()->login($mgrA1);
session(['tenant_id' => $tenantA->id]);

$debtViewA1 = $debtController->index(Request::create('/debts', 'GET'));
$debtDataA1 = $debtViewA1->getData();

echo "   • Lekki Branch Debtors Count: " . $debtDataA1['totalDebtorsCount'] . " (Expected: 1)\n";
echo "   • Lekki Branch Outstanding Debt: ₦" . number_format($debtDataA1['totalOutstandingDebt']) . " (Expected: ₦150,000)\n";
$debtorNamesA1 = $debtDataA1['debtors']->pluck('name')->toArray();
echo "   • Lekki Branch Debtor Names: " . implode(', ', $debtorNamesA1) . "\n";

if ($debtDataA1['totalOutstandingDebt'] != 150000 || in_array('Madam Bisi (Ikeja)', $debtorNamesA1)) {
    echo "❌ AUDIT FAILURE: Lekki Manager can see Ikeja Branch customer debt data!\n";
    exit(1);
}
echo "   ✅ PASS: Level 2 Branch Debt Isolation is 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 3: HQ EXECUTIVE OVERALL CONSOLIDATED DEBT VIEW
// ─────────────────────────────────────────────────────────
echo "[3/3] Testing Tenant Executive HQ Consolidated Debt View...\n";
auth()->login($execA);
session(['tenant_id' => $tenantA->id]);

$debtViewExec = $debtController->index(Request::create('/debts', 'GET'));
$debtDataExec = $debtViewExec->getData();

echo "   • Executive HQ Debtors Count: " . $debtDataExec['totalDebtorsCount'] . " (Expected: 2)\n";
echo "   • Executive HQ Total Outstanding Debt: ₦" . number_format($debtDataExec['totalOutstandingDebt']) . " (Expected: ₦180,000)\n";

if ($debtDataExec['totalOutstandingDebt'] != 180000 || $debtDataExec['totalDebtorsCount'] != 2) {
    echo "❌ AUDIT FAILURE: Executive HQ failed to view consolidated multi-branch debt analytics!\n";
    exit(1);
}
echo "   ✅ PASS: Executive HQ consolidated multi-branch debt oversight verified.\n\n";

echo "====================================================================\n";
echo "🌟 DEBT ISOLATION AUDIT VERDICT: 100% PASSED!\n";
echo "CUSTOMER DEBTS ARE AIRTIGHT ISOLATED PER TENANT AND PER BRANCH.\n";
echo "====================================================================\n";
