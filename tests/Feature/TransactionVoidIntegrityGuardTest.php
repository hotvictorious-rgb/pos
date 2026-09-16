<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\InventoryLog;
use App\Models\StockAdjustment;
use App\Models\StockReservation;
use App\Services\StockService;
use App\Services\TransactionVoidService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TransactionVoidIntegrityGuardTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $admin;
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

        $this->tenant = Tenant::create([
            'id' => 'tenant-' . Str::random(6),
            'name' => 'Lagos Central Depot',
            'slug' => 'lagos-central-' . Str::random(5),
            'owner_email' => 'owner@centraldepot.test',
            'status' => 'active',
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH',
            'address' => '12 Marina Road',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Owner',
            'email' => 'owner@centraldepot.test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Inverter Battery 200Ah',
            'code' => 'BAT-200',
            'category' => 'Power Equipment',
            'unitPrice' => 180000.0,
            'costPrice' => 140000.0,
            'currentStock' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 0,
            'allocated_stock' => 0,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test 1: Void Stock-In is BLOCKED when physical stock is 0 after sale.
     * Proves physical stock can NEVER become negative (-1).
     */
    public function test_void_stock_in_blocked_when_physical_stock_zero_after_sale(): void
    {
        // 1. Stock In 1 unit
        $stockLog = InventoryLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'productId' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'STOCK_IN',
            'quantity' => 1,
            'userId' => (string) $this->admin->id,
            'userName' => $this->admin->name,
            'productCode' => $this->product->code,
            'productName' => $this->product->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        $stockLevel = StockLevel::withoutGlobalScopes()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $stockLevel->update(['physical_stock' => 1]);

        // 2. Sell the 1 unit (Supplied to customer)
        $stockLevel->update(['physical_stock' => 0]); // Unit left shop

        // 3. Admin attempts to void the Stock In of 1 unit
        $response = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('transactions.void.stock-in', $stockLog->id), [
                'reason' => 'Trying to void stock in that was already sold',
            ]);

        // Must be rejected with 422
        $response->assertStatus(422);
        $response->assertJson([
            'error' => "Cannot void Stock In: Physical stock on ground (0) is lower than the void amount (1). Products may already have been sold.",
        ]);

        // Physical stock remains 0, NEVER negative
        $stockLevel->refresh();
        $this->assertEquals(0, $stockLevel->physical_stock, 'Physical stock must stay 0 and never drop negative');
    }

    /**
     * Test 2: Void Sale is BLOCKED when sale already has processed Returns & Refunds.
     * Prevents double-restocking phantom inventory and ledger corruption.
     */
    public function test_void_sale_blocked_when_sale_has_existing_sales_returns(): void
    {
        $stockService = app(StockService::class);

        $stockLevel = StockLevel::withoutGlobalScopes()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $stockLevel->update(['physical_stock' => 5]);

        // Sale of 2 units
        $sale = Sale::create([
            'id' => 'INV-TEST-RET-01',
            'warehouse_id' => $this->warehouse->id,
            'tenant_id' => $this->tenant->id,
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'customerName' => 'Emeka Okafor',
            'totalAmount' => 360000.0,
            'paidAmount' => 360000.0,
            'cashAmount' => 360000.0,
            'posAmount' => 0.0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
        ]);

        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'productCode' => $this->product->code,
            'quantity' => 2,
            'unitPrice' => 180000.0,
            'totalPrice' => 360000.0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Customer returns 1 unit through Returns & Refunds
        $stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->product->id, 'quantity' => 1, 'refundAmount' => 180000.0, 'reason' => 'Customer changed mind']],
            $this->warehouse->id,
            'CASH_REFUND',
            'Partial return of 1 unit',
            (string) $this->admin->id,
            $this->admin->name
        );

        // Physical stock increased from 5 to 6 (5 + 1 returned)
        $stockLevel->refresh();
        $this->assertEquals(6, $stockLevel->physical_stock);

        // Now Admin tries to void the original sale
        $response = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('transactions.void.sale', $sale->id), [
                'reason' => 'Trying to void sale with active returns',
            ]);

        // Must be rejected with 422
        $response->assertStatus(422);
        $this->assertStringContainsString('processed Return & Refund record(s)', $response->json('error'));

        // Stock remains 6 (no double-restock of 2 units!)
        $stockLevel->refresh();
        $this->assertEquals(6, $stockLevel->physical_stock, 'Physical stock must not be double-restocked');
    }

    /**
     * Test 3: Void Sale with partial pickup restores ONLY fulfilled units and releases outstanding.
     */
    public function test_void_sale_with_partial_pickup_restores_only_fulfilled_units_and_releases_outstanding(): void
    {
        $stockLevel = StockLevel::withoutGlobalScopes()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        // Initially 10 on ground, 2 allocated
        $stockLevel->update(['physical_stock' => 10, 'allocated_stock' => 2]);

        $sale = Sale::create([
            'id' => 'INV-UNSUPPLIED-PARTIAL-01',
            'warehouse_id' => $this->warehouse->id,
            'tenant_id' => $this->tenant->id,
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'customerName' => 'Alhaji Musa',
            'totalAmount' => 360000.0,
            'paidAmount' => 360000.0,
            'cashAmount' => 360000.0,
            'posAmount' => 0.0,
            'status' => 'PAID',
            'deliveryStatus' => 'PARTIALLY_FULFILLED',
        ]);

        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'productCode' => $this->product->code,
            'quantity' => 2,
            'unitPrice' => 180000.0,
            'totalPrice' => 360000.0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Reservation: 1 unit was collected/fulfilled, 1 is still outstanding
        // (So physical was decremented to 9 when collected, allocated is 1)
        $stockLevel->update(['physical_stock' => 9, 'allocated_stock' => 1]);

        $reservation = StockReservation::create([
            'id' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'reserved_qty' => 2,
            'fulfilled_qty' => 1,
            'cancelled_qty' => 0,
            'status' => 'PARTIALLY_FULFILLED',
            'tenant_id' => $this->tenant->id,
        ]);

        // Admin voids this sale
        $response = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('transactions.void.sale', $sale->id), [
                'reason' => 'Customer cancelled after partial pickup',
            ]);

        $response->assertOk();

        // Check stock levels:
        // Physical stock should increase by 1 (the fulfilled unit returned): 9 + 1 = 10
        // Allocated stock should decrease by 1 (the outstanding unit released): 1 - 1 = 0
        $stockLevel->refresh();
        $this->assertEquals(10, $stockLevel->physical_stock, 'Physical stock restored for the 1 unit that had left');
        $this->assertEquals(0, $stockLevel->allocated_stock, 'Allocated buffer released down to 0');

        $reservation->refresh();
        $this->assertEquals('CANCELLED', $reservation->status);
    }

    /**
     * Test 4: Void Stock Adjustment restores stock and cleans up write-off log.
     */
    public function test_void_stock_adjustment_cleans_damage_analytics(): void
    {
        $stockLevel = StockLevel::withoutGlobalScopes()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $stockLevel->update(['physical_stock' => 8]);

        // Create adjustment writing off 2 damaged units
        $adj = StockAdjustment::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_code' => $this->product->code,
            'type' => 'DAMAGE',
            'quantity' => 2,
            'reason' => 'Damaged during offloading',
            'recorded_by' => $this->admin->name,
            'status' => 'APPROVED',
        ]);

        InventoryLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'productId' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'STOCK_ADJUSTMENT_DAMAGE',
            'quantity' => -2,
            'userId' => (string) $this->admin->id,
            'userName' => $this->admin->name,
            'productCode' => $this->product->code,
            'productName' => $this->product->name,
            'description' => "Stock Adjustment (DAMAGE): 2 units written off. Reason: Damaged during offloading",
            'timestamp' => now()->toIso8601String(),
        ]);

        // Void the adjustment
        $response = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('transactions.void.stock-out', $adj->id), [
                'reason' => 'Mistakenly logged wrong product as damaged',
            ]);

        $response->assertOk();

        // Stock restored from 8 to 10
        $stockLevel->refresh();
        $this->assertEquals(10, $stockLevel->physical_stock);

        // Adjustment deleted
        $this->assertNull(StockAdjustment::find($adj->id));

        // Damage log cleaned up
        $logCount = InventoryLog::where('productId', $this->product->id)
            ->where('type', 'STOCK_ADJUSTMENT_DAMAGE')
            ->count();
        $this->assertEquals(0, $logCount, 'Damage inventory log must be cleaned up');
    }

    /**
     * Test 5: Daily Comprehensive Report reconciles net zero inventory movement after sale void.
     */
    public function test_daily_report_reconciles_net_zero_inventory_movement_on_voided_sale(): void
    {
        $reportingService = app(AccountingReportService::class);

        $stockLevel = StockLevel::withoutGlobalScopes()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $stockLevel->update(['physical_stock' => 10]);

        // 1. Record Sale of 2 units
        $sale = Sale::create([
            'id' => 'INV-ZERO-NET-01',
            'warehouse_id' => $this->warehouse->id,
            'tenant_id' => $this->tenant->id,
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'customerName' => 'Walk-in Customer',
            'totalAmount' => 360000.0,
            'paidAmount' => 360000.0,
            'cashAmount' => 360000.0,
            'posAmount' => 0.0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
        ]);

        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'productCode' => $this->product->code,
            'quantity' => 2,
            'unitPrice' => 180000.0,
            'totalPrice' => 360000.0,
            'tenant_id' => $this->tenant->id,
        ]);

        InventoryLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'productId' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'SALE',
            'quantity' => -2,
            'userId' => (string) $this->admin->id,
            'userName' => $this->admin->name,
            'productCode' => $this->product->code,
            'productName' => $this->product->name,
            'description' => "Sale #{$sale->id}",
            'timestamp' => now()->toIso8601String(),
        ]);

        // 2. Void this sale
        $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('transactions.void.sale', $sale->id), [
                'reason' => 'Customer cancelled immediately',
            ]);

        // 3. Query Daily Comprehensive Report
        $report = $reportingService->getDailyComprehensiveReport([
            'warehouse_id' => $this->warehouse->id,
            'date_preset' => 'TODAY',
        ]);

        // Option A: Clean Void Reconciliation
        // Initial sale outflow log was cleanly deleted -> Stock Out is 0
        $this->assertEquals(0, $report['stock_out_total_units'], 'Voided sale must not appear in Stock Out');
        // No artificial SALE_VOIDED stock-in log needed -> Stock In is 0
        $this->assertEquals(0, $report['stock_in_total_units'], 'No ghost stock-in log created');
        // Net inventory movement is exactly 0!
        $this->assertEquals(0, $report['net_inventory_movement_units'], 'Net movement must be exactly 0 after voiding sale');

        // Customer ledger and transactions tabs are also 100% clean
        $this->assertEquals(0, InventoryLog::where('description', 'like', "%Sale #{$sale->id}%")->count());
        $this->assertEquals(0, \App\Models\CustomerLedger::where('sale_id', $sale->id)->count());
    }
}
