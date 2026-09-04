<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Transfer;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ServerSideAuthorizationContractTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $warehouseA1;
    protected Warehouse $warehouseA2;
    protected Warehouse $warehouseB1;
    protected Product $productA1;
    protected Product $productA2;

    protected User $superAdmin;
    protected User $adminA;
    protected User $branchManagerA;
    protected User $cashierA;
    protected User $storekeeperA;
    protected User $salesOfficerA;
    protected User $unassignedCashier;
    protected User $unknownRoleUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);
        config(['saas.super_admin_email' => 'superadmin@hysam.com']);

        // 1. Setup Tenants
        $this->tenantA = Tenant::create([
            'id' => 'tenant-alpha',
            'name' => 'Alpha Solar Ltd',
            'owner_email' => 'owner@alpha.com',
            'domain' => 'alpha.vmarket.test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'id' => 'tenant-beta',
            'name' => 'Beta Energy Ltd',
            'owner_email' => 'owner@beta.com',
            'domain' => 'beta.vmarket.test',
            'status' => 'active',
            'plan' => 'starter',
        ]);

        // 2. Setup Warehouses
        $this->warehouseA1 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Branch 1',
            'code' => 'ALP-01',
            'is_active' => true,
        ]);

        $this->warehouseA2 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Branch 2',
            'code' => 'ALP-02',
            'is_active' => true,
        ]);

        $this->warehouseB1 = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Branch 1',
            'code' => 'BET-01',
            'is_active' => true,
        ]);

        // 3. Setup Products & Stock for Tenant A
        $this->productA1 = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'code' => 'SOL-300',
            'name' => '300W Monocrystalline Panel',
            'category' => 'Solar',
            'unitPrice' => 50000,
            'currentStock' => 100,
            'minStockLevel' => 5,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA1->id,
            'warehouse_id' => $this->warehouseA1->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA1->id,
            'warehouse_id' => $this->warehouseA2->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);

        // 4. Setup Users
        $this->superAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@hysam.com',
            'password' => bcrypt('supersecret'),
            'role' => 'admin',
        ]);

        $this->adminA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Admin',
            'email' => 'admin@alpha.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $this->branchManagerA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'name' => 'Alpha Branch Manager',
            'email' => 'manager@alpha.com',
            'password' => bcrypt('secret123'),
            'role' => 'branch_manager',
        ]);

        $this->cashierA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'name' => 'Alpha Cashier',
            'email' => 'cashier@alpha.com',
            'password' => bcrypt('secret123'),
            'role' => 'cashier',
        ]);

        $this->storekeeperA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'name' => 'Alpha Storekeeper',
            'email' => 'store@alpha.com',
            'password' => bcrypt('secret123'),
            'role' => 'storekeeper',
        ]);

        $this->salesOfficerA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'name' => 'Alpha Sales Officer',
            'email' => 'sales@alpha.com',
            'password' => bcrypt('secret123'),
            'role' => 'sales_officer',
        ]);

        $this->unassignedCashier = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => null, // Strictly unassigned
            'name' => 'Floating Cashier',
            'email' => 'floating@alpha.com',
            'password' => bcrypt('secret123'),
            'role' => 'cashier',
        ]);

        $this->unknownRoleUser = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'name' => 'Unknown Hacker',
            'email' => 'hacker@alpha.com',
            'password' => bcrypt('secret123'),
            'role' => 'unrecognized_custom_role',
        ]);
    }

    /**
     * TEST 1: Cashier is blocked with 403 on stock-in, transfers, adjustments, reports, users, and api/data.
     */
    public function test_cashier_cannot_reach_unauthorized_endpoints()
    {
        $session = [
            'user_id' => $this->cashierA->id,
            'user_name' => $this->cashierA->name,
            'user_role' => $this->cashierA->role,
            'tenant_id' => $this->tenantA->id,
        ];

        // 1. Stock In (POST) -> 403
        $this->actingAs($this->cashierA)->withSession($session)
            ->postJson(route('stock.in'), [
                'warehouse_id' => $this->warehouseA1->id,
                'product_id' => $this->productA1->id,
                'quantity' => 10,
            ])->assertStatus(403);

        // 2. Transfer Out (POST) -> 403
        $this->actingAs($this->cashierA)->withSession($session)
            ->postJson(route('stock.transfer.out'), [
                'source_warehouse_id' => $this->warehouseA1->id,
                'destination_warehouse_id' => $this->warehouseA2->id,
                'items' => [['productId' => $this->productA1->id, 'quantity' => 2]],
                'carrier_name' => 'Driver 1',
            ])->assertStatus(403);

        // 3. Adjustments (POST) -> 403
        $this->actingAs($this->cashierA)->withSession($session)
            ->postJson(route('stock.adjustments.record'), [
                'warehouse_id' => $this->warehouseA1->id,
                'product_id' => $this->productA1->id,
                'type' => 'DAMAGED',
                'quantity' => 1,
                'reason' => 'Dropped',
            ])->assertStatus(403);

        // 4. Reports (GET) -> Redirect to dashboard or 403
        $res = $this->actingAs($this->cashierA)->withSession($session)->get(route('reports.index'));
        $res->assertRedirect(route('dashboard'));

        // 5. User Management (POST) -> 403
        $this->actingAs($this->cashierA)->withSession($session)
            ->postJson(route('users.store'), [
                'name' => 'New Guy',
                'email' => 'newguy@alpha.com',
                'password' => 'secret123',
                'role' => 'cashier',
            ])->assertStatus(403);

        // 6. Data Dump (GET /api/data) -> 403
        $this->actingAs($this->cashierA)->withSession($session)
            ->getJson('/api/data')->assertStatus(403);
    }

    /**
     * TEST 2: Storekeeper is blocked with 403 on POS checkout, debt payments, returns, and reports.
     */
    public function test_storekeeper_cannot_reach_pos_debt_and_returns()
    {
        $session = [
            'user_id' => $this->storekeeperA->id,
            'user_name' => $this->storekeeperA->name,
            'user_role' => $this->storekeeperA->role,
            'tenant_id' => $this->tenantA->id,
        ];

        // 1. POS Checkout (POST) -> 403
        $this->actingAs($this->storekeeperA)->withSession($session)
            ->postJson(route('pos.checkout'), [
                'warehouse_id' => $this->warehouseA1->id,
                'items' => [['productId' => $this->productA1->id, 'quantity' => 1]],
                'paidAmount' => 50000,
                'cashAmount' => 50000,
                'is_supplied' => 'yes',
            ])->assertStatus(403);

        // 2. Debt Payment (POST) -> 403
        $customer = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Debtor Customer',
            'phone' => '08011223344',
            'total_debt' => 20000,
        ]);

        $this->actingAs($this->storekeeperA)->withSession($session)
            ->postJson(route('debts.pay', $customer->id), [
                'amount' => 10000,
                'payment_method' => 'CASH',
            ])->assertStatus(403);

        // 3. Returns (POST) -> 403
        $this->actingAs($this->storekeeperA)->withSession($session)
            ->postJson(route('pos.returns.process'), [
                'sale_id' => 'fake-sale-id',
                'warehouse_id' => $this->warehouseA1->id,
                'items' => [['productId' => $this->productA1->id, 'quantity' => 1]],
                'refund_method' => 'CASH_REFUND',
                'reason' => 'Defective',
            ])->assertStatus(403);

        // 4. Reports (GET) -> Redirect to dashboard
        $this->actingAs($this->storekeeperA)->withSession($session)
            ->get(route('reports.index'))->assertRedirect(route('dashboard'));
    }

    /**
     * TEST 3: Sales Officer can sell and view reports, but cannot mutate stock directly (stock-in, adjustments).
     */
    public function test_sales_officer_cannot_mutate_stock_directly()
    {
        $session = [
            'user_id' => $this->salesOfficerA->id,
            'user_name' => $this->salesOfficerA->name,
            'user_role' => $this->salesOfficerA->role,
            'tenant_id' => $this->tenantA->id,
        ];

        // Stock in -> 403
        $this->actingAs($this->salesOfficerA)->withSession($session)
            ->postJson(route('stock.in'), [
                'warehouse_id' => $this->warehouseA1->id,
                'product_id' => $this->productA1->id,
                'quantity' => 10,
            ])->assertStatus(403);

        // Stock adjustment -> 403
        $this->actingAs($this->salesOfficerA)->withSession($session)
            ->postJson(route('stock.adjustments.record'), [
                'warehouse_id' => $this->warehouseA1->id,
                'product_id' => $this->productA1->id,
                'type' => 'DAMAGED',
                'quantity' => 1,
                'reason' => 'Broken glass',
            ])->assertStatus(403);

        // But Reports view IS allowed for sales_officer!
        $this->actingAs($this->salesOfficerA)->withSession($session)
            ->get(route('reports.index'))->assertStatus(200);
    }

    /**
     * TEST 4: Unassigned non-executive employee MUST fail closed with 403 on branch operations.
     */
    public function test_unassigned_employee_fails_closed_with_403()
    {
        $session = [
            'user_id' => $this->unassignedCashier->id,
            'user_name' => $this->unassignedCashier->name,
            'user_role' => $this->unassignedCashier->role,
            'tenant_id' => $this->tenantA->id,
        ];

        // 1. POS Index -> 403 Forbidden
        $res = $this->actingAs($this->unassignedCashier)->withSession($session)
            ->get(route('pos.index'));
        $res->assertStatus(403);

        // 2. POS Checkout -> 403 Forbidden
        $this->actingAs($this->unassignedCashier)->withSession($session)
            ->postJson(route('pos.checkout'), [
                'warehouse_id' => $this->warehouseA1->id,
                'items' => [['productId' => $this->productA1->id, 'quantity' => 1]],
                'paidAmount' => 50000,
                'cashAmount' => 50000,
                'is_supplied' => 'yes',
            ])->assertStatus(403);

        // 3. Stock Index -> 403 or redirect to dashboard
        $resStock = $this->actingAs($this->unassignedCashier)->withSession($session)
            ->get(route('stock.index'));
        $this->assertTrue(in_array($resStock->status(), [302, 403]));
    }

    /**
     * TEST 5: Unknown/unrecognized role fails closed on all protected endpoints.
     */
    public function test_unknown_role_fails_closed()
    {
        $session = [
            'user_id' => $this->unknownRoleUser->id,
            'user_name' => $this->unknownRoleUser->name,
            'user_role' => $this->unknownRoleUser->role,
            'tenant_id' => $this->tenantA->id,
        ];

        $this->actingAs($this->unknownRoleUser)->withSession($session)
            ->postJson(route('pos.checkout'), [])->assertStatus(403);

        $this->actingAs($this->unknownRoleUser)->withSession($session)
            ->postJson(route('stock.in'), [])->assertStatus(403);

        $this->actingAs($this->unknownRoleUser)->withSession($session)
            ->getJson('/api/data')->assertStatus(403);
    }

    /**
     * TEST 6: Service-level assertTenantWarehouse rejects cross-tenant warehouse operations.
     */
    public function test_service_level_assert_tenant_warehouse_rejects_foreign_branch()
    {
        session(['tenant_id' => $this->tenantA->id]);

        $stockService = app(StockService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("does not belong to active tenant");

        // Attempting to record stock in for Tenant B's warehouse while in Tenant A context
        $stockService->recordStockIn(
            $this->productA1->id,
            $this->warehouseB1->id, // Foreign warehouse!
            5,
            'Foreign Supplier',
            $this->adminA->id,
            $this->adminA->name
        );
    }

    /**
     * TEST 7: Refund method validation strictly allows CASH_REFUND and DEBT_REDUCTION only.
     */
    public function test_invalid_refund_method_is_rejected()
    {
        $session = [
            'user_id' => $this->adminA->id,
            'user_name' => $this->adminA->name,
            'user_role' => $this->adminA->role,
            'tenant_id' => $this->tenantA->id,
        ];

        // 1. Controller validation rejection
        $res = $this->actingAs($this->adminA)->withSession($session)
            ->postJson(route('pos.returns.process'), [
                'sale_id' => 'fake-sale',
                'warehouse_id' => $this->warehouseA1->id,
                'items' => [['productId' => $this->productA1->id, 'quantity' => 1]],
                'refund_method' => 'MAGIC_VOUCHER', // Invalid!
                'reason' => 'Customer changed mind',
            ]);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors('refund_method');

        // 2. Service level rejection
        $stockService = app(StockService::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid refund method 'MAGIC_VOUCHER'");

        $stockService->recordSaleReturn(
            'fake-sale',
            [['productId' => $this->productA1->id, 'quantity' => 1]],
            $this->warehouseA1->id,
            'MAGIC_VOUCHER',
            'Customer changed mind',
            $this->adminA->id,
            $this->adminA->name
        );
    }

    /**
     * TEST 8: Cash refund balances financial ledger (Sale.paidAmount decreases and negative Payment created).
     */
    public function test_cash_refund_balances_financial_ledger()
    {
        session(['tenant_id' => $this->tenantA->id]);

        $stockService = app(StockService::class);

        // 1. Create a fully paid sale
        $sale = $stockService->recordSale(
            [
                'totalAmount' => 50000,
                'paidAmount' => 50000,
                'cashAmount' => 50000,
                'posAmount' => 0,
                'transferAmount' => 0,
                'customerName' => 'Walk-in Cash Buyer',
                'sale_type' => 'RETAIL',
            ],
            [
                [
                    'productId' => $this->productA1->id,
                    'quantity' => 1,
                    'unitPrice' => 50000,
                ]
            ],
            $this->warehouseA1->id,
            true,
            $this->adminA->id,
            $this->adminA->name
        );

        $this->assertEquals(50000, $sale->paidAmount);
        $this->assertEquals(1, Payment::where('saleId', $sale->id)->where('amount', 50000)->count());

        // 2. Process Cash Return
        $salesReturn = $stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productA1->id, 'quantity' => 1]],
            $this->warehouseA1->id,
            'CASH_REFUND',
            'Defective solar cell',
            $this->adminA->id,
            $this->adminA->name
        );

        $this->assertNotNull($salesReturn);

        // 3. Verify Sale paidAmount reduced
        $sale->refresh();
        $this->assertEquals(0, $sale->paidAmount);
        $this->assertEquals('RETURNED', $sale->status);

        // 4. Verify negative Payment record created
        $negativePayment = Payment::where('saleId', $sale->id)->where('method', 'REFUND_CASH')->first();
        $this->assertNotNull($negativePayment);
        $this->assertEquals(-50000, $negativePayment->amount);

        // 5. Net drawer total from payments for this sale is exactly 0
        $this->assertEquals(0, Payment::where('saleId', $sale->id)->sum('amount'));
    }

    /**
     * TEST 9: Mutation idempotency replay and tampering protection on Stock In.
     */
    public function test_stock_in_idempotency_replay_and_tampering()
    {
        session(['tenant_id' => $this->tenantA->id]);

        $session = [
            'user_id' => $this->storekeeperA->id,
            'user_name' => $this->storekeeperA->name,
            'user_role' => $this->storekeeperA->role,
            'tenant_id' => $this->tenantA->id,
        ];

        $stockBefore = StockLevel::where('product_id', $this->productA1->id)
            ->where('warehouse_id', $this->warehouseA1->id)
            ->value('physical_stock');

        $key = 'idemp-stock-in-' . Str::random(10);

        // First call: executes mutation (+10 units)
        $res1 = $this->actingAs($this->storekeeperA)->withSession($session)
            ->withHeader('X-Idempotency-Key', $key)
            ->post(route('stock.in'), [
                'warehouse_id' => $this->warehouseA1->id,
                'product_id' => $this->productA1->id,
                'quantity' => 10,
                'supplier_name' => 'Jinko HQ',
            ]);
        $res1->assertRedirect(route('stock.index'));

        $stockAfterFirst = StockLevel::where('product_id', $this->productA1->id)
            ->where('warehouse_id', $this->warehouseA1->id)
            ->value('physical_stock');
        $this->assertEquals($stockBefore + 10, $stockAfterFirst);

        // Second call with same key and same payload: idempotent replay (no duplicate stock addition)
        $res2 = $this->actingAs($this->storekeeperA)->withSession($session)
            ->withHeader('X-Idempotency-Key', $key)
            ->post(route('stock.in'), [
                'warehouse_id' => $this->warehouseA1->id,
                'product_id' => $this->productA1->id,
                'quantity' => 10,
                'supplier_name' => 'Jinko HQ',
            ]);
        $res2->assertRedirect(route('stock.index'));

        $stockAfterSecond = StockLevel::where('product_id', $this->productA1->id)
            ->where('warehouse_id', $this->warehouseA1->id)
            ->value('physical_stock');
        $this->assertEquals($stockAfterFirst, $stockAfterSecond, "Stock must NOT increase on idempotent replay!");

        // Third call with same key but tampered payload (quantity = 50): conflict rejection!
        $res3 = $this->actingAs($this->storekeeperA)->withSession($session)
            ->withHeader('X-Idempotency-Key', $key)
            ->post(route('stock.in'), [
                'warehouse_id' => $this->warehouseA1->id,
                'product_id' => $this->productA1->id,
                'quantity' => 50, // Tampered!
                'supplier_name' => 'Jinko HQ',
            ]);
        $res3->assertSessionHasErrors('error');
        $this->assertStringContainsString('Idempotency Conflict', session('errors')->first('error'));

        $stockAfterTampered = StockLevel::where('product_id', $this->productA1->id)
            ->where('warehouse_id', $this->warehouseA1->id)
            ->value('physical_stock');
        $this->assertEquals($stockAfterSecond, $stockAfterTampered, "Tampered payload must NOT alter stock!");
    }

    /**
     * TEST 10: Platform Super Admin root identity invariant and backup normalization.
     */
    public function test_super_admin_invariant_and_backup_restore_normalization()
    {
        // 1. Root super admin identity check
        $this->assertTrue($this->superAdmin->isSuperAdmin());

        // 2. An arbitrary user with role='super_admin' under tenant A is NOT super admin
        $imposter = new User([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Fake Super Admin',
            'email' => 'fake@alpha.com',
            'role' => 'super_admin',
        ]);
        $this->assertFalse($imposter->isSuperAdmin());

        // 3. User model saving hook normalizes non-root super_admin to admin
        $imposter->id = (string) Str::uuid();
        $imposter->password = bcrypt('secret');
        $imposter->save();
        $this->assertEquals('admin', $imposter->fresh()->role);
    }
}
