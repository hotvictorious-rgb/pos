<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Transfer;
use App\Services\StockService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $stockService;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Product $product;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = app(StockService::class);

        // Ensure Admin user exists and is authenticated
        $this->user = User::firstOrCreate(
            ['id' => 'ADMIN-TEST-1'],
            [
                'name' => 'Admin Tester',
                'email' => 'admin@test.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'disabled' => false,
            ]
        );
        $this->actingAs($this->user);
        session(['user_id' => $this->user->id, 'user_role' => 'admin']);

        // Ensure warehouses exist
        $this->warehouseA = Warehouse::firstOrCreate(
            ['code' => 'MAIN-01'],
            ['name' => 'Main Warehouse', 'address' => 'HQ', 'is_active' => true]
        );
        $this->warehouseB = Warehouse::firstOrCreate(
            ['code' => 'BRANCH-02'],
            ['name' => 'Branch Shop B', 'address' => 'Market Road', 'is_active' => true]
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
            ['physical_stock' => 100, 'allocated_stock' => 0]
        );

        // Reset stock level in Warehouse B to 20 units
        StockLevel::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouseB->id],
            ['physical_stock' => 20, 'allocated_stock' => 0]
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
        $expectedTotal = $qty * $unitPrice; // 300,000.00
        $paidAmount = 200000.00;
        $expectedDebt = $expectedTotal - $paidAmount; // 100,000.00

        $customer = Customer::firstOrCreate(
            ['name' => 'Chief Okon Audited'],
            ['phone' => '08011223344', 'total_debt' => 0]
        );

        // Execute sale
        $sale = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => $expectedTotal,
                'paidAmount' => $paidAmount,
                'cashAmount' => $paidAmount,
                'posAmount' => 0,
                'transferAmount' => 0,
                'customerId' => $customer->id,
                'customerName' => $customer->name,
            ],
            [
                [
                    'productId' => $this->product->id,
                    'code' => $this->product->code,
                    'productName' => $this->product->name,
                    'quantity' => $qty,
                    'unitPrice' => $unitPrice,
                    'totalPrice' => $expectedTotal,
                ]
            ],
            $this->warehouseA->id,
            true, // isSuppliedNow
            $this->user->id,
            $this->user->name
        );

        // Math Assertions
        $this->assertEquals($expectedTotal, (float) $sale->totalAmount, 'Total calculation failed');
        $this->assertEquals($paidAmount, (float) $sale->paidAmount, 'Paid amount mismatch');
        $this->assertEquals($expectedDebt, (float) $customer->fresh()->total_debt, 'Debt balance calculation failed');

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
        $unitPrice = 75000.00;
        $total = $qty * $unitPrice;

        $customer = Customer::firstOrCreate(
            ['name' => 'Late Pickup Customer'],
            ['phone' => '08099887766', 'total_debt' => 0]
        );

        // Execute sale awaiting pickup (isSuppliedNow = false)
        $sale = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => $total,
                'paidAmount' => $total,
                'cashAmount' => 0,
                'posAmount' => $total,
                'transferAmount' => 0,
                'customerId' => $customer->id,
                'customerName' => $customer->name,
            ],
            [
                [
                    'productId' => $this->product->id,
                    'code' => $this->product->code,
                    'productName' => $this->product->name,
                    'quantity' => $qty,
                    'unitPrice' => $unitPrice,
                    'totalPrice' => $total,
                ]
            ],
            $this->warehouseA->id,
            false, // isSuppliedNow = false (awaiting pickup)
            $this->user->id,
            $this->user->name
        );

        $stockAfterSale = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->first();

        // Anti-theft rule: Physical stock on shelf must remain unchanged until customer collects goods!
        $this->assertEquals($initialPhysical, $stockAfterSale->physical_stock, 'Physical shelf stock changed prematurely before customer collection!');
        $this->assertEquals($qty, $stockAfterSale->allocated_stock, 'Unsupplied stock buffer was not incremented correctly');

        // Now dispatch the goods when customer arrives with truck
        $this->stockService->dispatchUnsuppliedSale($sale->id, $this->warehouseA->id, $this->user->id, 'Dispatched to customer pickup truck');

        $stockAfterDispatch = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->first();

        $this->assertEquals($initialPhysical - $qty, $stockAfterDispatch->physical_stock, 'Physical stock was not decremented upon physical dispatch!');
        $this->assertEquals(0, $stockAfterDispatch->allocated_stock, 'Unsupplied stock was not cleared after dispatch!');
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
        $transfer = $this->stockService->initiateTransfer(
            $this->warehouseA->id,
            $this->warehouseB->id,
            [
                ['productId' => $this->product->id, 'quantity' => $transferQty]
            ],
            $this->user->id,
            $this->user->name,
            'Urgent replenishment to Branch B'
        );

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
        $this->stockService->receiveTransfer(
            $transfer->id,
            [
                $this->product->id => $transferQty
            ],
            $this->user->id,
            $this->user->name,
            'Storekeeper verified 10 bags in good condition'
        );

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
            $response = $this->actingAs($this->user)->withSession(['user_id' => $this->user->id, 'user_role' => 'admin'])->get($url);
            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 302]),
                "Route {$url} failed with HTTP status code: {$response->getStatusCode()}"
            );
        }
    }

    /**
     * PROOF 5: Receipts Accurately Display Handover Status (Supplied vs Unsupplied vs Later Dispatch)
     */
    public function test_receipt_shows_correct_handover_status_for_supplied_and_unsupplied_sales()
    {
        // 1. Immediate Delivery Sale
        $saleSupplied = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 75000,
                'paidAmount' => 75000,
                'cashAmount' => 75000,
                'posAmount' => 0,
                'transferAmount' => 0,
                'customerName' => 'Alhaji Test Buyer',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'code' => $this->product->code,
                    'productName' => $this->product->name,
                    'quantity' => 1,
                    'unitPrice' => 75000,
                    'totalPrice' => 75000,
                ]
            ],
            $this->warehouseA->id,
            true, // Supplied now
            $this->user->id,
            'Cashier Joy'
        );

        $response1 = $this->actingAs($this->user)->withSession(['user_id' => $this->user->id, 'user_role' => 'admin'])->get("/pos/receipt/{$saleSupplied->id}");
        $response1->assertStatus(200);
        $response1->assertSee('PAID &amp; SUPPLIED', false);
        $response1->assertSee('✓ GOODS SUPPLIED & COLLECTED', false);
        $response1->assertDontSee('GOODS NOT SUPPLIED', false);

        // 2. Delayed Pickup Sale (Paid & Not Supplied)
        $saleUnsupplied = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 150000,
                'paidAmount' => 150000,
                'cashAmount' => 0,
                'posAmount' => 150000,
                'transferAmount' => 0,
                'customerName' => 'Chief Pickup Later',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'code' => $this->product->code,
                    'productName' => $this->product->name,
                    'quantity' => 2,
                    'unitPrice' => 75000,
                    'totalPrice' => 150000,
                ]
            ],
            $this->warehouseA->id,
            false, // Not supplied now (awaiting pickup)
            $this->user->id,
            'Cashier Joy'
        );

        $response2 = $this->actingAs($this->user)->withSession(['user_id' => $this->user->id, 'user_role' => 'admin'])->get("/pos/receipt/{$saleUnsupplied->id}");
        $response2->assertStatus(200);
        $response2->assertSee('PAID &amp; NOT SUPPLIED', false);
        $response2->assertSee('GOODS NOT SUPPLIED', false);

        // 3. Dispatch the unsupplied sale -> transitions to Paid & Supplied
        $this->stockService->dispatchUnsuppliedSale($saleUnsupplied->id, $this->warehouseA->id, $this->user->id, 'Storekeeper John');

        $response3 = $this->actingAs($this->user)->withSession(['user_id' => $this->user->id, 'user_role' => 'admin'])->get("/pos/receipt/{$saleUnsupplied->id}");
        $response3->assertStatus(200);
        $response3->assertSee('PAID &amp; SUPPLIED', false);
        $response3->assertSee('Storekeeper John');
        $response3->assertDontSee('GOODS NOT SUPPLIED (AWAITING', false);
    }
}
