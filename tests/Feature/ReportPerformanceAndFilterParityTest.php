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
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Tests\TestCase;

class ReportPerformanceAndFilterParityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouseLagos;
    protected Warehouse $warehouseAbuja;
    protected User $cashierLagos;
    protected User $cashierAbuja;
    protected User $tenantAdmin;
    protected Product $product;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true]);

        $this->accountingService = app(AccountingReportService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-report-parity',
            'name' => 'Parity Wholesale Stores',
            'owner_email' => 'parity@stores.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouseLagos = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lagos Mainland Branch',
            'code' => 'LOS-MAIN',
            'is_active' => true,
        ]);

        $this->warehouseAbuja = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Abuja Central Branch',
            'code' => 'ABJ-CENT',
            'is_active' => true,
        ]);

        $this->tenantAdmin = User::create([
            'id' => 'user-admin-p8',
            'tenant_id' => $this->tenant->id,
            'name' => 'Chief Admin',
            'email' => 'admin@parity.ng',
            'password' => bcrypt('AdminSecret123!'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseLagos->id,
            'disabled' => false,
            'permissions' => ['*'],
        ]);

        $this->cashierLagos = User::create([
            'id' => 'user-cashier-los',
            'tenant_id' => $this->tenant->id,
            'name' => 'Lagos Cashier',
            'email' => 'lagos@parity.ng',
            'password' => bcrypt('CashierSecret123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseLagos->id,
            'disabled' => false,
            'permissions' => ['reports.view', 'reports.export'],
        ]);

        $this->cashierAbuja = User::create([
            'id' => 'user-cashier-abj',
            'tenant_id' => $this->tenant->id,
            'name' => 'Abuja Cashier',
            'email' => 'abuja@parity.ng',
            'password' => bcrypt('CashierSecret123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseAbuja->id,
            'disabled' => false,
            'permissions' => ['reports.view', 'reports.export'],
        ]);

        $this->product = Product::create([
            'id' => 'prod-report-1',
            'tenant_id' => $this->tenant->id,
            'name' => 'Industrial Generator 5KVA',
            'code' => 'GEN-5KVA',
            'category' => 'Power',
            'brand' => 'PowerMaster',
            'size' => 'Standard',
            'unitPrice' => 250000.0,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseLagos->id,
            'product_id' => $this->product->id,
            'physical_stock' => 50,
        ]);
    }

    protected function createSale(array $overrides = []): Sale
    {
        $defaults = [
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseLagos->id,
            'customerName' => 'Walk-in Customer',
            'userId' => $this->cashierLagos->id,
            'userName' => $this->cashierLagos->name,
            'totalAmount' => 10000.0,
            'paidAmount' => 0.0,
            'cashAmount' => 0.0,
            'posAmount' => 0.0,
            'status' => 'PENDING',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ];

        return Sale::create(array_merge($defaults, $overrides));
    }

    protected function createPayment(array $overrides = []): Payment
    {
        $defaults = [
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'amount' => 5000.0,
            'method' => 'CASH',
            'recordedBy' => $this->cashierLagos->name,
            'timestamp' => now()->toIso8601String(),
        ];

        return Payment::create(array_merge($defaults, $overrides));
    }

    public function test_debt_aging_anchors_to_oldest_unpaid_invoice_not_customer_updated_at(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alhaji Danladi',
            'phone' => '08031112233',
            'address' => 'Balogun Market, Lagos',
            'total_debt' => 150000.0,
        ]);

        // Create an unpaid sale from 45 days ago
        $this->createSale([
            'id' => 'sale-old-debt-45d',
            'customerId' => $customer->id,
            'customerName' => $customer->name,
            'customerPhone' => $customer->phone,
            'totalAmount' => 150000.0,
            'paidAmount' => 0.0,
            'createdAt' => Carbon::now()->subDays(45)->toIso8601String(),
        ]);

        // Simulating customer profile edit TODAY (updating phone/address)
        $customer->update([
            'phone' => '08099998877',
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->cashierLagos)->get(route('reports.index', ['tab' => 'debts']));
        $response->assertOk();

        // Must still be classified as CRITICAL (30+ Days) based on oldest invoice date, not touched profile date
        $debtors = $response->viewData('debtors');
        $this->assertNotEmpty($debtors);
        $debtorEntry = $debtors->firstWhere('id', $customer->id);
        $this->assertNotNull($debtorEntry);
        $this->assertEquals('CRITICAL (30+ Days)', $debtorEntry->aging_category);
        $this->assertEquals(150000.0, $debtorEntry->branch_debt);
    }

    public function test_branch_scoping_prevents_debt_leakage_between_branches(): void
    {
        $sharedCustomer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Multi-Branch Merchant',
            'phone' => '08055554444',
            'total_debt' => 300000.0,
        ]);

        // Lagos branch debt: ₦200,000
        $this->createSale([
            'id' => 'sale-lagos-debt',
            'warehouse_id' => $this->warehouseLagos->id,
            'customerId' => $sharedCustomer->id,
            'customerName' => $sharedCustomer->name,
            'totalAmount' => 200000.0,
            'paidAmount' => 0.0,
            'createdAt' => Carbon::now()->subDays(10)->toIso8601String(),
        ]);

        // Abuja branch debt: ₦100,000
        $this->createSale([
            'id' => 'sale-abuja-debt',
            'warehouse_id' => $this->warehouseAbuja->id,
            'customerId' => $sharedCustomer->id,
            'customerName' => $sharedCustomer->name,
            'totalAmount' => 100000.0,
            'paidAmount' => 0.0,
            'userId' => $this->cashierAbuja->id,
            'userName' => $this->cashierAbuja->name,
            'createdAt' => Carbon::now()->subDays(5)->toIso8601String(),
        ]);

        // Lagos cashier should only see Lagos branch debt of ₦200,000
        $losResponse = $this->actingAs($this->cashierLagos)->get(route('reports.index', ['tab' => 'debts']));
        $losResponse->assertOk();
        $losDebtors = $losResponse->viewData('debtors');
        $this->assertEquals(200000.0, $losDebtors->firstWhere('id', $sharedCustomer->id)->branch_debt);
        $this->assertEquals(200000.0, $losResponse->viewData('totalDebtOwedAllTime'));

        // Abuja cashier should only see Abuja branch debt of ₦100,000
        $abjResponse = $this->actingAs($this->cashierAbuja)->get(route('reports.index', ['tab' => 'debts']));
        $abjResponse->assertOk();
        $abjDebtors = $abjResponse->viewData('debtors');
        $this->assertEquals(100000.0, $abjDebtors->firstWhere('id', $sharedCustomer->id)->branch_debt);
        $this->assertEquals(100000.0, $abjResponse->viewData('totalDebtOwedAllTime'));

        // Lagos cashier debtor CSV export must only include Lagos debt
        $csvResponse = $this->actingAs($this->cashierLagos)->get(route('reports.export.csv', ['type' => 'debtors']));
        $csvResponse->assertOk();
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('200000', $csvContent);
        $this->assertStringNotContainsString('300000', $csvContent);
    }

    public function test_filter_parity_between_on_screen_dashboard_and_csv_export(): void
    {
        // Sale 1: Paid & Supplied
        $s1 = $this->createSale([
            'id' => 'sale-parity-1',
            'customerName' => 'Customer Alpha',
            'totalAmount' => 100000.0,
            'paidAmount' => 100000.0,
            'cashAmount' => 100000.0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'SUPPLIED',
        ]);
        $this->createPayment([
            'saleId' => $s1->id,
            'amount' => 100000.0,
            'method' => 'CASH',
        ]);

        // Sale 2: Debt (Unpaid) & Supplied
        $this->createSale([
            'id' => 'sale-parity-2',
            'customerName' => 'Customer Beta',
            'totalAmount' => 75000.0,
            'paidAmount' => 0.0,
            'status' => 'PENDING',
            'deliveryStatus' => 'SUPPLIED',
        ]);

        // Sale 3: Paid & Not Supplied
        $s3 = $this->createSale([
            'id' => 'sale-parity-3',
            'customerName' => 'Customer Gamma',
            'totalAmount' => 50000.0,
            'paidAmount' => 50000.0,
            'posAmount' => 50000.0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'NOT_SUPPLIED',
        ]);
        $this->createPayment([
            'saleId' => $s3->id,
            'amount' => 50000.0,
            'method' => 'POS',
        ]);

        // 1. Test Filter: payment_status = DEBT
        $dashDebtResponse = $this->actingAs($this->cashierLagos)->get(route('reports.index', ['payment_status' => 'DEBT']));
        $dashDebtResponse->assertOk();
        $dashSales = $dashDebtResponse->viewData('sales');
        $this->assertCount(1, $dashSales);
        $this->assertEquals('sale-parity-2', $dashSales->first()->id);

        $csvDebtResponse = $this->actingAs($this->cashierLagos)->get(route('reports.export.csv', [
            'type' => 'sales',
            'payment_status' => 'DEBT',
        ]));
        $csvDebtResponse->assertOk();
        $csvDebtContent = $csvDebtResponse->streamedContent();
        $this->assertStringContainsString('sale-parity-2', $csvDebtContent);
        $this->assertStringNotContainsString('sale-parity-1', $csvDebtContent);
        $this->assertStringNotContainsString('sale-parity-3', $csvDebtContent);

        // 2. Test Compound Filter: delivery_status = PAID_SUPPLIED
        $dashPaidSuppliedResponse = $this->actingAs($this->cashierLagos)->get(route('reports.index', ['delivery_status' => 'PAID_SUPPLIED']));
        $dashPaidSuppliedResponse->assertOk();
        $dashPaidSuppliedSales = $dashPaidSuppliedResponse->viewData('sales');
        $this->assertCount(1, $dashPaidSuppliedSales);
        $this->assertEquals('sale-parity-1', $dashPaidSuppliedSales->first()->id);

        $csvPaidSuppliedResponse = $this->actingAs($this->cashierLagos)->get(route('reports.export.csv', [
            'type' => 'sales',
            'delivery_status' => 'PAID_SUPPLIED',
        ]));
        $csvPaidSuppliedResponse->assertOk();
        $csvPaidSuppliedContent = $csvPaidSuppliedResponse->streamedContent();
        $this->assertStringContainsString('sale-parity-1', $csvPaidSuppliedContent);
        $this->assertStringNotContainsString('sale-parity-2', $csvPaidSuppliedContent);
        $this->assertStringNotContainsString('sale-parity-3', $csvPaidSuppliedContent);
    }

    public function test_batch_invoice_balance_calculation_prevents_n_plus_one_query_explosion(): void
    {
        // Seed 10 sales for the branch
        for ($i = 1; $i <= 10; $i++) {
            $s = $this->createSale([
                'id' => "sale-batch-perf-{$i}",
                'customerName' => "Batch Customer {$i}",
                'totalAmount' => 10000.0 * $i,
                'paidAmount' => 5000.0 * $i,
                'cashAmount' => 5000.0 * $i,
                'status' => 'PARTIAL',
                'deliveryStatus' => 'SUPPLIED',
                'createdAt' => Carbon::now()->subDays($i)->toIso8601String(),
            ]);

            $this->createPayment([
                'saleId' => $s->id,
                'amount' => 5000.0 * $i,
                'method' => 'CASH',
                'timestamp' => Carbon::now()->subDays($i)->toIso8601String(),
            ]);
        }

        // Measure query count with 10 sales
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->cashierLagos)->get(route('reports.index'));
        $queries10Sales = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Seed 10 MORE sales (total 20 sales)
        for ($i = 11; $i <= 20; $i++) {
            $s = $this->createSale([
                'id' => "sale-batch-perf-{$i}",
                'customerName' => "Batch Customer {$i}",
                'totalAmount' => 10000.0 * $i,
                'paidAmount' => 5000.0 * $i,
                'cashAmount' => 5000.0 * $i,
                'status' => 'PARTIAL',
                'deliveryStatus' => 'SUPPLIED',
                'createdAt' => Carbon::now()->subDays($i)->toIso8601String(),
            ]);

            $this->createPayment([
                'saleId' => $s->id,
                'amount' => 5000.0 * $i,
                'method' => 'CASH',
                'timestamp' => Carbon::now()->subDays($i)->toIso8601String(),
            ]);
        }

        // Measure query count with 20 sales
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->cashierLagos)->get(route('reports.index'));
        $queries20Sales = count(DB::getQueryLog());
        DB::disableQueryLog();

        // If N+1 existed, queries would have increased by at least 20-30 queries (2-3 queries per sale).
        // With batch calculation, the query count difference between 10 and 20 sales must be <= 2 (constant query complexity).
        $queryDiff = abs($queries20Sales - $queries10Sales);
        $this->assertLessThanOrEqual(
            2,
            $queryDiff,
            "Query count increased by {$queryDiff} when sales doubled. Expected O(1) constant query complexity."
        );
    }
}
