<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Http\Controllers\Web\PosController;
use Illuminate\Http\Request;

echo "====================================================================\n";
echo "   HYSAM VENTURES - CUSTOMER UNIQUENESS & ZERO-BYPASS AUDIT PROOF   \n";
echo "====================================================================\n\n";

$warehouse = Warehouse::firstOrCreate(['code' => 'MAIN-TEST'], ['name' => 'Main Test Warehouse', 'is_active' => true]);
$product = Product::firstOrCreate(
    ['code' => 'PROD-CUST-TEST'],
    ['id' => 'prod-cust-test-1', 'name' => 'Test Tile 60x60', 'category' => 'Floor Tiles', 'unitPrice' => 10000, 'currentStock' => 500, 'updatedAt' => now()->toIso8601String()]
);

$stockLevel = StockLevel::firstOrCreate(
    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
    ['physical_stock' => 500, 'allocated_stock' => 0]
);
$stockLevel->physical_stock = 500;
$stockLevel->save();

$controller = app(PosController::class);

// ─────────────────────────────────────────────────────────────
// PROOF 1: Quick Customer Registration & Auto-Code Generation
// ─────────────────────────────────────────────────────────────
$phone = '080' . rand(10000000, 99999999);
$quickReq = Request::create('/pos/customer/quick-register', 'POST', [
    'name' => 'Alhaji Unique Creditor',
    'phone' => $phone,
    'address' => 'Shop 44 Building Materials Market',
    'credit_limit' => 50000,
]);
$quickReq->headers->set('Accept', 'application/json');

$quickRes = $controller->quickRegisterCustomer($quickReq);
$data = json_decode($quickRes->getContent(), true);

assert($data['success'] === true, 'Proof 1 Failed: Quick register returned failure');
assert(!empty($data['customer']['customer_code']), 'Proof 1 Failed: Customer code not generated');
assert($data['customer']['phone'] === $phone, 'Proof 1 Failed: Phone mismatch');

echo "✅ [PROOF 1 PASSED] Quick Customer Registration & Auto-Code\n";
echo "   • Name: {$data['customer']['name']}\n";
echo "   • Unique Code: {$data['customer']['customer_code']}\n";
echo "   • Phone: {$data['customer']['phone']}\n\n";

$registeredCustomer = Customer::find($data['customer']['id']);

// ─────────────────────────────────────────────────────────────
// PROOF 2: Zero-Bypass Test: Credit Sale without Phone MUST BE BLOCKED
// ─────────────────────────────────────────────────────────────
$blockedCreditReq = Request::create('/pos/checkout', 'POST', [
    'warehouse_id' => $warehouse->id,
    'items' => [
        ['productId' => $product->id, 'quantity' => 2, 'unitPrice' => 10000]
    ],
    'totalAmount' => 20000,
    'paidAmount' => 5000, // ₦15,000 Debt
    'is_supplied' => 'yes',
    'customerName' => 'Walk-in Customer',
    'customerPhone' => '', // NO PHONE
]);
$blockedCreditReq->headers->set('Accept', 'application/json');

$blockedCreditRes = $controller->checkout($blockedCreditReq);
$blockedCreditData = json_decode($blockedCreditRes->getContent(), true);

assert($blockedCreditRes->getStatusCode() === 422, 'Proof 2 Failed: Expected 422 Unprocessable Entity');
assert($blockedCreditData['success'] === false, 'Proof 2 Failed: Expected success=false');
assert(str_contains($blockedCreditData['error'], 'Phone Number'), 'Proof 2 Failed: Error does not mention Phone Number');

echo "✅ [PROOF 2 PASSED] Zero-Bypass: Credit / Part-Payment without Phone is STRICTLY BLOCKED\n";
echo "   • Attempted: ₦15,000 Debt under 'Walk-in Customer' with no phone\n";
echo "   • Intercepted Error: {$blockedCreditData['error']}\n\n";

// ─────────────────────────────────────────────────────────────
// PROOF 3: Zero-Bypass Test: Delayed Pickup without Phone MUST BE BLOCKED
// ─────────────────────────────────────────────────────────────
$blockedPickupReq = Request::create('/pos/checkout', 'POST', [
    'warehouse_id' => $warehouse->id,
    'items' => [
        ['productId' => $product->id, 'quantity' => 2, 'unitPrice' => 10000]
    ],
    'totalAmount' => 20000,
    'paidAmount' => 20000, // Fully Paid
    'is_supplied' => 'no', // Delayed Pickup (Not Supplied)
    'customerName' => 'Walk-in Customer',
    'customerPhone' => '', // NO PHONE
]);
$blockedPickupReq->headers->set('Accept', 'application/json');

$blockedPickupRes = $controller->checkout($blockedPickupReq);
$blockedPickupData = json_decode($blockedPickupRes->getContent(), true);

assert($blockedPickupRes->getStatusCode() === 422, 'Proof 3 Failed: Expected 422 for Delayed Pickup without Phone');
assert($blockedPickupData['success'] === false, 'Proof 3 Failed: Expected success=false');

