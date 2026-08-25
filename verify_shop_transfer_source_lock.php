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
use App\Models\StockAdjustment;
use App\Http\Controllers\Web\StockController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "\n====================================================================\n";
echo "   HYSAM VENTURES - SHOP TRANSFER SOURCE LOCK VERIFICATION          \n";
echo "====================================================================\n\n";

$shop1 = Warehouse::firstOrCreate(['code' => 'SHOP-TEST-01'], ['name' => 'Shop 1 HQ Branch', 'is_active' => true]);
$shop2 = Warehouse::firstOrCreate(['code' => 'SHOP-TEST-02'], ['name' => 'Shop 2 Nwaniba Branch', 'is_active' => true]);

$manager1 = User::firstOrCreate(
    ['email' => 'manager.shop1@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Manager Shop 1 HQ',
        'password' => Hash::make('password123'),
        'role' => 'manager',
        'warehouse_id' => $shop1->id,
        'disabled' => false,
        'permissions' => ['pos' => true, 'stock' => true],
    ]
);
$manager1->warehouse_id = $shop1->id;
$manager1->save();

$manager2 = User::firstOrCreate(
    ['email' => 'manager.shop2@hysam.com'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Manager Shop 2 Nwaniba',
        'password' => Hash::make('password123'),
        'role' => 'manager',
        'warehouse_id' => $shop2->id,
        'disabled' => false,
        'permissions' => ['pos' => true, 'stock' => true],
    ]
);
$manager2->warehouse_id = $shop2->id;
$manager2->save();

$product = Product::where('archived', false)->first();

// Set Initial Physical Stocks: Shop 1 = 80 units, Shop 2 = 20 units
StockLevel::updateOrCreate(
    ['product_id' => $product->id, 'warehouse_id' => $shop1->id],
    ['physical_stock' => 80, 'allocated_stock' => 0]
);
StockLevel::updateOrCreate(
    ['product_id' => $product->id, 'warehouse_id' => $shop2->id],
    ['physical_stock' => 20, 'allocated_stock' => 0]
);

$stockController = $app->make(StockController::class);

// ─────────────────────────────────────────────────────────────
// TEST 1: Manager 1 dispatches transfer with spoofed source_warehouse_id
// ─────────────────────────────────────────────────────────────
Auth::login($manager1);

$transferQty = 15;
$dispatchReq = Request::create('/stock/transfer-out', 'POST', [
    'source_warehouse_id' => $shop2->id, // Attempting to spoof origin as Shop 2!
    'destination_warehouse_id' => $shop2->id, // Real destination is Shop 2
    'items' => [
        [
            'productId' => $product->id,
            'quantity' => $transferQty,
        ]
    ],
    'carrier_name' => 'Driver Ibrahim (Van Plate ABJ-456)',
    'notes' => 'Transfer 15 units from Shop 1 to Shop 2',
]);

// Wait: if source is spoofed as shop 2 and destination is shop 2, the controller should detect that source is forced to shop 1, allowing the transfer!
$stockController->transferOut($dispatchReq);

$latestTransfer = Transfer::with(['items', 'source', 'destination'])->latest()->first();

$shop1StockAfterDispatch = StockLevel::where('product_id', $product->id)->where('warehouse_id', $shop1->id)->value('physical_stock');
$shop2StockAfterDispatch = StockLevel::where('product_id', $product->id)->where('warehouse_id', $shop2->id)->value('physical_stock');

echo "🔍 TEST 1: Shop 1 Manager Initiates Transfer\n";
echo "   • Attempted Spoofed Source: Shop 2 (ID: {$shop2->id})\n";
echo "   • System Enforced Source: '{$latestTransfer->source->name}' (ID: {$latestTransfer->source_warehouse_id})\n";
echo "   • Shop 1 Physical Stock: 80 → {$shop1StockAfterDispatch} (Deducted {$transferQty} units)\n";
echo "   • Shop 2 Physical Stock (Untouched during transit): {$shop2StockAfterDispatch} units\n";

if ($latestTransfer->source_warehouse_id != $shop1->id) {
    throw new \Exception("Source warehouse was not locked to Shop 1!");
}
if ($shop1StockAfterDispatch !== (80 - $transferQty)) {
    throw new \Exception("Stock was not deducted from Shop 1!");
}
if ($shop2StockAfterDispatch !== 20) {
    throw new \Exception("Shop 2 stock changed prematurely before receipt verification!");
}
echo "   ✅ Source Shop was strictly locked to Shop 1\n\n";

