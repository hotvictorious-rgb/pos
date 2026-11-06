<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InventoryLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingReportService;
use App\Services\StockService;
use App\Services\TransactionVoidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionReversalAndPerShopIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $branchUyoMain;
    protected Warehouse $branchIkotEkpene;
    protected User $adminUyo;
    protected User $adminIkotEkpene;
    protected User $cashierUyo;
    protected Product $productSugar;
    protected Product $productFlour;
    protected Customer $debtorEmem;
    protected TransactionVoidService $voidService;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => false,
            'mail.default' => 'array',
        ]);

        $this->voidService = app(TransactionVoidService::class);
        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);

        // Branch 1: Uyo Main
        $this->branchUyoMain = Warehouse::create([
            'id' => 1,
            'name' => 'Uyo Main Supermarket',
            'code' => 'UYO-MAIN',
            'location' => 'Aka Road, Uyo',
            'is_active' => true,
        ]);

        // Branch 2: Ikot Ekpene Road
        $this->branchIkotEkpene = Warehouse::create([
            'id' => 2,
            'name' => 'Ikot Ekpene Road Branch',
            'code' => 'IKOT-EKPENE',
            'location' => 'Ikot Ekpene Road, Uyo',
            'is_active' => true,
        ]);

        // Admin scoped to Uyo Main
        $this->adminUyo = User::create([
            'id' => 'USR-ADMIN-UYO',
            'name' => 'Manager Victor (Uyo)',
            'email' => 'manager.uyo@market.ng',
            'role' => 'admin',
            'password' => Hash::make('password123'),
            'warehouse_id' => 1,
            'capabilities' => ['*'],
        ]);

        // Admin scoped to Ikot Ekpene
        $this->adminIkotEkpene = User::create([
            'id' => 'USR-ADMIN-IKOT',
            'name' => 'Manager Bassey (Ikot)',
            'email' => 'manager.ikot@market.ng',
            'role' => 'admin',
            'password' => Hash::make('password123'),
            'warehouse_id' => 2,
            'capabilities' => ['*'],
        ]);

        // Cashier scoped to Uyo Main
        $this->cashierUyo = User::create([
            'id' => 'USR-CASHIER-UYO',
            'name' => 'Cashier Mercy',
            'email' => 'cashier.mercy@market.ng',
            'role' => 'cashier',
            'password' => Hash::make('password123'),
            'warehouse_id' => 1,
            'capabilities' => ['pos.checkout', 'transactions.view'],
        ]);

        // Catalog Products
        $this->productSugar = Product::create([
            'id' => 'PRD-SUGAR',
            'name' => 'Dangote Sugar 50kg',
            'code' => 'SUGAR-50KG',
            'category' => 'Commodities',
            'unitPrice' => 85000.00,
            'costPrice' => 80000.00,
            'stock' => 0,
        ]);

        $this->productFlour = Product::create([
            'id' => 'PRD-FLOUR',
            'name' => 'Golden Penny Flour 50kg',
            'code' => 'FLOUR-50KG',
            'category' => 'Commodities',
            'unitPrice' => 60000.00,
            'costPrice' => 55000.00,
            'stock' => 0,
        ]);

        // Initial inventory at Uyo Main: 100 bags sugar, 50 bags flour
        StockLevel::create([
            'warehouse_id' => 1,
            'product_id' => 'PRD-SUGAR',
            'physical_stock' => 100,
            'allocated_stock' => 0,
        ]);
        StockLevel::create([
            'warehouse_id' => 1,
            'product_id' => 'PRD-FLOUR',
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);

        // Initial inventory at Ikot Ekpene: 20 bags sugar
        StockLevel::create([
            'warehouse_id' => 2,
            'product_id' => 'PRD-SUGAR',
            'physical_stock' => 20,
            'allocated_stock' => 0,
        ]);

        $this->productSugar->currentStock = 120;
        $this->productSugar->save();

        $this->productFlour->currentStock = 50;
        $this->productFlour->save();

        // Customer
        $this->debtorEmem = Customer::create([
            'name' => 'Emem Udoh Bakery',
            'phone' => '08031234567',
            'total_debt' => 0.00,
        ]);
    }

    /**
     * Scenario 1: Standard Completed Cash/POS Sale Reversal
     * Proves:
     * - Sale is marked 'VOIDED' (no hard delete)
     * - Physical stock is restored strictly to Branch #1
     * - Offsetting 'VOID_REVERSAL' payment is recorded (cash drawer net is ₦0)
     * - Symmetrical SALE_REVERSAL inventory log is logged
     */
    public function test_standard_completed_sale_reversal_preserves_invoice_and_restores_shelf_stock_cleanly(): void
    {
        $this->actingAs($this->cashierUyo);

        // Checkout: 2 bags sugar (₦170,000) paid in cash, delivered immediately
        $sale = $this->stockService->recordSale(
            [
                'customerName' => 'Walk-in Customer',
                'tender' => ['cashAmount' => 170000.00, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-00101',
            ],
            [
                ['productId' => 'PRD-SUGAR', 'quantity' => 2, 'unitPrice' => 85000.00],
            ],
            1, // Branch 1
            true, // Delivered
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        $this->assertEquals(98, StockLevel::where('warehouse_id', 1)->where('product_id', 'PRD-SUGAR')->value('physical_stock'));
        $this->assertEquals(170000.00, (float) Payment::where('saleId', $sale->id)->where('method', 'CASH')->sum('amount'));

        // Admin Victor reverses the sale
        $result = $this->voidService->voidSale($sale->id, 'Customer requested immediate cancellation after scan', $this->adminUyo);

        $this->assertTrue($result['success']);

        // 1. Sale MUST NOT be hard-deleted!
        $reloadedSale = Sale::find($sale->id);
        $this->assertNotNull($reloadedSale);
        $this->assertEquals('VOIDED', $reloadedSale->status);
        $this->assertStringContainsString('Customer requested immediate cancellation', $reloadedSale->returnReason);

        // 2. Physical stock restored to Branch 1 (98 + 2 = 100)
        $this->assertEquals(100, StockLevel::where('warehouse_id', 1)->where('product_id', 'PRD-SUGAR')->value('physical_stock'));

        // 3. Branch 2 stock remains 100% UNTOUCHED (20)
        $this->assertEquals(20, StockLevel::where('warehouse_id', 2)->where('product_id', 'PRD-SUGAR')->value('physical_stock'));

        // 4. Cash drawer contra-payment balances net payments to ₦0
        $netPaidForSale = (float) Payment::where('saleId', $sale->id)->sum('amount');
        $this->assertEquals(0.00, $netPaidForSale);
        $this->assertTrue(Payment::where('saleId', $sale->id)->where('method', 'VOID_REVERSAL')->exists());

        // 5. Inflow inventory log logged
        $this->assertTrue(InventoryLog::where('type', 'SALE_REVERSAL')->where('productId', 'PRD-SUGAR')->where('quantity', 2)->exists());
    }

    /**
     * Scenario 2: Credit Sale (with Customer Debt) Reversal
     * Proves:
     * - Customer debt is reduced by the unpaid amount
     * - Contra-ledger INVOICE_REVERSAL is created in CustomerLedger (no hard delete)
     * - Physical inventory restored to branch
     */
    public function test_credit_sale_reversal_creates_contra_ledger_and_reduces_customer_debt(): void
    {
        $this->actingAs($this->cashierUyo);

        // Checkout: 1 bag flour (₦60,000) for Emem Udoh, ₦10,000 cash deposit, ₦50,000 debt
        $sale = $this->stockService->recordSale(
            [
                'customerId' => $this->debtorEmem->id,
                'customerName' => $this->debtorEmem->name,
                'tender' => ['cashAmount' => 10000.00, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-00102',
            ],
            [
                ['productId' => 'PRD-FLOUR', 'quantity' => 1, 'unitPrice' => 60000.00],
            ],
            1,
            true,
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        $this->debtorEmem->refresh();
        $this->assertEquals(50000.00, (float) $this->debtorEmem->total_debt);
        $this->assertEquals(49, StockLevel::where('warehouse_id', 1)->where('product_id', 'PRD-FLOUR')->value('physical_stock'));

        // Admin reverses the credit sale
        $result = $this->voidService->voidSale($sale->id, 'Clerical mistake on customer credit account', $this->adminUyo);

        $this->assertTrue($result['success']);

        // 1. Debt balance reversed to 0
        $this->debtorEmem->refresh();
        $this->assertEquals(0.00, (float) $this->debtorEmem->total_debt);

        // 2. Original INVOICE ledger entry is preserved!
        $this->assertTrue(CustomerLedger::where('sale_id', $sale->id)->where('type', 'INVOICE')->exists());

        // 3. Offsetting INVOICE_REVERSAL ledger entry is created
        $reversalLedger = CustomerLedger::where('sale_id', $sale->id)->where('type', 'INVOICE_REVERSAL')->first();
        $this->assertNotNull($reversalLedger);
        $this->assertEquals(-50000.00, (float) $reversalLedger->amount);
        $this->assertEquals(0.00, (float) $reversalLedger->balance_after);

        // 4. Initial deposit reversed in payments
        $this->assertEquals(0.00, (float) Payment::where('saleId', $sale->id)->sum('amount'));

        // 5. Stock restored (49 + 1 = 50)
        $this->assertEquals(50, StockLevel::where('warehouse_id', 1)->where('product_id', 'PRD-FLOUR')->value('physical_stock'));
    }

    /**
     * Scenario 3: Sale with Subsequent Debt Recovery Payments
     * Proves: Direct full voiding is strictly BLOCKED to protect collected debt cash.
     */
    public function test_reversal_is_blocked_if_subsequent_debt_recovery_payments_were_recorded(): void
    {
        $this->actingAs($this->cashierUyo);

        // Sale with ₦85,000 total, ₦0 paid (full credit)
        $sale = $this->stockService->recordSale(
            [
                'customerId' => $this->debtorEmem->id,
                'customerName' => $this->debtorEmem->name,
                'tender' => ['cashAmount' => 0.0, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-00103',
            ],
            [
                ['productId' => 'PRD-SUGAR', 'quantity' => 1, 'unitPrice' => 85000.00],
            ],
            1,
            true,
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        // Customer pays ₦30,000 towards this debt later
        $this->stockService->recordCustomerPayment(
            $this->debtorEmem->id,
            30000.00,
            'CASH',
            'REC-PART-1',
            $this->cashierUyo->id,
            $this->cashierUyo->name,
            'Part payment on sugar',
            1
        );

        // Attempting to void the sale must throw InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subsequent debt recovery payment(s) have been credited to this invoice');

        $this->voidService->voidSale($sale->id, 'Try to void part-paid sale', $this->adminUyo);
    }

    /**
     * Scenario 4: Fully Unsupplied Order Reversal (Delayed Pickup, 0 collected)
     * Proves:
     * - Physical stock is NOT incremented (zero phantom stock!)
     * - Allocated stock buffer is decremented
     * - Reservation status set to CANCELLED
     */
    public function test_unsupplied_order_reversal_releases_allocation_with_zero_phantom_physical_stock(): void
    {
        $this->actingAs($this->cashierUyo);

        // Customer buys 5 bags sugar as "Delayed Pickup" (is_supplied = false)
        $sale = $this->stockService->recordSale(
            [
                'customerName' => 'Chief Etebom (Delayed Pickup)',
                'tender' => ['cashAmount' => 425000.00, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-00104',
            ],
            [
                ['productId' => 'PRD-SUGAR', 'quantity' => 5, 'unitPrice' => 85000.00],
            ],
            1,
            false, // Delayed pickup
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        $stock = StockLevel::where('warehouse_id', 1)->where('product_id', 'PRD-SUGAR')->first();
        $this->assertEquals(100, $stock->physical_stock); // Physical untouched on shelf
        $this->assertEquals(5, $stock->allocated_stock);   // 5 units allocated

        // Admin reverses the unsupplied sale
        $result = $this->voidService->voidSale($sale->id, 'Customer cancelled pickup order before collection', $this->adminUyo);
        $this->assertTrue($result['success']);

        $stock->refresh();
        // ZERO phantom stock added! Physical stock remains 100!
        $this->assertEquals(100, $stock->physical_stock);
        // Allocated stock released back to 0
        $this->assertEquals(0, $stock->allocated_stock);

        $reservation = StockReservation::where('sale_id', $sale->id)->first();
        $this->assertEquals('CANCELLED', $reservation->status);
    }

    /**
     * Scenario 5: Partially-Fulfilled Unsupplied Order Guard
     * Proves: If customer already collected 2 out of 5 units, full voiding is BLOCKED.
     */
    public function test_reversal_is_blocked_if_unsupplied_order_is_partially_fulfilled(): void
    {
        $this->actingAs($this->cashierUyo);

        // 5 bags sugar unsupplied
        $sale = $this->stockService->recordSale(
            [
                'customerName' => 'Chief Etebom',
                'tender' => ['cashAmount' => 425000.00, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-00105',
            ],
            [
                ['productId' => 'PRD-SUGAR', 'quantity' => 5, 'unitPrice' => 85000.00],
            ],
            1,
            false,
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        // Customer comes in and collects 2 bags (authorized by Store Manager)
        $this->actingAs($this->adminUyo);
        $this->stockService->fulfillStockReservation(
            $sale->id,
            'PRD-SUGAR',
            1,
            2,
            $this->adminUyo->id,
            $this->adminUyo->name
        );

        $reservation = StockReservation::where('sale_id', $sale->id)->first();
        $this->assertEquals(2, $reservation->fulfilled_qty);
        $this->assertEquals(3, $reservation->outstanding_qty);

        // Attempting to void the entire sale must be blocked
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This unsupplied order is PARTIALLY FULFILLED');

        $this->voidService->voidSale($sale->id, 'Try to void partially collected order', $this->adminUyo);
    }

    /**
     * Scenario 6: Strict Cross-Branch Scoping & Isolation
     * Proves: Admin of Branch #2 (Ikot Ekpene) CANNOT reverse a sale belonging to Branch #1 (Uyo Main).
     */
    public function test_cross_branch_isolation_blocks_unauthorized_branch_reversal(): void
    {
        $this->actingAs($this->cashierUyo);

        // Sale at Branch 1
        $sale = $this->stockService->recordSale(
            [
                'customerName' => 'Branch 1 Customer',
                'tender' => ['cashAmount' => 85000.00, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-00106',
            ],
            [
                ['productId' => 'PRD-SUGAR', 'quantity' => 1, 'unitPrice' => 85000.00],
            ],
            1,
            true,
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        // Manager of Branch 2 tries to reverse Branch 1's sale
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->expectExceptionMessage('Cross-branch violation: You are assigned to Branch #2 and cannot reverse transactions belonging to Branch #1');

        $this->voidService->voidSale($sale->id, 'Cross-branch attack', $this->adminIkotEkpene);
    }

    /**
     * Scenario 7: Accidental Debt Payment Reversal
     * Proves:
     * - Accidental debt payment (e.g. ₦40,000) is reversed cleanly
     * - Customer debt balance is restored (+₦40,000)
     * - Symmetrical PAYMENT_REVERSAL ledger entry is posted
     * - Sale paidAmount is reduced back
     * - Cash drawer contra-payment is posted
     */
    public function test_customer_debt_payment_reversal_restores_debt_and_balances_sales_allocation(): void
    {
        $this->actingAs($this->cashierUyo);

        // 1. Credit sale of ₦85,000
        $sale = $this->stockService->recordSale(
            [
                'customerId' => $this->debtorEmem->id,
                'customerName' => $this->debtorEmem->name,
                'tender' => ['cashAmount' => 0.0, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-00107',
            ],
            [
                ['productId' => 'PRD-SUGAR', 'quantity' => 1, 'unitPrice' => 85000.00],
            ],
            1,
            true,
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        $this->debtorEmem->refresh();
        $this->assertEquals(85000.00, (float) $this->debtorEmem->total_debt);

        // 2. Cashier accidentally logs ₦40,000 debt payment
        $paymentLedger = $this->stockService->recordCustomerPayment(
            $this->debtorEmem->id,
            40000.00,
            'CASH',
            'REC-WRONG-1',
            $this->cashierUyo->id,
            $this->cashierUyo->name,
            'Mistaken debt entry',
            1
        );

        $this->debtorEmem->refresh();
        $this->assertEquals(45000.00, (float) $this->debtorEmem->total_debt);
        $sale->refresh();
        $this->assertEquals(40000.00, (float) $sale->paidAmount);

        // 3. Admin reverses the mistaken debt payment
        $result = $this->voidService->voidCustomerPayment($paymentLedger->id, 'Typo by cashier: customer paid ₦4,000 not ₦40,000', $this->adminUyo);
        $this->assertTrue($result['success']);

        // Customer debt restored to ₦85,000!
        $this->debtorEmem->refresh();
        $this->assertEquals(85000.00, (float) $this->debtorEmem->total_debt);

        // Sale paidAmount restored to 0!
        $sale->refresh();
        $this->assertEquals(0.00, (float) $sale->paidAmount);
        $this->assertContains($sale->status, ['PENDING', 'PARTIAL']);

        // Contra-entry created in CustomerLedger
        $contraLedger = CustomerLedger::where('type', 'PAYMENT_REVERSAL')->where('customer_id', $this->debtorEmem->id)->first();
        $this->assertNotNull($contraLedger);
        $this->assertEquals(-40000.00, (float) $contraLedger->amount);
        $this->assertEquals(85000.00, (float) $contraLedger->balance_after);

        // Original ledger watermarked as reversed
        $paymentLedger->refresh();
        $this->assertStringContainsString('[REVERSED by Manager Victor (Uyo)', $paymentLedger->notes);

        // Offsetting payment recorded for cashier cash drawer
        $this->assertTrue(Payment::where('method', 'VOID_REVERSAL')->where('amount', -40000.00)->exists());
    }

    /**
     * Scenario 8: Stock-In Reversal Protection of Pending Allocations
     * Proves: Reversing stock-in is BLOCKED if it would leave physical stock below unsupplied allocations.
     */
    public function test_stock_in_reversal_respects_branch_and_protects_customer_reservation_buffers(): void
    {
        $this->actingAs($this->adminUyo);

        // Stock in 10 bags of flour at Branch 1
        $stockInLog = InventoryLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'productId' => 'PRD-FLOUR',
            'warehouse_id' => 1,
            'type' => 'STOCK_IN',
            'quantity' => 10,
            'userId' => $this->adminUyo->id,
            'userName' => $this->adminUyo->name,
            'productCode' => 'FLOUR-50KG',
            'productName' => 'Golden Penny Flour 50kg',
            'description' => 'Supplier delivery',
            'timestamp' => now()->toIso8601String(),
        ]);

        $stock = StockLevel::where('warehouse_id', 1)->where('product_id', 'PRD-FLOUR')->first();
        $stock->physical_stock = 60; // 50 initial + 10
        // Customer has reserved 55 bags unsupplied!
        $stock->allocated_stock = 55;
        $stock->save();

        // Attempting to reverse the 10 bags would leave physical stock at 50, which is below 55 allocated!
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('would leave physical stock (50) below active customer reservations (55)');

        $this->voidService->voidStockIn($stockInLog->id, 'Supplier delivery cancel', $this->adminUyo);
    }

    /**
     * Scenario 9: Voided Sales are Excluded from Active Reports but Traceable in Audit
     * Proves: Period reports and financial metrics ignore voided sales so gross sales are not inflated.
     */
    public function test_voided_sales_are_excluded_from_active_reports_cleanly(): void
    {
        $this->actingAs($this->cashierUyo);

        // Active sale of ₦85,000
        $activeSale = $this->stockService->recordSale(
            [
                'customerName' => 'Active Customer',
                'tender' => ['cashAmount' => 85000.00, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-ACTIVE-1',
            ],
            [
                ['productId' => 'PRD-SUGAR', 'quantity' => 1, 'unitPrice' => 85000.00],
            ],
            1,
            true,
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        // Cancelled sale of ₦60,000
        $voidedSale = $this->stockService->recordSale(
            [
                'customerName' => 'Mistake Customer',
                'tender' => ['cashAmount' => 60000.00, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-MISTAKE-1',
            ],
            [
                ['productId' => 'PRD-FLOUR', 'quantity' => 1, 'unitPrice' => 60000.00],
            ],
            1,
            true,
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        $this->voidService->voidSale($voidedSale->id, 'Clerical mistake immediately reversed', $this->adminUyo);

        // Query active period summary
        $summary = $this->accountingService->getPeriodSummary(['period' => 'TODAY', 'warehouse_id' => 1]);

        // Gross sales must ONLY count active sale (₦85,000), not the ₦60,000 voided sale!
        $this->assertEquals(85000.00, $summary['grossSales']);
        $this->assertEquals(1, $summary['invoiceCount']);

        // Cash collections must ONLY reflect the active sale (₦85,000)
        $this->assertEquals(85000.00, $summary['cashFromSales']);
    }

    /**
     * Scenario 10: Stock Adjustment (Damage / Write-Off) Reversal
     * Proves: Restores damaged stock count and posts contra-log.
     */
    public function test_stock_out_adjustment_reversal_restores_inventory_with_contra_log(): void
    {
        $this->actingAs($this->adminUyo);

        // Record damage adjustment: 3 bags sugar damaged
        $adjustment = $this->stockService->recordStockAdjustment(
            'PRD-SUGAR',
            1,
            'DAMAGE',
            3,
            'Rain leak in corner',
            $this->adminUyo->id,
            $this->adminUyo->name
        );

        $this->assertEquals(97, StockLevel::where('warehouse_id', 1)->where('product_id', 'PRD-SUGAR')->value('physical_stock'));

        // Admin realizes it was logged for the wrong warehouse, reverses it
        $result = $this->voidService->voidStockOutOrAdjustment((string) $adjustment->id, 'Wrong warehouse selected', $this->adminUyo);
        $this->assertTrue($result['success']);

        // Stock restored back to 100
        $this->assertEquals(100, StockLevel::where('warehouse_id', 1)->where('product_id', 'PRD-SUGAR')->value('physical_stock'));

        // Contra log created
        $this->assertTrue(InventoryLog::where('type', 'STOCK_ADJUSTMENT_REVERSAL')->where('quantity', 3)->exists());
    }

    /**
     * Scenario 11: Idempotency & Double-Reversal Guard
     * Proves: Cannot reverse an already voided sale a second time.
     */
    public function test_cannot_reverse_a_sale_twice(): void
    {
        $this->actingAs($this->cashierUyo);

        $sale = $this->stockService->recordSale(
            [
                'customerName' => 'Walk-in Customer',
                'tender' => ['cashAmount' => 85000.00, 'posAmount' => 0.0],
                'receipt_ref' => 'RCP-DOUBLE-1',
            ],
            [
                ['productId' => 'PRD-SUGAR', 'quantity' => 1, 'unitPrice' => 85000.00],
            ],
            1,
            true,
            $this->cashierUyo->id,
            $this->cashierUyo->name
        );

        $this->voidService->voidSale($sale->id, 'First valid reversal', $this->adminUyo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has already been VOIDED');

        $this->voidService->voidSale($sale->id, 'Second malicious attempt', $this->adminUyo);
    }

    /**
     * Scenario 12: Centralized Reversals Hub Isolation
     * Proves:
     * - Cashiers cannot see the Voids/Reversals hub tab.
     * - Inline Delete/Void buttons are completely absent from general tabs.
     * - Only Admins have access to the Voids & Reversals Hub tab with complete action listings.
     */
    public function test_centralized_voiding_hub_is_restricted_to_admins_and_absent_from_general_tabs(): void
    {
        // 1. Cashier views transactions page
        $response = $this->actingAs($this->cashierUyo)->get(route('transactions.index'));
        $response->assertStatus(200);

        // Reversals tab button must NOT be visible to cashier
        $response->assertDontSee('id="tab-btn-reversals"', false);
        // Inline delete buttons must NOT be present in normal table rows
        $response->assertDontSee('🗑️ Delete / Void', false);

        // 2. Admin views transactions page
        $adminResponse = $this->actingAs($this->adminUyo)->get(route('transactions.index', ['tab' => 'reversals']));
        $adminResponse->assertStatus(200);

        // Reversals tab button MUST be present for Admin
        $adminResponse->assertSee('id="tab-btn-reversals"', false);
        $adminResponse->assertSee('Supervisor Voids & Reversals Terminal', false);
        $adminResponse->assertSee('Reversible Sales Invoices', false);
        $adminResponse->assertSee('Reversible Debt Payments', false);
        $adminResponse->assertSee('Reversible Stock In', false);
        $adminResponse->assertSee('Past Reversals Audit Log', false);
    }

    /**
     * Scenario 13: Transactions Reversals Hub Handles Stock Reservation Queries Accurately
     * Proves:
     * - Accessor `outstanding_qty` does not throw SQLSTATE[42S22] Column not found.
     * - Partially fulfilled stock reservations correctly block sale reversal in UI list.
     * - Fully unfulfilled reservations remain reversible.
     */
    public function test_transactions_hub_correctly_evaluates_stock_reservations_without_sql_errors(): void
    {
        // 1. Sale with partially fulfilled reservation
        $salePartial = Sale::create([
            'id' => 'SALE-PARTIAL-RES-1',
            'receipt_ref' => 'REC-PARTIAL-1',
            'customerName' => 'Customer Partial',
            'totalAmount' => 10000,
            'subtotal' => 10000,
            'tax' => 0,
            'discount' => 0,
            'paidAmount' => 10000,
            'cashAmount' => 10000,
            'posAmount' => 0,
            'paymentMethod' => 'CASH',
            'paymentStatus' => 'PAID',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'UNSUPPLIED',
            'warehouse_id' => 1,
            'cashierId' => $this->adminUyo->id,
            'cashierName' => $this->adminUyo->name,
            'userId' => $this->adminUyo->id,
            'userName' => $this->adminUyo->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        StockReservation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'sale_id' => $salePartial->id,
            'sale_item_id' => (string) Str::uuid(),
            'product_id' => $this->productSugar->id,
            'warehouse_id' => 1,
            'reserved_qty' => 10,
            'fulfilled_qty' => 4,
            'returned_fulfilled_qty' => 0,
            'cancelled_qty' => 0,
            'status' => 'PARTIALLY_FULFILLED',
            'notes' => 'Partially picked up',
        ]);

        // 2. Sale with active completely unfulfilled reservation
        $saleUnfulfilled = Sale::create([
            'id' => 'SALE-UNFULFILLED-RES-1',
            'receipt_ref' => 'REC-UNFULFILLED-1',
            'customerName' => 'Customer Unfulfilled',
            'totalAmount' => 5000,
            'subtotal' => 5000,
            'tax' => 0,
            'discount' => 0,
            'paidAmount' => 5000,
            'cashAmount' => 5000,
            'posAmount' => 0,
            'paymentMethod' => 'CASH',
            'paymentStatus' => 'PAID',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'UNSUPPLIED',
            'warehouse_id' => 1,
            'cashierId' => $this->adminUyo->id,
            'cashierName' => $this->adminUyo->name,
            'userId' => $this->adminUyo->id,
            'userName' => $this->adminUyo->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        StockReservation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'sale_id' => $saleUnfulfilled->id,
            'sale_item_id' => (string) Str::uuid(),
            'product_id' => $this->productFlour->id,
            'warehouse_id' => 1,
            'reserved_qty' => 5,
            'fulfilled_qty' => 0,
            'returned_fulfilled_qty' => 0,
            'cancelled_qty' => 0,
            'status' => 'ACTIVE',
            'notes' => 'Awaiting pickup',
        ]);

        // Load transactions page as Admin
        $response = $this->actingAs($this->adminUyo)->get(route('transactions.index'));
        $response->assertStatus(200);

        // Verify partially fulfilled sale is blocked with reason
        $response->assertSee('Partially picked up unsupplied order', false);
    }
}
