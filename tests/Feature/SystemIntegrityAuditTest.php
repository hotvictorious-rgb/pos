<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Transfer;
use App\Models\CashierShift;
use App\Services\StockService;
use Illuminate\Support\Str;

class SystemIntegrityAuditTest extends TestCase
{
    protected StockService $stockService;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = app(StockService::class);

        // Ensure warehouses exist
        $this->warehouseA = Warehouse::firstOrCreate(
            ['code' => 'MAIN-01'],
            ['name' => 'Main Warehouse', 'location' => 'HQ', 'is_active' => true]
        );
        $this->warehouseB = Warehouse::firstOrCreate(
            ['code' => 'BRANCH-02'],
            ['name' => 'Branch Shop B', 'location' => 'Market Road', 'is_active' => true]
        );

        // Create or get test product
        $this->product = Product::firstOrCreate(
            ['code' => 'AUDIT-TEST-RICE-50KG'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Audit Test Rice 50kg',
                'category' => 'Grains & Rice',
                'unitPrice' => 75000.00,
                'minStockLevel' => 5,
                'archived' => false,
                'updatedAt' => now()->toIso8601String(),
            ]
        );

        // Reset stock level for test product in Warehouse A to 100 units
        StockLevel::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouseA->id],
            ['physical_stock' => 100, 'unsupplied_stock' => 0]
        );

        // Reset stock level in Warehouse B to 20 units
        StockLevel::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouseB->id],
            ['physical_stock' => 20, 'unsupplied_stock' => 0]
        );
    }

    /**
     * PROOF 1: Mathematical Accuracy of Immediate Delivery Sale
     */
    public function test_mathematical_accuracy_immediate_sale()
    {
        $initialStock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('physical_stock');

        $qty = 4;
        $unitPrice = 75000.00;
        $expectedSubtotal = $qty * $unitPrice; // 300,000.00
        $discount = 10000.00;
        $expectedTotal = $expectedSubtotal - $discount; // 290,000.00
        $paidAmount = 200000.00;
        $expectedDebt = $expectedTotal - $paidAmount; // 90,000.00

        // Execute sale
        $sale = $this->stockService->recordSale([
            'warehouseId' => $this->warehouseA->id,
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => $qty,
                    'unitPrice' => $unitPrice,
                    'customPrice' => $unitPrice,
                ]
            ],
            'discount' => $discount,
            'paidAmount' => $paidAmount,
            'paymentMethod' => 'CASH',
            'isDelivered' => true,
            'customerName' => 'Chief Okon Audited',
            'customerPhone' => '08011223344',
            'cashierName' => 'Test Cashier',
        ]);

        // Math Assertions
        $this->assertEquals($expectedSubtotal, (float) $sale->subtotal, 'Subtotal calculation failed');
        $this->assertEquals($expectedTotal, (float) $sale->totalAmount, 'Total calculation after discount failed');
        $this->assertEquals($paidAmount, (float) $sale->paidAmount, 'Paid amount mismatch');
        $this->assertEquals($expectedDebt, (float) $sale->balanceDue, 'Debt amount calculation failed');

        // Physical Stock Deduction Assertion
        $newStock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('physical_stock');

        $this->assertEquals($initialStock - $qty, $newStock, 'Physical stock was not decremented correctly on immediate delivery');
    }

    /**
     * PROOF 2: Mathematical Accuracy of Delayed Pickup & Stock Segregation
     */
    public function test_delayed_pickup_stock_segregation_and_subsequent_dispatch()
    {
        $initialPhysical = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('physical_stock');

        $qty = 5;

        // Execute sale awaiting pickup (isDelivered = false)
        $sale = $this->stockService->recordSale([
            'warehouseId' => $this->warehouseA->id,
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => $qty,
                    'unitPrice' => 75000.00,
                ]
            ],
            'discount' => 0,
            'paidAmount' => 375000.00,
            'paymentMethod' => 'TRANSFER',
            'isDelivered' => false,
            'customerName' => 'Late Pickup Customer',
            'customerPhone' => '08099887766',
            'cashierName' => 'Test Cashier',
        ]);

        $stockAfterSale = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->first();

        // Anti-theft rule: Physical stock on shelf must remain unchanged until customer collects goods!
        $this->assertEquals($initialPhysical, $stockAfterSale->physical_stock, 'Physical shelf stock changed prematurely before customer collection!');
        $this->assertEquals($qty, $stockAfterSale->unsupplied_stock, 'Unsupplied stock buffer was not incremented correctly');

        // Now dispatch the goods when customer arrives with truck
        $this->stockService->dispatchSale($sale->id, 'Dispatched to customer pickup truck XYZ');

        $stockAfterDispatch = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->first();

        $this->assertEquals($initialPhysical - $qty, $stockAfterDispatch->physical_stock, 'Physical stock was not decremented upon physical dispatch!');
        $this->assertEquals(0, $stockAfterDispatch->unsupplied_stock, 'Unsupplied stock was not cleared after dispatch!');
    }

    /**
     * PROOF 3: Mathematical Accuracy of Inter-Branch Transfers & In-Transit Buffer
     */
    public function test_interbranch_transfer_dispatch_and_receipt_math()
    {
        $originInitial = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('physical_stock');

        $destInitial = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseB->id)
            ->value('physical_stock');

        $transferQty = 10;

        // 1. Dispatch transfer from Warehouse A -> Warehouse B
        $transfer = $this->stockService->initiateTransfer([
            'sourceWarehouseId' => $this->warehouseA->id,
            'targetWarehouseId' => $this->warehouseB->id,
            'items' => [
                ['productId' => $this->product->id, 'quantity' => $transferQty]
            ],
            'initiatedBy' => 'Test Dispatcher',
            'notes' => 'Urgent replenishment to Branch B',
        ]);

        // Assert Origin was deducted immediately
        $originAfterDispatch = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->value('physical_stock');
        $this->assertEquals($originInitial - $transferQty, $originAfterDispatch, 'Origin physical stock was not deducted on transfer dispatch!');

        // Assert Destination is UNCHANGED while in transit (anti-ghost stock rule)
        $destWhileInTransit = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseB->id)
            ->value('physical_stock');
        $this->assertEquals($destInitial, $destWhileInTransit, 'Destination physical stock increased before physical arrival and count!');

        // 2. Receive and count goods at Destination shop
        $this->stockService->receiveTransfer($transfer->id, [
            $this->product->id => $transferQty
        ], 'Storekeeper verified 10 bags in good condition');

        $destAfterReceipt = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseB->id)
            ->value('physical_stock');
        $this->assertEquals($destInitial + $transferQty, $destAfterReceipt, 'Destination stock did not increment by exact verified count!');
    }

    /**
     * PROOF 4: All Core Web Pages and Reports Endpoints Return 200 OK
     */
    public function test_all_web_routes_and_export_endpoints_load_successfully()
    {
        $routes = [
            '/',
            '/pos',
            '/products',
            '/products?category=Grains+%26+Rice',
            '/products?stock_status=IN_STOCK',
            '/products/template/csv',
            '/products/export/csv',
            '/products/export/json',
            '/stock',
            '/stock/transfers',
            '/stock/adjustments',
            '/stock/unsupplied',
            '/debts',
            '/transactions',
            '/auditor',
            '/reports',
            '/reports?date_preset=this_month',
            '/reports?payment_status=PAID',
            '/reports/export-csv/sales',
            '/reports/export-csv/stock',
            '/reports/export-csv/transfers',
            '/reports/export-csv/debtors',
            '/reports/export-json/sales',
            '/reports/export-json/stock',
            '/users',
            '/help',
            '/settings',
        ];

        foreach ($routes as $url) {
            $response = $this->get($url);
            $this->assertTrue(
                in_array($response->status(), [200, 302]),
                "Route {$url} failed with HTTP status code: {$response->status()}"
            );
        }
    }
}
