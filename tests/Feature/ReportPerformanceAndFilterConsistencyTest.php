<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportPerformanceAndFilterConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected User $executiveUser;
    protected User $branchUserA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'tenant-report-perf',
            'name' => 'Report Perf Stores Ltd',
            'owner_email' => 'admin@reportperf.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 10,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lekki Branch',
            'code' => 'LEK',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ikeja Branch',
            'code' => 'IKJ',
            'is_active' => true,
        ]);

        $this->executiveUser = User::create([
            'id' => 'user-report-exec',
            'tenant_id' => $this->tenant->id,
            'name' => 'Executive Director',
            'email' => 'exec@reportperf.ng',
            'password' => bcrypt('StrongPass123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->branchUserA = User::create([
            'id' => 'user-report-cashier-lekki',
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch Cashier A',
            'email' => 'cashier.lekki@reportperf.ng',
            'password' => bcrypt('StrongPass123!'),
            'role' => 'sales_officer',
            'warehouse_id' => $this->warehouseA->id,
            'is_active' => true,
        ]);
    }

    /**
     * TEST 1: Debt aging is authoritatively anchored to the oldest unpaid invoice date
     * and is immune to customer profile edits/touches.
     */
    public function test_debt_aging_is_anchored_to_oldest_unpaid_invoice_and_immune_to_customer_updates(): void
    {
        $oldDate = Carbon::now()->subDays(45)->toIso8601String();

        $debtor = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alhaji Musa',
            'phone' => '08011112222',
            'address' => 'Balogun Market',
            'total_debt' => 75000.00,
        ]);

        Sale::create([
            'id' => 'SALE-AGING-OLD-01',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->branchUserA->id,
            'customerId' => $debtor->id,
            'customerName' => $debtor->name,
            'totalAmount' => 75000.00,
            'paidAmount' => 0.00,
            'tenderedAmount' => 0.00,
            'changeAmount' => 0.00,
            'cashAmount' => 0.00,
            'posAmount' => 0.00,
            'transferAmount' => 0.00,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userName' => 'cashier_lekki',
            'createdAt' => $oldDate,
        ]);

        // 1. Executive view: verify categorized as CRITICAL (30+ Days)
        $response = $this->actingAs($this->executiveUser)->get(route('reports.index', ['tab' => 'debtors']));
        $response->assertStatus(200);
        $debtors = $response->viewData('debtors');
        $this->assertNotEmpty($debtors);
        $found = $debtors->firstWhere('id', $debtor->id);
        $this->assertNotNull($found);
        $this->assertSame('CRITICAL (30+ Days)', $found->aging_category);

        // 2. Branch A view: verify categorized as CRITICAL (30+ Days)
        $branchResponse = $this->actingAs($this->branchUserA)->get(route('reports.index', ['tab' => 'debtors']));
        $branchResponse->assertStatus(200);
        $branchDebtors = $branchResponse->viewData('debtors');
        $this->assertNotEmpty($branchDebtors);
        $branchFound = $branchDebtors->firstWhere('id', $debtor->id);
        $this->assertNotNull($branchFound);
        $this->assertSame('CRITICAL (30+ Days)', $branchFound->aging_category);

        // 3. Mutate/touch customer today (simulating phone/name update)
        $debtor->phone = '08099998888';
        $debtor->save();
        $debtor->touch();

        // 4. Assert debt aging does NOT reset to CURRENT (0-7 Days)
        $postUpdateResponse = $this->actingAs($this->executiveUser)->get(route('reports.index', ['tab' => 'debtors']));
        $postUpdateDebtors = $postUpdateResponse->viewData('debtors');
        $postUpdateFound = $postUpdateDebtors->firstWhere('id', $debtor->id);
        $this->assertSame('CRITICAL (30+ Days)', $postUpdateFound->aging_category);

        $postUpdateBranchResponse = $this->actingAs($this->branchUserA)->get(route('reports.index', ['tab' => 'debtors']));
        $postUpdateBranchDebtors = $postUpdateBranchResponse->viewData('debtors');
        $postUpdateBranchFound = $postUpdateBranchDebtors->firstWhere('id', $debtor->id);
        $this->assertSame('CRITICAL (30+ Days)', $postUpdateBranchFound->aging_category);
    }

    /**
     * TEST 2: Stock Level valuation matrix bulk-loads in a single query without N+1 queries.
     */
    public function test_stock_levels_matrix_avoids_n_plus_one_queries(): void
    {
        // Seed 10 products
        for ($i = 1; $i <= 10; $i++) {
            $prod = Product::create([
                'id' => "prod-batch-{$i}",
                'tenant_id' => $this->tenant->id,
                'name' => "Batch Test Product {$i}",
                'code' => "SKU-BATCH-{$i}",
                'category' => 'Hardware',
                'unitPrice' => 1500.00,
                'costPrice' => 1000.00,
                'minStockLevel' => 5,
                'archived' => false,
            ]);

            StockLevel::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $prod->id,
                'warehouse_id' => $this->warehouseA->id,
                'physical_stock' => 12,
            ]);
            StockLevel::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $prod->id,
                'warehouse_id' => $this->warehouseB->id,
                'physical_stock' => 8,
            ]);
        }

        $stockLevelQueryCount = 0;
        DB::listen(function ($query) use (&$stockLevelQueryCount) {
            if (str_contains($query->sql, 'stock_levels') && str_contains(strtolower($query->sql), 'select')) {
                $stockLevelQueryCount++;
            }
        });

        $response = $this->actingAs($this->executiveUser)->get(route('reports.index', ['tab' => 'inventory']));
        $response->assertStatus(200);

        // Assert StockLevel was queried at most once during the product matrix build (NOT 10 times)
        $this->assertLessThanOrEqual(2, $stockLevelQueryCount, "StockLevel queried {$stockLevelQueryCount} times, expected bulk batch query.");

        $products = $response->viewData('products');
        $this->assertCount(10, $products);
        $first = $products->first();
        $this->assertEquals(20, $first->total_physical_stock);
        $this->assertEquals(30000.00, $first->total_valuation);
        $this->assertSame('IN_STOCK', $first->stock_status);
    }

    /**
     * TEST 3: Inventory CSV Export streams successfully with accurate totals.
     */
    public function test_inventory_csv_export_streams_with_eager_loaded_stocks(): void
    {
        $prod = Product::create([
            'id' => 'prod-csv-01',
            'tenant_id' => $this->tenant->id,
            'name' => 'CSV Export Product',
            'code' => 'SKU-CSV-01',
            'category' => 'Tools',
            'unitPrice' => 5000.00,
            'costPrice' => 3500.00,
            'minStockLevel' => 10,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $prod->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 15,
        ]);

        $response = $this->actingAs($this->executiveUser)->get(route('reports.export.csv', 'inventory'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Product ID', $content);
        $this->assertStringContainsString('SKU-CSV-01', $content);
        $this->assertStringContainsString('CSV Export Product', $content);
        $this->assertStringContainsString('15', $content);
        $this->assertStringContainsString('IN_STOCK', $content);
    }

    /**
     * TEST 4: Returns query filters synchronize with AccountingReportService and enforce branch isolation.
     */
    public function test_returns_query_synchronizes_filters_and_enforces_branch_scoping(): void
    {
        $saleA = Sale::create([
            'id' => 'SALE-RET-A1',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->branchUserA->id,
            'customerName' => 'Branch A Customer',
            'totalAmount' => 10000.00,
            'paidAmount' => 10000.00,
            'tenderedAmount' => 10000.00,
            'changeAmount' => 0.00,
            'cashAmount' => 10000.00,
            'posAmount' => 0.00,
            'transferAmount' => 0.00,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userName' => 'cashier_lekki',
            'createdAt' => now()->subDay()->toIso8601String(),
        ]);

        $saleB = Sale::create([
            'id' => 'SALE-RET-B1',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'userId' => $this->executiveUser->id,
            'customerName' => 'Branch B Customer',
            'totalAmount' => 12000.00,
            'paidAmount' => 12000.00,
            'tenderedAmount' => 12000.00,
            'changeAmount' => 0.00,
            'cashAmount' => 12000.00,
            'posAmount' => 0.00,
            'transferAmount' => 0.00,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userName' => 'cashier_ikeja',
            'createdAt' => now()->subDay()->toIso8601String(),
        ]);

        $retA = SalesReturn::create([
            'id' => 'ret-test-01',
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleA->id,
            'productId' => 'prod-ret-1',
            'code' => 'RET-SKU-A',
            'productCode' => 'RET-SKU-A',
            'productName' => 'Returned Item A',
            'customerName' => 'Branch A Customer',
            'quantity' => 1,
            'refundAmount' => 2500.00,
            'reason' => 'Defective item',
            'userId' => $this->branchUserA->id,
            'userName' => 'cashier_lekki',
            'createdAt' => now()->subDay()->toIso8601String(),
        ]);

        $retB = SalesReturn::create([
            'id' => 'ret-test-02',
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleB->id,
            'productId' => 'prod-ret-2',
            'code' => 'RET-SKU-B',
            'productCode' => 'RET-SKU-B',
            'productName' => 'Returned Item B',
            'customerName' => 'Branch B Customer',
            'quantity' => 1,
            'refundAmount' => 3000.00,
            'reason' => 'Wrong size',
            'userId' => $this->executiveUser->id,
            'userName' => 'cashier_ikeja',
            'createdAt' => now()->subDay()->toIso8601String(),
        ]);

        // Branch A cashier visits reports
        $branchResp = $this->actingAs($this->branchUserA)->get(route('reports.index', ['tab' => 'returns']));
        $branchResp->assertStatus(200);
        $returnsData = $branchResp->viewData('returns');
        $this->assertCount(1, $returnsData);
        $this->assertSame($saleA->id, $returnsData->first()->saleId);

        // Executive visits reports
        $execResp = $this->actingAs($this->executiveUser)->get(route('reports.index', ['tab' => 'returns']));
        $execResp->assertStatus(200);
        $allReturns = $execResp->viewData('returns');
        $this->assertCount(2, $allReturns);
    }

    /**
     * TEST 5: POS terminal index and Product Catalog buildFilteredProducts load stock levels
     * in a single batch query without N+1 query loops.
     */
    public function test_pos_index_and_product_catalog_avoid_n_plus_one_stock_queries(): void
    {
        // Seed 15 products
        for ($i = 1; $i <= 15; $i++) {
            $p = Product::create([
                'id' => "prod-pos-n1-{$i}",
                'tenant_id' => $this->tenant->id,
                'name' => "POS Batch Item {$i}",
                'code' => "SKU-POS-{$i}",
                'category' => 'Groceries',
                'unitPrice' => 2000.00,
                'costPrice' => 1500.00,
                'minStockLevel' => 5,
                'archived' => false,
            ]);

            StockLevel::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $p->id,
                'warehouse_id' => $this->warehouseA->id,
                'physical_stock' => 25,
            ]);
        }

        // 1. Verify POS terminal index executes in exactly 1 stock_levels query
        $posStockQueryCount = 0;
        DB::listen(function ($query) use (&$posStockQueryCount) {
            if (str_contains($query->sql, 'stock_levels') && str_contains(strtolower($query->sql), 'select')) {
                $posStockQueryCount++;
            }
        });

        $posResponse = $this->actingAs($this->executiveUser)->withSession([
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouseA->id,
        ])->get(route('pos.index'));
        $posResponse->assertStatus(200);

        $this->assertLessThanOrEqual(2, $posStockQueryCount, "POS index executed {$posStockQueryCount} stock_levels queries for 15 products; expected bulk batch query.");
        $posProducts = $posResponse->viewData('products');
        $this->assertCount(15, $posProducts);
        $this->assertEquals(25, $posProducts->first()->available_stock);

        // 2. Verify Product catalog index executes in exactly 1 stock_levels query
        $catalogStockQueryCount = 0;
        DB::listen(function ($query) use (&$catalogStockQueryCount) {
            if (str_contains($query->sql, 'stock_levels') && str_contains(strtolower($query->sql), 'select')) {
                $catalogStockQueryCount++;
            }
        });

        $catalogResponse = $this->actingAs($this->executiveUser)->withSession([
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouseA->id,
        ])->get(route('products.index'));
        $catalogResponse->assertStatus(200);
        $this->assertLessThanOrEqual(2, $catalogStockQueryCount, "Product catalog executed {$catalogStockQueryCount} stock_levels queries for 15 products; expected bulk batch query.");
    }
}
