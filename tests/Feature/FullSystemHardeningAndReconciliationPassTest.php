<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\SalesReturn;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Str;

class FullSystemHardeningAndReconciliationPassTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $stockService;
    protected AccountingReportService $accountingService;
    protected Tenant $tenant;
    protected Warehouse $branchMain;
    protected Warehouse $branchSecond;
    protected User $adminUser;
    protected User $cashierUser;
    protected Product $productSugar;
    protected Product $productFlour;
    protected Customer $customerAlhaji;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@hysam.com',
        ]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-hardening-01',
            'name' => 'Victorious Enterprise Ltd',
            'owner_email' => 'ceo@victorious.ng',
            'plan' => 'enterprise',
            'status' => 'active',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->branchMain = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Central Warehouse',
            'code' => 'WH-MAIN-01',
            'is_active' => true,
        ]);

        $this->branchSecond = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Island Branch Warehouse',
            'code' => 'WH-ISLAND-02',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'id' => 'user-admin-hardening',
            'tenant_id' => $this->tenant->id,
            'name' => 'Chief Auditor Adamu',
            'email' => 'adamu@victorious.ng',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'warehouse_id' => $this->branchMain->id,
            'disabled' => false,
        ]);

        $this->cashierUser = User::create([
            'id' => 'user-cashier-hardening',
            'tenant_id' => $this->tenant->id,
            'name' => 'Cashier Blessing',
            'email' => 'blessing@victorious.ng',
            'password' => bcrypt('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->branchMain->id,
            'disabled' => false,
        ]);

        $this->productSugar = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Dangote Sugar 50kg',
            'code' => 'SUGAR-50KG',
            'category' => 'Commodities',
            'unitPrice' => 50000.00,
            'costPrice' => 42000.00,
            'currentStock' => 20,
            'warehouse_id' => $this->branchMain->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $this->productFlour = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Golden Penny Flour 50kg',
            'code' => 'FLOUR-50KG',
            'category' => 'Commodities',
            'unitPrice' => 45000.00,
            'costPrice' => 38000.00,
            'currentStock' => 10,
            'warehouse_id' => $this->branchMain->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::updateOrCreate(
            ['product_id' => $this->productSugar->id, 'warehouse_id' => $this->branchMain->id],
            [
                'tenant_id' => $this->tenant->id,
                'physical_stock' => 20,
                'allocated_stock' => 0,
            ]
        );

        StockLevel::updateOrCreate(
            ['product_id' => $this->productFlour->id, 'warehouse_id' => $this->branchMain->id],
            [
                'tenant_id' => $this->tenant->id,
                'physical_stock' => 10,
                'allocated_stock' => 0,
            ]
        );

        $this->customerAlhaji = Customer::create([
            'name' => 'Alhaji Musa Trading',
            'phone' => '08031112233',
            'address' => 'Balogun Market, Lagos',
            'total_debt' => 0.00,
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // =========================================================================
    // 1. WHOLESALE SUBSYSTEM REMOVAL & POS ROUTE SHUTDOWN
    // =========================================================================

    public function test_wholesale_routes_return_404_and_subsystem_is_fully_eliminated()
    {
        $this->actingAs($this->adminUser);

        // All wholesale routes must return 404
        $respIndex = $this->get('/wholesale');
        $respIndex->assertStatus(404);

        $respCreate = $this->post('/wholesale', []);
        $respCreate->assertStatus(404);

        $respInvoice = $this->get('/wholesale/invoice/123');
        $respInvoice->assertStatus(404);
    }

    public function test_pos_checkout_rejects_wholesale_spoofing_and_forces_retail_pricing()
    {
        $payload = [
            'warehouse_id' => $this->branchMain->id,
            'is_supplied' => 'yes',
            'sale_type' => 'WHOLESALE_DISPATCH', // Attempt to trigger removed wholesale mode
            'totalAmount' => 1000.00, // Forged total
            'paidAmount' => 50000.00,
            'cashAmount' => 50000.00,
            'items' => [
                [
                    'productId' => $this->productSugar->id,
                    'quantity' => 1,
                    'unitPrice' => 1000.00, // Attacker attempts to forge price
                ],
            ],
        ];

        $response = $this->actingAs($this->cashierUser)->withSession([
            'user_id' => $this->cashierUser->id,
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $response->assertStatus(200);
        $saleId = $response->json('saleId');
        $sale = Sale::findOrFail($saleId);

        $this->assertEquals('RETAIL', $sale->sale_type, "POS checkout must force RETAIL sale_type.");
        $this->assertEquals(50000.00, $sale->totalAmount, "Authoritative catalog price 50,000 must be enforced!");
    }

    // =========================================================================
    // 2. TWO-DIMENSIONAL DECOUPLED INVENTORY & SHORTFALL INVARIANTS
    // =========================================================================

    public function test_decoupled_unsupplied_sale_allows_shortfall_and_creates_stock_reservation()
    {
        // Product Flour has 10 physical units.
        // Customer reserves 15 unsupplied units.
        $sale = $this->stockService->recordSale(
            [
                'totalAmount' => 15 * 45000.00,
                'paidAmount' => 15 * 45000.00,
                'cashAmount' => 15 * 45000.00,
                'customerId' => $this->customerAlhaji->id,
                'customerName' => $this->customerAlhaji->name,
            ],
            [['productId' => $this->productFlour->id, 'quantity' => 15]],
            $this->branchMain->id,
            false, // UN-SUPPLIED
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        $stock = StockLevel::where('product_id', $this->productFlour->id)
            ->where('warehouse_id', $this->branchMain->id)
            ->first();

        // Mathematical Proof:
        // Physical shelf stock remains 10 (untouched).
        // Allocated stock becomes 15.
        // Shortfall = max(0, 15 - 10) = 5.
        $this->assertEquals(10, $stock->physical_stock);
        $this->assertEquals(15, $stock->allocated_stock);
        $this->assertEquals(5, $stock->reservation_shortfall);

        // Authoritative StockReservation created
        $reservation = StockReservation::where('sale_id', $sale->id)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(15, $reservation->reserved_qty);
        $this->assertEquals(0, $reservation->fulfilled_qty);
        $this->assertEquals('ACTIVE', $reservation->status);
    }

    public function test_immediate_pos_sale_is_not_blocked_by_reservations_if_physical_stock_exists()
    {
        // Setup: Physical = 10, Allocated = 15 (shortfall = 5)
        $stock = StockLevel::where('product_id', $this->productFlour->id)
            ->where('warehouse_id', $this->branchMain->id)
            ->first();
        $stock->physical_stock = 10;
        $stock->allocated_stock = 15;
        $stock->save();

        // A walk-in buyer arrives and buys 4 units SUPPLIED IMMEDIATELY.
        // Even though allocated (15) > physical (10), physical (10) >= 4!
        // Business rule: Walk-in sales are NEVER blocked by customer reservations.
        $sale = $this->stockService->recordSale(
            [
                'totalAmount' => 4 * 45000.00,
                'paidAmount' => 4 * 45000.00,
                'cashAmount' => 4 * 45000.00,
                'customerName' => 'Walk-in Cash Buyer',
            ],
            [['productId' => $this->productFlour->id, 'quantity' => 4]],
            $this->branchMain->id,
            true, // SUPPLIED IMMEDIATELY
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        $stock->refresh();
        $this->assertEquals(6, $stock->physical_stock, "Physical stock decrements: 10 - 4 = 6.");
        $this->assertEquals(15, $stock->allocated_stock, "Allocated stock remains untouched at 15.");
        $this->assertEquals(9, $stock->reservation_shortfall, "Shortfall increases to max(0, 15 - 6) = 9.");
    }

    public function test_dispatch_unsupplied_sale_checks_physical_stock_and_decrements_both_dimensions()
    {
        // Unsupplied sale for 5 units of Sugar
        $sale = $this->stockService->recordSale(
            [
                'totalAmount' => 5 * 50000.00,
                'paidAmount' => 5 * 50000.00,
                'cashAmount' => 5 * 50000.00,
                'customerId' => $this->customerAlhaji->id,
                'customerName' => $this->customerAlhaji->name,
            ],
            [['productId' => $this->productSugar->id, 'quantity' => 5]],
            $this->branchMain->id,
            false,
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        $stock = StockLevel::where('product_id', $this->productSugar->id)
            ->where('warehouse_id', $this->branchMain->id)
            ->first();
        $this->assertEquals(20, $stock->physical_stock);
        $this->assertEquals(5, $stock->allocated_stock);

        // Fulfill unsupplied pickup
        \Illuminate\Support\Facades\Auth::login($this->adminUser);
        $this->stockService->dispatchUnsuppliedSale(
            $sale->id,
            $this->branchMain->id,
            $this->adminUser->id,
            $this->adminUser->name
        );

        $stock->refresh();
        $this->assertEquals(15, $stock->physical_stock, "Physical stock must decrement to 15.");
        $this->assertEquals(0, $stock->allocated_stock, "Allocated stock must decrement to 0.");

        $res = StockReservation::where('sale_id', $sale->id)->first();
        $this->assertEquals('FULFILLED', $res->status);
        $this->assertEquals(5, $res->fulfilled_qty);
    }

    public function test_unsupplied_sale_return_cancels_reservation_without_increasing_physical_stock()
    {
        // Unsupplied sale for 4 units of Sugar
        $sale = $this->stockService->recordSale(
            [
                'totalAmount' => 4 * 50000.00,
                'paidAmount' => 4 * 50000.00,
                'cashAmount' => 4 * 50000.00,
                'customerId' => $this->customerAlhaji->id,
                'customerName' => $this->customerAlhaji->name,
            ],
            [['productId' => $this->productSugar->id, 'quantity' => 4]],
            $this->branchMain->id,
            false,
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        $stock = StockLevel::where('product_id', $this->productSugar->id)
            ->where('warehouse_id', $this->branchMain->id)
            ->first();
        $physBefore = $stock->physical_stock;
        $allocBefore = $stock->allocated_stock;

        // Customer cancels unsupplied order before pickup
        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productSugar->id, 'quantity' => 4]],
            $this->branchMain->id,
            'CASH_REFUND',
            'Order cancelled before collection',
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        $stock->refresh();
        $this->assertEquals($physBefore, $stock->physical_stock, "Physical shelf count must NOT increase on unsupplied return.");
        $this->assertEquals($allocBefore - 4, $stock->allocated_stock, "Allocated stock must be released.");

        $res = StockReservation::where('sale_id', $sale->id)->first();
        $this->assertEquals('CANCELLED', $res->status);
        $this->assertEquals(4, $res->cancelled_qty);
    }

    // =========================================================================
    // 3. TENDER MATHEMATICS: STRICTLY CASH AND POS ONLY
    // =========================================================================

    public function test_checkout_rejects_electronic_overpayment_and_disburses_change_only_from_cash()
    {
        // 1. Electronic overpayment attempt (POS 60,000 for a 50,000 item)
        try {
            $this->accountingService->calculateCheckout(
                [['productId' => $this->productSugar->id, 'quantity' => 1]],
                ['cashAmount' => 0, 'posAmount' => 60000.00]
            );
            $this->fail("Should have rejected POS electronic overpayment.");
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("cannot exceed sale total amount", $e->getMessage());
        }

        // 2. Mixed Tender: Item 50,000. Paid with POS 30,000 + Cash 30,000 (Total 60,000).
        // Change is 10,000. Since Cash tendered is 30,000 >= 10,000 change, change is disbursed cleanly.
        // Retained Cash = 20,000. Retained POS = 30,000. Total Paid = 50,000.
        $calc = $this->accountingService->calculateCheckout(
            [['productId' => $this->productSugar->id, 'quantity' => 1]],
            ['cashAmount' => 30000.00, 'posAmount' => 30000.00]
        );

        $this->assertEquals(50000.00, $calc['grossTotal']);
        $this->assertEquals(60000.00, $calc['totalTendered']);
        $this->assertEquals(10000.00, $calc['changeAmount']);
        $this->assertEquals(50000.00, $calc['paidAmount']);
        $this->assertEquals(20000.00, $calc['retainedCash']);
        $this->assertEquals(30000.00, $calc['retainedPos']);
        $this->assertEquals(0.00, $calc['outstandingDebt']);
        $this->assertEquals('COMPLETED', $calc['status']);
    }

    // =========================================================================
    // 4. ACCOUNTING ENGINE, DEBT RECONCILIATION & RETURN CREDITS
    // =========================================================================

    public function test_sale_return_with_cash_refund_does_not_distort_customer_debt()
    {
        // Sale: 2 bags of Sugar = 100,000.
        // Customer pays 60,000 cash. Debt = 40,000.
        $sale = $this->stockService->recordSale(
            [
                'totalAmount' => 100000.00,
                'paidAmount' => 60000.00,
                'cashAmount' => 60000.00,
                'customerId' => $this->customerAlhaji->id,
                'customerName' => $this->customerAlhaji->name,
            ],
            [['productId' => $this->productSugar->id, 'quantity' => 2]],
            $this->branchMain->id,
            true,
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        $initialBalance = $this->accountingService->calculateInvoiceBalance($sale);
        $this->assertEquals(40000.00, $initialBalance);

        // Customer returns 1 bag (worth 50,000) and receives a CASH REFUND of 50,000.
        // Mathematical Proof:
        // Net Invoice = 100,000 - 50,000 = 50,000.
        // Inflow Payments = 60,000. Cash Refunds = 50,000.
        // Net Money Applied = 60,000 - 50,000 = 10,000.
        // Remaining Invoice Balance = 50,000 - 10,000 = 40,000!
        // Customer still owes 40,000 on the 1 bag of sugar they kept!
        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productSugar->id, 'quantity' => 1]],
            $this->branchMain->id,
            'CASH_REFUND',
            'Returned damaged bag for refund',
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        $updatedBalance = $this->accountingService->calculateInvoiceBalance($sale);
        $this->assertEquals(40000.00, $updatedBalance, "Customer debt must remain 40,000 on goods retained!");
    }

    public function test_reconcile_customer_debt_is_strictly_read_only_and_correct_debt_requires_authorization()
    {
        $this->customerAlhaji->total_debt = 99999.00; // Artificially tampered stored debt
        $this->customerAlhaji->save();

        // reconcileCustomerDebt must detect variance without mutating the database
        $report = $this->accountingService->reconcileCustomerDebt($this->customerAlhaji);
        $this->assertFalse($report['balanced']);
        $this->assertEquals(99999.00, $report['storedDebt']);
        $this->assertEquals(0.00, $report['derivedDebt']);

        // Verify customer in DB was NOT modified
        $this->customerAlhaji->refresh();
        $this->assertEquals(99999.00, $this->customerAlhaji->total_debt, "reconcileCustomerDebt must be read-only!");

        // Unauthorized user cannot correct debt
        $this->actingAs($this->cashierUser);
        try {
            $this->accountingService->correctCustomerDebt(
                $this->customerAlhaji,
                0.00,
                'Clearing tampered balance',
                $this->cashierUser->id,
                $this->cashierUser->name
            );
            $this->fail("Cashier should not have permission to correct debt.");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertStringContainsString("debt.correct", $e->getMessage());
        }

        // Admin corrects debt with mandatory audit reason
        $this->actingAs($this->adminUser);
        $correction = $this->accountingService->correctCustomerDebt(
            $this->customerAlhaji,
            0.00,
            'Administrative reconciliation approved by Chief Auditor',
            $this->adminUser->id,
            $this->adminUser->name
        );

        $this->assertEquals(99999.00, $correction['oldDebt']);
        $this->assertEquals(0.00, $correction['newDebt']);

        $this->customerAlhaji->refresh();
        $this->assertEquals(0.00, $this->customerAlhaji->total_debt);
    }

    public function test_drawer_cash_formula_separates_cash_from_pos_receipts_and_excludes_fake_markdowns()
    {
        // 1. Cash Sale of 50,000
        $sale = $this->stockService->recordSale(
            [
                'totalAmount' => 50000.00,
                'paidAmount' => 50000.00,
                'cashAmount' => 50000.00,
                'customerName' => 'Cash Customer',
            ],
            [['productId' => $this->productSugar->id, 'quantity' => 1]],
            $this->branchMain->id,
            true,
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        // 2. POS Card Sale of 45,000
        $sale2 = $this->stockService->recordSale(
            [
                'totalAmount' => 45000.00,
                'paidAmount' => 45000.00,
                'posAmount' => 45000.00,
                'customerName' => 'Card Customer',
            ],
            [['productId' => $this->productFlour->id, 'quantity' => 1]],
            $this->branchMain->id,
            true,
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        // Setup Alhaji debt balance of 50,000 to recover
        $this->customerAlhaji->total_debt = 50000.00;
        $this->customerAlhaji->save();

        // 3. Debt Payment via Cash: 10,000
        $this->stockService->recordCustomerPayment(
            $this->customerAlhaji->id,
            10000.00,
            'CASH',
            'REC-CASH-01',
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        // 4. Debt Payment via POS: 20,000
        $this->stockService->recordCustomerPayment(
            $this->customerAlhaji->id,
            20000.00,
            'POS',
            'REC-POS-01',
            $this->cashierUser->id,
            $this->cashierUser->name
        );

        $summary = $this->accountingService->getPeriodSummary(['period' => 'TODAY']);

        // Cash drawer should be: 50,000 (sale) + 10,000 (cash debt recovery) = 60,000.
        // It must NOT include the 45,000 card sale or 20,000 POS debt recovery!
        $this->assertEquals(50000.00, $summary['cashCollected']);
        $this->assertEquals(45000.00, $summary['posCollected']);
        $this->assertEquals(10000.00, $summary['cashDebtRecovered']);
        $this->assertEquals(20000.00, $summary['posDebtRecovered']);
        $this->assertEquals(60000.00, $summary['expectedCashInDrawer']);

        // Retail Inventory Valuation:
        // Sugar has 19 physical units * 50,000 = 950,000
        // Flour has 9 physical units * 45,000 = 405,000
        // Total retail inventory value = 1,355,000
        $this->assertEquals(1355000.00, $summary['retailInventoryValue']);
        // Cost Inventory Valuation strictly rejects synthetic 0.7 markdown:
        $this->assertEquals(0.00, $summary['costInventoryValue'], "Fake 0.7 retail fallback is strictly removed; evaluates to exact cost basis 0.00.");
    }

    // =========================================================================
    // 5. MASTER RECONCILIATION AUDIT ENGINE INVARIANT CHECK
    // =========================================================================

    public function test_master_reconciliation_audit_reports_balanced_with_shortfall_present()
    {
        // Intentionally create a reservation shortfall:
        // Flour: Physical = 5, Allocated = 8 (valid decoupled shortfall = 3)
        $stock = StockLevel::where('product_id', $this->productFlour->id)
            ->where('warehouse_id', $this->branchMain->id)
            ->first();
        $stock->physical_stock = 5;
        $stock->allocated_stock = 8;
        $stock->save();

        // Ensure customer debt is balanced
        $customers = Customer::all();
        foreach ($customers as $c) {
            $c->total_debt = $this->accountingService->calculateCustomerDebt($c->id);
            $c->save();
        }

        $audit = $this->accountingService->runReconciliationAudit();

        $this->assertEquals('BALANCED', $audit['overall']);
        $this->assertEquals(0, $audit['error_count']);
        $this->assertEquals('PASS', $audit['detailed']['inventory_availability_invariant']['status']);
        $this->assertEquals('PASS', $audit['detailed']['sale_totals_integrity']['status']);
        $this->assertEquals('PASS', $audit['detailed']['payment_ledger_integrity']['status']);
        $this->assertEquals('PASS', $audit['detailed']['customer_debt_reconciliation']['status']);
    }
}
