<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Activity;
use App\Models\InventoryLog;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ExchangeReportAndStockInInflowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $admin;
    protected User $cashier;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@test.com',
        ]);

        Tenant::withoutGlobalScopes()->firstOrCreate(['id' => 'default-tenant'], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@test.com',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-' . Str::random(6),
            'name' => 'Victory Super Market',
            'slug' => 'vic-store-' . Str::random(5),
            'owner_email' => 'store@victory.ng',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Kano Retail Branch',
            'code' => 'KN-01',
            'location' => 'Kano Sabon Gari',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Victory Admin',
            'email' => 'admin-' . Str::random(5) . '@victory.ng',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Amina Cashier',
            'email' => 'amina-' . Str::random(5) . '@victory.ng',
            'password' => Hash::make('secret123'),
            'role' => 'cashier',
            'status' => 'active',
        ]);

        $this->productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'code' => 'SKU-SHIRT-M',
            'name' => 'Blue Oxford Shirt Size M',
            'category' => 'Apparel',
            'costPrice' => 4000.0,
            'unitPrice' => 6000.0,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->productA->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
        ]);

        $this->productB = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'code' => 'SKU-SHIRT-L',
            'name' => 'Blue Oxford Shirt Size L',
            'category' => 'Apparel',
            'costPrice' => 5000.0,
            'unitPrice' => 7500.0,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->productB->id,
            'physical_stock' => 15,
            'allocated_stock' => 0,
        ]);
    }

    /**
     * Test that POS Customer Exchange correctly tags EXCHANGE_IN log, emits STOCK_IN activity event,
     * and appears in Stock-In transactions view.
     */
    public function test_customer_exchange_appears_in_stock_in_and_emits_stock_in_security_event(): void
    {
        $this->actingAs($this->cashier);
        session(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id]);

        $stockService = app(StockService::class);

        // 1. First sell Product A
        $initialSale = $stockService->recordSale(
            [
                'customerName' => 'Alhaji Musa',
                'paymentMethod' => 'CASH',
                'tender' => ['cash' => 6000.0, 'pos' => 0.0],
                'cashAmount' => 6000.0,
                'posAmount' => 0.0,
                'paidAmount' => 6000.0,
                'totalAmount' => 6000.0,
            ],
            [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                    'unitPrice' => 6000.0,
                    'totalPrice' => 6000.0,
                ]
            ],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        $stockABefore = StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('physical_stock');
        $this->assertEquals(19, $stockABefore);

        // 2. Customer returns Product A (worth 6000) and exchanges for Product B (worth 7500), paying 1500 cash differential
        $exchangeSale = $stockService->recordSale(
            [
                'customerName' => 'Alhaji Musa',
                'paymentMethod' => 'SPLIT',
                'tender' => [
                    'cash' => 1500.0,
                    'pos' => 0.0,
                    'exchange_credit' => 6000.0,
                ],
                'cashAmount' => 1500.0,
                'posAmount' => 0.0,
                'exchange_credit' => 6000.0,
                'paidAmount' => 7500.0,
                'totalAmount' => 7500.0,
                'exchange_returns' => [
                    [
                        'saleId' => $initialSale->id,
                        'origSaleRef' => '#' . substr($initialSale->id, 0, 8),
                        'productId' => $this->productA->id,
                        'quantity' => 1,
                        'unitPrice' => 6000.0,
                        'creditAmount' => 6000.0,
                        'delivered' => true,
                    ]
                ],
            ],
            [
                [
                    'productId' => $this->productB->id,
                    'quantity' => 1,
                    'unitPrice' => 7500.0,
                    'totalPrice' => 7500.0,
                ]
            ],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        // Physical stock of Product A must have increased back to 20!
        $stockAAfter = StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->productA->id)->value('physical_stock');
        $this->assertEquals(20, $stockAAfter);

        // Verify InventoryLog was created with type EXCHANGE_IN
        $exchangeLog = InventoryLog::where('productId', $this->productA->id)->where('type', 'EXCHANGE_IN')->first();
        $this->assertNotNull($exchangeLog);
        $this->assertEquals(1, $exchangeLog->quantity);

        // Verify STOCK_IN Activity security event was emitted
        $stockInActivity = Activity::where('type', 'STOCK_IN')
            ->where('description', 'like', '%Customer Exchange%')
            ->first();
        $this->assertNotNull($stockInActivity);
        $this->assertEquals('CUSTOMER_EXCHANGE', $stockInActivity->metadata['inflow_type'] ?? null);
        $this->assertEquals('SKU-SHIRT-M', $stockInActivity->metadata['sku'] ?? null);

        // Verify TransactionController getStockInQuery finds the exchange
        $transController = app(\App\Http\Controllers\Web\TransactionController::class);
        $request = new \Illuminate\Http\Request(['inflow_category' => 'EXCHANGE']);
        $inLogs = $transController->getStockInQuery($request)->get();
        $this->assertTrue($inLogs->contains('id', $exchangeLog->id));

        // Verify StockController also queries it
        $stockLogs = InventoryLog::where('warehouse_id', $this->warehouse->id)
            ->whereIn('type', ['STOCK_IN', 'TRANSFER_IN', 'RETURN', 'SALES_RETURN', 'EXCHANGE_IN'])
            ->get();
        $this->assertTrue($stockLogs->contains('id', $exchangeLog->id));
    }

    /**
     * Test that Customer Return emits STOCK_IN activity event and is recognized as Stock In.
     */
    public function test_customer_return_appears_in_stock_in_and_emits_stock_in_security_event(): void
    {
        $this->actingAs($this->cashier);
        session(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id]);

        $stockService = app(StockService::class);

        // Sell 2 units of Product A
        $sale = $stockService->recordSale(
            [
                'customerName' => 'Balarabe',
                'paymentMethod' => 'CASH',
                'tender' => ['cash' => 12000.0, 'pos' => 0.0],
                'cashAmount' => 12000.0,
                'posAmount' => 0.0,
                'paidAmount' => 12000.0,
                'totalAmount' => 12000.0,
            ],
            [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 2,
                    'unitPrice' => 6000.0,
                    'totalPrice' => 12000.0,
                ]
            ],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        // Process return of 1 unit
        $stockService->recordSaleReturn(
            $sale->id,
            [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                ]
            ],
            $this->warehouse->id,
            'CASH_REFUND',
            'Wrong size',
            $this->cashier->id,
            $this->cashier->name
        );

        // Verify STOCK_IN activity was recorded with inflow_type CUSTOMER_RETURN
        $stockInActivity = Activity::where('type', 'STOCK_IN')
            ->where('description', 'like', '%Customer Return%')
            ->first();
        $this->assertNotNull($stockInActivity);
        $this->assertEquals('CUSTOMER_RETURN', $stockInActivity->metadata['inflow_type'] ?? null);
        $this->assertEquals('SKU-SHIRT-M', $stockInActivity->metadata['sku'] ?? null);
    }

    /**
     * Test that AccountingReportService getExchangeReport pairs returned and replacement items,
     * and that getPeriodSummary includes totalExchangeCreditApplied.
     */
    public function test_exchange_report_and_day_book_balancing(): void
    {
        $this->actingAs($this->admin);
        session(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id]);

        $stockService = app(StockService::class);
        $accountingService = app(AccountingReportService::class);

        // 1. Initial sale
        $initialSale = $stockService->recordSale(
            [
                'customerName' => 'Hajiya Fatima',
                'paymentMethod' => 'CASH',
                'tender' => ['cash' => 6000.0, 'pos' => 0.0],
                'cashAmount' => 6000.0,
                'posAmount' => 0.0,
                'paidAmount' => 6000.0,
                'totalAmount' => 6000.0,
            ],
            [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                    'unitPrice' => 6000.0,
                    'totalPrice' => 6000.0,
                ]
            ],
            $this->warehouse->id,
            true,
            $this->admin->id,
            $this->admin->name
        );

        // 2. Exchange sale
        $exchangeSale = $stockService->recordSale(
            [
                'customerName' => 'Hajiya Fatima',
                'paymentMethod' => 'SPLIT',
                'tender' => [
                    'cash' => 1500.0,
                    'pos' => 0.0,
                    'exchange_credit' => 6000.0,
                ],
                'cashAmount' => 1500.0,
                'posAmount' => 0.0,
                'exchange_credit' => 6000.0,
                'paidAmount' => 7500.0,
                'totalAmount' => 7500.0,
                'exchange_returns' => [
                    [
                        'saleId' => $initialSale->id,
                        'origSaleRef' => '#' . substr($initialSale->id, 0, 8),
                        'productId' => $this->productA->id,
                        'quantity' => 1,
                        'unitPrice' => 6000.0,
                        'creditAmount' => 6000.0,
                        'delivered' => true,
                    ]
                ],
            ],
            [
                [
                    'productId' => $this->productB->id,
                    'quantity' => 1,
                    'unitPrice' => 7500.0,
                    'totalPrice' => 7500.0,
                ]
            ],
            $this->warehouse->id,
            true,
            $this->admin->id,
            $this->admin->name
        );

        // 3. Verify getExchangeReport
        $report = $accountingService->getExchangeReport(['date_preset' => 'TODAY']);
        $this->assertGreaterThanOrEqual(1, $report['exchange_count']);
        $this->assertGreaterThanOrEqual(6000.0, $report['total_exchange_credit']);
        $this->assertNotEmpty($report['rows']);

        $matchRow = collect($report['rows'])->firstWhere('sale_id', $exchangeSale->id);
        $this->assertNotNull($matchRow);
        $this->assertEquals($exchangeSale->id, $matchRow['sale_id']);
        $this->assertEquals('Hajiya Fatima', $matchRow['customer_name']);
        $this->assertEquals(6000.0, $matchRow['exchange_credit']);
        $this->assertEquals(7500.0, $matchRow['replacement_total']);
        $this->assertEquals(1500.0, $matchRow['differential']);
        $this->assertStringContainsString('SKU-SHIRT-L', $matchRow['replacement_skus']);

        // 4. Verify Period Summary and Day Book
        $summary = $accountingService->getPeriodSummary(['date_preset' => 'TODAY']);
        $this->assertGreaterThanOrEqual(6000.0, $summary['exchangeCreditApplied']);
        $this->assertGreaterThanOrEqual(1, $summary['exchangeCreditCount']);

        $daily = $accountingService->getDailyComprehensiveReport(['date_preset' => 'TODAY']);
        $this->assertGreaterThanOrEqual(6000.0, $daily['exchange_credit_applied']);
        $this->assertGreaterThanOrEqual(1, $daily['stock_in_exchange_units']);
    }

    /**
     * Test that ReportController web routes render the exchanges tab and export CSV.
     */
    public function test_reports_index_and_csv_export(): void
    {
        $this->actingAs($this->admin);
        session(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id]);

        // Test Reports Index with tab=exchanges
        $response = $this->get(route('reports.index', ['tab' => 'exchanges']));
        $response->assertStatus(200);
        $response->assertSee('Customer Exchanges');
        $response->assertSee('repExchanges');

        // Test CSV Export for exchanges
        $csvResponse = $this->get(route('reports.export.csv', ['type' => 'exchanges']));
        $csvResponse->assertStatus(200);
        $csvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Test CSV Export for stock_in in TransactionController
        $stockInCsv = $this->get(route('transactions.export.csv', ['tab' => 'stock_in']));
        $stockInCsv->assertStatus(200);
    }
}
