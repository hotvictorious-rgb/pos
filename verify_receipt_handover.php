<?php

/**
 * Receipt Handover Status Verification Script
 * Validates that receipts dynamically and accurately display:
 * 1. "✓ GOODS SUPPLIED & COLLECTED" when goods are taken immediately (isSuppliedNow = true)
 * 2. "⏳ GOODS IN SHOP (PENDING PICKUP)" when goods are left in shop (isSuppliedNow = false)
 * 3. "✓ GOODS SUPPLIED & COLLECTED" after unsupplied goods are later fulfilled/dispatched.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\StockService;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo "   HYSAM VENTURES - RECEIPT HANDOVER STATUS VERIFICATION SUITE\n";
echo "====================================================================\n\n";

$stockService = app(StockService::class);

// 1. Setup Test Warehouse & Product
$warehouse = Warehouse::firstOrCreate(
    ['code' => 'RECEIPT-TEST-SHOP'],
    ['name' => 'Receipt Test Branch', 'address' => 'Market Square', 'phone' => '0800000000', 'is_active' => true]
);

$product = Product::firstOrCreate(
    ['code' => 'RECEIPT-TEST-PROD-01'],
    [
        'id' => (string) Str::uuid(),
        'name' => 'Receipt Test Vegetable Oil 25L',
        'category' => 'Groceries',
        'unitPrice' => 50000,
        'minStockLevel' => 5,
        'archived' => false,
        'updatedAt' => now()->toIso8601String(),
    ]
);

StockLevel::updateOrCreate(
    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
    ['physical_stock' => 100, 'allocated_stock' => 0]
);

$customer = Customer::firstOrCreate(
    ['name' => 'Hon. Test Customer'],
    ['phone' => '08012345678', 'total_debt' => 0]
);

$passCount = 0;
$failCount = 0;

$assertCondition = function(bool $condition, string $testName) use (&$passCount, &$failCount) {
    if ($condition) {
        echo "   ✓ PASS: {$testName}\n";
        $passCount++;
    } else {
        echo "   ✗ FAIL: {$testName}\n";
        $failCount++;
    }
};

// -----------------------------------------------------------------------------
// SCENARIO 1: Immediate Counter Handover (isSuppliedNow = true)
// -----------------------------------------------------------------------------
echo "[1/3] Testing Sale with IMMEDIATE Handover (Goods Supplied Now)...\n";

$saleSupplied = $stockService->recordSale(
    [
        'id' => (string) Str::uuid(),
        'totalAmount' => 50000,
        'paidAmount' => 50000,
        'cashAmount' => 50000,
        'posAmount' => 0,
        'transferAmount' => 0,
        'customerId' => $customer->id,
        'customerName' => $customer->name,
    ],
    [
        [
            'productId' => $product->id,
            'code' => $product->code,
            'productName' => $product->name,
            'quantity' => 1,
            'unitPrice' => 50000,
            'totalPrice' => 50000,
        ]
    ],
    $warehouse->id,
    true, // isSuppliedNow = true
    'USER-TEST-1',
    'Cashier Joy'
);

$assertCondition($saleSupplied->deliveryStatus === 'DELIVERED', "Sale deliveryStatus in DB is 'DELIVERED'");

$htmlSupplied = view('pos.receipt', ['sale' => $saleSupplied, 'warehouse' => $warehouse])->render();

$assertCondition(str_contains($htmlSupplied, '✓ GOODS SUPPLIED & COLLECTED'), "Receipt HTML contains '✓ GOODS SUPPLIED & COLLECTED' badge");
$assertCondition(!str_contains($htmlSupplied, '⏳ GOODS IN SHOP (PENDING PICKUP)'), "Receipt HTML does NOT contain pending pickup badge");
$assertCondition(str_contains($htmlSupplied, '₦50,000'), "Receipt displays exact zero-decimal total ₦50,000");

// -----------------------------------------------------------------------------
// SCENARIO 2: Delayed Pickup / Goods Left in Shop (isSuppliedNow = false)
// -----------------------------------------------------------------------------
echo "\n[2/3] Testing Sale with DELAYED Handover (Goods Unsupplied / Left in Shop)...\n";

$saleUnsupplied = $stockService->recordSale(
    [
        'id' => (string) Str::uuid(),
        'totalAmount' => 100000,
        'paidAmount' => 100000,
        'cashAmount' => 0,
        'posAmount' => 0,
        'transferAmount' => 100000,
        'customerId' => $customer->id,
        'customerName' => $customer->name,
    ],
    [
        [
            'productId' => $product->id,
            'code' => $product->code,
            'productName' => $product->name,
            'quantity' => 2,
            'unitPrice' => 50000,
            'totalPrice' => 100000,
        ]
    ],
    $warehouse->id,
    false, // isSuppliedNow = false
    'USER-TEST-1',
    'Cashier Joy'
);

$assertCondition($saleUnsupplied->deliveryStatus === 'UNSUPPLIED', "Sale deliveryStatus in DB is 'UNSUPPLIED'");

$htmlUnsupplied = view('pos.receipt', ['sale' => $saleUnsupplied, 'warehouse' => $warehouse])->render();

$assertCondition(str_contains($htmlUnsupplied, '⏳ GOODS IN SHOP (PENDING PICKUP)'), "Receipt HTML contains '⏳ GOODS IN SHOP (PENDING PICKUP)' badge");
$assertCondition(str_contains($htmlUnsupplied, 'Items locked in shop stock buffer until customer pickup'), "Receipt explains physical buffer status clearly");
$assertCondition(!str_contains($htmlUnsupplied, '✓ GOODS SUPPLIED & COLLECTED'), "Receipt HTML does NOT contain supplied badge");

// -----------------------------------------------------------------------------
// SCENARIO 3: Subsequent Dispatch / Handover of Unsupplied Goods
// -----------------------------------------------------------------------------
echo "\n[3/3] Testing Subsequent Dispatch of Previously Unsupplied Order...\n";

$dispatchedSale = $stockService->dispatchUnsuppliedSale($saleUnsupplied->id, $warehouse->id, 'USER-TEST-DISPATCH', 'Storekeeper Emeka');

$assertCondition($dispatchedSale->deliveryStatus === 'DELIVERED', "Sale deliveryStatus transitioned to 'DELIVERED' after dispatch");
$assertCondition(!empty($dispatchedSale->deliveredAt), "Sale deliveredAt timestamp is recorded ({$dispatchedSale->deliveredAt})");
$assertCondition($dispatchedSale->deliveredBy === 'Storekeeper Emeka', "Sale deliveredBy is recorded ('Storekeeper Emeka')");

$htmlDispatched = view('pos.receipt', ['sale' => $dispatchedSale->fresh(), 'warehouse' => $warehouse])->render();

$assertCondition(str_contains($htmlDispatched, '✓ GOODS SUPPLIED & COLLECTED'), "Receipt HTML now renders '✓ GOODS SUPPLIED & COLLECTED'");
$assertCondition(str_contains($htmlDispatched, 'By: Storekeeper Emeka'), "Receipt shows handler officer name ('Storekeeper Emeka')");
$assertCondition(!str_contains($htmlDispatched, '⏳ GOODS IN SHOP (PENDING PICKUP)'), "Receipt no longer displays pending pickup badge");

echo "\n====================================================================\n";
echo "   VERIFICATION SUMMARY\n";
echo "====================================================================\n";
echo "Passed: {$passCount} | Failed: {$failCount}\n";

if ($failCount === 0) {
    echo "🌟 SUCCESS: Receipts are 100% verified to reflect correct handover status across all states!\n";
    exit(0);
} else {
    echo "❌ ERROR: One or more assertions failed.\n";
    exit(1);
}
