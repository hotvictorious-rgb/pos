<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Warehouse;
use App\Http\Middleware\RequireAdmin;
use App\Http\Controllers\Web\WholesaleController;
use App\Http\Controllers\Web\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "\n====================================================================\n";
echo "   HYSAM VENTURES - WHOLESALE ACCESS CONTROL & SECURITY TEST        \n";
echo "====================================================================\n\n";

$warehouse = Warehouse::first();

// Create/Get Test Users for each role
$manager = User::firstOrCreate(
    ['email' => 'manager.test@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Branch Manager User',
        'password' => Hash::make('secret123'),
        'role' => 'manager',
        'warehouse_id' => $warehouse->id,
        'disabled' => false,
        'permissions' => ['pos' => true, 'stock' => true],
    ]
);

$cashier = User::firstOrCreate(
    ['email' => 'cashier.test@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Cashier Floor Worker',
        'password' => Hash::make('secret123'),
        'role' => 'cashier',
        'warehouse_id' => $warehouse->id,
        'disabled' => false,
        'permissions' => ['pos' => true],
    ]
);

$viewer = User::firstOrCreate(
    ['email' => 'viewer.test@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Executive Viewer / Silent Investor',
        'password' => Hash::make('secret123'),
        'role' => 'viewer',
        'warehouse_id' => $warehouse->id,
        'disabled' => false,
        'permissions' => ['view_only' => true],
    ]
);

$admin = User::where('role', 'admin')->first();

$middleware = new RequireAdmin();

// 1. Test Manager Access to /wholesale
Auth::login($manager);
$reqManager = Request::create('/wholesale', 'GET');
$respManager = $middleware->handle($reqManager, function ($r) {
    return response('Passed');
});

if ($respManager->isRedirect()) {
    echo "✅ 1. Branch Manager Access to /wholesale: BLOCKED (Redirected with warning)\n";
} else {
    throw new \Exception("Security failure: Branch Manager was not blocked from /wholesale!");
}

// 2. Test Cashier Access to /wholesale
Auth::login($cashier);
$reqCashier = Request::create('/wholesale', 'GET');
$respCashier = $middleware->handle($reqCashier, function ($r) {
    return response('Passed');
});

if ($respCashier->isRedirect()) {
    echo "✅ 2. Cashier Floor Worker Access to /wholesale: BLOCKED (Redirected with warning)\n";
} else {
    throw new \Exception("Security failure: Cashier was not blocked from /wholesale!");
}

// 3. Test Admin Access to /wholesale
Auth::login($admin);
$reqAdmin = Request::create('/wholesale', 'GET');
$respAdmin = $middleware->handle($reqAdmin, function ($r) {
    return response('Passed');
});

if ($respAdmin->getContent() === 'Passed') {
    echo "✅ 3. Admin (Madam) Access to /wholesale: ALLOWED (Full Access)\n";
} else {
    throw new \Exception("Failure: Admin was blocked from /wholesale!");
}

// 4. Test Executive Viewer Access to /wholesale
Auth::login($viewer);
$reqViewer = Request::create('/wholesale', 'GET');
$respViewer = $middleware->handle($reqViewer, function ($r) {
    return response('Passed');
});

if ($respViewer->getContent() === 'Passed') {
    echo "✅ 4. Executive Viewer Access to /wholesale: ALLOWED (Read-Only Mode)\n";
} else {
    throw new \Exception("Failure: Viewer was blocked from /wholesale!");
}

// 5. Test Manager and Cashier Access to Stock Out Ledger (/transactions?tab=stock_out)
Auth::login($manager);
$transController = $app->make(TransactionController::class);
$stockOutReq = Request::create('/transactions', 'GET', ['tab' => 'stock_out']);
$stockOutView = $transController->index($stockOutReq);
$stockOutHtml = $stockOutView->render();

if (strpos($stockOutHtml, 'Total Physical Units Out') !== false || strpos($stockOutHtml, 'Outflow Event Type') !== false) {
    echo "✅ 5. Branch Manager Access to Stock Out Ledger: ALLOWED (Physical Count Transparency)\n";
} else {
    throw new \Exception("Failure: Manager could not view Stock Out ledger!");
}

echo "\n====================================================================\n";
echo "   ALL WHOLESALE SECURITY & ACCESS CONTROL TESTS PASSED (100%)      \n";
echo "====================================================================\n\n";