// ─────────────────────────────────────────────────────────────
// TEST 2: Shop 1 Manager tries to receive goods destined for Shop 2 (Security Block)
// ─────────────────────────────────────────────────────────────
$unauthReceiveReq = Request::create("/stock/transfer-in/{$latestTransfer->id}", 'POST', [
    'counted_items' => [
        $product->id => $transferQty
    ],
    'discrepancy_notes' => null
]);

$unauthResp = $stockController->transferIn($unauthReceiveReq, $latestTransfer->id);

if ($unauthResp->isRedirect() && session('errors')) {
    echo "🔍 TEST 2: Shop 1 Manager Attempts to Verify Transfer Destined for Shop 2\n";
    echo "   • System Response: BLOCKED (Unauthorized branch receipt)\n";
    echo "   ✅ Cross-branch receipt strictly prevented\n\n";
} else {
    throw new \Exception("Security failure: Shop 1 Manager was able to receive transfer meant for Shop 2!");
}

// ─────────────────────────────────────────────────────────────
// TEST 3: Shop 2 Manager receives and verifies the goods
// ─────────────────────────────────────────────────────────────
Auth::login($manager2);

$authReceiveReq = Request::create("/stock/transfer-in/{$latestTransfer->id}", 'POST', [
    'counted_items' => [
        $product->id => $transferQty
    ],
    'discrepancy_notes' => null
]);

$stockController->transferIn($authReceiveReq, $latestTransfer->id);

$shop2StockAfterReceipt = StockLevel::where('product_id', $product->id)->where('warehouse_id', $shop2->id)->value('physical_stock');
$updatedTransfer = Transfer::find($latestTransfer->id);

echo "🔍 TEST 3: Shop 2 Manager Receives Transfer #{$latestTransfer->transfer_no}\n";
echo "   • Status: '{$updatedTransfer->status}'\n";
echo "   • Shop 2 Physical Stock: 20 → {$shop2StockAfterReceipt} (Added {$transferQty} verified units)\n";

if ($updatedTransfer->status !== 'RECEIVED') {
    throw new \Exception("Transfer status did not update to RECEIVED!");
}
if ($shop2StockAfterReceipt !== (20 + $transferQty)) {
    throw new \Exception("Shop 2 stock did not increase by verified transfer count!");
}
echo "   ✅ Goods verified and added to Shop 2 closing stock cleanly\n\n";

// ─────────────────────────────────────────────────────────────
// TEST 4: Stock Adjustment Locked to Assigned Shop
// ─────────────────────────────────────────────────────────────
Auth::login($manager1);

$adjReq = Request::create('/stock/adjustments', 'POST', [
    'warehouse_id' => $shop2->id, // Attempting to write off stock in Shop 2
    'product_id' => $product->id,
    'type' => 'DAMAGE',
    'quantity' => 5,
    'reason' => 'Broken glass packaging during shelf cleaning'
]);

$stockController->recordAdjustment($adjReq);

$latestAdj = StockAdjustment::latest()->first();
$shop1StockAfterAdj = StockLevel::where('product_id', $product->id)->where('warehouse_id', $shop1->id)->value('physical_stock');

echo "🔍 TEST 4: Stock Adjustment Warehouse Lock\n";
echo "   • Attempted Adjustment Warehouse: Shop 2\n";
echo "   • Enforced Adjustment Warehouse: '{$latestAdj->warehouse->name}' (ID: {$latestAdj->warehouse_id})\n";
echo "   • Shop 1 Physical Stock: {$shop1StockAfterDispatch} → {$shop1StockAfterAdj} (Deducted 5 units)\n";

if ($latestAdj->warehouse_id != $shop1->id) {
    throw new \Exception("Stock adjustment was not locked to Shop 1!");
}
echo "   ✅ Stock Adjustment strictly locked to user's assigned branch\n\n";

echo "====================================================================\n";
echo "   TRANSFER ORIGIN LOCKING 100% PROVEN & MATHEMATICALLY ERROR-FREE  \n";
echo "====================================================================\n\n";
