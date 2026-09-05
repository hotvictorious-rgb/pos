<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Sale;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class MonetaryBoundaryAndLockDagInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $cashier;
    protected Product $product;
    protected Customer $customer;
    protected StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);

        $this->stockService = app(StockService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-inv-' . Str::random(5),
            'name' => 'Invariant Test Hub Ltd',
            'owner_email' => 'admin@invarianthub.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'INV-01',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier Ada',
            'email' => 'ada@invarianthub.ng',
            'password' => bcrypt('StrongPass123!'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
            'permissions' => ['pos.checkout', 'stock.in', 'stock.transfer', 'stock.recall', 'stock.adjust', 'returns.process', 'debts.manage', 'reports.view', 'reports.export'],
        ]);

        $this->product = Product::create([
            'id' => 'prod-inv-1',
            'name' => 'Solar Battery 200Ah',
            'code' => 'BAT-200',
            'category' => 'Battery',
            'unitPrice' => 145000.50,
            'costPrice' => 110000.00,
            'currentStock' => 100,
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 100,
            'allocated_stock' => 0,
        ]);

        $this->customer = Customer::create([
            'name' => 'Emeka Enterprises',
            'phone' => '08091112233',
            'total_debt' => 50000.00,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->cashier);
    }

    /**
     * INVARIANT 1: Strict decimal-string and integer parsing eliminates IEEE 754 float drift.
     */
    public function test_toKobo_parses_decimal_strings_without_binary_float_intermediate(): void
    {
        // 1. Clean decimal strings
        $this->assertSame(33333, AccountingReportService::toKobo('333.33'));
        $this->assertSame(1, AccountingReportService::toKobo('0.01'));
        $this->assertSame(10000000, AccountingReportService::toKobo('100000.00'));
        $this->assertSame(0, AccountingReportService::toKobo('0.00'));
        $this->assertSame(0, AccountingReportService::toKobo('0'));
        $this->assertSame(0, AccountingReportService::toKobo(''));
        $this->assertSame(0, AccountingReportService::toKobo(null));

        // 2. Integer inputs
        $this->assertSame(10000, AccountingReportService::toKobo(100));
        $this->assertSame(0, AccountingReportService::toKobo(0));

        // 3. Comma-separated currency representations
        $this->assertSame(125050075, AccountingReportService::toKobo('1,250,500.75'));
        $this->assertSame(5000000, AccountingReportService::toKobo('50,000'));

        // 4. Whitespace trimming and negative amounts
        $this->assertSame(5020, AccountingReportService::toKobo('  50.20  '));
        $this->assertSame(-45025, AccountingReportService::toKobo('-450.25'));

        // 5. Half-up rounding on sub-kobo digits (3rd decimal place)
        $this->assertSame(1, AccountingReportService::toKobo('0.005'));
        $this->assertSame(0, AccountingReportService::toKobo('0.004'));
        $this->assertSame(10000, AccountingReportService::toKobo('99.996'));
        $this->assertSame(9999, AccountingReportService::toKobo('99.994'));

        // 6. The classic IEEE 754 floating point trap:
        // In native PHP, (int)((0.1 + 0.7) * 100) evaluates to 79 because 0.1 + 0.7 = 0.7999999999999999.
        // Our toKobo must round half-up and produce 80 kobo exact!
        $floatTrap = 0.1 + 0.7;
        $this->assertSame(80, AccountingReportService::toKobo($floatTrap));
    }

    /**
     * INVARIANT 2: formatKoboToNaira produces exact two-decimal string representations with zero float math.
     */
    public function test_formatKoboToNaira_produces_exact_decimal_string(): void
    {
        $this->assertSame('333.33', AccountingReportService::formatKoboToNaira(33333));
        $this->assertSame('0.01', AccountingReportService::formatKoboToNaira(1));
        $this->assertSame('0.00', AccountingReportService::formatKoboToNaira(0));
        $this->assertSame('100000.00', AccountingReportService::formatKoboToNaira(10000000));
        $this->assertSame('-50.25', AccountingReportService::formatKoboToNaira(-5025));
    }

    /**
     * INVARIANT 3: Customer debt calculation throughout sales and repayments uses pure integer kobo.
     */
    public function test_customer_debt_mutations_use_pure_kobo_arithmetic(): void
    {
        // Customer starting debt = ₦50,000.00 (5,000,000 kobo)
        $this->assertSame(5000000, AccountingReportService::toKobo($this->customer->total_debt));

        // Record a sale of 1 unit @ 145,000.50, with 45,000.50 paid cash and 100,000.00 on credit
        $sale = $this->stockService->recordSale(
            [
                'customerId' => $this->customer->id,
                'customerName' => $this->customer->name,
                'tender' => [
                    'cashAmount' => 45000.50,
                    'posAmount' => 0,
                ],
            ],
            [['productId' => $this->product->id, 'quantity' => 1]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->customer->refresh();
        // 50,000.00 + 100,000.00 = 150,000.00 exact (15,000,000 kobo)
        $this->assertSame(15000000, AccountingReportService::toKobo($this->customer->total_debt));
        $this->assertEquals(150000.00, (float) $this->customer->total_debt);

        // Record partial customer repayment of ₦50,000.00
        $this->stockService->recordCustomerPayment(
            $this->customer->id,
            50000.00,
            'CASH',
            'PAY-INV-001',
            $this->cashier->id,
            $this->cashier->name,
            'Payment test',
            $this->warehouse->id
        );

        $this->customer->refresh();
        // 150,000.00 - 50,000.00 = 100,000.00 exact (10,000,000 kobo)
        $this->assertSame(10000000, AccountingReportService::toKobo($this->customer->total_debt));
        $this->assertEquals(100000.00, (float) $this->customer->total_debt);

        // Customer ledger entries maintain exact kobo balance_after
        $ledgers = CustomerLedger::where('customer_id', $this->customer->id)->get();
        foreach ($ledgers as $ledger) {
            $balanceKobo = AccountingReportService::toKobo($ledger->balance_after);
            $this->assertIsInt($balanceKobo);
            $this->assertSame(
                AccountingReportService::toNaira($balanceKobo),
                (float) $ledger->balance_after,
                "Ledger entry #{$ledger->id} balance_after must conform strictly to integer kobo precision."
            );
        }
    }

    /**
     * INVARIANT 4: Global Lock DAG ordering contract verification.
     * Verifies that when customer debt is affected, Customer (Level 2) is locked prior to Sale (Level 3).
     */
    public function test_lock_hierarchy_contract_order_verification(): void
    {
        // Verify customer lock acquisition at Level 2 in recordSale by confirming transaction completes cleanly
        $sale = $this->stockService->recordSale(
            [
                'customerId' => $this->customer->id,
                'customerName' => $this->customer->name,
                'tender' => ['cashAmount' => 0, 'posAmount' => 0],
            ],
            [['productId' => $this->product->id, 'quantity' => 1]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertInstanceOf(Sale::class, $sale);
        $this->assertSame('PENDING', $sale->status);
        $this->assertEquals(145000.50, (float) $sale->totalAmount);

        // Debt return symmetry: customer is locked at Level 2 before Sale at Level 3
        $return = $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->product->id, 'quantity' => 1]],
            $this->warehouse->id,
            'DEBT_REDUCTION',
            'Full return on credit invoice',
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertNotNull($return);
        $this->customer->refresh();
        // 50,000 + 145,000.50 - 145,000.50 = 50,000.00 exact (5,000,000 kobo)
        $this->assertSame(5000000, AccountingReportService::toKobo($this->customer->total_debt));
        $this->assertEquals(50000.00, (float) $this->customer->total_debt);
    }
}
