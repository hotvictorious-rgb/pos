<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\SalesReturn;
use App\Models\InventoryLog;
use App\Models\Transfer;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Support\Str;

class AccountingIntegrityAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouseMain;
    protected Warehouse $warehouseSecond;
    protected User $admin;
    protected User $cashier;
    protected Product $prodRice;
    protected Product $prodOil;
    protected Product $prodSugar;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);
        config(['saas.super_admin_email' => 'superadmin@hysam.com']);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);

        // 1. Create Tenant
        $this->tenant = Tenant::create([
            'id' => 'tenant-accounting-matrix',
            'name' => 'Apex Mathematical Mart',
            'owner_email' => 'owner@apexmart.ng',
            'owner_phone' => '08031234567',
            'plan' => 'enterprise',
            'status' => 'active',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        // 2. Create Warehouses
        $this->warehouseMain = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Apex Central Branch',
            'code' => 'APX-01',
            'is_active' => true,
        ]);

        $this->warehouseSecond = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Apex Annex Branch',
            'code' => 'APX-02',
            'is_active' => true,
        ]);

        // 3. Create Users
        $this->admin = User::create([
            'id' => 'admin-apex-01',
            'tenant_id' => $this->tenant->id,
            'name' => 'Apex Admin',
            'email' => 'admin@apexmart.ng',
            'password' => bcrypt('AdminSecret#2026'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseMain->id,
        ]);

        $this->cashier = User::create([
            'id' => 'cashier-apex-01',
            'tenant_id' => $this->tenant->id,
            'name' => 'Apex Cashier',
            'email' => 'cashier@apexmart.ng',
            'password' => bcrypt('CashierSecret#2026'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseMain->id,
        ]);

        // 4. Create Products (Rice: ₦40,000, Oil: ₦15,000, Sugar: ₦2,500.50)
        $this->prodRice = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Royal Rice 50kg',
            'code' => 'RICE-50KG',
            'category' => 'Grains',
            'unitPrice' => 40000.00,
            'costPrice' => 32000.00,
            'currentStock' => 100,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $this->prodOil = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Golden Vegetable Oil 5L',
            'code' => 'OIL-5L',
            'category' => 'Cooking',
            'unitPrice' => 15000.00,
            'costPrice' => 11500.00,
            'currentStock' => 100,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $this->prodSugar = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Granulated Sugar 1kg',
            'code' => 'SUGAR-1KG',
            'category' => 'Groceries',
            'unitPrice' => 2500.50,
            'costPrice' => 1900.00,
            'currentStock' => 100,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        // Seed stock levels at Main Branch
        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->prodRice->id,
            'warehouse_id' => $this->warehouseMain->id,
            'physical_stock' => 100,
            'allocated_stock' => 0,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->prodOil->id,
            'warehouse_id' => $this->warehouseMain->id,
            'physical_stock' => 100,
            'allocated_stock' => 0,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->prodSugar->id,
            'warehouse_id' => $this->warehouseMain->id,
            'physical_stock' => 100,
            'allocated_stock' => 0,
        ]);
    }

    // =========================================================================
    // SECTION 1: SALES & TENDER INTEGRITY (CASH & POS ONLY, CHANGE & SPLITS)
    // =========================================================================

    public function test_scenario_01_to_10_sales_tender_variations_and_change_equations()
    {
        // Scenario 1: Exact Cash Tender (₦40,000 Rice, tenders ₦40,000 cash)
        $sale1 = $this->stockService->recordSale(
            ['cashAmount' => 40000.00, 'posAmount' => 0.00],
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(40000.00, $sale1->totalAmount);
        $this->assertEquals(40000.00, $sale1->paidAmount);
        $this->assertEquals(40000.00, $sale1->cashAmount);
        $this->assertEquals(0.00, $sale1->posAmount);
        $this->assertEquals(0.00, $sale1->changeAmount);
        $this->assertEquals('COMPLETED', $sale1->status);
        $this->assertEquals(1, Payment::where('saleId', $sale1->id)->count());
        $this->assertEquals(40000.00, Payment::where('saleId', $sale1->id)->where('method', 'CASH')->sum('amount'));

        // Scenario 2: Cash Overpayment (₦40,000 Rice, tenders ₦50,000 cash -> ₦10,000 change)
        $sale2 = $this->stockService->recordSale(
            ['cashAmount' => 50000.00, 'posAmount' => 0.00],
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(40000.00, $sale2->totalAmount);
        $this->assertEquals(50000.00, $sale2->tenderedAmount);
        $this->assertEquals(10000.00, $sale2->changeAmount);
        $this->assertEquals(40000.00, $sale2->paidAmount);
        $this->assertEquals(40000.00, $sale2->cashAmount);
        $this->assertEquals('COMPLETED', $sale2->status);
        $this->assertEquals(40000.00, Payment::where('saleId', $sale2->id)->where('method', 'CASH')->sum('amount'));

        // Scenario 3: Cash Partial Underpayment (₦40,000 Rice, tenders ₦25,000 cash -> PARTIAL, ₦15,000 balance)
        $sale3 = $this->stockService->recordSale(
            ['cashAmount' => 25000.00, 'posAmount' => 0.00],
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(40000.00, $sale3->totalAmount);
        $this->assertEquals(25000.00, $sale3->paidAmount);
        $this->assertEquals(25000.00, $sale3->cashAmount);
        $this->assertEquals(0.00, $sale3->changeAmount);
        $this->assertEquals('PARTIAL', $sale3->status);
        $this->assertEquals(25000.00, Payment::where('saleId', $sale3->id)->where('method', 'CASH')->sum('amount'));

        // Scenario 4: Exact POS Tender (₦15,000 Oil, tenders ₦15,000 POS)
        $sale4 = $this->stockService->recordSale(
            ['cashAmount' => 0.00, 'posAmount' => 15000.00],
            [['productId' => $this->prodOil->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(15000.00, $sale4->totalAmount);
        $this->assertEquals(15000.00, $sale4->paidAmount);
        $this->assertEquals(0.00, $sale4->cashAmount);
        $this->assertEquals(15000.00, $sale4->posAmount);
        $this->assertEquals(0.00, $sale4->changeAmount);
        $this->assertEquals('COMPLETED', $sale4->status);
        $this->assertEquals(15000.00, Payment::where('saleId', $sale4->id)->where('method', 'POS')->sum('amount'));

        // Scenario 5: POS Partial Underpayment (₦15,000 Oil, tenders ₦10,000 POS -> PARTIAL)
        $sale5 = $this->stockService->recordSale(
            ['cashAmount' => 0.00, 'posAmount' => 10000.00],
            [['productId' => $this->prodOil->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(15000.00, $sale5->totalAmount);
        $this->assertEquals(10000.00, $sale5->paidAmount);
        $this->assertEquals(10000.00, $sale5->posAmount);
        $this->assertEquals('PARTIAL', $sale5->status);

        // Scenario 6: Mixed Exact Tender (₦40,000 Rice + ₦15,000 Oil = ₦55,000; tenders Cash ₦30k + POS ₦25k)
        $sale6 = $this->stockService->recordSale(
            ['cashAmount' => 30000.00, 'posAmount' => 25000.00],
            [
                ['productId' => $this->prodRice->id, 'quantity' => 1],
                ['productId' => $this->prodOil->id, 'quantity' => 1],
            ],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(55000.00, $sale6->totalAmount);
        $this->assertEquals(55000.00, $sale6->paidAmount);
        $this->assertEquals(30000.00, $sale6->cashAmount);
        $this->assertEquals(25000.00, $sale6->posAmount);
        $this->assertEquals(0.00, $sale6->changeAmount);
        $this->assertEquals(2, Payment::where('saleId', $sale6->id)->count());
        $this->assertEquals(30000.00, Payment::where('saleId', $sale6->id)->where('method', 'CASH')->sum('amount'));
        $this->assertEquals(25000.00, Payment::where('saleId', $sale6->id)->where('method', 'POS')->sum('amount'));

        // Scenario 7: Mixed Overpayment with Cash (₦55,000 total; tenders POS ₦20,000 + Cash ₦40,000 = ₦60k -> ₦5k Cash change)
        $sale7 = $this->stockService->recordSale(
            ['cashAmount' => 40000.00, 'posAmount' => 20000.00],
            [
                ['productId' => $this->prodRice->id, 'quantity' => 1],
                ['productId' => $this->prodOil->id, 'quantity' => 1],
            ],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(55000.00, $sale7->totalAmount);
        $this->assertEquals(60000.00, $sale7->tenderedAmount);
        $this->assertEquals(5000.00, $sale7->changeAmount);
        $this->assertEquals(55000.00, $sale7->paidAmount);
        $this->assertEquals(35000.00, $sale7->cashAmount); // 40k tendered - 5k change
        $this->assertEquals(20000.00, $sale7->posAmount);
        $this->assertEquals(2, Payment::where('saleId', $sale7->id)->count());
        $this->assertEquals(35000.00, Payment::where('saleId', $sale7->id)->where('method', 'CASH')->sum('amount'));
        $this->assertEquals(20000.00, Payment::where('saleId', $sale7->id)->where('method', 'POS')->sum('amount'));

        // Scenario 8: Mixed Underpayment (₦55,000 total; tenders Cash ₦20,000 + POS ₦15,000 = ₦35,000 -> PARTIAL)
        $sale8 = $this->stockService->recordSale(
            ['cashAmount' => 20000.00, 'posAmount' => 15000.00],
            [
                ['productId' => $this->prodRice->id, 'quantity' => 1],
                ['productId' => $this->prodOil->id, 'quantity' => 1],
            ],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(55000.00, $sale8->totalAmount);
        $this->assertEquals(35000.00, $sale8->paidAmount);
        $this->assertEquals('PARTIAL', $sale8->status);

        // Scenario 9: Zero-Payment Credit Sale (tenders ₦0 -> CREDIT status, 0 payment records)
        $customerCredit = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Credit Customer Alhaji',
            'phone' => '08011223399',
            'total_debt' => 0.0,
        ]);

        $sale9 = $this->stockService->recordSale(
            [
                'cashAmount' => 0.00,
                'posAmount' => 0.00,
                'customerId' => $customerCredit->id,
                'customerName' => $customerCredit->name,
            ],
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(40000.00, $sale9->totalAmount);
        $this->assertEquals(0.00, $sale9->paidAmount);
        $this->assertEquals('PENDING', $sale9->status);
        $this->assertEquals(0, Payment::where('saleId', $sale9->id)->count());
        $this->assertEquals(40000.00, $customerCredit->fresh()->total_debt);

        // Scenario 10: Duplicate SKU Line Consolidation
        $sale10 = $this->stockService->recordSale(
            ['cashAmount' => 80000.00, 'posAmount' => 0.00],
            [
                ['productId' => $this->prodRice->id, 'quantity' => 1],
                ['productId' => $this->prodRice->id, 'quantity' => 1], // Same SKU passed twice
            ],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals(80000.00, $sale10->totalAmount);
        $this->assertEquals(1, $sale10->items->count(), "Duplicate lines must consolidate to a single line item.");
        $this->assertEquals(2, $sale10->items->first()->quantity);
    }

    public function test_scenario_11_to_20_fraud_neutralization_and_tender_restrictions()
    {
        // Scenario 11: Electronic Overpayment Rejection (POS ₦50,000 for ₦40,000 sale)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("cannot exceed sale total amount");

        $this->stockService->recordSale(
            ['cashAmount' => 0.00, 'posAmount' => 50000.00],
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
    }

    public function test_scenario_12_client_forged_totals_neutralized()
    {
        // Scenario 12: Client sends totalAmount = 100 and paidAmount = 100 for 40,000 item
        $sale = $this->stockService->recordSale(
            [
                'totalAmount' => 100.00,
                'paidAmount' => 100.00,
                'cashAmount' => 40000.00,
                'posAmount' => 0.00,
            ],
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertEquals(40000.00, $sale->totalAmount);
        $this->assertEquals(40000.00, $sale->paidAmount);
        $this->assertEquals('COMPLETED', $sale->status);
    }

    public function test_scenario_13_wholesale_negotiated_pricing_supported()
    {
        // Scenario 13: Wholesale sale where worker negotiated price from ₦40,000 to ₦38,000
        $sale = $this->stockService->recordSale(
            [
                'sale_type' => 'WHOLESALE',
                'cashAmount' => 38000.00,
                'posAmount' => 0.00,
            ],
            [['productId' => $this->prodRice->id, 'quantity' => 1, 'unitPrice' => 38000.00]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertEquals(38000.00, $sale->totalAmount);
        $this->assertEquals(38000.00, $sale->paidAmount);
        $this->assertEquals(38000.00, $sale->items->first()->unitPrice);
    }

    public function test_scenario_14_to_20_kobo_cents_precision_and_tender_conservation()
    {
        // Sugar is ₦2,500.50. Buying 3 units = ₦7,501.50
        // Tendering Cash ₦5,000 + POS ₦2,501.50 = ₦7,501.50
        $sale = $this->stockService->recordSale(
            ['cashAmount' => 5000.00, 'posAmount' => 2501.50],
            [['productId' => $this->prodSugar->id, 'quantity' => 3]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertEquals(7501.50, $sale->totalAmount);
        $this->assertEquals(7501.50, $sale->paidAmount);
        $this->assertEquals(5000.00, $sale->cashAmount);
        $this->assertEquals(2501.50, $sale->posAmount);

        $payments = Payment::where('saleId', $sale->id)->get();
        $this->assertEquals(2, $payments->count());
        $this->assertEquals(7501.50, round($payments->sum('amount'), 2));
    }

    // =========================================================================
    // SECTION 2: DEBT, RECEIVABLES & FIFO INVOICE REPAYMENTS (CASH & POS ONLY)
    // =========================================================================

    public function test_scenario_21_to_35_debt_fifo_allocation_and_tender_verification()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Nkechi Stores',
            'phone' => '08099884433',
            'total_debt' => 0.0,
        ]);

        // Invoice 1: Rice ₦40,000, paid ₦15,000 Cash -> Outstanding ₦25,000
        $sale1 = $this->stockService->recordSale(
            [
                'customerId' => $customer->id,
                'customerName' => $customer->name,
                'cashAmount' => 15000.00,
                'posAmount' => 0.00,
            ],
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        // Invoice 2: Oil ₦15,000, paid ₦5,000 POS -> Outstanding ₦10,000
        $sale2 = $this->stockService->recordSale(
            [
                'customerId' => $customer->id,
                'customerName' => $customer->name,
                'cashAmount' => 0.00,
                'posAmount' => 5000.00,
            ],
            [['productId' => $this->prodOil->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        // Customer debt must equal exactly ₦25,000 + ₦10,000 = ₦35,000
        $customer->refresh();
        $this->assertEquals(35000.00, $customer->total_debt);

        // Repayment 1: Customer pays ₦30,000 via POS (strictly POS tender)
        // FIFO Rule: Must completely pay off Invoice 1 (₦25,000) and apply ₦5,000 to Invoice 2
        $ledger1 = $this->stockService->recordCustomerPayment(
            $customer->id,
            30000.00,
            'POS',
            'POS-REF-30000',
            $this->cashier->id,
            $this->cashier->name
        );

        $customer->refresh();
        $this->assertEquals(5000.00, $customer->total_debt);
        $this->assertEquals(5000.00, $ledger1->balance_after);

        $sale1->refresh();
        $this->assertEquals(40000.00, $sale1->paidAmount);
        $this->assertEquals('COMPLETED', $sale1->status);

        $sale2->refresh();
        $this->assertEquals(10000.00, $sale2->paidAmount);
        $this->assertEquals('PARTIAL', $sale2->status);

        // Granular payment records created on the sales
        $this->assertEquals(25000.00, Payment::where('saleId', $sale1->id)->where('method', 'POS')->sum('amount'));
        $this->assertEquals(10000.00, Payment::where('saleId', $sale2->id)->where('method', 'POS')->sum('amount'));
        $this->assertEquals(2, Payment::where('saleId', $sale2->id)->where('method', 'POS')->count());

        // Repayment 2: Customer pays remaining ₦5,000 via CASH
        $ledger2 = $this->stockService->recordCustomerPayment(
            $customer->id,
            5000.00,
            'CASH',
            'CSH-REF-5000',
            $this->cashier->id,
            $this->cashier->name
        );

        $customer->refresh();
        $this->assertEquals(0.00, $customer->total_debt);
        $this->assertEquals(0.00, $ledger2->balance_after);

        $sale2->refresh();
        $this->assertEquals(15000.00, $sale2->paidAmount);
        $this->assertEquals('COMPLETED', $sale2->status);

        // Transfer rejection on debt payment
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Debt payment method must be either 'CASH' or 'POS'");

        $this->stockService->recordCustomerPayment(
            $customer->id,
            1000.00,
            'TRANSFER',
            'ILLEGAL-TRF',
            $this->cashier->id,
            $this->cashier->name
        );
    }

    // =========================================================================
    // SECTION 3: SALES RETURNS, REFUNDS & LEDGER ADJUSTMENTS
    // =========================================================================

    public function test_scenario_36_to_55_sales_returns_and_refund_invariants()
    {
        // 1. Setup sale of 3 bags of Rice (₦120,000), paid ₦80,000 Cash, ₦40,000 Debt
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Emeka Wholesale',
            'phone' => '08022339900',
            'total_debt' => 0.0,
        ]);

        $sale = $this->stockService->recordSale(
            [
                'customerId' => $customer->id,
                'customerName' => $customer->name,
                'cashAmount' => 80000.00,
                'posAmount' => 0.00,
            ],
            [['productId' => $this->prodRice->id, 'quantity' => 3]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertEquals(120000.00, $sale->totalAmount);
        $this->assertEquals(80000.00, $sale->paidAmount);
        $this->assertEquals(40000.00, $customer->fresh()->total_debt);

        $shelfStockBefore = StockLevel::where('product_id', $this->prodRice->id)->where('warehouse_id', $this->warehouseMain->id)->first()->physical_stock;

        // Return 1: Customer returns 1 bag with CASH_REFUND (₦40,000 cash returned)
        $return1 = $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            'CASH_REFUND',
            'Customer ordered wrong size',
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertEquals(40000.00, $return1->refundAmount);
        $shelfStockAfter1 = StockLevel::where('product_id', $this->prodRice->id)->where('warehouse_id', $this->warehouseMain->id)->first()->physical_stock;
        $this->assertEquals($shelfStockBefore + 1, $shelfStockAfter1, "Delivered return must restore physical shelf stock.");

        $sale->refresh();
        $this->assertEquals(40000.00, $sale->paidAmount); // 80k paid - 40k cash refund
        // Negative payment record created for till reconciliation
        $this->assertEquals(-40000.00, Payment::where('saleId', $sale->id)->where('method', 'REFUND_CASH')->sum('amount'));

        // Return 2: Customer returns 1 bag with DEBT_REDUCTION (reduces invoice total & customer debt by ₦40,000)
        $return2 = $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            'DEBT_REDUCTION',
            'Customer damaged sack',
            $this->cashier->id,
            $this->cashier->name
        );

        $sale->refresh();
        $this->assertEquals(80000.00, $sale->totalAmount); // 120k original - 40k debt reduction
        $this->assertEquals(40000.00, $sale->paidAmount);
        $this->assertEquals(0.00, $customer->fresh()->total_debt, "Debt reduction must eliminate customer debt.");

        // Return 3: Attempting to return 2 more bags (only 1 remaining eligible out of original 3)
        try {
            $this->stockService->recordSaleReturn(
                $sale->id,
                [['productId' => $this->prodRice->id, 'quantity' => 2]],
                $this->warehouseMain->id,
                'CASH_REFUND',
                'Exceeding remaining',
                $this->cashier->id,
                $this->cashier->name
            );
            $this->fail("Should have rejected return exceeding sold quantity.");
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("Remaining eligible: 1", $e->getMessage());
        }

        // Return 4: Unsupplied Return releases allocated stock
        $saleUnsupplied = $this->stockService->recordSale(
            ['cashAmount' => 40000.00, 'posAmount' => 0.00],
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            false, // Unsupplied
            $this->cashier->id,
            $this->cashier->name
        );

        $stockRecord = StockLevel::where('product_id', $this->prodRice->id)->where('warehouse_id', $this->warehouseMain->id)->first();
        $allocBefore = $stockRecord->allocated_stock;
        $physBefore = $stockRecord->physical_stock;

        $this->stockService->recordSaleReturn(
            $saleUnsupplied->id,
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            'CASH_REFUND',
            'Unsupplied cancellation',
            $this->cashier->id,
            $this->cashier->name
        );

        $stockRecord->refresh();
        $this->assertEquals($allocBefore - 1, $stockRecord->allocated_stock, "Unsupplied return must release allocation.");
        $this->assertEquals($physBefore, $stockRecord->physical_stock, "Unsupplied return must not increase physical shelf count.");
    }

    // =========================================================================
    // SECTION 4: INVENTORY CONSERVATION & TRANSFER INVARIANTS
    // =========================================================================

    public function test_scenario_56_to_75_inventory_invariants_and_transfer_rules()
    {
        $stock = StockLevel::where('product_id', $this->prodOil->id)->where('warehouse_id', $this->warehouseMain->id)->first();
        $stock->physical_stock = 20;
        $stock->allocated_stock = 15; // Only 5 available! (20 - 15 = 5)
        $stock->save();

        // 1. Attempting transfer of 8 units when available is only 5
        \Illuminate\Support\Facades\Auth::login($this->admin);
        try {
            $this->stockService->initiateTransfer(
                $this->warehouseMain->id,
                $this->warehouseSecond->id,
                [['productId' => $this->prodOil->id, 'quantity' => 8]],
                'Fast Cargo',
                $this->admin->id,
                $this->admin->name,
                'TRF-FAIL-01'
            );
            $this->fail("Should have rejected transfer exceeding available stock.");
        } catch (\App\Exceptions\InsufficientStockException $e) {
            $this->assertStringContainsString("Cannot dispatch transfer", $e->getMessage());
        }

        // 2. Attempting stock adjustment write-off of 8 units when available is 5
        try {
            $this->stockService->recordStockAdjustment(
                $this->prodOil->id,
                $this->warehouseMain->id,
                'DAMAGED',
                8,
                'Damaged cans',
                $this->admin->id,
                $this->admin->name
            );
            $this->fail("Should have rejected write-off exceeding available stock.");
        } catch (\App\Exceptions\InsufficientStockException $e) {
            $this->assertStringContainsString("Cannot record stock write-off", $e->getMessage());
        }

        // 3. Valid transfer of 5 units succeeds
        $transfer = $this->stockService->initiateTransfer(
            $this->warehouseMain->id,
            $this->warehouseSecond->id,
            [['productId' => $this->prodOil->id, 'quantity' => 5]],
            'Apex Van',
            $this->admin->id,
            $this->admin->name,
            'TRF-OK-01'
        );

        $stock->refresh();
        $this->assertEquals(15, $stock->physical_stock);
        $this->assertEquals(15, $stock->allocated_stock);

        // 4. Destination arrival receives 5 units
        $this->stockService->receiveTransfer(
            $transfer->id,
            [$this->prodOil->id => 5],
            $this->admin->id,
            $this->admin->name
        );

        $destStock = StockLevel::where('product_id', $this->prodOil->id)->where('warehouse_id', $this->warehouseSecond->id)->first();
        $this->assertEquals(5, $destStock->physical_stock);

        // 5. Cross-tenant transfer strictly forbidden
        $otherTenant = Tenant::create([
            'id' => 'tenant-foreign-mart',
            'name' => 'Foreign Mart',
            'owner_email' => 'owner@foreign.ng',
            'plan' => 'basic',
            'status' => 'active',
        ]);
        $foreignWarehouse = Warehouse::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Warehouse',
            'code' => 'FOR-01',
            'is_active' => true,
        ]);

        try {
            $this->stockService->initiateTransfer(
                $this->warehouseMain->id,
                $foreignWarehouse->id,
                [['productId' => $this->prodOil->id, 'quantity' => 1]],
                'Smuggler',
                $this->admin->id,
                $this->admin->name,
                'TRF-ILLEGAL-CROSS'
            );
            $this->fail("Should have blocked cross-tenant transfer.");
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("Cross-tenant stock transfers are strictly forbidden", $e->getMessage());
        }
    }

    // =========================================================================
    // SECTION 5: UNIFIED REPORTING, DATE PRESETS & EXPORT PARITY (76 - 100)
    // =========================================================================

    public function test_scenario_76_to_100_unified_reporting_engine_filters_and_export_parity()
    {
        // 1. Create dated transactions for testing all presets
        $saleToday = $this->stockService->recordSale(
            ['cashAmount' => 40000.00, 'posAmount' => 0.00],
            [['productId' => $this->prodRice->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $saleYesterday = $this->stockService->recordSale(
            ['cashAmount' => 0.00, 'posAmount' => 15000.00],
            [['productId' => $this->prodOil->id, 'quantity' => 1]],
            $this->warehouseMain->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );
        $saleYesterday->created_at = now()->subDay()->startOfDay()->addHours(10);
        $saleYesterday->createdAt = $saleYesterday->created_at->toIso8601String();
        $saleYesterday->save();

        Payment::where('saleId', $saleYesterday->id)->update([
            'created_at' => $saleYesterday->created_at,
            'timestamp' => $saleYesterday->created_at->toIso8601String(),
        ]);

        // Verify Date Presets
        $presets = [
            'TODAY', 'YESTERDAY', 'THIS_WEEK', 'LAST_WEEK', 'THIS_MONTH', 
            'LAST_MONTH', 'THIS_QUARTER', 'LAST_QUARTER', 'YEAR_TO_DATE', 
            'THIS_YEAR', 'LAST_YEAR', 'ALL'
        ];

        foreach ($presets as $preset) {
            $range = $this->accountingService->resolveDateRange($preset);
            $this->assertArrayHasKey('from', $range);
            $this->assertArrayHasKey('to', $range);
            $this->assertArrayHasKey('preset', $range);

            $summary = $this->accountingService->getPeriodSummary(['date_preset' => $preset]);
            $this->assertArrayHasKey('gross_revenue', $summary);
            $this->assertArrayHasKey('cash_collected', $summary);
            $this->assertArrayHasKey('pos_collected', $summary);
            $this->assertArrayHasKey('net_payments', $summary);
            $this->assertArrayHasKey('inventory_cost_valuation', $summary);
            $this->assertArrayHasKey('inventory_retail_valuation', $summary);
        }

        // Verify CSV vs JSON parity via ReportController
        $responseCsv = $this->actingAs($this->admin)->withSession([
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant',
        ])->get('/reports/export-csv/sales?date_preset=ALL');
        $responseCsv->assertStatus(200);
        $csvContent = $responseCsv->streamedContent();
        $this->assertStringContainsString('SALE ID', $csvContent);
        $this->assertStringContainsString('TOTAL AMOUNT', $csvContent);
        $this->assertStringContainsString('PAID AMOUNT', $csvContent);

        $responseJson = $this->actingAs($this->admin)->withSession([
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant',
        ])->get('/reports/export-json/sales?date_preset=ALL');
        $responseJson->assertStatus(200);
        $jsonData = $responseJson->json();
        $this->assertArrayHasKey('meta', $jsonData);
        $this->assertArrayHasKey('data', $jsonData);

        // Filter Parity: Single-sided date filter (from_date only)
        $fromDate = now()->subDays(5)->toDateString();
        $querySingleSide = $this->accountingService->buildSalesQuery(['from_date' => $fromDate]);
        $this->assertGreaterThanOrEqual(2, $querySingleSide->count());

        // Full System Reconciliation Audit: Must pass with 0 errors!
        $auditResult = $this->accountingService->runReconciliationAudit();
        $this->assertEquals('BALANCED', $auditResult['status'], "Reconciliation audit must be BALANCED with 0 anomalies.");
        $this->assertEquals(0, $auditResult['error_count']);
        $this->assertEquals('PASSED', $auditResult['checks']['invoice_balance_vs_line_items']);
        $this->assertEquals('PASSED', $auditResult['checks']['payment_tender_integrity']);
        $this->assertEquals('PASSED', $auditResult['checks']['customer_debt_ledger']);
        $this->assertEquals('PASSED', $auditResult['checks']['stock_levels_vs_inventory_logs']);
    }
}
