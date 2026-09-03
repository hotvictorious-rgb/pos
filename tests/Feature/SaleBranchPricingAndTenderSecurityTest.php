<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Payment;
use App\Services\StockService;
use Illuminate\Support\Str;

class SaleBranchPricingAndTenderSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $stockService;
    protected Tenant $tenant;
    protected Warehouse $branchA;
    protected Warehouse $branchB;
    protected User $cashierA;
    protected User $cashierB;
    protected User $adminUser;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);

        $this->stockService = app(StockService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-branch-audit',
            'name' => 'Mega Retail Ltd',
            'owner_email' => 'owner@megaretail.ng',
            'plan' => 'enterprise',
            'status' => 'active',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->branchA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lekki Flagship Branch',
            'code' => 'WH-LEKKI-01',
            'is_active' => true,
        ]);

        $this->branchB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ikeja Mainland Branch',
            'code' => 'WH-IKEJA-02',
            'is_active' => true,
        ]);

        $this->cashierA = User::create([
            'id' => 'user-cashier-lekki',
            'tenant_id' => $this->tenant->id,
            'name' => 'Lekki Cashier Joy',
            'email' => 'joy@megaretail.ng',
            'password' => bcrypt('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->branchA->id,
            'disabled' => false,
        ]);

        $this->cashierB = User::create([
            'id' => 'user-cashier-ikeja',
            'tenant_id' => $this->tenant->id,
            'name' => 'Ikeja Cashier Tunde',
            'email' => 'tunde@megaretail.ng',
            'password' => bcrypt('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->branchB->id,
            'disabled' => false,
        ]);

        $this->adminUser = User::create([
            'id' => 'user-tenant-admin',
            'tenant_id' => $this->tenant->id,
            'name' => 'General Manager Emeka',
            'email' => 'emeka@megaretail.ng',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'warehouse_id' => $this->branchA->id,
            'disabled' => false,
        ]);

        $this->productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Luxury Basmati Rice 25kg',
            'code' => 'RICE-BASMATI-25KG',
            'category' => 'Grains',
            'unitPrice' => 40000.00,
            'costPrice' => 32000.00,
            'currentStock' => 50,
            'warehouse_id' => $this->branchA->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::updateOrCreate(
            ['product_id' => $this->productA->id, 'warehouse_id' => $this->branchA->id],
            [
                'tenant_id' => $this->tenant->id,
                'physical_stock' => 50,
                'allocated_stock' => 0,
            ]
        );

        StockLevel::updateOrCreate(
            ['product_id' => $this->productA->id, 'warehouse_id' => $this->branchB->id],
            [
                'tenant_id' => $this->tenant->id,
                'physical_stock' => 20,
                'allocated_stock' => 0,
            ]
        );

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // ─────────────────────────────────────────────────────────────
    // 1. WHOLESALE PRICING BYPASS MITIGATION
    // ─────────────────────────────────────────────────────────────

    public function test_pos_checkout_strictly_forces_retail_pricing_and_ignores_client_tampering()
    {
        // An attacker attempts to select wholesale pricing and submit unitPrice = 100 via /pos/checkout
        $payload = [
            'warehouse_id' => $this->branchA->id,
            'is_supplied' => 'yes',
            'sale_type' => 'WHOLESALE_DISPATCH', // Privileged mode injection attempt
            'paidAmount' => 100.00,
            'cashAmount' => 100.00,
            'items' => [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                    'unitPrice' => 100.00, // Price tampering attempt (Catalog price is 40,000!)
                ],
            ],
        ];

        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        // Checkout should either succeed at the authoritative retail price (40,000) or fail due to insufficient tender
        // Since paidAmount is 100 but catalog price is 40,000, it marks the sale as 40,000 total!
        $response->assertStatus(200);
        $saleId = $response->json('saleId');

        $sale = Sale::findOrFail($saleId);
        $this->assertEquals('RETAIL', $sale->sale_type, "POS checkout must strictly force RETAIL sale_type.");
        $this->assertEquals(40000.00, $sale->totalAmount, "Total amount must be authoritatively 40,000 from product catalog!");

        // Sale item unit price must be strictly 40,000
        $saleItem = $sale->items->first();
        $this->assertEquals(40000.00, $saleItem->unitPrice, "SaleItem unitPrice must be authoritative catalog price.");
    }

    // ─────────────────────────────────────────────────────────────
    // 2. TENDER ACCOUNTING & CHANGE MODELING RECONCILIATION
    // ─────────────────────────────────────────────────────────────

    public function test_cash_overpayment_models_exact_change_and_reconciles_cash_drawer()
    {
        // Customer buys 1 bag of rice (₦40,000), tenders ₦50,000 in cash
        $payload = [
            'warehouse_id' => $this->branchA->id,
            'is_supplied' => 'yes',
            'cashAmount' => 50000.00,
            'posAmount' => 0.00,
            'transferAmount' => 0.00,
            'paidAmount' => 40000.00,
            'items' => [
                ['productId' => $this->productA->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $response->assertStatus(200);
        $saleId = $response->json('saleId');
        $sale = Sale::findOrFail($saleId);

        // Authoritative Financial Equations:
        // totalAmount = 40,000
        // tenderedAmount = 50,000
        // changeAmount = 10,000
        // paidAmount = 40,000 (CANNOT exceed totalAmount!)
        // netCash = 40,000 (net cash retained in cashier till)
        $this->assertEquals(40000.00, $sale->totalAmount);
        $this->assertEquals(50000.00, $sale->tenderedAmount);
        $this->assertEquals(10000.00, $sale->changeAmount);
        $this->assertEquals(40000.00, $sale->paidAmount);
        $this->assertEquals(40000.00, $sale->cashAmount);
        $this->assertEquals('COMPLETED', $sale->status);

        // Payment record reflects net payment received
        $payment = Payment::where('saleId', $saleId)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(40000.00, $payment->amount);
    }

    public function test_paid_amount_cannot_silently_exceed_total_amount()
    {
        // Attacker attempts to forge a ₦500,000 payment for a ₦40,000 sale
        $payload = [
            'warehouse_id' => $this->branchA->id,
            'is_supplied' => 'yes',
            'cashAmount' => 500000.00,
            'paidAmount' => 500000.00,
            'items' => [
                ['productId' => $this->productA->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $response->assertStatus(200);
        $saleId = $response->json('saleId');
        $sale = Sale::findOrFail($saleId);

        // The paidAmount must be capped at totalAmount (40,000) with 460,000 recorded as change returned
        $this->assertEquals(40000.00, $sale->totalAmount);
        $this->assertEquals(40000.00, $sale->paidAmount, "paidAmount must strictly NEVER exceed totalAmount.");
        $this->assertEquals(500000.00, $sale->tenderedAmount);
        $this->assertEquals(460000.00, $sale->changeAmount);
    }

    public function test_electronic_overpayment_is_rejected()
    {
        // Customer attempts to pay ₦60,000 via POS card for a ₦40,000 sale (requesting cash change from card)
        $payload = [
            'warehouse_id' => $this->branchA->id,
            'is_supplied' => 'yes',
            'cashAmount' => 0.00,
            'posAmount' => 60000.00, // Card overpayment
            'paidAmount' => 40000.00,
            'items' => [
                ['productId' => $this->productA->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $response->assertStatus(422);
        $this->assertStringContainsString('cannot exceed sale total', $response->json('error'));
    }

    // ─────────────────────────────────────────────────────────────
    // 3. BRANCH & HISTORICAL SALE ISOLATION
    // ─────────────────────────────────────────────────────────────

    public function test_sale_permanently_persists_authoritative_warehouse_id()
    {
        $payload = [
            'warehouse_id' => $this->branchA->id,
            'is_supplied' => 'yes',
            'cashAmount' => 40000.00,
            'paidAmount' => 40000.00,
            'items' => [
                ['productId' => $this->productA->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $response->assertStatus(200);
        $saleId = $response->json('saleId');
        $sale = Sale::findOrFail($saleId);

        $this->assertEquals($this->branchA->id, $sale->warehouse_id, "Sale record must permanently persist warehouse_id.");
        $this->assertEquals($this->tenant->id, $sale->tenant_id, "Sale record must permanently persist tenant_id.");
    }

    public function test_cashier_cannot_access_receipt_of_another_branch()
    {
        // 1. Sale made at Branch A (Lekki)
        $saleA = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 40000.00,
                'paidAmount' => 40000.00,
                'cashAmount' => 40000.00,
                'customerName' => 'Lekki Resident',
            ],
            [['productId' => $this->productA->id, 'quantity' => 1]],
            $this->branchA->id,
            true,
            $this->cashierA->id,
            $this->cashierA->name
        );

        $this->assertEquals($this->branchA->id, $saleA->warehouse_id);

        // 2. Cashier A (Lekki) CAN access Lekki receipt
        $resA = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->get("/pos/receipt/{$saleA->id}");

        $resA->assertStatus(200);

        // 3. Cashier B (Ikeja) CANNOT access Lekki receipt! (Must be rejected with 403)
        $resB = $this->actingAs($this->cashierB)->withSession([
            'user_id' => $this->cashierB->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->get("/pos/receipt/{$saleA->id}");

        $resB->assertStatus(403);

        // 4. Tenant Admin CAN view all branches' receipts
        $resAdmin = $this->actingAs($this->adminUser)->withSession([
            'user_id' => $this->adminUser->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant',
        ])->get("/pos/receipt/{$saleA->id}");

        $resAdmin->assertStatus(200);
    }

    public function test_cross_branch_sales_return_is_strictly_rejected()
    {
        // Sale made at Branch A (Lekki)
        $saleA = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 40000.00,
                'paidAmount' => 40000.00,
                'cashAmount' => 40000.00,
                'customerName' => 'Lekki Buyer',
            ],
            [['productId' => $this->productA->id, 'quantity' => 1]],
            $this->branchA->id,
            true,
            $this->cashierA->id,
            $this->cashierA->name
        );

        // Attempt to process return for Sale A at Branch B (Ikeja)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cross-branch return rejected");

        $this->stockService->recordSaleReturn(
            $saleA->id,
            [['productId' => $this->productA->id, 'quantity' => 1]],
            $this->branchB->id, // WRONG BRANCH!
            'CASH_REFUND',
            'Customer walked into Ikeja branch with Lekki receipt',
            $this->cashierB->id,
            $this->cashierB->name
        );
    }

    public function test_cross_branch_unsupplied_dispatch_is_strictly_rejected()
    {
        // Customer purchases 2 units unsupplied at Branch A (Lekki)
        $saleA = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 80000.00,
                'paidAmount' => 80000.00,
                'cashAmount' => 80000.00,
                'customerName' => 'Delayed Pickup Customer',
            ],
            [['productId' => $this->productA->id, 'quantity' => 2]],
            $this->branchA->id,
            false, // UN-SUPPLIED (reserved at Lekki)
            $this->cashierA->id,
            $this->cashierA->name
        );

        // Customer attempts to pick up the goods from Branch B (Ikeja) where goods were never reserved!
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cross-branch dispatch rejected");

        $this->stockService->dispatchUnsuppliedSale(
            $saleA->id,
            $this->branchB->id, // WRONG BRANCH!
            $this->cashierB->id,
            $this->cashierB->name
        );
    }

    // ─────────────────────────────────────────────────────────────
    // 4. MIXED PAYMENT ACCOUNTING RECONCILIATION
    // ─────────────────────────────────────────────────────────────

    public function test_mixed_payment_creates_granular_reconciled_payment_records()
    {
        // 1 item priced at 35,000
        $product35k = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Premium Olive Oil 5L',
            'code' => 'OIL-OLIVE-5L',
            'category' => 'Cooking',
            'unitPrice' => 35000.00,
            'costPrice' => 28000.00,
            'currentStock' => 10,
            'warehouse_id' => $this->branchA->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::updateOrCreate(
            ['product_id' => $product35k->id, 'warehouse_id' => $this->branchA->id],
            [
                'tenant_id' => $this->tenant->id,
                'physical_stock' => 10,
                'allocated_stock' => 0,
            ]
        );

        // Mixed Tender: Cash 20k + POS 10k + Transfer 5k = 35,000
        $payload = [
            'warehouse_id' => $this->branchA->id,
            'is_supplied' => 'yes',
            'cashAmount' => 20000.00,
            'posAmount' => 10000.00,
            'transferAmount' => 5000.00,
            'paidAmount' => 35000.00,
            'items' => [
                ['productId' => $product35k->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $response->assertStatus(200);
        $saleId = $response->json('saleId');
        $sale = Sale::findOrFail($saleId);

        // Sale breakdown
        $this->assertEquals(35000.00, $sale->totalAmount);
        $this->assertEquals(35000.00, $sale->paidAmount);
        $this->assertEquals(20000.00, $sale->cashAmount);
        $this->assertEquals(10000.00, $sale->posAmount);
        $this->assertEquals(5000.00, $sale->transferAmount);
        $this->assertEquals(0.00, $sale->changeAmount);

        // Granular Payment Records in Database
        $payments = Payment::where('saleId', $saleId)->get();
        $this->assertCount(3, $payments, "Must generate exactly 3 discrete payment records for Cash, POS, and Transfer.");

        $cashPayment = $payments->firstWhere('method', 'CASH');
        $this->assertNotNull($cashPayment);
        $this->assertEquals(20000.00, $cashPayment->amount);
        $this->assertEquals($this->tenant->id, $cashPayment->tenant_id);

        $posPayment = $payments->firstWhere('method', 'POS');
        $this->assertNotNull($posPayment);
        $this->assertEquals(10000.00, $posPayment->amount);
        $this->assertEquals($this->tenant->id, $posPayment->tenant_id);

        $transferPayment = $payments->firstWhere('method', 'TRANSFER');
        $this->assertNotNull($transferPayment);
        $this->assertEquals(5000.00, $transferPayment->amount);
        $this->assertEquals($this->tenant->id, $transferPayment->tenant_id);

        // The sum of payments must equal the total paid amount exactly
        $this->assertEquals(35000.00, $payments->sum('amount'));
    }

    // ─────────────────────────────────────────────────────────────
    // 5. MULTI-TENANT ISOLATION ON SALEITEM, SALESRETURN & PAYMENTS
    // ─────────────────────────────────────────────────────────────

    public function test_sale_items_and_sales_returns_are_strictly_isolated_by_tenant()
    {
        // 1. Tenant A completes a sale and a return
        $saleA = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 40000.00,
                'paidAmount' => 40000.00,
                'cashAmount' => 40000.00,
                'customerName' => 'Tenant A Customer',
            ],
            [['productId' => $this->productA->id, 'quantity' => 1]],
            $this->branchA->id,
            true,
            $this->cashierA->id,
            $this->cashierA->name
        );

        $this->stockService->recordSaleReturn(
            $saleA->id,
            [['productId' => $this->productA->id, 'quantity' => 1]],
            $this->branchA->id,
            'CASH_REFUND',
            'Return test',
            $this->cashierA->id,
            $this->cashierA->name
        );

        // Verify Tenant A can see its own records
        $this->assertEquals(1, \App\Models\SaleItem::count());
        $this->assertEquals(1, \App\Models\SalesReturn::count());
        $this->assertEquals(1, \App\Models\Payment::count());

        // 2. Tenant B Session Context
        $tenantB = Tenant::create([
            'id' => 'tenant-competing-mart',
            'name' => 'Competing Mart Ltd',
            'owner_email' => 'owner@competing.ng',
            'plan' => 'basic',
            'status' => 'active',
        ]);

        session(['tenant_id' => $tenantB->id]);

        // In Tenant B context, queries on SaleItem, SalesReturn, and Payment MUST return 0!
        $this->assertEquals(0, \App\Models\SaleItem::count(), "SaleItem must be strictly isolated by TenantScope.");
        $this->assertEquals(0, \App\Models\SalesReturn::count(), "SalesReturn must be strictly isolated by TenantScope.");
        $this->assertEquals(0, \App\Models\Payment::count(), "Payment must be strictly isolated by TenantScope.");
    }

    // ─────────────────────────────────────────────────────────────
    // 6. INSTALLER SECURITY: HASHED PASSWORDS & SANITIZED DB ERRORS
    // ─────────────────────────────────────────────────────────────

    public function test_installer_does_not_store_plaintext_password_in_session()
    {
        $response = $this->withoutMiddleware(\App\Http\Middleware\CheckInstalled::class)
            ->post(route('installer.install'), [
                'admin_name' => 'Super Administrator',
                'admin_email' => 'admin@platform.ng',
                'admin_password' => 'Secr3tP@ssw0rd!',
                'admin_password_confirmation' => 'Secr3tP@ssw0rd!',
            ]);

        $response->assertStatus(200);

        // Plaintext password MUST NOT exist in session
        $this->assertFalse(session()->has('installer_admin_password'), "Plaintext password must NEVER be placed in session.");

        // Only pre-hashed password should exist in session
        $this->assertTrue(session()->has('installer_admin_password_hash'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Secr3tP@ssw0rd!', session('installer_admin_password_hash')));
    }

    // ─────────────────────────────────────────────────────────────
    // 7. CHECKWEBAUTH SESSION REHYDRATION ANTI-SPOOFING
    // ─────────────────────────────────────────────────────────────

    public function test_session_tenant_spoofing_is_automatically_corrected_by_check_web_auth()
    {
        // An attacker from Tenant A tries to set session(['tenant_id' => 'tenant-victim'])
        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => 'tenant-victim-spoofed', // Attacker injects a foreign tenant ID
            'portal' => 'tenant-employee',
        ])->get('/dashboard');

        // CheckWebAuth must detect the mismatch between the authenticated user's DB tenant and session tenant,
        // and override it back to the user's authentic tenant!
        $this->assertEquals($this->tenant->id, session('tenant_id'), "CheckWebAuth must forcefully realign session tenant to user's DB tenant.");
    }
}