echo "✅ [PROOF 3 PASSED] Zero-Bypass: Delayed Pickup (Not Supplied) without Phone is STRICTLY BLOCKED\n";
echo "   • Attempted: Delayed Pickup under 'Walk-in Customer' with no phone\n";
echo "   • Intercepted Error: {$blockedPickupData['error']}\n\n";

// ─────────────────────────────────────────────────────────────
// PROOF 4: Zero-Bypass Test: Credit Sale with Invalid Phone MUST BE BLOCKED
// ─────────────────────────────────────────────────────────────
$invalidPhoneCreditReq = Request::create('/pos/checkout', 'POST', [
    'warehouse_id' => $warehouse->id,
    'items' => [
        ['productId' => $product->id, 'quantity' => 7, 'unitPrice' => 10000]
    ],
    'totalAmount' => 70000,
    'paidAmount' => 10000, // ₦60,000 Debt
    'is_supplied' => 'yes',
    'customerName' => 'Alhaji Musa',
    'customerPhone' => '080123', // Short invalid phone
]);
$invalidPhoneCreditReq->headers->set('Accept', 'application/json');

$invalidPhoneCreditRes = $controller->checkout($invalidPhoneCreditReq);
$invalidPhoneCreditData = json_decode($invalidPhoneCreditRes->getContent(), true);

assert($invalidPhoneCreditRes->getStatusCode() === 422, 'Proof 4 Failed: Expected 422 for invalid phone');
assert(str_contains($invalidPhoneCreditData['error'], '11 digits'), 'Proof 4 Failed: Error does not mention 11 digits');

echo "✅ [PROOF 4 PASSED] Invalid Phone Intercepted: Short phone on credit sale is STRICTLY BLOCKED\n";
echo "   • Attempted: Phone '080123' with ₦60,000 Debt\n";
echo "   • Intercepted Error: {$invalidPhoneCreditData['error']}\n\n";

// ─────────────────────────────────────────────────────────────
// PROOF 5: Legitimate Credit Sale with Verified Customer & Phone
// ─────────────────────────────────────────────────────────────
$validCreditReq = Request::create('/pos/checkout', 'POST', [
    'warehouse_id' => $warehouse->id,
    'items' => [
        ['productId' => $product->id, 'quantity' => 3, 'unitPrice' => 10000]
    ],
    'totalAmount' => 30000,
    'paidAmount' => 10000, // ₦20,000 Debt (within ₦50,000 limit)
    'is_supplied' => 'yes',
    'customerId' => $registeredCustomer->id,
    'customerName' => $registeredCustomer->name,
    'customerPhone' => $registeredCustomer->phone,
]);
$validCreditReq->headers->set('Accept', 'application/json');

$validCreditRes = $controller->checkout($validCreditReq);
$validCreditData = json_decode($validCreditRes->getContent(), true);

assert($validCreditData['success'] === true, 'Proof 5 Failed: Legitimate sale failed');

$registeredCustomer->refresh();
assert($registeredCustomer->total_debt == 20000, "Proof 5 Failed: Customer total debt expected 20,000, got {$registeredCustomer->total_debt}");

$ledger = CustomerLedger::where('customer_id', $registeredCustomer->id)->latest('id')->first();
assert($ledger !== null, 'Proof 5 Failed: Customer ledger record not found');
assert($ledger->balance_after == 20000, 'Proof 5 Failed: Ledger balance after expected 20,000');

echo "✅ [PROOF 5 PASSED] Legitimate Credit Sale Processed with Exact Account Code & Ledger Binding\n";
echo "   • Customer: {$registeredCustomer->name} [{$registeredCustomer->customer_code}]\n";
echo "   • Bill: ₦30,000 | Paid: ₦10,000 | New Customer Debt: ₦" . number_format($registeredCustomer->total_debt) . "\n";
echo "   • Ledger Entry: Type={$ledger->type}, Balance After=₦" . number_format($ledger->balance_after) . "\n\n";

// ─────────────────────────────────────────────────────────────
// PROOF 6: Full Cash Payment Allows Walk-in Customer
// ─────────────────────────────────────────────────────────────
$walkinReq = Request::create('/pos/checkout', 'POST', [
    'warehouse_id' => $warehouse->id,
    'items' => [
        ['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 10000]
    ],
    'totalAmount' => 10000,
    'paidAmount' => 10000, // Fully paid
    'is_supplied' => 'yes',
    'customerName' => 'Walk-in Customer',
    'customerPhone' => '',
]);
$walkinReq->headers->set('Accept', 'application/json');

$walkinRes = $controller->checkout($walkinReq);
$walkinData = json_decode($walkinRes->getContent(), true);

assert($walkinData['success'] === true, 'Proof 6 Failed: Walk-in full payment failed');

echo "✅ [PROOF 6 PASSED] Full Cash Payment allows Walk-in Customer for Fast Checkout\n";
echo "   • Total: ₦10,000 | Paid: ₦10,000 | Debt: ₦0\n";
echo "   • Sale ID: {$walkinData['saleId']}\n\n";

echo "====================================================================\n";
echo "   ALL 6 PROOFS PASSED (100% SUCCESS) - ZERO BYPASS VERIFIED        \n";
echo "====================================================================\n";
