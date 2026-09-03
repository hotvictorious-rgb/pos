<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\SalesReturn;
use App\Models\InventoryLog;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineOnlyApiSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $branchA1;
    protected Warehouse $branchA2;
    protected Warehouse $branchB;
    protected User $adminA;
    protected User $cashierA1;
    protected User $cashierA2;
    protected User $adminB;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);

        // Tenant A
        $this->tenantA = Tenant::create([
            'id' => 'tenant-online-a',
            'name' => 'Prime Supermarket',
            'owner_email' => 'owner@prime.com',
            'owner_phone' => '08033333333',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        $this->branchA1 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Prime Branch 1',
            'code' => 'PRIME-01',
            'is_active' => true,
        ]);

        $this->branchA2 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Prime Branch 2',
            'code' => 'PRIME-02',
            'is_active' => true,
        ]);

        $this->adminA = User::create([
            'id' => 'admin-prime',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Prime Admin',
            'email' => 'admin@prime.com',
            'password' => bcrypt('AdminPass123!'),
            'role' => 'admin',
        ]);

        $this->cashierA1 = User::create([
            'id' => 'cashier-prime-1',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cashier Prime 1',
            'email' => 'cashier1@prime.com',
            'password' => bcrypt('CashierPass123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->branchA1->id,
        ]);

        $this->cashierA2 = User::create([
            'id' => 'cashier-prime-2',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cashier Prime 2',
            'email' => 'cashier2@prime.com',
            'password' => bcrypt('CashierPass123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->branchA2->id,
        ]);

        $this->productA = Product::create([
            'id' => 'prod-prime-oil',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Vegetable Oil 25L',
            'code' => 'PRIME-OIL',
            'unitPrice' => 35000.00,
            'costPrice' => 30000.00,
            'category' => 'Cooking',
            'currentStock' => 50,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->branchA1->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        // Tenant B
        $this->tenantB = Tenant::create([
            'id' => 'tenant-online-b',
            'name' => 'Apex Depot',
            'owner_email' => 'owner@apex.com',
            'owner_phone' => '08044444444',
            'status' => 'active',
            'plan' => 'basic',
            'max_branches' => 2,
            'max_users' => 5,
        ]);

        $this->branchB = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Apex Main',
            'code' => 'APEX-01',
            'is_active' => true,
        ]);

        $this->adminB = User::create([
            'id' => 'admin-apex',
            'tenant_id' => $this->tenantB->id,
            'name' => 'Apex Admin',
            'email' => 'admin@apex.com',
            'password' => bcrypt('AdminPass123!'),
            'role' => 'admin',
        ]);

        $this->productB = Product::create([
            'id' => 'prod-apex-flour',
            'tenant_id' => $this->tenantB->id,
            'name' => 'Wheat Flour 50kg',
            'code' => 'APEX-FLOUR',
            'unitPrice' => 45000.00,
            'costPrice' => 40000.00,
            'category' => 'Bakery',
            'currentStock' => 40,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantB->id,
            'product_id' => $this->productB->id,
            'warehouse_id' => $this->branchB->id,
            'physical_stock' => 40,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // ─────────────────────────────────────────────────────────
    // 1. ONLINE-ONLY ENFORCEMENT & API AUTHENTICATION
    // ─────────────────────────────────────────────────────────

    public function test_unauthenticated_requests_to_api_are_rejected()
    {
        // Must reject unauthenticated API queries with 401
        $this->getJson('/api/me')->assertStatus(401);
        $this->getJson('/api/data')->assertStatus(401);
        $this->postJson('/api/data', [])->assertStatus(401);
        $this->postJson('/api/reset')->assertStatus(401);
        $this->getJson('/api/backups')->assertStatus(401);
    }

    public function test_legacy_offline_sync_push_is_permanently_disabled()
    {
        // Authenticated admin attempts to push bulk database mutations via /api/data
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->postJson('/api/data', [
            'products' => [
                ['id' => 'hacked-product', 'name' => 'Injected Product', 'unitPrice' => 1]
            ],
            'sales' => [
                ['id' => 'fake-sale', 'totalAmount' => 0.01, 'paidAmount' => 0.01]
            ]
        ]);

        // MUST return 403 Forbidden: Offline sync disabled in online mode
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden. Offline data synchronization is disabled. VMarket POS is strictly online-only; all transactions must be submitted via authoritative business endpoints.']);

        // Assert nothing was injected
        $this->assertNull(Product::withoutGlobalScopes()->find('hacked-product'));
        $this->assertNull(Sale::withoutGlobalScopes()->find('fake-sale'));
    }

    // ─────────────────────────────────────────────────────────
    // 2. FINANCIAL LEDGER IMMUTABILITY (HTTP LEVEL)
    // ─────────────────────────────────────────────────────────

    public function test_financial_ledgers_have_no_update_or_delete_http_endpoints()
    {
        // 1. Create a legitimate sale, payment, return, and ledger entry
        session(['tenant_id' => $this->tenantA->id]);
        $stockService = app(StockService::class);

        $sale = $stockService->recordSale(
            ['totalAmount' => 35000.00, 'paidAmount' => 35000.00, 'cashAmount' => 35000.00],
            [['productId' => $this->productA->id, 'quantity' => 1]],
            $this->branchA1->id,
            true,
            $this->cashierA1->id,
            $this->cashierA1->name
        );

        $payment = Payment::where('saleId', $sale->id)->first();
        $this->assertNotNull($payment);

        // 2. Attempt HTTP PUT/PATCH/DELETE on payment
        $resPutPay = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->putJson("/payments/{$payment->id}", ['amount' => 0.01]);
        $resPutPay->assertStatus(404); // No such route!

        $resDelPay = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->deleteJson("/payments/{$payment->id}");
        $resDelPay->assertStatus(404); // No such route!

        // 3. Attempt HTTP PUT/PATCH/DELETE on inventory logs
        $log = InventoryLog::withoutGlobalScopes()->where('productId', $this->productA->id)->first();
        $this->assertNotNull($log);

        $resDelLog = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->deleteJson("/inventory-logs/{$log->id}");
        $resDelLog->assertStatus(404); // No such route!

        // 4. Attempt HTTP PUT/PATCH/DELETE on activities
        $act = Activity::create([
            'id' => 'act-audit-immutability-01',
            'tenant_id' => $this->tenantA->id,
            'type' => 'AUDIT_TEST',
            'description' => 'Test immutable activity entry',
            'userId' => $this->adminA->id,
            'userName' => $this->adminA->name,
            'timestamp' => now()->toIso8601String(),
        ]);
        $this->assertNotNull($act);

        $resDelAct = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->deleteJson("/activities/{$act->id}");
        $resDelAct->assertStatus(404); // No such route!
    }

    // ─────────────────────────────────────────────────────────
    // 3. AUDIT LOG AUTHENTICITY & ANTI-FORGERY
    // ─────────────────────────────────────────────────────────

    public function test_audit_logs_derive_actor_from_server_session_not_client_payload()
    {
        // Cashier A1 attempts to forge identity by sending another user's ID/name in checkout
        $response = $this->actingAs($this->cashierA1)->withSession([
            'user_id' => $this->cashierA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', [
            'warehouse_id' => $this->branchA1->id,
            'is_supplied' => 'yes',
            'paidAmount' => 35000.00,
            'cashAmount' => 35000.00,
            'userId' => 'admin-prime', // Attempt to forge audit identity!
            'userName' => 'Prime Admin',
            'items' => [
                ['productId' => $this->productA->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200);

        // Verify that the inventory log strictly recorded cashierA1, NOT the forged admin!
        $latestLog = InventoryLog::withoutGlobalScopes()->where('productId', $this->productA->id)->latest('created_at')->first();
        $this->assertNotNull($latestLog);
        $this->assertEquals($this->cashierA1->id, $latestLog->userId);
        $this->assertEquals($this->cashierA1->name, $latestLog->userName);
    }

    // ─────────────────────────────────────────────────────────
    // 4. SERVER AS SOLE SOURCE OF TRUTH (PRICING & STOCK)
    // ─────────────────────────────────────────────────────────

    public function test_client_cannot_become_authoritative_for_pricing()
    {
        // Client attempts to claim unitPrice is ₦1.00 (authoritative database price is ₦35,000.00)
        $response = $this->actingAs($this->cashierA1)->withSession([
            'user_id' => $this->cashierA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', [
            'warehouse_id' => $this->branchA1->id,
            'is_supplied' => 'yes',
            'paidAmount' => 1.00,
            'cashAmount' => 1.00,
            'items' => [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                    'unitPrice' => 1.00, // Spoofed client price!
                    'totalPrice' => 1.00,
                ],
            ],
        ]);

        // Server authoritative calculation calculates ₦35,000; paidAmount (₦1) creates PARTIAL sale
        $response->assertStatus(200);
        $saleId = $response->json('saleId');
        $sale = Sale::withoutGlobalScopes()->find($saleId);

        $this->assertEquals(35000.00, (float) $sale->totalAmount);
        $this->assertEquals(1.00, (float) $sale->paidAmount);
        $this->assertEquals('PARTIAL', $sale->status);
    }

    // ─────────────────────────────────────────────────────────
    // 5. TENANT & BRANCH ISOLATION VIA API
    // ─────────────────────────────────────────────────────────

    public function test_api_data_export_strictly_isolates_tenants()
    {
        // Admin A fetches /api/data
        $responseA = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->getJson('/api/data');

        $responseA->assertStatus(200);
        $dataA = $responseA->json();

        // Must see Tenant A's products, 0 of Tenant B's
        $prodIdsA = collect($dataA['products'])->pluck('id')->all();
        $this->assertContains($this->productA->id, $prodIdsA);
        $this->assertNotContains($this->productB->id, $prodIdsA);

        // Admin B fetches /api/data
        $responseB = $this->actingAs($this->adminB)->withSession([
            'user_id' => $this->adminB->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantB->id,
            'portal' => 'tenant',
        ])->getJson('/api/data');

        $responseB->assertStatus(200);
        $dataB = $responseB->json();

        // Must see Tenant B's products, 0 of Tenant A's
        $prodIdsB = collect($dataB['products'])->pluck('id')->all();
        $this->assertContains($this->productB->id, $prodIdsB);
        $this->assertNotContains($this->productA->id, $prodIdsB);
    }

    public function test_branch_cashier_cannot_checkout_from_unassigned_branch()
    {
        // Cashier A1 is assigned to Branch A1.
        // Attempts to checkout stock from Branch A2!
        $response = $this->actingAs($this->cashierA1)->withSession([
            'user_id' => $this->cashierA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', [
            'warehouse_id' => $this->branchA2->id, // Unassigned branch!
            'is_supplied' => 'yes',
            'paidAmount' => 35000.00,
            'cashAmount' => 35000.00,
            'items' => [
                ['productId' => $this->productA->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200);

        // Branch-scoped user is automatically forced to their assigned branch in PosController!
        // Stock in Branch A1 must be 50 - 1 = 49
        $stockA1 = StockLevel::where('product_id', $this->productA->id)->where('warehouse_id', $this->branchA1->id)->first();
        $this->assertEquals(49, $stockA1->physical_stock);

        // Branch A2 never had stock allocated and was not touched
        $stockA2 = StockLevel::where('product_id', $this->productA->id)->where('warehouse_id', $this->branchA2->id)->first();
        $this->assertNull($stockA2);
    }
}
