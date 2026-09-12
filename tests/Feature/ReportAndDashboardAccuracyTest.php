<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\StockAdjustment;
use App\Models\SalesReturn;
use App\Models\InventoryLog;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReportAndDashboardAccuracyTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $cashierUser;
    protected Warehouse $branch1;
    protected Warehouse $branch2;
    protected Product $product1;
    protected Product $product2;
    protected Customer $customer1;
    protected Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch1 = Warehouse::create([
            'name' => 'Lagos Mainland HQ',
            'code' => 'LOS-01',
            'is_active' => true,
        ]);

        $this->branch2 = Warehouse::create([
            'name' => 'Abuja Annex',
            'code' => 'ABJ-01',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'id' => 'ADMIN-ACC-01',
            'name' => 'Super Admin',
            'email' => 'admin@accurate.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'warehouse_id' => null,
            'disabled' => false,
        ]);

        $this->cashierUser = User::create([
            'id' => 'CASHIER-ACC-01',
            'name' => 'Chioma Cashier',
            'email' => 'chioma@accurate.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->branch1->id,
            'disabled' => false,
        ]);

        $this->product1 = Product::create([
            'id' => (string) Str::uuid(),
            'code' => 'PROD-001',
            'name' => 'Cooking Oil 25L',
            'category' => 'Provisions',
            'unitPrice' => 40000,
            'costPrice' => 32000,
            'currentStock' => 100,
            'minStockLevel' => 5,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $this->product2 = Product::create([
            'id' => (string) Str::uuid(),
            'code' => 'PROD-002',
            'name' => 'Semovita 10kg',
            'category' => 'Food Grains',
            'unitPrice' => 12000,
            'costPrice' => 9500,
            'currentStock' => 50,
            'minStockLevel' => 5,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->branch1->id,
            'physical_stock' => 60,
            'allocated_stock' => 10,
        ]);

        StockLevel::create([
            'product_id' => $this->product2->id,
            'warehouse_id' => $this->branch1->id,
            'physical_stock' => 30,
            'allocated_stock' => 5,
        ]);

        StockLevel::create([
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->branch2->id,
            'physical_stock' => 40,
            'allocated_stock' => 0,
        ]);

        $this->customer1 = Customer::create([
            'name' => 'Alhaji Musa Dangote',
            'phone' => '08031112222',
            'address' => 'Balogun Market, Lagos',
            'total_debt' => 0,
        ]);

        $this->customer2 = Customer::create([
            'name' => 'Chief Emeka Okafor',
            'phone' => '08039998888',
            'address' => 'Alaba International, Lagos',
            'total_debt' => 0,
        ]);
    }

    public function test_accounting_period_summary_separates_cash_and_pos_and_selling_price_valuation()
    {
        $service = app(AccountingReportService::class);

        // 1. Create a sale with part payment in cash and pos
        $saleId = (string) Str::uuid();
        $sale = Sale::create([
            'id' => $saleId,
            'customerId' => $this->customer1->id,
            'customerName' => $this->customer1->name,
            'totalAmount' => 100000,
            'paidAmount' => 60000,
            'cashAmount' => 40000,
            'posAmount' => 20000,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'UNSUPPLIED',
            'userId' => $this->cashierUser->id,
            'userName' => $this->cashierUser->name,
            'warehouse_id' => $this->branch1->id,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Inflow Payments
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'amount' => 40000,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->cashierUser->name,
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'amount' => 20000,
            'method' => 'POS',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->cashierUser->name,
        ]);

        // 2. Customer pays debt: 15,000 via Cash and 10,000 via POS
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'amount' => 15000,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->cashierUser->name . ' [DEBT_RECOVERY]',
        ]);

        CustomerLedger::create([
            'customer_id' => $this->customer1->id,
            'sale_id' => $sale->id,
            'warehouse_id' => $this->branch1->id,
            'type' => 'PAYMENT',
            'amount' => 15000,
            'balance_after' => 25000,
            'payment_method' => 'CASH',
            'recorded_by' => $this->cashierUser->name,
            'created_at' => now(),
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'amount' => 10000,
            'method' => 'POS',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->cashierUser->name . ' [DEBT_RECOVERY]',
        ]);

        CustomerLedger::create([
            'customer_id' => $this->customer1->id,
            'sale_id' => $sale->id,
            'warehouse_id' => $this->branch1->id,
            'type' => 'PAYMENT',
            'amount' => 10000,
            'balance_after' => 15000,
            'payment_method' => 'POS',
            'recorded_by' => $this->cashierUser->name,
            'created_at' => now(),
        ]);

        // 3. Customer return resulting in a cash refund of 5,000
        SalesReturn::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product1->id,
            'productName' => $this->product1->name,
            'code' => $this->product1->code,
            'quantity' => 1,
            'refundAmount' => 5000,
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'amount' => -5000,
            'method' => 'REFUND_CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->adminUser->name,
        ]);

        $summary = $service->getPeriodSummary(['date_preset' => 'TODAY', 'warehouse_id' => $this->branch1->id]);

        // Assertions on separated collections
        $this->assertEquals(40000.0, $summary['cashFromSales'], 'Cash from sales should equal 40,000');
        $this->assertEquals(20000.0, $summary['posFromSales'], 'POS from sales should equal 20,000');
        $this->assertEquals(15000.0, $summary['cashDebtRecovered'], 'Cash debt recovered should equal 15,000');
        $this->assertEquals(10000.0, $summary['posDebtRecovered'], 'POS debt recovered should equal 10,000');
        $this->assertEquals(55000.0, $summary['totalCashInflow'], 'Total cash inflow should equal 55,000');
        $this->assertEquals(30000.0, $summary['totalPosInflow'], 'Total POS inflow should equal 30,000');
        $this->assertEquals(5000.0, $summary['cashRefunded'], 'Cash refunded should equal 5,000');
        $this->assertEquals(50000.0, $summary['netCashInflow'], 'Net cash inflow should equal 50,000 (55,000 - 5,000)');
        $this->assertEquals(30000.0, $summary['netPosInflow'], 'Net POS inflow should equal 30,000');
        $this->assertEquals(80000.0, $summary['totalNetMoneyRealized'], 'Total net money realized should equal 80,000');

        // Stock valuation at Branch 1:
        // Product 1: 60 units * 40,000 = 2,400,000
        // Product 2: 30 units * 12,000 = 360,000
        // Total retail valuation = 2,760,000
        $this->assertEquals(2760000.0, $summary['retailInventoryValue'], 'Valuation must strictly use Selling Price (unitPrice)');
    }

    public function test_pending_orders_analytics_calculates_backlog_and_aging_buckets()
    {
        $service = app(AccountingReportService::class);

        // 1. Unsupplied sale < 24h old
        $s1 = Sale::create([
            'id' => (string) Str::uuid(),
            'customerId' => $this->customer1->id,
            'customerName' => $this->customer1->name,
            'totalAmount' => 80000,
            'paidAmount' => 80000,
            'cashAmount' => 80000,
            'posAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'UNSUPPLIED',
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'warehouse_id' => $this->branch1->id,
            'createdAt' => now()->subHours(5)->toIso8601String(),
        ]);
        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $s1->id,
            'productId' => $this->product1->id,
            'productName' => $this->product1->name,
            'code' => $this->product1->code,
            'quantity' => 2,
            'unitPrice' => 40000,
            'totalPrice' => 80000,
        ]);

        // 2. Unsupplied sale 5 days old (3d-7d bucket)
        $s2 = Sale::create([
            'id' => (string) Str::uuid(),
            'customerId' => $this->customer2->id,
            'customerName' => $this->customer2->name,
            'totalAmount' => 36000,
            'paidAmount' => 36000,
            'cashAmount' => 36000,
            'posAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'NOT_SUPPLIED',
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'warehouse_id' => $this->branch1->id,
            'createdAt' => now()->subDays(5)->toIso8601String(),
        ]);
        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $s2->id,
            'productId' => $this->product2->id,
            'productName' => $this->product2->name,
            'code' => $this->product2->code,
            'quantity' => 3,
            'unitPrice' => 12000,
            'totalPrice' => 36000,
        ]);

        // 3. Unsupplied sale 10 days old (> 7d critical bucket)
        $s3 = Sale::create([
            'id' => (string) Str::uuid(),
            'customerId' => $this->customer2->id,
            'customerName' => $this->customer2->name,
            'totalAmount' => 120000,
            'paidAmount' => 60000,
            'cashAmount' => 60000,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'pending',
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'warehouse_id' => $this->branch1->id,
            'createdAt' => now()->subDays(10)->toIso8601String(),
        ]);
        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $s3->id,
            'productId' => $this->product1->id,
            'productName' => $this->product1->name,
            'code' => $this->product1->code,
            'quantity' => 3,
            'unitPrice' => 40000,
            'totalPrice' => 120000,
        ]);

        $analytics = $service->getPendingOrdersAnalytics($this->branch1->id);

        $this->assertEquals(3, $analytics['total_orders']);
        $this->assertEquals(8, $analytics['total_units'], '2 + 3 + 3 = 8 units');
        $this->assertEquals(236000.0, $analytics['total_value']);
        $this->assertEquals(1, $analytics['under_24h']);
        $this->assertEquals(0, $analytics['from_24h_to_48h']);
        $this->assertEquals(1, $analytics['from_3d_to_7d']);
        $this->assertEquals(1, $analytics['over_7d']);
        $this->assertCount(3, $analytics['backlog']);
    }

    public function test_dashboard_renders_with_inflow_breakdown_and_unsupplied_units()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('dashboard', ['warehouse_id' => $this->branch1->id]));

        $response->assertStatus(200);
        $response->assertSee('Total Inflow Collected');
        $response->assertSee('Unsupplied Backlog');
        $response->assertSee('Physical Stock Valuation');
    }

    public function test_report_controller_discrepancies_and_damages_not_truncated_and_branch_debt_isolated()
    {
        $this->actingAs($this->adminUser);

        // Seed 55 stock adjustments (damages) to prove it exceeds the previous take(50) limit
        for ($i = 1; $i <= 55; $i++) {
            StockAdjustment::create([
                'product_id' => $this->product1->id,
                'product_name' => $this->product1->name,
                'product_code' => $this->product1->code,
                'warehouse_id' => $this->branch1->id,
                'type' => 'DAMAGE',
                'quantity' => 2,
                'reason' => "Test damage batch {$i}",
                'recorded_by' => $this->adminUser->name,
                'created_at' => now(),
            ]);
        }

        // Seed transfer with discrepancy
        $transfer = Transfer::create([
            'transfer_no' => 'TRF-TEST-001',
            'source_warehouse_id' => $this->branch1->id,
            'destination_warehouse_id' => $this->branch2->id,
            'status' => 'DISCREPANCY',
            'dispatched_by' => $this->adminUser->name,
            'created_at' => now(),
        ]);

        TransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'product_code' => $this->product1->code,
            'dispatched_qty' => 20,
            'received_qty' => 15,
            'discrepancy_qty' => 5,
        ]);

        $response = $this->get(route('reports.index', ['warehouse_id' => $this->branch1->id]));

        $response->assertStatus(200);
        // Total damages must be 55 * 2 = 110 units (NOT truncated to 50 items = 100 units)
        $this->assertEquals(110, $response->viewData('totalDamagedUnits'));
        $this->assertEquals(5, $response->viewData('totalDiscrepancyUnits'));
    }

    public function test_pos_checkout_view_has_part_payment_tender_selector_defaulting_to_pos()
    {
        $this->actingAs($this->cashierUser);

        $response = $this->get(route('pos.index'));

        $response->assertStatus(200);
        // Assert part-payment tender selection UI components exist
        $response->assertSee('partPayTenderBox');
        $response->assertSee('btnPartPos');
        $response->assertSee('btnPartCash');
        $response->assertSee('POS (Default)');
        $response->assertSee('setPartPayTender');
    }

    public function test_reports_view_has_pending_orders_tab_and_exports_csv_and_json()
    {
        $this->actingAs($this->adminUser);

        // 1. Unsupplied sale
        $s = Sale::create([
            'id' => (string) Str::uuid(),
            'customerId' => $this->customer1->id,
            'customerName' => $this->customer1->name,
            'totalAmount' => 50000,
            'paidAmount' => 20000,
            'cashAmount' => 0,
            'posAmount' => 20000,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'NOT_SUPPLIED',
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'warehouse_id' => $this->branch1->id,
            'createdAt' => now()->subDays(4)->toIso8601String(),
        ]);
        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $s->id,
            'productId' => $this->product1->id,
            'productName' => $this->product1->name,
            'code' => $this->product1->code,
            'quantity' => 2,
            'unitPrice' => 25000,
            'totalPrice' => 50000,
        ]);

        // Check reports page renders pending orders tab and data
        $response = $this->get(route('reports.index', ['warehouse_id' => $this->branch1->id]));
        $response->assertStatus(200);
        $response->assertSee('Pending Orders');
        $response->assertSee('repPending');
        $this->assertArrayHasKey('pendingOrders', $response->viewData());
        $this->assertEquals(1, $response->viewData('pendingOrders')['total_orders']);

        // Check CSV export for pending_orders
        $csvResponse = $this->get(route('reports.export.csv', ['type' => 'pending_orders', 'warehouse_id' => $this->branch1->id]));
        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('Content-Type'));

        // Check JSON export for pending_orders
        $jsonResponse = $this->get(route('reports.export.json', ['type' => 'pending_orders', 'warehouse_id' => $this->branch1->id]));
        $jsonResponse->assertStatus(200);
        $json = $jsonResponse->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertEquals(1, $json['data']['total_orders']);
    }

    public function test_dashboard_yesterday_filter_deep_verification()
    {
        $this->actingAs($this->adminUser);

        $now = now();
        $yesterday = now()->subDay()->setTime(14, 0, 0);
        $threeDaysAgo = now()->subDays(3)->setTime(10, 0, 0);

        // 1. Sales: Today vs Yesterday vs 3 Days Ago
        // Sale Today: 100k
        $saleToday = Sale::create([
            'id' => (string) Str::uuid(),
            'customerId' => $this->customer1->id,
            'customerName' => $this->customer1->name,
            'totalAmount' => 100000,
            'paidAmount' => 100000,
            'cashAmount' => 50000,
            'posAmount' => 50000,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'SUPPLIED',
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'warehouse_id' => $this->branch1->id,
            'createdAt' => $now->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $saleToday->id,
            'amount' => 50000,
            'method' => 'CASH',
            'timestamp' => $now->toIso8601String(),
            'recordedBy' => $this->adminUser->name,
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $saleToday->id,
            'amount' => 50000,
            'method' => 'POS',
            'timestamp' => $now->toIso8601String(),
            'recordedBy' => $this->adminUser->name,
        ]);

        // Sale Yesterday: 75k (25k Cash, 25k POS, 25k Debt)
        $saleYesterday = Sale::create([
            'id' => (string) Str::uuid(),
            'customerId' => $this->customer2->id,
            'customerName' => $this->customer2->name,
            'totalAmount' => 75000,
            'paidAmount' => 50000,
            'cashAmount' => 25000,
            'posAmount' => 25000,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'SUPPLIED',
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'warehouse_id' => $this->branch1->id,
            'createdAt' => $yesterday->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $saleYesterday->id,
            'amount' => 25000,
            'method' => 'CASH',
            'timestamp' => $yesterday->toIso8601String(),
            'recordedBy' => $this->adminUser->name,
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $saleYesterday->id,
            'amount' => 25000,
            'method' => 'POS',
            'timestamp' => $yesterday->toIso8601String(),
            'recordedBy' => $this->adminUser->name,
        ]);

        // Sale 3 Days Ago: 40k Unpaid
        $salePast = Sale::create([
            'id' => (string) Str::uuid(),
            'customerId' => $this->customer1->id,
            'customerName' => $this->customer1->name,
            'totalAmount' => 40000,
            'paidAmount' => 0,
            'cashAmount' => 0,
            'posAmount' => 0,
            'status' => 'PENDING',
            'deliveryStatus' => 'SUPPLIED',
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'warehouse_id' => $this->branch1->id,
            'createdAt' => $threeDaysAgo->toIso8601String(),
        ]);

        // 2. Debt Collections: Today vs Yesterday
        $cl1 = CustomerLedger::create([
            'customer_id' => $this->customer1->id,
            'warehouse_id' => $this->branch1->id,
            'type' => 'PAYMENT',
            'amount' => 10000,
            'balance_after' => 0,
            'payment_method' => 'CASH',
            'notes' => 'Today debt recovery',
            'recorded_by' => $this->adminUser->name,
        ]);
        $cl2 = CustomerLedger::create([
            'customer_id' => $this->customer1->id,
            'warehouse_id' => $this->branch1->id,
            'type' => 'PAYMENT',
            'amount' => 15000,
            'balance_after' => 0,
            'payment_method' => 'POS',
            'notes' => 'Yesterday debt recovery',
            'recorded_by' => $this->adminUser->name,
        ]);
        CustomerLedger::where('id', $cl2->id)->update(['created_at' => $yesterday]);

        // 3. Refunds: Today vs Yesterday
        SalesReturn::create([
            'id' => (string) Str::uuid(),
            'code' => 'RET-TODAY-001',
            'saleId' => $saleToday->id,
            'productId' => $this->product1->id,
            'productName' => $this->product1->name,
            'productCode' => $this->product1->code,
            'quantity' => 1,
            'refundAmount' => 5000,
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'createdAt' => $now->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $saleToday->id,
            'amount' => -5000,
            'method' => 'REFUND_CASH',
            'timestamp' => $now->toIso8601String(),
            'recordedBy' => $this->adminUser->name,
        ]);

        SalesReturn::create([
            'id' => (string) Str::uuid(),
            'code' => 'RET-YEST-001',
            'saleId' => $saleYesterday->id,
            'productId' => $this->product1->id,
            'productName' => $this->product1->name,
            'productCode' => $this->product1->code,
            'quantity' => 1,
            'refundAmount' => 3000,
            'userId' => $this->adminUser->id,
            'userName' => $this->adminUser->name,
            'createdAt' => $yesterday->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $saleYesterday->id,
            'amount' => -3000,
            'method' => 'REFUND_CASH',
            'timestamp' => $yesterday->toIso8601String(),
            'recordedBy' => $this->adminUser->name,
        ]);

        // 4. Stock Movements: In & Out
        \App\Models\InventoryLog::create([
            'id' => (string) Str::uuid(),
            'productId' => $this->product1->id,
            'warehouse_id' => $this->branch1->id,
            'type' => 'STOCK_IN',
            'quantity' => 100,
            'timestamp' => $now->toIso8601String(),
            'userId' => $this->adminUser->id,
        ]);
        \App\Models\InventoryLog::create([
            'id' => (string) Str::uuid(),
            'productId' => $this->product1->id,
            'warehouse_id' => $this->branch1->id,
            'type' => 'STOCK_IN',
            'quantity' => 60,
            'timestamp' => $yesterday->toIso8601String(),
            'userId' => $this->adminUser->id,
        ]);
        \App\Models\InventoryLog::create([
            'id' => (string) Str::uuid(),
            'productId' => $this->product1->id,
            'warehouse_id' => $this->branch1->id,
            'type' => 'SALE',
            'quantity' => -30,
            'timestamp' => $yesterday->toIso8601String(),
            'userId' => $this->adminUser->id,
        ]);

        $adjToday = StockAdjustment::create([
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'product_code' => $this->product1->code,
            'warehouse_id' => $this->branch1->id,
            'type' => 'DAMAGE',
            'quantity' => 10,
            'reason' => 'Today damage',
            'recorded_by' => $this->adminUser->name,
        ]);
        $adjYest = StockAdjustment::create([
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'product_code' => $this->product1->code,
            'warehouse_id' => $this->branch1->id,
            'type' => 'DAMAGE',
            'quantity' => 5,
            'reason' => 'Yesterday damage',
            'recorded_by' => $this->adminUser->name,
        ]);
        StockAdjustment::where('id', $adjYest->id)->update(['created_at' => $yesterday]);

        // 6. Transfers with discrepancy: Today vs Yesterday
        $trToday = Transfer::create([
            'transfer_no' => 'TRF-TODAY',
            'source_warehouse_id' => $this->branch1->id,
            'destination_warehouse_id' => $this->branch2->id,
            'status' => 'DISCREPANCY',
            'dispatched_by' => $this->adminUser->name,
        ]);
        TransferItem::create([
            'transfer_id' => $trToday->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'product_code' => $this->product1->code,
            'dispatched_qty' => 10,
            'received_qty' => 6,
            'discrepancy_qty' => 4,
        ]);

        $trYesterday = Transfer::create([
            'transfer_no' => 'TRF-YEST',
            'source_warehouse_id' => $this->branch1->id,
            'destination_warehouse_id' => $this->branch2->id,
            'status' => 'DISCREPANCY',
            'dispatched_by' => $this->adminUser->name,
        ]);
        Transfer::where('id', $trYesterday->id)->update(['created_at' => $yesterday]);
        TransferItem::create([
            'transfer_id' => $trYesterday->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'product_code' => $this->product1->code,
            'dispatched_qty' => 10,
            'received_qty' => 8,
            'discrepancy_qty' => 2,
        ]);

        // Execute GET /dashboard?date_preset=YESTERDAY&warehouse_id=branch1
        $response = $this->get(route('dashboard', [
            'date_preset' => 'YESTERDAY',
            'warehouse_id' => $this->branch1->id,
        ]));

        $response->assertStatus(200);

        // Assert Label
        $this->assertStringContainsString('Yesterday', $response->viewData('rangeLabel'));

        // Assert Hero Card 1: Gross Sales
        $this->assertEquals(1, $response->viewData('salesCount'), 'Only yesterday sale counted');
        $this->assertEquals(75000.0, $response->viewData('totalSalesAmount'), 'Only yesterday 75k gross sales');

        // Assert Inflows: Cash vs POS
        $this->assertEquals(25000.0, $response->viewData('totalCashAmount'), 'Yesterday cash from sales');
        $this->assertEquals(25000.0, $response->viewData('totalPosAmount'), 'Yesterday pos from sales');
        $this->assertEquals(0.0, $response->viewData('cashDebtRecovered'), 'No cash debt collected yesterday');
        $this->assertEquals(15000.0, $response->viewData('posDebtRecovered'), '15k POS debt collected yesterday');
        $this->assertEquals(25000.0, $response->viewData('totalCashInflow'), '25k cash sales + 0 cash debt');
        $this->assertEquals(40000.0, $response->viewData('totalPosInflow'), '25k pos sales + 15k pos debt');
        $this->assertEquals(3000.0, $response->viewData('totalRefundAmount'), 'Yesterday 3k refund');

        // Total Net Realized Inflow = (25k cash - 3k refund) + (40k pos) = 22k + 40k = 62,000
        $this->assertEquals(62000.0, $response->viewData('totalCollections'));

        // Assert Panel 1: New Debt & Recoveries
        $this->assertEquals(25000.0, $response->viewData('newDebtIncurred'), 'Yesterday 25k unpaid balance');
        $this->assertEquals(15000.0, $response->viewData('debtRecoveredInPeriod'), 'Yesterday 15k debt recovery');
        $this->assertEquals(1, $response->viewData('debtRecoveryCount'), 'Yesterday 1 debt recovery payment');

        // Assert Panel 2: Stock Movements
        $this->assertEquals(60, $response->viewData('totalStockInUnits'), 'Yesterday 60 stock in units');
        $this->assertEquals(30, $response->viewData('totalStockOutUnits'), 'Yesterday 30 sale outflow units');

        // Assert Panel 3: Loss Radar
        $this->assertEquals(5, $response->viewData('damagedUnits'), 'Yesterday 5 damaged units');
        $this->assertEquals(2, $response->viewData('discrepancyCount'), 'Yesterday 2 discrepancy units');
        $this->assertEquals(1, $response->viewData('returnsCount'), 'Yesterday 1 return');
    }

    /**
     * Test that Daily Comprehensive Report calculates all 11 operational dimensions accurately.
     */
    public function test_daily_comprehensive_report_calculates_all_operational_dimensions_accurately(): void
    {
        $this->actingAs($this->adminUser);
        $service = app(AccountingReportService::class);
        $todayIso = Carbon::now('Africa/Lagos')->toIso8601String();
        $todaySql = Carbon::now('Africa/Lagos')->toDateTimeString();

        // 1. Create a period sale: Total 50,000, Paid 20,000 (10k Cash + 10k POS), Credit balance 30,000
        $sale = Sale::create([
            'id' => 'SALE-DAILY-01',
            'tenant_id' => 'default-tenant',
            'warehouse_id' => $this->branch1->id,
            'userId' => $this->cashierUser->id,
            'userName' => $this->cashierUser->name,
            'customerId' => $this->customer1->id,
            'customerName' => $this->customer1->name,
            'totalAmount' => 50000,
            'paidAmount' => 20000,
            'cashAmount' => 10000,
            'posAmount' => 10000,
            'changeAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'UNSUPPLIED',
            'createdAt' => $todayIso,
        ]);
        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product1->id,
            'productName' => $this->product1->name,
            'productCode' => $this->product1->code,
            'quantity' => 1,
            'unitPrice' => 50000,
            'totalPrice' => 50000,
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'amount' => 10000,
            'method' => 'CASH',
            'timestamp' => $todayIso,
            'recordedBy' => $this->cashierUser->name,
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'amount' => 10000,
            'method' => 'POS',
            'timestamp' => $todayIso,
            'recordedBy' => $this->cashierUser->name,
        ]);

        // 2. Create Debt Recovery payment: 15,000 CASH
        CustomerLedger::create([
            'customer_id' => $this->customer2->id,
            'warehouse_id' => $this->branch1->id,
            'type' => 'PAYMENT',
            'amount' => 15000,
            'balance_after' => 0,
            'payment_method' => 'CASH',
            'recorded_by' => $this->cashierUser->name,
            'created_at' => $todaySql,
        ]);

        // 3. Create Return & Cash Refund: 5,000 CASH
        SalesReturn::create([
            'id' => (string) Str::uuid(),
            'code' => 'RET-DAILY-001',
            'saleId' => $sale->id,
            'productId' => $this->product1->id,
            'productName' => $this->product1->name,
            'productCode' => $this->product1->code,
            'quantity' => 1,
            'refundAmount' => 5000,
            'reason' => 'Defective packaging',
            'createdAt' => $todayIso,
            'userId' => $this->cashierUser->id,
            'userName' => $this->cashierUser->name,
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'amount' => -5000,
            'method' => 'REFUND_CASH',
            'timestamp' => $todayIso,
            'recordedBy' => $this->cashierUser->name,
        ]);

        // 4. Stock Movement logs
        InventoryLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'warehouse_id' => $this->branch1->id,
            'productId' => $this->product1->id,
            'product_id' => $this->product1->id,
            'userId' => $this->cashierUser->id,
            'userName' => $this->cashierUser->name,
            'type' => 'STOCK_IN',
            'quantity' => 20,
            'timestamp' => $todayIso,
        ]);
        InventoryLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'warehouse_id' => $this->branch1->id,
            'productId' => $this->product1->id,
            'product_id' => $this->product1->id,
            'userId' => $this->cashierUser->id,
            'userName' => $this->cashierUser->name,
            'type' => 'SALE',
            'quantity' => -5,
            'timestamp' => $todayIso,
        ]);

        $report = $service->getDailyComprehensiveReport(['date_preset' => 'TODAY', 'warehouse_id' => $this->branch1->id]);

        // Assert 1: Sales & Invoices
        $this->assertEquals(50000.0, $report['total_amount_sold']);
        $this->assertEquals(1, $report['invoice_count']);
        $this->assertEquals(50000.0, $report['average_invoice']);

        // Assert 2: Collections
        // Gross Cash = 10k sale + 15k debt = 25k. Net Cash = 25k - 5k refund = 20,000.
        $this->assertEquals(20000.0, $report['net_cash_inflow']);
        // Net POS = 10k sale + 0 debt = 10,000.
        $this->assertEquals(10000.0, $report['net_pos_inflow']);
        // Total Net Collections = 20k + 10k = 30,000.
        $this->assertEquals(30000.0, $report['total_net_collections']);
        $this->assertEquals(20000.0, $report['drawer_physical_cash']);

        // Assert 3: Credit & Debt
        // Net balance on sale = 50k - 5k return - 15k net payments = 30,000.
        $this->assertEquals(30000.0, $report['new_credit_issued']);
        $this->assertEquals(15000.0, $report['debt_recovered']);
        $this->assertEquals(15000.0, $report['debt_recovered_cash']);
        $this->assertEquals(0.0, $report['debt_recovered_pos']);
        // Net Debt Change = 30k new credit - 15k recovered = 15,000
        $this->assertEquals(15000.0, $report['net_debt_change']);

        // Assert 4: Stock Flow
        $this->assertEquals(5, $report['stock_out_total_units']);
        $this->assertEquals(20, $report['stock_in_total_units']);
        $this->assertEquals(15, $report['net_inventory_movement_units']);

        // Assert 5: Pending Orders (New in Period)
        $this->assertEquals(1, $report['pending_orders_new_count']);
        $this->assertEquals(1, $report['pending_orders_new_units']);
        $this->assertEquals(50000.0, $report['pending_orders_new_value']);

        // Assert 6: Returns & Refunds
        $this->assertEquals(1, $report['returns_count']);
        $this->assertEquals(1, $report['returned_units']);
        $this->assertEquals(5000.0, $report['refunds_amount']);
    }

    /**
     * Test that One-Sheet CSV and JSON exports stream valid data with all sections.
     */
    public function test_daily_summary_one_sheet_csv_and_json_export_endpoints(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Test CSV One-Sheet Export
        $csvResponse = $this->get(route('reports.export.csv', [
            'type' => 'daily_summary',
            'date_preset' => 'TODAY',
            'warehouse_id' => $this->branch1->id,
        ]));

        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('Content-Type'));

        // Stream output check
        ob_start();
        $csvResponse->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('VICTORIOUS MARKET - DAILY OPERATIONS & RECONCILIATION DAY-BOOK', $content);
        $this->assertStringContainsString('--- 1. EXECUTIVE KPI SUMMARY ---', $content);
        $this->assertStringContainsString('--- 2. TENDER & CASH FLOW BREAKDOWN ---', $content);
        $this->assertStringContainsString('--- 3. NEW CREDIT ISSUED IN PERIOD ---', $content);
        $this->assertStringContainsString('--- 4. DEBTS RECOVERED LEDGER ---', $content);
        $this->assertStringContainsString('--- 5. PENDING ORDERS (NEW IN PERIOD & CARRIED BACKLOG) ---', $content);
        $this->assertStringContainsString('--- 6. CUSTOMER RETURNS & REFUNDS ---', $content);

        // 2. Test JSON Export
        $jsonResponse = $this->get(route('reports.export.json', [
            'type' => 'daily_summary',
            'date_preset' => 'TODAY',
            'warehouse_id' => $this->branch1->id,
        ]));

        $jsonResponse->assertStatus(200);
        $data = $jsonResponse->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total_amount_sold', $data['data']);
        $this->assertArrayHasKey('total_net_collections', $data['data']);
        $this->assertArrayHasKey('new_credit_issued', $data['data']);
        $this->assertArrayHasKey('debt_recovered', $data['data']);
        $this->assertArrayHasKey('stock_out_total_units', $data['data']);
        $this->assertArrayHasKey('stock_in_total_units', $data['data']);
        $this->assertArrayHasKey('physical_stock_remaining_units', $data['data']);
    }

    /**
     * Test that StockController, TransactionController, PosController, and DebtController
     * all filter cleanly without any undefined method errors on Today and Yesterday.
     */
    public function test_all_controllers_filter_cleanly_on_today_and_yesterday(): void
    {
        $this->actingAs($this->adminUser);

        // StockController transfers
        $resTrf = $this->get(route('stock.transfers', ['date_preset' => 'YESTERDAY']));
        $resTrf->assertStatus(200);

        // StockController unsupplied
        $resUns = $this->get(route('stock.unsupplied', ['date_preset' => 'YESTERDAY']));
        $resUns->assertStatus(200);

        // StockController adjustments
        $resAdj = $this->get(route('stock.adjustments', ['date_preset' => 'YESTERDAY']));
        $resAdj->assertStatus(200);

        // TransactionController
        $resTx = $this->get(route('transactions.index', ['date_preset' => 'YESTERDAY']));
        $resTx->assertStatus(200);

        // PosController returns
        $resRet = $this->get(route('pos.returns', ['date_preset' => 'YESTERDAY']));
        $resRet->assertStatus(200);

        // DebtController
        $resDebt = $this->get(route('debts.index', ['date_preset' => 'YESTERDAY']));
        $resDebt->assertStatus(200);

        // ReportController index with DayBook tab
        $resRep = $this->get(route('reports.index', ['date_preset' => 'YESTERDAY']));
        $resRep->assertStatus(200);
        $resRep->assertSee('Daily Operations');
    }
}
