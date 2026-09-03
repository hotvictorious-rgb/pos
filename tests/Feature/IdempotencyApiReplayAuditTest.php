<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Activity;
use App\Models\InventoryLog;
use App\Models\Payment;
use App\Models\SalesReturn;
use App\Services\StockService;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyApiReplayAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected User $userA1;
    protected User $userA2;
    protected User $userB;
    protected Product $productA1;
    protected Product $productA2;
    protected Product $productB;
    protected StockService $stockService;
    protected IdempotencyService $idempotencyService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);

        $this->stockService = app(StockService::class);
        $this->idempotencyService = app(IdempotencyService::class);

        // Tenant A setup
        $this->tenantA = Tenant::create([
            'id' => 'tenant-idem-a',
            'name' => 'Alpha Supermarket',
            'owner_email' => 'owner@alpha.com',
            'owner_phone' => '08011111111',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Main',
            'code' => 'ALPHA-01',
            'is_active' => true,
        ]);

        $this->userA1 = User::create([
            'id' => 'user-a1',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Cashier 1',
            'email' => 'cashier1@alpha.com',
            'password' => bcrypt('Password123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $this->userA2 = User::create([
            'id' => 'user-a2',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Cashier 2',
            'email' => 'cashier2@alpha.com',
            'password' => bcrypt('Password123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $this->productA1 = Product::create([
            'id' => 'prod-alpha-rice',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Rice 50kg',
            'code' => 'ALPHA-RICE',
            'unitPrice' => 50000.00,
            'costPrice' => 45000.00,
            'category' => 'Grains',
            'currentStock' => 100,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA1->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 100,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        $this->productA2 = Product::create([
            'id' => 'prod-alpha-sugar',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Sugar 50kg',
            'code' => 'ALPHA-SUGAR',
            'unitPrice' => 40000.00,
            'costPrice' => 36000.00,
            'category' => 'Commodities',
            'currentStock' => 50,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA2->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        // Tenant B setup
        $this->tenantB = Tenant::create([
            'id' => 'tenant-idem-b',
            'name' => 'Beta Mart',
            'owner_email' => 'owner@beta.com',
            'owner_phone' => '08022222222',
            'status' => 'active',
            'plan' => 'basic',
            'max_branches' => 2,
            'max_users' => 5,
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Main',
            'code' => 'BETA-01',
            'is_active' => true,
        ]);

        $this->userB = User::create([
            'id' => 'user-b1',
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Cashier',
            'email' => 'cashier@beta.com',
            'password' => bcrypt('Password123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseB->id,
        ]);

        $this->productB = Product::create([
            'id' => 'prod-beta-rice',
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Rice 50kg',
            'code' => 'BETA-RICE',
            'unitPrice' => 52000.00,
            'costPrice' => 48000.00,
            'category' => 'Grains',
            'currentStock' => 80,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantB->id,
            'product_id' => $this->productB->id,
            'warehouse_id' => $this->warehouseB->id,
            'physical_stock' => 80,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // ─────────────────────────────────────────────────────────
    // 1. IDEMPOTENCY KEY SECURITY & REPLAY TESTS
    // ─────────────────────────────────────────────────────────

    public function test_same_key_identical_request_is_idempotent()
    {
        $idempotencyKey = 'IDEM-KEY-IDENTICAL-01';

        $payload = [
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'paidAmount' => 50000.00,
            'cashAmount' => 50000.00,
            'items' => [
                ['productId' => $this->productA1->id, 'quantity' => 1],
            ],
        ];

        // First Request
        $res1 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payload);

        $res1->assertStatus(200);
        $saleId1 = $res1->json('saleId');

        // Physical stock 100 - 1 = 99
        $stock1 = StockLevel::where('product_id', $this->productA1->id)->where('warehouse_id', $this->warehouseA->id)->first();
        $this->assertEquals(99, $stock1->physical_stock);

        // Second Request: Identical payload + identical key
        $res2 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payload);

        $res2->assertStatus(200);
        $saleId2 = $res2->json('saleId');
        $this->assertEquals($saleId1, $saleId2);

        // Stock MUST NOT be deducted a second time
        $stock2 = StockLevel::where('product_id', $this->productA1->id)->where('warehouse_id', $this->warehouseA->id)->first();
        $this->assertEquals(99, $stock2->physical_stock);
        $this->assertEquals(1, Sale::where('id', $saleId1)->count());
    }

    public function test_same_key_different_request_payload_is_rejected_as_conflict()
    {
        $idempotencyKey = 'IDEM-KEY-CONFLICT-01';

        // Request 1: 1 bag of rice
        $payload1 = [
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'paidAmount' => 50000.00,
            'cashAmount' => 50000.00,
            'items' => [
                ['productId' => $this->productA1->id, 'quantity' => 1],
            ],
        ];

        $res1 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payload1);

        $res1->assertStatus(200);

        // Request 2: Attacker/client reuses same key for 10 bags of sugar!
        $payload2 = [
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'paidAmount' => 40000.00 * 10,
            'cashAmount' => 40000.00 * 10,
            'items' => [
                ['productId' => $this->productA2->id, 'quantity' => 10],
            ],
        ];

        $res2 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payload2);

        // Must reject key reuse with different request payload (HTTP 422 Conflict)
        $res2->assertStatus(422);
        $this->assertStringContainsString('Idempotency Conflict', $res2->json('error'));

        // Sugar stock MUST NOT be altered
        $sugarStock = StockLevel::where('product_id', $this->productA2->id)->where('warehouse_id', $this->warehouseA->id)->first();
        $this->assertEquals(50, $sugarStock->physical_stock);
    }

    public function test_same_key_across_different_tenants_does_not_collide_or_leak()
    {
        $sharedKey = 'IDEM-KEY-CROSS-TENANT-777';

        // 1. Tenant A uses the key
        $payloadA = [
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'paidAmount' => 50000.00,
            'cashAmount' => 50000.00,
            'items' => [
                ['productId' => $this->productA1->id, 'quantity' => 1],
            ],
        ];

        $resA = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $sharedKey)
          ->postJson('/pos/checkout', $payloadA);

        $resA->assertStatus(200);
        $saleIdA = $resA->json('saleId');

        // 2. Tenant B independently uses the EXACT SAME KEY for their own business
        $payloadB = [
            'warehouse_id' => $this->warehouseB->id,
            'is_supplied' => 'yes',
            'paidAmount' => 52000.00,
            'cashAmount' => 52000.00,
            'items' => [
                ['productId' => $this->productB->id, 'quantity' => 1],
            ],
        ];

        $resB = $this->actingAs($this->userB)->withSession([
            'user_id' => $this->userB->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantB->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $sharedKey)
          ->postJson('/pos/checkout', $payloadB);

        $resB->assertStatus(200);
        $saleIdB = $resB->json('saleId');

        // Tenant B must get their OWN sale, completely independent of Tenant A
        $this->assertNotEquals($saleIdA, $saleIdB);

        $saleRecordA = Sale::withoutGlobalScopes()->find($saleIdA);
        $saleRecordB = Sale::withoutGlobalScopes()->find($saleIdB);
        $this->assertEquals($this->tenantA->id, $saleRecordA->tenant_id);
        $this->assertEquals($this->tenantB->id, $saleRecordB->tenant_id);
    }

    public function test_same_key_across_different_users_is_rejected()
    {
        $idempotencyKey = 'IDEM-KEY-CROSS-USER-888';

        // User A1 creates an order with this key
        $payload = [
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'paidAmount' => 50000.00,
            'cashAmount' => 50000.00,
            'items' => [
                ['productId' => $this->productA1->id, 'quantity' => 1],
            ],
        ];

        $res1 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payload);

        $res1->assertStatus(200);

        // User A2 attempts to submit with User A1's key
        $res2 = $this->actingAs($this->userA2)->withSession([
            'user_id' => $this->userA2->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payload);

        // Must reject cross-user key reuse
        $res2->assertStatus(422);
        $this->assertStringContainsString('Idempotency Authorization Violation', $res2->json('error'));
    }

    public function test_failed_transaction_does_not_poison_idempotency_key_for_retry()
    {
        $idempotencyKey = 'IDEM-KEY-RETRY-01';

        // Request 1: Attempt to checkout 500 units (fails due to insufficient stock)
        $payloadFail = [
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'paidAmount' => 50000.00 * 500,
            'cashAmount' => 50000.00 * 500,
            'items' => [
                ['productId' => $this->productA1->id, 'quantity' => 500],
            ],
        ];

        $res1 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payloadFail);

        $res1->assertStatus(422);

        // Request 2: Client fixes the quantity to 2 units and retries with the key
        $payloadSuccess = [
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'paidAmount' => 50000.00 * 2,
            'cashAmount' => 50000.00 * 2,
            'items' => [
                ['productId' => $this->productA1->id, 'quantity' => 2],
            ],
        ];

        $res2 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payloadSuccess);

        // Must succeed without being poisoned by the prior rollback!
        $res2->assertStatus(200);
        $stock = StockLevel::where('product_id', $this->productA1->id)->where('warehouse_id', $this->warehouseA->id)->first();
        $this->assertEquals(98, $stock->physical_stock);
    }

    // ─────────────────────────────────────────────────────────
    // 2. AUDIT LOG IMMUTABILITY & ANTI-TAMPER TESTS
    // ─────────────────────────────────────────────────────────

    public function test_audit_activity_records_cannot_be_pruned_via_offline_sync()
    {
        // 1. Create 3 audit activities under Tenant A
        Activity::create([
            'id' => 'act-001',
            'tenant_id' => $this->tenantA->id,
            'type' => 'SALE',
            'description' => 'User recorded sale #1',
            'userId' => $this->userA1->id,
            'userName' => $this->userA1->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        Activity::create([
            'id' => 'act-002',
            'tenant_id' => $this->tenantA->id,
            'type' => 'STOCK_IN',
            'description' => 'User added 50 units',
            'userId' => $this->userA1->id,
            'userName' => $this->userA1->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        Activity::create([
            'id' => 'act-003',
            'tenant_id' => $this->tenantA->id,
            'type' => 'DEBT_PAYMENT',
            'description' => 'User cleared customer debt',
            'userId' => $this->userA1->id,
            'userName' => $this->userA1->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->assertEquals(3, Activity::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->count());

        // 2. Tenant Admin sends offline sync payload with EMPTY activities: []
        // In vulnerable code, whereNotIn('id', []) deleted all audit logs!
        $adminUser = User::create([
            'id' => 'admin-user-alpha',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Admin',
            'email' => 'admin@alpha.com',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($adminUser)->withSession([
            'user_id' => $adminUser->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->postJson('/api/data', [
            'activities' => [], // Attempt to erase audit history!
            'logs' => [],       // Attempt to erase inventory logs!
            'payments' => [],   // Attempt to erase payment logs!
            'returns' => [],    // Attempt to erase return logs!
        ]);

        // Endpoint must reject offline sync with 403 Forbidden
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden. Offline data synchronization is disabled. VMarket POS is strictly online-only; all transactions must be submitted via authoritative business endpoints.']);

        // All 3 audit activity records MUST STILL EXIST intact!
        $this->assertEquals(3, Activity::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->count());
    }

    public function test_financial_transactions_produce_complete_immutable_audit_trail()
    {
        session(['tenant_id' => $this->tenantA->id]);

        // 1. Sale
        $sale = $this->stockService->recordSale(
            ['totalAmount' => 50000.00, 'paidAmount' => 50000.00],
            [['productId' => $this->productA1->id, 'quantity' => 1]],
            $this->warehouseA->id,
            true,
            $this->userA1->id,
            $this->userA1->name
        );

        // Check InventoryLog
        $saleLog = InventoryLog::where('productId', $this->productA1->id)
            ->where('type', 'SALE')
            ->latest('timestamp')
            ->first();

        $this->assertNotNull($saleLog);
        $this->assertEquals(-1, $saleLog->quantity);
        $this->assertEquals($this->tenantA->id, $saleLog->tenant_id);
        $this->assertEquals($this->userA1->id, $saleLog->userId);

        // 2. Return
        $return = $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productA1->id, 'quantity' => 1]],
            $this->warehouseA->id,
            'CASH_REFUND',
            'Customer refund test',
            $this->userA1->id,
            $this->userA1->name
        );

        $returnLog = InventoryLog::where('productId', $this->productA1->id)
            ->where('type', 'SALES_RETURN')
            ->latest('timestamp')
            ->first();

        $this->assertNotNull($returnLog);
        $this->assertEquals(1, $returnLog->quantity);
        $this->assertEquals($this->tenantA->id, $returnLog->tenant_id);

        // 3. Customer Debt Payment
        $customer = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Audit Test Customer',
            'phone' => '08099881122',
            'total_debt' => 20000.00,
        ]);

        $ledger = $this->stockService->recordCustomerPayment(
            $customer->id,
            15000.00,
            'TRANSFER',
            'TRF-AUDIT-999',
            $this->userA1->id,
            $this->userA1->name
        );

        $this->assertEquals($this->tenantA->id, $ledger->tenant_id);
        $this->assertEquals(15000.00, $ledger->amount);
        $this->assertEquals(5000.00, $ledger->balance_after);

        // Activity log check
        $act = Activity::where('type', 'DEBT_PAYMENT')->where('tenant_id', $this->tenantA->id)->latest('timestamp')->first();
        $this->assertNotNull($act);
        $this->assertEquals($this->userA1->id, $act->userId);
        $this->assertStringContainsString('15,000.00', $act->description);
    }

    /**
     * TEST 8: Cache loss / eviction does NOT cause duplicate financial transactions.
     * Persistent IdempotencyRecord protects against replaying previously processed checkouts.
     */
    public function test_idempotency_survives_cache_loss_and_prevents_duplicate_financial_transactions()
    {
        $idempotencyKey = 'IDEM-KEY-DURABLE-01';

        $payload = [
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'paidAmount' => 50000.00,
            'cashAmount' => 50000.00,
            'items' => [
                ['productId' => $this->productA1->id, 'quantity' => 1],
            ],
        ];

        // Request 1: Initial successful checkout
        $res1 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payload);

        $res1->assertStatus(200);
        $saleId1 = $res1->json('saleId');
        $this->assertNotNull($saleId1);
        $this->assertEquals(1, Sale::where('id', $saleId1)->count());

        // Stock was deducted by 1: 100 -> 99
        $stock1 = StockLevel::where('product_id', $this->productA1->id)->where('warehouse_id', $this->warehouseA->id)->first();
        $this->assertEquals(99, $stock1->physical_stock);

        // Verify that a persistent IdempotencyRecord was written to the database
        $dbRecord = \App\Models\IdempotencyRecord::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)
            ->where('operation', 'pos_checkout')
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        $this->assertNotNull($dbRecord, "Persistent idempotency record must exist in database.");
        $this->assertEquals('COMPLETED', $dbRecord->status);
        $this->assertEquals($this->userA1->id, $dbRecord->user_id);

        // ── SIMULATE COMPLETE CACHE LOSS / FLUSH / REDIS CRASH / EVICTION ──
        \Illuminate\Support\Facades\Cache::flush();

        // Verify cache is genuinely empty
        $cacheKey = "idempotency:{$this->tenantA->id}:pos_checkout:{$idempotencyKey}";
        $this->assertNull(\Illuminate\Support\Facades\Cache::get($cacheKey));

        // Request 2: Retry with exact same key after cache loss
        $res2 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payload);

        $res2->assertStatus(200);
        $saleId2 = $res2->json('saleId');

        // MUST return the EXACT same sale ID
        $this->assertEquals($saleId1, $saleId2);

        // Stock MUST NOT be deducted a second time (MUST remain 99, not 98)
        $stock2 = StockLevel::where('product_id', $this->productA1->id)->where('warehouse_id', $this->warehouseA->id)->first();
        $this->assertEquals(99, $stock2->physical_stock, "Physical stock must remain 99; no second deduction permitted.");

        // Sales table MUST NOT contain a second duplicate sale record
        $this->assertEquals(1, Sale::where('tenant_id', $this->tenantA->id)->count(), "No duplicate sale may be created.");
    }

    /**
     * TEST 9: Persistent idempotency rejects payload tampering and user hijacking even after cache loss
     */
    public function test_persistent_idempotency_enforces_fingerprint_and_user_authorization_after_cache_loss()
    {
        $idempotencyKey = 'IDEM-KEY-TAMPER-DURABLE-02';

        $payloadOriginal = [
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'paidAmount' => 50000.00,
            'cashAmount' => 50000.00,
            'items' => [
                ['productId' => $this->productA1->id, 'quantity' => 1],
            ],
        ];

        // 1. Initial checkout by Cashier 1
        $res1 = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payloadOriginal);

        $res1->assertStatus(200);

        // Wipe cache
        \Illuminate\Support\Facades\Cache::flush();

        // 2. Tampered Payload: Attempt to use same key with different amount
        $payloadTampered = $payloadOriginal;
        $payloadTampered['paidAmount'] = 10000.00;

        $resTampered = $this->actingAs($this->userA1)->withSession([
            'user_id' => $this->userA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payloadTampered);

        $resTampered->assertStatus(422); // Rejection
        $this->assertStringContainsString('different request payload', $resTampered->json('error'));

        // 3. User Hijacking: Attempt by Cashier 2 to reuse Cashier 1's key
        $resHijack = $this->actingAs($this->userA2)->withSession([
            'user_id' => $this->userA2->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->withHeader('X-Idempotency-Key', $idempotencyKey)
          ->postJson('/pos/checkout', $payloadOriginal);

        $resHijack->assertStatus(422);
        $this->assertStringContainsString('across different user accounts', $resHijack->json('error'));
    }
}
