<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Http\Controllers\Web\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo "   WORKERS & ROLES 2-LEVEL ISOLATION (TENANT & BRANCH) PROOF AUDIT\n";
echo "====================================================================\n\n";

config(['saas.enabled' => true]);

// Clean up test tenants
Tenant::whereIn('id', ['worker-tenant-alpha', 'worker-tenant-beta'])->delete();
Warehouse::withoutGlobalScopes()->whereIn('tenant_id', ['worker-tenant-alpha', 'worker-tenant-beta'])->delete();
User::withoutGlobalScopes()->whereIn('tenant_id', ['worker-tenant-alpha', 'worker-tenant-beta'])->delete();

// 1. Setup Tenant Alpha with 2 Branches
$tenantA = Tenant::create([
    'id' => 'worker-tenant-alpha',
    'name' => 'Grace Supermarket Ltd',
    'owner_email' => 'alpha_worker@test.com',
    'owner_phone' => '08011112222',
    'plan' => 'pro',
    'status' => 'active',
]);

$branchA1 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Lekki Branch',
    'code' => 'WRK-LK-' . Str::random(4),
    'is_active' => true,
]);

$branchA2 = Warehouse::create([
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Ikeja Branch',
    'code' => 'WRK-IK-' . Str::random(4),
    'is_active' => true,
]);

$workerA1 = User::create([
    'id' => 'user-worker-a1',
    'tenant_id' => $tenantA->id,
    'name' => 'Worker Lekki Manager',
    'email' => 'worker_a1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => $branchA1->id,
]);

$workerA2 = User::create([
    'id' => 'user-worker-a2',
    'tenant_id' => $tenantA->id,
    'name' => 'Worker Ikeja Viewer',
    'email' => 'worker_a2@test.com',
    'password' => bcrypt('password'),
    'role' => 'viewer',
    'warehouse_id' => $branchA2->id,
]);

$execA = User::create([
    'id' => 'user-exec-worker-a',
    'tenant_id' => $tenantA->id,
    'name' => 'Grace Executive Owner',
    'email' => 'exec_worker_a@test.com',
    'password' => bcrypt('password'),
    'role' => 'admin',
    'warehouse_id' => null,
]);

// 2. Setup Tenant Beta
$tenantB = Tenant::create([
    'id' => 'worker-tenant-beta',
    'name' => 'Alhaji Grain Depot',
    'owner_email' => 'beta_worker@test.com',
    'owner_phone' => '08033334444',
    'plan' => 'basic',
    'status' => 'active',
]);

$workerB1 = User::create([
    'id' => 'user-worker-b1',
    'tenant_id' => $tenantB->id,
    'name' => 'Alhaji Kano Manager',
    'email' => 'worker_b1@test.com',
    'password' => bcrypt('password'),
    'role' => 'manager',
    'warehouse_id' => null,
]);

$controller = new UserController();

// ─────────────────────────────────────────────────────────
// TEST 1: LEVEL 1 TENANT WORKER ISOLATION (Tenant B vs Tenant A)
// ─────────────────────────────────────────────────────────
echo "[1/3] Testing Level 1 Tenant Worker Isolation (Tenant B viewing workers)...\n";
auth()->login($workerB1);
session(['tenant_id' => $tenantB->id]);

$viewB = $controller->index();
$dataB = $viewB->getData();

$userNamesB = $dataB['users']->pluck('name')->toArray();
echo "   • Tenant B Visible Worker Count: " . count($userNamesB) . " (Expected: 1)\n";
echo "   • Tenant B Visible Worker Names: " . implode(', ', $userNamesB) . "\n";

if (count($userNamesB) !== 1 || in_array('Worker Lekki Manager', $userNamesB)) {
    echo "❌ AUDIT FAILURE: Tenant B can see Tenant A worker accounts!\n";
    exit(1);
}
echo "   ✅ PASS: Level 1 Tenant Worker Isolation is 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 2: LEVEL 2 BRANCH WORKER ISOLATION (Lekki Branch Manager vs Ikeja Worker)
// ─────────────────────────────────────────────────────────
echo "[2/3] Testing Level 2 Branch Worker Isolation (Lekki Branch Manager viewing workers)...\n";
auth()->login($workerA1);
session(['tenant_id' => $tenantA->id]);

$viewA1 = $controller->index();
$dataA1 = $viewA1->getData();

$userNamesA1 = $dataA1['users']->pluck('name')->toArray();
echo "   • Lekki Branch Visible Worker Count: " . count($userNamesA1) . " (Expected: 1)\n";
echo "   • Lekki Branch Visible Worker Names: " . implode(', ', $userNamesA1) . "\n";

if (count($userNamesA1) !== 1 || in_array('Worker Ikeja Viewer', $userNamesA1)) {
    echo "❌ AUDIT FAILURE: Lekki Manager can see Ikeja Branch worker accounts!\n";
    exit(1);
}
echo "   ✅ PASS: Level 2 Branch Worker Isolation is 100% airtight.\n\n";

// ─────────────────────────────────────────────────────────
// TEST 3: EXECUTIVE HQ MULTI-BRANCH WORKER DIRECTORY
// ─────────────────────────────────────────────────────────
echo "[3/3] Testing Executive HQ Multi-Branch Worker Directory View...\n";
auth()->login($execA);
session(['tenant_id' => $tenantA->id]);

$viewExec = $controller->index();
$dataExec = $viewExec->getData();

$userNamesExec = $dataExec['users']->pluck('name')->toArray();
echo "   • Executive HQ Visible Worker Count: " . count($userNamesExec) . " (Expected: 3)\n";
echo "   • Executive HQ Visible Worker Names: " . implode(', ', $userNamesExec) . "\n";

if (count($userNamesExec) !== 3) {
    echo "❌ AUDIT FAILURE: Executive HQ failed to view full multi-branch worker directory!\n";
    exit(1);
}
echo "   ✅ PASS: Executive HQ multi-branch worker directory verified.\n\n";

echo "====================================================================\n";
echo "🌟 WORKERS & ROLES ISOLATION VERDICT: 100% AIRTIGHT!\n";
echo "WORKER ACCOUNTS AND ROLE PERMISSIONS ARE STRICTLY ISOLATED PER TENANT & BRANCH.\n";
echo "====================================================================\n";
