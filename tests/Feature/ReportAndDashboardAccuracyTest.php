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
}
