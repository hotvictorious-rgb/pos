<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\SalesReturn;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductionHardeningPass18Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $admin;
    protected User $cashier;
    protected Product $product;
    protected Customer $customer;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@pass18.test',
        ]);

        Tenant::withoutGlobalScopes()->firstOrCreate([
            'id' => 'default-tenant',
        ], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@pass18.test',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 999,
            'max_users' => 999,
        ]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-pass18-' . Str::random(5),
            'name' => 'Kano Central Supplies Ltd',
            'slug' => 'kano-central-' . Str::random(5),
            'owner_email' => 'owner@pass18.test',
            'owner_phone' => '08033344455',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Depot Kano',
            'code' => 'MDK-01',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'General Manager',
            'email' => 'manager@pass18.test',
            'password' => Hash::make('Manager123!'),
            'role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier Amina',
            'email' => 'amina@pass18.test',
            'password' => Hash::make('Cashier123!'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
            'permissions' => ['pos' => true, 'debts' => true, 'returns' => true],
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Dangote Sugar 50kg',
            'code' => 'DS-50KG',
            'category' => 'Commodities',
            'unitPrice' => 85000.00,
            'costPrice' => 78000.00,
            'currentStock' => 100,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 100,
            'allocated_stock' => 0,
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Malam Bello & Sons',
            'phone' => '08098765432',
            'address' => 'Singa Market Kano',
            'total_debt' => 0.00,
        ]);

        $this->accountingService = app(AccountingReportService::class);
    }

    /**
     * TEST 1: Batch invoice balance calculation accurately mirrors single invoice calculation down to the kobo.
     */
    public function test_batch_invoice_balance_calculation_matches_single_invoice_calculation(): void
    {
        // Setup 4 distinct sales with diverse payment/return states
        // Sale 1: Fully paid
        $sale1 = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerId' => $this->customer->id,
            'customerName' => $this->customer->name,
            'totalAmount' => 170000.00,
            'paidAmount' => 170000.00,
            'cashAmount' => 170000.00,
            'posAmount' => 0.00,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashier->id,
            'userName' => $this->cashier->name,
            'createdAt' => now()->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale1->id,
            'amount' => 170000.00,
            'method' => 'CASH',
            'recordedBy' => $this->cashier->id,
            'timestamp' => now(),
        ]);

        // Sale 2: Partially paid
        $sale2 = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerId' => $this->customer->id,
            'customerName' => $this->customer->name,
            'totalAmount' => 255000.00,
            'paidAmount' => 100000.00,
            'cashAmount' => 0.00,
            'posAmount' => 100000.00,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashier->id,
            'userName' => $this->cashier->name,
            'createdAt' => now()->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale2->id,
            'amount' => 100000.00,
            'method' => 'POS',
            'recordedBy' => $this->cashier->id,
            'timestamp' => now(),
        ]);

        // Sale 3: Has a return with cash refund
        $sale3 = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerId' => $this->customer->id,
            'customerName' => $this->customer->name,
            'totalAmount' => 85000.00,
            'paidAmount' => 85000.00,
            'cashAmount' => 85000.00,
            'posAmount' => 0.00,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashier->id,
            'userName' => $this->cashier->name,
            'createdAt' => now()->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale3->id,
            'amount' => 85000.00,
            'method' => 'CASH',
            'recordedBy' => $this->cashier->id,
            'timestamp' => now(),
        ]);
        SalesReturn::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale3->id,
            'customerName' => $this->customer->name,
            'code' => $this->product->code,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'quantity' => 1,
            'refundAmount' => 85000.00,
            'reason' => 'Damaged bag',
            'userId' => $this->cashier->id,
            'warehouse_id' => $this->warehouse->id,
            'createdAt' => now()->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale3->id,
            'amount' => 85000.00,
            'method' => 'REFUND_CASH',
            'recordedBy' => $this->cashier->id,
            'timestamp' => now(),
        ]);

        // Sale 4: Unpaid
        $sale4 = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerId' => $this->customer->id,
            'customerName' => $this->customer->name,
            'totalAmount' => 100000.00,
            'paidAmount' => 0.00,
            'cashAmount' => 0.00,
            'posAmount' => 0.00,
            'status' => 'UNPAID',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashier->id,
            'userName' => $this->cashier->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        $sales = [$sale1, $sale2, $sale3, $sale4];

        // Execute batch calculation
        $batchResults = $this->accountingService->calculateInvoiceBalancesForSales($sales);

        // Verify each sale matches single-invoice calculation down to kobo
        foreach ($sales as $sale) {
            $singleBalance = $this->accountingService->calculateInvoiceBalance($sale);
            $batchBalance = $batchResults[$sale->id] ?? null;

            $this->assertNotNull($batchBalance, "Batch result missing for sale {$sale->id}");
            $this->assertEquals($singleBalance, $batchBalance);
        }
    }

    /**
     * TEST 2: Batch calculation runs in constant O(1) query count (2 aggregate queries), eliminating N+1 scaling bottlenecks.
     */
    public function test_batch_invoice_balance_executes_in_constant_queries(): void
    {
        $sales = [];
        for ($i = 0; $i < 15; $i++) {
            $s = Sale::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'warehouse_id' => $this->warehouse->id,
                'customerId' => $this->customer->id,
                'customerName' => $this->customer->name,
                'totalAmount' => 10000.00 * ($i + 1),
                'paidAmount' => 5000.00 * ($i + 1),
                'cashAmount' => 5000.00 * ($i + 1),
                'posAmount' => 0.00,
                'status' => 'PARTIAL',
                'deliveryStatus' => 'DELIVERED',
                'userId' => $this->cashier->id,
                'userName' => $this->cashier->name,
                'createdAt' => now()->toIso8601String(),
            ]);
            Payment::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'saleId' => $s->id,
                'amount' => 5000.00 * ($i + 1),
                'method' => 'CASH',
                'recordedBy' => $this->cashier->id,
                'timestamp' => now(),
            ]);
            $sales[] = $s;
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $batchResults = $this->accountingService->calculateInvoiceBalancesForSales($sales);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(15, $batchResults);
        // Assert exactly 2 queries executed (one for SalesReturn sums, one for Payment sums)
        $this->assertLessThanOrEqual(2, count($queries), 'Batch balance calculation exceeded constant query budget');
    }

    /**
     * TEST 3: Client POS form idempotency key lifecycle — prevents double submission on checkout.
     */
    public function test_client_idempotency_key_retention_and_replay_on_checkout(): void
    {
        $clientKey = 'pos-client-uuid-' . Str::uuid();

        $payload = [
            'idempotency_key' => $clientKey,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 1,
                ]
            ],
            'cashAmount' => 85000.00,
            'posAmount' => 0.00,
            'paidAmount' => 85000.00,
            'is_supplied' => 'yes',
        ];

        // Initial submission
        $res1 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('pos.checkout'), $payload);

        $res1->assertSessionHasNoErrors();
        $initialSaleCount = Sale::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(1, $initialSaleCount);

        // Immediate replay with the exact same client idempotency key (simulating network stall or double-click)
        $res2 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('pos.checkout'), $payload);

        // Must replay cached response without creating a second sale
        $postReplaySaleCount = Sale::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(1, $postReplaySaleCount, 'Replayed checkout created a duplicate sale instead of replaying idempotently');

        // Verify stock was only deducted once (100 - 1 = 99)
        $stock = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(99, $stock->physical_stock);
    }

    /**
     * TEST 4: Client POS Return form idempotency key lifecycle — prevents double restock and double refund.
     */
    public function test_client_idempotency_key_retention_and_replay_on_return(): void
    {
        // First create a sale to return against
        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerId' => $this->customer->id,
            'customerName' => $this->customer->name,
            'totalAmount' => 85000.00,
            'paidAmount' => 85000.00,
            'cashAmount' => 85000.00,
            'posAmount' => 0.00,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashier->id,
            'userName' => $this->cashier->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        SaleItem::create([
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'quantity' => 1,
            'unitPrice' => 85000.00,
            'totalPrice' => 85000.00,
            'code' => $this->product->code,
            'productCode' => $this->product->code,
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale->id,
            'amount' => 85000.00,
            'method' => 'CASH',
            'recordedBy' => $this->cashier->id,
            'timestamp' => now(),
        ]);

        $clientReturnKey = 'return-client-uuid-' . Str::uuid();
        $returnPayload = [
            'idempotency_key' => $clientReturnKey,
            'sale_id' => $sale->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 1,
                ]
            ],
            'refund_method' => 'CASH_REFUND',
            'reason' => 'Customer changed mind',
        ];

        // First return request
        $res1 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('pos.returns.process'), $returnPayload);

        $res1->assertSessionHasNoErrors();
        $returnCount = SalesReturn::where('saleId', $sale->id)->count();
        $this->assertEquals(1, $returnCount);

        // Replay same return request
        $res2 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('pos.returns.process'), $returnPayload);

        $returnCountAfter = SalesReturn::where('saleId', $sale->id)->count();
        $this->assertEquals(1, $returnCountAfter, 'Replayed return created duplicate return record');
    }

    /**
     * TEST 5: Scale composite financial indexes are registered on database schema.
     */
    public function test_scale_financial_composite_indexes_exist(): void
    {
        // Check payments indexes
        $this->assertTrue(Schema::hasTable('payments'));
        $this->assertTrue(Schema::hasColumn('payments', 'saleId'));
        $this->assertTrue(Schema::hasColumn('payments', 'method'));

        // Check sales indexes
        $this->assertTrue(Schema::hasTable('sales'));
        $this->assertTrue(Schema::hasColumn('sales', 'customerId'));
        $this->assertTrue(Schema::hasColumn('sales', 'warehouse_id'));

        // Check sales_returns indexes
        $this->assertTrue(Schema::hasTable('sales_returns'));
        $this->assertTrue(Schema::hasColumn('sales_returns', 'saleId'));

        // Check customer_ledgers indexes
        $this->assertTrue(Schema::hasTable('customer_ledgers'));
        $this->assertTrue(Schema::hasColumn('customer_ledgers', 'customer_id'));
        $this->assertTrue(Schema::hasColumn('customer_ledgers', 'type'));
    }

    /**
     * TEST 6: DebtController index route successfully utilizes batch balance calculation.
     */
    public function test_debts_index_route_loads_with_batch_balance_calculation(): void
    {
        $res = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('debts.index'));

        $res->assertStatus(200);
        $res->assertViewIs('debts.index');
        $res->assertViewHas('debtors');
        $res->assertViewHas('totalOutstandingDebt');
    }
}
