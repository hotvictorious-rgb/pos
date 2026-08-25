<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "\n====================================================================\n";
echo "   HYSAM VENTURES - EXECUTIVE VIEW-ONLY ROLE VERIFICATION           \n";
echo "====================================================================\n\n";

// 1. Setup Viewer User
$viewer = User::firstOrCreate(
    ['email' => 'executive.viewer@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Alhaji Executive Partner',
        'password' => Hash::make('secret123'),
        'role' => 'viewer',
        'disabled' => false,
        'permissions' => ['view_only' => true, 'reports' => true, 'products' => true, 'stock' => true, 'transactions' => true, 'debts' => true, 'auditor' => true],
    ]
);

Auth::login($viewer);

echo "✅ Step 1: Logged in as Executive View-Only User\n";
echo "   • User: {$viewer->name} ({$viewer->email})\n";
echo "   • Role: {$viewer->role}\n\n";

// 2. Test Read-Only GET Access to All Business Hubs
$getRoutes = [
    '/',
    '/products',
    '/stock',
    '/stock/transfers',
    '/stock/unsupplied',
    '/stock/adjustments',
    '/transactions',
    '/debts',
    '/reports',
    '/auditor',
    '/users',
];

echo "🔍 Step 2: Testing Full Business Visibility (GET Routes)...\n";
foreach ($getRoutes as $route) {
    $req = Request::create($route, 'GET');
    $req->setUserResolver(fn() => $viewer);
    $response = $app->handle($req);
    $status = $response->getStatusCode();
    
    assert($status === 200, "Proof Failed: Viewer blocked from {$route} (Status: {$status})");
    echo "   • [200 OK] Visibility confirmed on: {$route}\n";
}

echo "\n✅ Step 2: 100% Business Transparency Verified (All 11 hubs accessible in read-only mode)\n\n";

// 3. Test Zero-Mutation Guard (POST Requests Blocked with 403 Forbidden)
$dummyCustomer = Customer::first() ?? Customer::create(['id' => (string) Str::uuid(), 'name' => 'Test Debtor', 'phone' => '08011223344', 'total_debt' => 50000]);

$postMutations = [
    ['route' => '/products', 'data' => ['code' => 'TEST-SKU', 'unitPrice' => 5000, 'costPrice' => 3000]],
    ['route' => '/stock/in', 'data' => ['product_id' => 1, 'quantity' => 10]],
    ['route' => '/stock/transfer-out', 'data' => ['destination_warehouse_id' => 2]],
    ['route' => '/stock/adjustments', 'data' => ['product_id' => 1, 'quantity' => 5]],
    ['route' => "/debts/pay/{$dummyCustomer->id}", 'data' => ['amount' => 10000, 'payment_method' => 'CASH']],
    ['route' => '/users', 'data' => ['name' => 'New Worker', 'email' => 'worker@hysam.com', 'role' => 'cashier']],
];

echo "🔒 Step 3: Testing Zero-Mutation Guard (POST Attempt Interception)...\n";
$session = $app->make('session.store');
$session->start();

foreach ($postMutations as $item) {
    $req = Request::create($item['route'], 'POST', array_merge($item['data'], ['_token' => $session->token()]));
    $req->setLaravelSession($session);
    $req->headers->set('Accept', 'application/json');
    $req->setUserResolver(fn() => $viewer);
    $response = $app->handle($req);
    $status = $response->getStatusCode();

    assert($status === 403, "Proof Failed: Mutation was not blocked for {$item['route']} (Status: {$status})");
    echo "   • [403 Forbidden] Blocked mutating POST request to: {$item['route']}\n";
}

echo "\n✅ Step 3: Zero-Mutation Guard Verified (All mutating actions strictly rejected)\n\n";

// 4. Test System Settings Isolation (Settings is strictly admin-only)
$settingsReq = Request::create('/settings', 'GET');
$settingsReq->headers->set('Accept', 'application/json');
$settingsReq->setUserResolver(fn() => $viewer);
$settingsResp = $app->handle($settingsReq);
assert($settingsResp->getStatusCode() === 403, "Proof Failed: Settings was not blocked for viewer");
echo "✅ Step 4: Settings Hub Privacy Verified (Admin-only restricted)\n\n";

echo "====================================================================\n";
echo "   ALL VIEW-ONLY REFINEMENT PROOFS PASSED (100% SUCCESS)             \n";
echo "====================================================================\n\n";
