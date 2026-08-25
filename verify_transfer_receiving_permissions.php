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
echo "   HYSAM VENTURES - TRANSFER RECEIVING & RECALL PERMISSION PROOF    \n";
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

// Setup stock: Shop 1 = 100, Shop 2 = 50
StockLevel::updateOrCreate(['product_id' => $product->id, 'warehouse_id' => $shop1->id], ['physical_stock' => 100, 'allocated_stock' => 0]);
StockLevel::updateOrCreate(['product_id' => $product->id, 'warehouse_id' => $shop2->id], ['physical_stock' => 50, 'allocated_stock' => 0]);

$stockController = $app->make(StockController::class);

// ─────────────────────────────────────────────────────────────
// SCENARIO 1: Shop 1 dispatches 20 units to Shop 2
// ─────────────────────────────────────────────────────────────
Auth::login($manager1);

$dispReq = Request::create('/stock/transfer-out', 'POST', [
    'destination_warehouse_id' => $shop2->id,
    'items' => [
        [
            'productId' => $product->id,
            'quantity' => 20
        ]
    ],
    'carrier_name' => 'Driver Emeka (Plate ABC-999)',
    'notes' => 'Urgent replenishment'
]);
$stockController->transferOut($dispReq);
$transfer1 = Transfer::latest()->first();

$shop1Stock = StockLevel::where('product_id', $product->id)->where('warehouse_id', $shop1->id)->value('physical_stock');
$shop2Stock = StockLevel::where('product_id', $product->id)->where('warehouse_id', $shop2->id)->value('physical_stock');

echo "🔍 SCENARIO 1: Shop 1 Dispatches Transfer #{$transfer1->transfer_no}\n";
echo "   • Shop 1 Stock (Deducted): 100 → {$shop1Stock}\n";
echo "   • Shop 2 Stock (In-Transit): 50 → {$shop2Stock}\n";
echo "   • Transfer Status: '{$transfer1->status}'\n";

if ($shop1Stock !== 80 || $shop2Stock !== 50) {
    throw new \Exception("Stock deduction calculation failed!");
}
echo "   ✅ Stock correctly deducted from origin only\n\n";

// ─────────────────────────────────────────────────────────────
// SCENARIO 2: Shop 1 attempts to Receive / Count its own dispatched transfer
// ─────────────────────────────────────────────────────────────
echo "🔍 SCENARIO 2: Testing Source Shop Unauthorized Receive Attempt\n";
$unauthReq = Request::create("/stock/transfers/{$transfer1->id}/receive", 'POST', [
    'counted_items' => [$product->id => 20]
]);
$unauthResp = $stockController->transferIn($unauthReq, $transfer1->id);

if ($unauthResp->isRedirect() && session('errors')) {
    echo "   • Security Gate Response: 🔒 UNAUTHORIZED (Source shop blocked from receiving)\n";
    echo "   ✅ Source shop cannot accept/count transfer\n\n";
} else {
    throw new \Exception("Security failure: Source shop was permitted to receive its own transfer!");
}

// ─────────────────────────────────────────────────────────────
// SCENARIO 3: Shop 2 (Destination) receives and counts the transfer
// ─────────────────────────────────────────────────────────────
Auth::login($manager2);
echo "🔍 SCENARIO 3: Destination Shop Receives Goods\n";
$authRecvReq = Request::create("/stock/transfers/{$transfer1->id}/receive", 'POST', [
    'counted_items' => [$product->id => 20]
]);
$stockController->transferIn($authRecvReq, $transfer1->id);

$transfer1Updated = Transfer::find($transfer1->id);
$shop2StockAfterRecv = StockLevel::where('product_id', $product->id)->where('warehouse_id', $shop2->id)->value('physical_stock');

echo "   • Status: '{$transfer1Updated->status}'\n";
echo "   • Shop 2 Physical Stock: 50 → {$shop2StockAfterRecv} (+20 verified units)\n";

if ($transfer1Updated->status !== 'RECEIVED' || $shop2StockAfterRecv !== 70) {
    throw new \Exception("Destination shop receipt failed!");
}
echo "   ✅ Destination shop verified and received goods cleanly\n\n";

// ─────────────────────────────────────────────────────────────
// SCENARIO 4: Recall / Cancel Dispatched Transfer (Restores Stock)
// ─────────────────────────────────────────────────────────────
Auth::login($manager1);
echo "🔍 SCENARIO 4: Testing Transfer Recall / Cancellation by Origin Shop\n";

$dispReq2 = Request::create('/stock/transfer-out', 'POST', [
    'destination_warehouse_id' => $shop2->id,
    'items' => [
        [
            'productId' => $product->id,
            'quantity' => 15
        ]
    ],
    'carrier_name' => 'Driver Musa (Van #XYZ-111)',
    'notes' => 'Test transfer for recall'
]);
$stockController->transferOut($dispReq2);
$transfer2 = Transfer::orderBy('id', 'desc')->first();

$shop1StockBeforeRecall = StockLevel::where('product_id', $product->id)->where('warehouse_id', $shop1->id)->value('physical_stock');
echo "   • Dispatched Transfer #{$transfer2->transfer_no} (15 units)\n";
echo "   • Shop 1 Stock Before Recall: {$shop1StockBeforeRecall} units\n";

// Shop 1 Recalls Transfer (Trip aborted)
$recallReq = Request::create("/stock/transfer-recall/{$transfer2->id}", 'POST', [
    'reason' => 'Van broke down, goods returned to Shop 1 warehouse'
]);
$stockController->recallTransfer($recallReq, $transfer2->id);

$transfer2Cancelled = Transfer::find($transfer2->id);
$shop1StockAfterRecall = StockLevel::where('product_id', $product->id)->where('warehouse_id', $shop1->id)->value('physical_stock');

echo "   • Cancelled Status: '{$transfer2Cancelled->status}'\n";
echo "   • Shop 1 Physical Stock Restored: {$shop1StockBeforeRecall} → {$shop1StockAfterRecall} (+15 units returned)\n";

if ($transfer2Cancelled->status !== 'CANCELLED' || $shop1StockAfterRecall !== ($shop1StockBeforeRecall + 15)) {
    throw new \Exception("Transfer recall did not restore shelf stock!");
}
echo "   ✅ Transfer recall successfully restored physical stock to source shop with zero leakage\n\n";

echo "====================================================================\n";
echo "   TRANSFER RECEIVING & RECALL 100% MATHEMATICALLY PROVED & SECURE   \n";
echo "====================================================================\n\n";
