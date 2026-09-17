<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\InventoryLog;
use App\Models\StockAdjustment;
use App\Services\StockService;
use App\Http\Controllers\Web\TransactionController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StockOutCategorizationAndAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $admin;
    protected User $storekeeper;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@test.com',
        ]);

        Tenant::withoutGlobalScopes()->firstOrCreate(['id' => 'default-tenant'], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@test.com',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenant = Tenant::firstOrCreate(['id' => 'tenant-stockout-' . Str::random(4)], [
            'name' => 'Victory Market Ltd',
            'owner_email' => 'owner-' . Str::random(4) . '@victory.ng',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Victoria Island Branch',
            'code' => 'VI-01',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Admin Boss',
            'email' => 'admin-' . Str::random(5) . '@victory.ng',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->storekeeper = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Musa Storekeeper',
            'email' => 'musa-' . Str::random(5) . '@victory.ng',
            'password' => Hash::make('secret123'),
            'role' => 'storekeeper',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'code' => 'SKU-OIL-25L',
            'name' => 'Kings Groundnut Oil 25L',
            'category' => 'Cooking Oil',
            'costPrice' => 25000.0,
            'unitPrice' => 32000.0,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);
    }

    /**
     * Test recording stock adjustment with type=ADJUSTMENT and a compulsory reason.
     */
    public function test_record_adjustment_requires_compulsory_reason_and_deducts_physical_stock(): void
    {
        $this->actingAs($this->storekeeper);
        session(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id]);

        // 1. Missing reason must fail validation
        $failResponse = $this->post(route('stock.adjustments.record'), [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'reason' => '', // blank reason
        ]);
        $failResponse->assertSessionHasErrors(['reason']);

        // 2. Providing valid reason succeeds
        $successResponse = $this->post(route('stock.adjustments.record'), [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'ADJUSTMENT',
            'quantity' => 2,
            'reason' => '2 jerrycans broken during offloading at gate',
        ]);
        $successResponse->assertSessionHasNoErrors();

        // 3. Verify physical stock dropped from 50 to 48
        $currentStock = StockLevel::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->value('physical_stock');
        $this->assertEquals(48, $currentStock);

        // 4. Verify StockAdjustment record created with type ADJUSTMENT
        $adj = StockAdjustment::where('product_id', $this->product->id)->first();
        $this->assertNotNull($adj);
        $this->assertEquals('ADJUSTMENT', $adj->type);
        $this->assertEquals('2 jerrycans broken during offloading at gate', $adj->reason);

        // 5. Verify InventoryLog created with type STOCK_ADJUSTMENT
        $log = InventoryLog::where('productId', $this->product->id)
            ->where('type', 'STOCK_ADJUSTMENT')
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals(-2, $log->quantity);
    }

    /**
     * Test that TransactionController getStockOutQuery correctly categorizes all outflow sources:
     * Retail Sale, Customer Exchange, Customer Pickup, Transfer Out, and Adjustment.
     */
    public function test_stock_out_categorization_and_filtering(): void
    {
        $this->actingAs($this->admin);
        session(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id]);

        $stockService = app(StockService::class);
        $transController = app(TransactionController::class);

        // 1. Retail Sale
        $stockService->recordSale(
            [
                'customerName' => 'Walk-in Customer',
                'paymentMethod' => 'CASH',
                'tender' => ['cash' => 32000.0, 'pos' => 0.0],
                'cashAmount' => 32000.0,
                'posAmount' => 0.0,
                'paidAmount' => 32000.0,
                'totalAmount' => 32000.0,
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 1,
                    'unitPrice' => 32000.0,
                    'totalPrice' => 32000.0,
                ]
            ],
            $this->warehouse->id,
            true,
            $this->admin->id,
            $this->admin->name
        );

        // 2. Exchange Replacement Sale
        $stockService->recordSale(
            [
                'customerName' => 'Alhaji Gambo',
                'paymentMethod' => 'SPLIT',
                'tender' => ['cash' => 2000.0, 'pos' => 0.0, 'exchange_credit' => 30000.0],
                'cashAmount' => 2000.0,
                'posAmount' => 0.0,
                'exchange_credit' => 30000.0,
                'paidAmount' => 32000.0,
                'totalAmount' => 32000.0,
                'exchange_returns' => [
                    [
                        'productId' => $this->product->id,
                        'quantity' => 1,
                        'unitPrice' => 30000.0,
                        'creditAmount' => 30000.0,
                    ]
                ],
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 1,
                    'unitPrice' => 32000.0,
                    'totalPrice' => 32000.0,
                ]
            ],
            $this->warehouse->id,
            true,
            $this->admin->id,
            $this->admin->name
        );

        // 3. Stock Adjustment
        $stockService->recordStockAdjustment(
            $this->product->id,
            $this->warehouse->id,
            'ADJUSTMENT',
            1,
            'Broken cap leaking oil',
            $this->storekeeper->id,
            $this->storekeeper->name
        );

        // Filter: SALE
        $reqSale = new \Illuminate\Http\Request(['outflow_category' => 'SALE']);
        $saleLogs = $transController->getStockOutQuery($reqSale)->get();
        $this->assertTrue($saleLogs->every(fn($l) => $l->type === 'SALE' && !str_contains($l->description, 'Exchange')));

        // Filter: EXCHANGE
        $reqEx = new \Illuminate\Http\Request(['outflow_category' => 'EXCHANGE']);
        $exchangeLogs = $transController->getStockOutQuery($reqEx)->get();
        $this->assertNotEmpty($exchangeLogs);
        $this->assertTrue($exchangeLogs->every(fn($l) => str_contains($l->description, 'Exchange')));

        // Filter: ADJUSTMENT
        $reqAdj = new \Illuminate\Http\Request(['outflow_category' => 'ADJUSTMENT']);
        $adjLogs = $transController->getStockOutQuery($reqAdj)->get();
        $this->assertNotEmpty($adjLogs);
        $this->assertTrue($adjLogs->every(fn($l) => str_contains($l->type, 'ADJUSTMENT')));
    }

    public function test_retroactive_exchange_detection_and_sales_badges(): void
    {
        $this->actingAs($this->admin);
        session(['tenant_id' => $this->tenant->id]);

        $saleId = (string) Str::uuid();
        $sale = Sale::create([
            'id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerName' => 'Chief Okonkwo',
            'totalAmount' => 50000.0,
            'paidAmount' => 50000.0,
            'tenderedAmount' => 50000.0,
            'changeAmount' => 0.0,
            'cashAmount' => 10000.0,
            'posAmount' => 0.0,
            'transferAmount' => 0.0,
            'sale_type' => 'RETAIL',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Exchange Credit payment
        \App\Models\Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleId,
            'amount' => 40000.0,
            'method' => 'EXCHANGE_CREDIT',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->admin->name,
        ]);

        \App\Models\Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleId,
            'amount' => 10000.0,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->admin->name,
        ]);

        // Legacy inventory log with type SALE and NO "Exchange" in description
        $legacyLog = InventoryLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'productId' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'SALE',
            'quantity' => -2,
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'productCode' => $this->product->code,
            'productName' => $this->product->name,
            'description' => "Sale #{$saleId} (Supplied to Chief Okonkwo)",
            'timestamp' => now()->toIso8601String(),
        ]);

        $transController = app(TransactionController::class);

        // 1. In Stock Out filter: EXCHANGE matches the legacy log
        $reqEx = new \Illuminate\Http\Request(['outflow_category' => 'EXCHANGE']);
        $exchangeLogs = $transController->getStockOutQuery($reqEx)->get();
        $this->assertTrue($exchangeLogs->contains('id', $legacyLog->id), 'Legacy exchange log must be found under EXCHANGE filter.');

        // 2. In Stock Out filter: SALE excludes the legacy exchange log
        $reqSale = new \Illuminate\Http\Request(['outflow_category' => 'SALE']);
        $saleLogs = $transController->getStockOutQuery($reqSale)->get();
        $this->assertFalse($saleLogs->contains('id', $legacyLog->id), 'Legacy exchange log must NOT appear under SALE filter.');

        // 3. Render transactions index and assert visual badges
        $this->actingAs($this->admin);
        session(['tenant_id' => $this->tenant->id]);
        $response = $this->get(route('transactions.index', ['tab' => 'sales']));
        $response->assertStatus(200);
        $response->assertSee('🔄 EXCHANGE');

        $responseStockOut = $this->get(route('transactions.index', ['tab' => 'stock_out']));
        $responseStockOut->assertStatus(200);
        $responseStockOut->assertSee('🔄 CUSTOMER EXCHANGE');

        // 4. Test Partial Return Badge: Sold 5 units, returned 2 units
        $salePartialId = (string) Str::uuid();
        $salePartial = Sale::create([
            'id' => $salePartialId,
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerName' => 'Madam Kemi',
            'totalAmount' => 160000.0,
            'paidAmount' => 160000.0,
            'tenderedAmount' => 160000.0,
            'changeAmount' => 0.0,
            'cashAmount' => 160000.0,
            'posAmount' => 0.0,
            'transferAmount' => 0.0,
            'sale_type' => 'RETAIL',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'createdAt' => now()->toIso8601String(),
        ]);
        \App\Models\SaleItem::create([
            'tenant_id' => $this->tenant->id,
            'saleId' => $salePartialId,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'quantity' => 5,
            'unitPrice' => 32000.0,
            'totalPrice' => 160000.0,
        ]);
        \App\Models\SalesReturn::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $salePartialId,
            'customerName' => 'Madam Kemi',
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'code' => $this->product->code,
            'quantity' => 2,
            'refundAmount' => 64000.0,
            'reason' => 'Customer changed mind',
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'timestamp' => now()->toIso8601String(),
            'createdAt' => now()->toIso8601String(),
        ]);

        // 5. Test Full Return Badge: Sold 3 units, returned 3 units
        $saleFullId = (string) Str::uuid();
        $saleFull = Sale::create([
            'id' => $saleFullId,
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerName' => 'Pastor Dave',
            'totalAmount' => 96000.0,
            'paidAmount' => 0.0,
            'tenderedAmount' => 0.0,
            'changeAmount' => 0.0,
            'cashAmount' => 0.0,
            'posAmount' => 0.0,
            'transferAmount' => 0.0,
            'sale_type' => 'RETAIL',
            'status' => 'RETURNED',
            'deliveryStatus' => 'RETURNED',
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'createdAt' => now()->toIso8601String(),
        ]);
        \App\Models\SaleItem::create([
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleFullId,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'quantity' => 3,
            'unitPrice' => 32000.0,
            'totalPrice' => 96000.0,
        ]);
        \App\Models\SalesReturn::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleFullId,
            'customerName' => 'Pastor Dave',
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'code' => $this->product->code,
            'quantity' => 3,
            'refundAmount' => 96000.0,
            'reason' => 'Defective batch return',
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'timestamp' => now()->toIso8601String(),
            'createdAt' => now()->toIso8601String(),
        ]);

        $responseSales = $this->get(route('transactions.index', ['tab' => 'sales']));
        $responseSales->assertStatus(200);
        $responseSales->assertSee('⚠️ PART-RETURN (2/5)');
        $responseSales->assertSee('↩️ FULL RETURN');
    }
}
