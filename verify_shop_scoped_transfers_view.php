<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Http\Controllers\Web\StockController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "\n====================================================================\n";
echo "   HYSAM VENTURES - STRICT SHOP SCOPED TRANSFERS VISIBILITY PROOF   \n";
echo "====================================================================\n\n";

$shop1 = Warehouse::firstOrCreate(['code' => 'SHOP-TEST-01'], ['name' => 'Shop 1 HQ Branch', 'is_active' => true]);
$shop2 = Warehouse::firstOrCreate(['code' => 'SHOP-TEST-02'], ['name' => 'Shop 2 Nwaniba Branch', 'is_active' => true]);
$shop3 = Warehouse::firstOrCreate(['code' => 'SHOP-TEST-03'], ['name' => 'Shop 3 Ikot Ekpene Branch', 'is_active' => true]);

$manager1 = User::firstOrCreate(['email' => 'manager.shop1@hysam.com']);
$manager1->warehouse_id = $shop1->id;
$manager1->save();

$manager2 = User::firstOrCreate(['email' => 'manager.shop2@hysam.com']);
$manager2->warehouse_id = $shop2->id;
$manager2->save();

$manager3 = User::firstOrCreate(
    ['email' => 'manager.shop3@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Manager Shop 3 Ikot Ekpene',
        'password' => Hash::make('secret123'),
        'role' => 'manager',
        'warehouse_id' => $shop3->id,
        'disabled' => false,
        'permissions' => ['pos' => true, 'stock' => true],
    ]
);
$manager3->warehouse_id = $shop3->id;
$manager3->save();

$product = Product::where('archived', false)->first();

$stockController = $app->make(StockController::class);

// Create Transfer A: Shop 1 -> Shop 2
Auth::login($manager1);
$stockController->transferOut(Request::create('/stock/transfer-out', 'POST', [
    'destination_warehouse_id' => $shop2->id,
    'items' => [['productId' => $product->id, 'quantity' => 10]],
    'carrier_name' => 'Driver A (Shop 1 to 2)',
    'notes' => 'Transfer A'
]));
$transferA = Transfer::orderBy('id', 'desc')->first();

// Create Transfer B: Shop 2 -> Shop 3
Auth::login($manager2);
$stockController->transferOut(Request::create('/stock/transfer-out', 'POST', [
    'destination_warehouse_id' => $shop3->id,
    'items' => [['productId' => $product->id, 'quantity' => 5]],
    'carrier_name' => 'Driver B (Shop 2 to 3)',
    'notes' => 'Transfer B'
]));
$transferB = Transfer::orderBy('id', 'desc')->first();

// ─────────────────────────────────────────────────────────────
// PROOF 1: SHOP 1 MANAGER VIEW SCOPING
// ─────────────────────────────────────────────────────────────
Auth::login($manager1);
$viewData1 = $stockController->transfersList(Request::create('/stock/transfers', 'GET'))->getData();
$transfersShop1 = $viewData1['allTransfers'];

$containsAForShop1 = $transfersShop1->contains('id', $transferA->id);
$containsBForShop1 = $transfersShop1->contains('id', $transferB->id);

echo "🔍 PROOF 1: Shop 1 Manager Visibility\n";
echo "   • Sees Transfer A (Shop 1 -> Shop 2): " . ($containsAForShop1 ? 'YES' : 'NO') . "\n";
echo "   • Sees Transfer B (Shop 2 -> Shop 3): " . ($containsBForShop1 ? 'YES (LEAK)' : 'NO (SECURE - Zero Visibility)') . "\n";

if (!$containsAForShop1 || $containsBForShop1) {
    throw new \Exception("Shop 1 Manager saw unrelated transfers between Shop 2 and Shop 3!");
}
echo "   ✅ Shop 1 strictly isolated to its own dispatched/received movements\n\n";

// ─────────────────────────────────────────────────────────────
// PROOF 2: SHOP 3 MANAGER VIEW SCOPING
// ─────────────────────────────────────────────────────────────
Auth::login($manager3);
$viewData3 = $stockController->transfersList(Request::create('/stock/transfers', 'GET'))->getData();
$transfersShop3 = $viewData3['allTransfers'];

$containsAForShop3 = $transfersShop3->contains('id', $transferA->id);
$containsBForShop3 = $transfersShop3->contains('id', $transferB->id);

echo "🔍 PROOF 2: Shop 3 Manager Visibility\n";
echo "   • Sees Transfer A (Shop 1 -> Shop 2): " . ($containsAForShop3 ? 'YES (LEAK)' : 'NO (SECURE - Zero Visibility)') . "\n";
echo "   • Sees Transfer B (Shop 2 -> Shop 3 - Incoming): " . ($containsBForShop3 ? 'YES' : 'NO') . "\n";

if ($containsAForShop3 || !$containsBForShop3) {
    throw new \Exception("Shop 3 Manager saw unrelated transfers between Shop 1 and Shop 2!");
}
echo "   ✅ Shop 3 strictly isolated to its own incoming shipment\n\n";

// ─────────────────────────────────────────────────────────────
// PROOF 3: ADMIN CONSOLIDATED MULTI-BRANCH OVERSIGHT
// ─────────────────────────────────────────────────────────────
$admin = User::where('role', 'admin')->first();
Auth::login($admin);
$viewDataAdmin = $stockController->transfersList(Request::create('/stock/transfers', 'GET'))->getData();
$transfersAdmin = $viewDataAdmin['allTransfers'];

$containsAForAdmin = $transfersAdmin->contains('id', $transferA->id);
$containsBForAdmin = $transfersAdmin->contains('id', $transferB->id);

echo "🔍 PROOF 3: Super Admin / Auditor Visibility\n";
echo "   • Sees Transfer A: " . ($containsAForAdmin ? 'YES' : 'NO') . "\n";
echo "   • Sees Transfer B: " . ($containsBForAdmin ? 'YES' : 'NO') . "\n";

if (!$containsAForAdmin || !$containsBForAdmin) {
    throw new \Exception("Super Admin failed to see all transfers!");
}
echo "   ✅ Super Admin has complete multi-branch visibility\n\n";

echo "====================================================================\n";
echo "   SHOP SCOPED TRANSFERS VISIBILITY 100% PROVED & VERIFIED          \n";
echo "====================================================================\n\n";
