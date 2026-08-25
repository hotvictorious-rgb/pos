<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Http\Controllers\Web\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "\n====================================================================\n";
echo "   HYSAM VENTURES - TRANSACTIONS HUB 8-TAB RENDER VERIFICATION      \n";
echo "====================================================================\n\n";

$admin = User::where('role', 'admin')->first();
Auth::login($admin);

$controller = $app->make(TransactionController::class);

$tabs = [
    'sales' => 'Tab 1: Sales',
    'stock_in' => 'Tab 2: Stock In',
    'stock_out' => 'Tab 3: Stock Out & Dispatches',
    'in_transit' => 'Tab 4: In-Transit',
    'transfers' => 'Tab 5: Incoming Transfers',
    'returns' => 'Tab 6: Customer Returns',
    'refunds' => 'Tab 7: Refunds',
    'debts' => 'Tab 8: Debts Ledger',
];

foreach ($tabs as $tabKey => $tabName) {
    $request = Request::create('/transactions', 'GET', ['tab' => $tabKey]);
    $view = $controller->index($request);
    $html = $view->render();

    echo "✅ {$tabName} rendered cleanly (HTML size: " . strlen($html) . " bytes)\n";
}

echo "\n====================================================================\n";
echo "   ALL 8 TRANSACTION TABS RENDERED 100% ERROR-FREE!                 \n";
echo "====================================================================\n\n";
