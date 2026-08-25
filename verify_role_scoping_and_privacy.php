<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Models\Product;
use App\Http\Controllers\Web\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "\n====================================================================\n";
echo "    HYSAM VENTURES - ROLE SCOPING & TRANSACTION PRIVACY AUDIT PROOF  \n";
echo "====================================================================\n\n";

// 1. Setup Test Branch and Users
$warehouse = Warehouse::firstOrCreate(
    ['code' => 'MAIN-TEST'],
    ['name' => 'Main Test Hub', 'is_active' => true]
);

$admin = User::firstOrCreate(
    ['email' => 'madam.admin@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Madam Owner',
        'password' => Hash::make('secret123'),
        'role' => 'admin',
        'disabled' => false,
    ]
);

$cashierA = User::firstOrCreate(
    ['email' => 'cashier.a@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Cashier Ada',
        'password' => Hash::make('secret123'),
        'role' => 'cashier',
        'warehouse_id' => $warehouse->id,
        'disabled' => false,
    ]
);

$cashierB = User::firstOrCreate(
    ['email' => 'cashier.b@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Cashier Bola',
        'password' => Hash::make('secret123'),
        'role' => 'cashier',
        'warehouse_id' => $warehouse->id,
        'disabled' => false,
    ]
);

$product = Product::first() ?? Product::create([
    'id' => (string) Str::uuid(),
    'code' => 'TEST-SKU-99',
    'name' => 'Test SKU 99 Tiles',
    'category' => 'Floor Tiles',
    'subCategory' => '60x60',
    'unitPrice' => 15000,
    'piecesPerBox' => 4,
    'sqmPerBox' => 1.44,
    'costPrice' => 10000,
    'archived' => false,
]);

// 2. Create Sale by Cashier A
$saleA = Sale::create([
    'id' => (string) Str::uuid(),
    'userId' => $cashierA->id,
    'userName' => $cashierA->name,
    'customerName' => 'Customer of Ada',
    'customerPhone' => '08011111111',
    'totalAmount' => 150000,
    'paidAmount' => 150000,
    'cashAmount' => 150000,
    'posAmount' => 0,
    'transferAmount' => 0,
    'status' => 'COMPLETED',
    'deliveryStatus' => 'SUPPLIED',
    'createdAt' => now()->toIso8601String(),
]);

// 3. Create Sale by Cashier B
$saleB = Sale::create([
    'id' => (string) Str::uuid(),
    'userId' => $cashierB->id,
    'userName' => $cashierB->name,
    'customerName' => 'Customer of Bola',
    'customerPhone' => '08022222222',
    'totalAmount' => 300000,
    'paidAmount' => 300000,
    'cashAmount' => 300000,
    'posAmount' => 0,
    'transferAmount' => 0,
    'status' => 'COMPLETED',
    'deliveryStatus' => 'SUPPLIED',
    'createdAt' => now()->toIso8601String(),
]);

$controller = new TransactionController();
$req = new Request();

// ─────────────────────────────────────────────────────────────
// PROOF 1: Cashier A query isolation (Cannot see Cashier B's sales)
// ─────────────────────────────────────────────────────────────
Auth::login($cashierA);
$salesQueryA = $controller->getSalesQuery($req);
$salesIdsA = $salesQueryA->pluck('id')->toArray();

assert(in_array($saleA->id, $salesIdsA), 'Proof 1 Failed: Cashier A cannot see their own sale');
assert(!in_array($saleB->id, $salesIdsA), 'Proof 1 Failed: Cashier A CAN SEE Cashier B sale (Privacy Breach!)');

echo "✅ [PROOF 1 PASSED] Cashier Privacy Scoping: Cashier A only sees their own transactions\n";
echo "   • Cashier Ada sees Sale A (₦150,000): YES\n";
echo "   • Cashier Ada sees Sale B (₦300,000 by Bola): BLOCKED (Zero visibility)\n\n";

// ─────────────────────────────────────────────────────────────
// PROOF 2: Cashier B query isolation (Cannot see Cashier A's sales)
// ─────────────────────────────────────────────────────────────
Auth::login($cashierB);
$salesQueryB = $controller->getSalesQuery($req);
$salesIdsB = $salesQueryB->pluck('id')->toArray();

assert(in_array($saleB->id, $salesIdsB), 'Proof 2 Failed: Cashier B cannot see their own sale');
assert(!in_array($saleA->id, $salesIdsB), 'Proof 2 Failed: Cashier B CAN SEE Cashier A sale (Privacy Breach!)');

echo "✅ [PROOF 2 PASSED] Cashier Privacy Scoping: Cashier B only sees their own transactions\n";
echo "   • Cashier Bola sees Sale B (₦300,000): YES\n";
echo "   • Cashier Bola sees Sale A (₦150,000 by Ada): BLOCKED (Zero visibility)\n\n";

// ─────────────────────────────────────────────────────────────
// PROOF 3: Admin Master Visibility (Sees all sales consolidated)
// ─────────────────────────────────────────────────────────────
Auth::login($admin);
$adminQuery = $controller->getSalesQuery($req);
$adminSalesIds = $adminQuery->pluck('id')->toArray();

assert(in_array($saleA->id, $adminSalesIds), 'Proof 3 Failed: Admin cannot see Sale A');
assert(in_array($saleB->id, $adminSalesIds), 'Proof 3 Failed: Admin cannot see Sale B');

echo "✅ [PROOF 3 PASSED] Executive Master Visibility: Madam / Admin sees all transactions\n";
echo "   • Admin sees Sale A (Ada): YES\n";
echo "   • Admin sees Sale B (Bola): YES\n";
echo "   • Consolidated Visibility: 100% Unrestricted\n\n";

// ─────────────────────────────────────────────────────────────
// PROOF 4: Route Guard Protection (Non-Admin cannot access /settings or /users)
// ─────────────────────────────────────────────────────────────
$middleware = new \App\Http\Middleware\RequireAdmin();

// Cashier A attempts to access admin route
Auth::login($cashierA);
$adminReq = Request::create('/settings', 'GET');
$adminReq->headers->set('Accept', 'application/json');

$response = $middleware->handle($adminReq, function () {
    return response()->json(['success' => true]);
});

assert($response->getStatusCode() === 403, 'Proof 4 Failed: Expected 403 Forbidden for non-admin');

echo "✅ [PROOF 4 PASSED] Administrative Route Guard: Non-admin staff blocked with 403 Forbidden\n";
echo "   • Attempted: Cashier accessing /settings\n";
echo "   • Intercepted HTTP Status: 403 Forbidden\n\n";

echo "====================================================================\n";
echo "   ALL 4 PROOFS PASSED (100% SUCCESS) - PRIVACY & SCOPING VERIFIED   \n";
echo "====================================================================\n\n";
