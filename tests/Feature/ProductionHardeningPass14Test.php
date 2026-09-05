<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\CustomerLedger;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass14Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $cashier;
    protected Product $productA;
    protected Product $productB;
    protected Customer $customer;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-pass14-concurrency',
            'name' => 'Pass 14 Concurrency Citadel Ltd',
            'owner_email' => 'admin@pass14citadel.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouse = Warehouse::create([
            'id' => 1401,
            'name' => 'Citadel Main Branch',
            'code' => 'WH-1401',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Citadel Cashier',
            'email' => 'cashier@pass14citadel.ng',
            'password' => bcrypt('StrongPass14!'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'is_active' => true,
            'disabled' => false,
            'permissions' => ['pos.checkout', 'stock.in', 'stock.transfer', 'stock.recall', 'stock.adjust', 'returns.process', 'debts.manage', 'reports.view', 'reports.export'],
        ]);

        $this->productA = Product::create([
            'id' => 'prod-pass14-aaa',
            'name' => 'Alpha Precision Widget',
            'code' => 'P14-AAA',
            'category' => 'Precision',
            'unitPrice' => 333.33,
            'costPrice' => 200.00,
            'currentStock' => 1000,
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $this->productB = Product::create([
            'id' => 'prod-pass14-bbb',
            'name' => 'Beta Precision Bolt',
            'code' => 'P14-BBB',
            'category' => 'Fasteners',
            'unitPrice' => 0.01,
            'costPrice' => 0.005,
            'currentStock' => 1000,
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 1000,
            'allocated_stock' => 0,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->productB->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 1000,
            'allocated_stock' => 0,
        ]);

        $this->customer = Customer::create([
            'name' => 'Alhaji Gambo Trading Co.',
            'phone' => '08031112233',
            'total_debt' => 100000.00,
            'tenant_id' => $this->tenant->id,
        ]);

        Auth::login($this->cashier);
    }

    /**
     * Test 1: Pure integer kobo domain arithmetic eliminates IEEE 754 float drift.
     */
    public function test_pure_integer_kobo_arithmetic_precision_eliminates_float_drift(): void
    {
        // 3 items @ 333.33 = 999.99 (99,999 kobo)
        // 1 item  @ 0.01   =   0.01 (     1 kobo)
        // Gross Total = 1,000.00 (100,000 kobo exactly)
        $items = [
            ['productId' => $this->productA->id, 'quantity' => 3],
            ['productId' => $this->productB->id, 'quantity' => 1],
        ];

        $tender = [
            'cashAmount' => 600.00,
            'posAmount'  => 400.00,
        ];

        $calc = $this->accountingService->calculateCheckout($items, $tender);

        $this->assertSame(100000, $calc['grossTotalKobo']);
        $this->assertSame(1000.00, $calc['grossTotal']);
        $this->assertSame(60000, $calc['cashTenderedKobo']);
        $this->assertSame(40000, $calc['posTenderedKobo']);
        $this->assertSame(100000, $calc['totalTenderedKobo']);
        $this->assertSame(0, $calc['changeAmountKobo']);
        $this->assertSame(100000, $calc['paidAmountKobo']);
        $this->assertSame(60000, $calc['retainedCashKobo']);
        $this->assertSame(40000, $calc['retainedPosKobo']);
        $this->assertSame(0, $calc['outstandingDebtKobo']);
        $this->assertSame('COMPLETED', $calc['status']);

        // Check utility conversions
        $this->assertSame(100000, AccountingReportService::toKobo('1000.00'));
        $this->assertSame(100000, AccountingReportService::toKobo(1000.00));
        $this->assertSame(100000, AccountingReportService::toKobo(1000));
        $this->assertSame(1000.00, AccountingReportService::toNaira(100000));
    }

    /**
     * Test 2: Calculate checkout strictly enforces kobo conservation and cash-backed change.
     */
    public function test_calculate_checkout_enforces_kobo_conservation_and_rejects_pos_overpayment(): void
    {
        $items = [
            ['productId' => $this->productA->id, 'quantity' => 1], // 333.33 (33333 kobo)
        ];

        // Attempt electronic overpayment: POS ₦400 > Gross ₦333.33
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Electronic payments.*cannot exceed sale total amount/i');

        $this->accountingService->calculateCheckout($items, [
            'cashAmount' => 0,
            'posAmount'  => 400.00,
        ]);
    }

    /**
     * Test 3: Customer debt serialization: concurrent debt increment + payment execution.
     */
    public function test_concurrent_customer_debt_incurrence_and_payment_serialization(): void
    {
        // Initial debt = ₦100,000.00
        $this->assertEquals(100000.00, (float) $this->customer->total_debt);

        // Product C: ₦50,000.00
        $productC = Product::create([
            'id' => 'prod-pass14-ccc',
            'name' => 'Gamma Engine Block',
            'code' => 'P14-CCC',
            'category' => 'Automotive',
            'unitPrice' => 50000.00,
            'costPrice' => 35000.00,
            'currentStock' => 50,
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $productC->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);

        // Transaction 1: Customer buys Gamma Engine Block on full credit (₦50,000 debt added)
        $sale1 = $this->stockService->recordSale(
            [
                'customerId' => $this->customer->id,
                'customerName' => $this->customer->name,
                'tender' => ['cashAmount' => 0, 'posAmount' => 0],
            ],
            [['productId' => $productC->id, 'quantity' => 1]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->customer->refresh();
        $this->assertEquals(150000.00, (float) $this->customer->total_debt);

        // Transaction 2: Customer makes a debt payment of ₦30,000.00
        $this->stockService->recordCustomerPayment(
            $this->customer->id,
            30000.00,
            'CASH',
            'PAY-PASS14-001',
            $this->cashier->id,
            $this->cashier->name,
            'Pass 14 debt reduction test',
            $this->warehouse->id
        );

        $this->customer->refresh();
        // 100,000 + 50,000 - 30,000 = 120,000.00 exact!
        $this->assertEquals(120000.00, (float) $this->customer->total_debt);

        // Verify CustomerLedger contains both events
        $ledgers = CustomerLedger::where('customer_id', $this->customer->id)->get();
        $this->assertCount(2, $ledgers);

        $invoiceLedger = $ledgers->where('type', 'INVOICE')->first();
        $this->assertNotNull($invoiceLedger);
        $this->assertEquals(50000.00, (float) $invoiceLedger->amount);
        $this->assertEquals(150000.00, (float) $invoiceLedger->balance_after);

        $paymentLedger = $ledgers->where('type', 'PAYMENT')->first();
        $this->assertNotNull($paymentLedger);
        $this->assertEquals(30000.00, (float) $paymentLedger->amount);
        $this->assertEquals(120000.00, (float) $paymentLedger->balance_after);
    }

    /**
     * Test 4: Sale return with DEBT_REDUCTION enforces Customer (Level 2) -> Sale (Level 3) -> Stock (Level 4).
     */
    public function test_record_sale_return_with_debt_reduction_reduces_customer_debt_correctly(): void
    {
        // Customer buys 2 units of Alpha Precision Widget on credit (2 * 333.33 = 666.66 debt)
        $sale = $this->stockService->recordSale(
            [
                'customerId' => $this->customer->id,
                'customerName' => $this->customer->name,
                'tender' => ['cashAmount' => 0, 'posAmount' => 0],
            ],
            [['productId' => $this->productA->id, 'quantity' => 2]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->customer->refresh();
        $expectedDebtBeforeReturn = 100000.00 + 666.66;
        $this->assertEquals($expectedDebtBeforeReturn, (float) $this->customer->total_debt);

        // Return 1 unit with DEBT_REDUCTION (reduces debt by 333.33)
        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productA->id, 'quantity' => 1]],
            $this->warehouse->id,
            'DEBT_REDUCTION',
            'Customer returned 1 defective widget',
            $this->cashier->id,
            $this->cashier->name
        );

        $this->customer->refresh();
        $expectedDebtAfterReturn = round($expectedDebtBeforeReturn - 333.33, 2);
        $this->assertEquals($expectedDebtAfterReturn, (float) $this->customer->total_debt);

        // Verify stock was restored
        $stock = StockLevel::where('product_id', $this->productA->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(999, $stock->physical_stock); // 1000 - 2 + 1

        // Verify return ledger entry
        $returnLedger = CustomerLedger::where('customer_id', $this->customer->id)
            ->where('type', 'RETURN_CREDIT')
            ->first();
        $this->assertNotNull($returnLedger);
        $this->assertEquals(333.33, (float) $returnLedger->amount);
        $this->assertEquals($expectedDebtAfterReturn, (float) $returnLedger->balance_after);
    }

    /**
     * Test 5: Walk-in sale with full cash payment acquires no Customer row locks and succeeds cleanly.
     */
    public function test_walk_in_sale_with_zero_debt_succeeds_without_customer_table_lock(): void
    {
        $sale = $this->stockService->recordSale(
            [
                'customerName' => 'Walk-in Customer',
                'tender' => ['cashAmount' => 333.33, 'posAmount' => 0],
            ],
            [['productId' => $this->productA->id, 'quantity' => 1]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertInstanceOf(Sale::class, $sale);
        $this->assertSame('COMPLETED', $sale->status);
        $this->assertNull($sale->customerId);
        $this->assertSame(0.0, (float) ($sale->totalAmount - $sale->paidAmount));

        // Ensure no CustomerLedger was created
        $this->assertDatabaseMissing('customer_ledgers', [
            'sale_id' => $sale->id,
        ]);
    }

    /**
     * Test 6: Deterministic monotonic lock ordering on multi-product unsupplied dispatch.
     */
    public function test_monotonic_lock_ordering_on_unsupplied_sale_and_dispatch(): void
    {
        // Create an unsupplied sale with product B and product A in reverse order
        $sale = $this->stockService->recordSale(
            [
                'customerName' => 'Buffer Client Ltd',
                'tender' => ['cashAmount' => 333.34, 'posAmount' => 0],
            ],
            [
                ['productId' => $this->productB->id, 'quantity' => 1], // P14-BBB (0.01)
                ['productId' => $this->productA->id, 'quantity' => 1], // P14-AAA (333.33)
            ],
            $this->warehouse->id,
            false, // Unsupplied
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertSame('UNSUPPLIED', $sale->deliveryStatus);

        // Dispatch the unsupplied sale (which iterates sorted by productId)
        $dispatched = $this->stockService->dispatchUnsuppliedSale(
            $sale->id,
            $this->warehouse->id,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertSame('DELIVERED', $dispatched->deliveryStatus);

        // Physical stocks decremented
        $stockA = StockLevel::where('product_id', $this->productA->id)->where('warehouse_id', $this->warehouse->id)->first();
        $stockB = StockLevel::where('product_id', $this->productB->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(999, $stockA->physical_stock);
        $this->assertEquals(999, $stockB->physical_stock);
        $this->assertEquals(0, $stockA->allocated_stock);
        $this->assertEquals(0, $stockB->allocated_stock);
    }
}
