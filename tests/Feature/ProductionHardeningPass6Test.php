<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\CustomerLedger;
use App\Models\InventoryLog;
use App\Http\Controllers\BackupController;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass6Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected User $cashierA;
    protected User $cashierB;
    protected User $branchManagerA;
    protected User $tenantAdmin;
    protected Product $productA;
    protected Product $productB;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        config(['saas.enabled' => true]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-pass6-hardening',
            'name' => 'Pass6 Vanguard Stores Ltd',
            'owner_email' => 'admin@vanguardstores.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 10,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vanguard Lagos Island Hub',
            'code' => 'VGD-LAG-01',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vanguard Abuja Hub',
            'code' => 'VGD-ABJ-02',
            'is_active' => true,
        ]);

        $this->tenantAdmin = User::create([
            'id' => 'user-p6-admin',
            'tenant_id' => $this->tenant->id,
            'name' => 'Vanguard Managing Director',
            'email' => 'md@vanguardstores.ng',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        $this->branchManagerA = User::create([
            'id' => 'user-p6-bm-lagos',
            'tenant_id' => $this->tenant->id,
            'name' => 'Lagos Branch Manager Yemi',
            'email' => 'yemi@vanguardstores.ng',
            'password' => bcrypt('ManagerPassword123!'),
            'role' => 'branch_manager',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        $this->cashierA = User::create([
            'id' => 'user-p6-cashier-lagos',
            'tenant_id' => $this->tenant->id,
            'name' => 'Lagos Cashier Titi',
            'email' => 'titi@vanguardstores.ng',
            'password' => bcrypt('CashierPassword123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        $this->cashierB = User::create([
            'id' => 'user-p6-cashier-abuja',
            'tenant_id' => $this->tenant->id,
            'name' => 'Abuja Cashier Kabir',
            'email' => 'kabir@vanguardstores.ng',
            'password' => bcrypt('CashierPassword123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseB->id,
            'disabled' => false,
        ]);

        $this->productA = Product::create([
            'id' => 'prod-p6-generator-5kva',
            'tenant_id' => $this->tenant->id,
            'name' => 'Tiger Generator 5KVA',
            'code' => 'GEN-5KVA',
            'category' => 'Power',
            'unitPrice' => 200000.00,
            'costPrice' => 170000.00,
            'currentStock' => 50,
            'warehouse_id' => $this->warehouseA->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);

        $this->productB = Product::create([
            'id' => 'prod-p6-solar-inverter',
            'tenant_id' => $this->tenant->id,
            'name' => 'Luminous Solar Inverter 3.5KVA',
            'code' => 'SOLAR-35KVA',
            'category' => 'Solar',
            'unitPrice' => 150000.00,
            'costPrice' => 125000.00,
            'currentStock' => 30,
            'warehouse_id' => $this->warehouseB->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->productB->id,
            'warehouse_id' => $this->warehouseB->id,
            'physical_stock' => 30,
            'allocated_stock' => 0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 1. BRANCH EMPLOYEE CROSS-BRANCH DEBT PAYMENT HARDENING (P1)
    // ─────────────────────────────────────────────────────────────

    public function test_branch_employee_cannot_record_payment_for_customer_without_branch_invoices()
    {
        // Customer John creates debt invoice ONLY at Abuja Branch (Warehouse B)
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chief John Okeke',
            'phone' => '08033221100',
            'total_debt' => 50000.00,
        ]);

        $saleB = Sale::create([
            'id' => 'SALE-ABJ-DEBT-01',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 150000.00,
            'paidAmount' => 100000.00,
            'cashAmount' => 100000.00,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashierB->id,
            'userName' => $this->cashierB->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleB->id,
            'amount' => 100000.00,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->cashierB->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Lagos Cashier (Warehouse A) attempts to record a debt payment for Chief John
        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouseA->id,
        ])->post(route('debts.pay', ['id' => $customer->id]), [
            'amount' => 20000.00,
            'payment_method' => 'CASH',
        ]);

        // Must be rejected with 302 and specific error
        $response->assertStatus(302);
        $response->assertSessionHasErrors('error');
        $this->assertStringContainsString('no outstanding invoices at your assigned branch', session('errors')->first('error'));

        // Assert customer total debt remains untouched
        $customer->refresh();
        $this->assertEquals(50000.00, (float) $customer->total_debt);
        $saleB->refresh();
        $this->assertEquals(100000.00, (float) $saleB->paidAmount);
    }

    public function test_debt_payment_allocation_is_strictly_contained_to_acting_branch_invoices()
    {
        // Customer has debt at BOTH Lagos (₦30,000) and Abuja (₦50,000) -> Total ₦80,000
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alhaji Gambo Express',
            'phone' => '08099881122',
            'total_debt' => 80000.00,
        ]);

        $saleLagos = Sale::create([
            'id' => 'SALE-LAG-DEBT-01',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 50000.00,
            'paidAmount' => 20000.00, // ₦30,000 unpaid
            'cashAmount' => 20000.00,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashierA->id,
            'userName' => $this->cashierA->name,
            'createdAt' => now()->subDay()->toIso8601String(),
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleLagos->id,
            'amount' => 20000.00,
            'method' => 'CASH',
            'timestamp' => now()->subDay()->toIso8601String(),
            'recordedBy' => $this->cashierA->name,
            'createdAt' => now()->subDay()->toIso8601String(),
        ]);

        $saleAbuja = Sale::create([
            'id' => 'SALE-ABJ-DEBT-02',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 70000.00,
            'paidAmount' => 20000.00, // ₦50,000 unpaid
            'cashAmount' => 20000.00,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashierB->id,
            'userName' => $this->cashierB->name,
            'createdAt' => now()->subDays(2)->toIso8601String(),
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleAbuja->id,
            'amount' => 20000.00,
            'method' => 'CASH',
            'timestamp' => now()->subDays(2)->toIso8601String(),
            'recordedBy' => $this->cashierB->name,
            'createdAt' => now()->subDays(2)->toIso8601String(),
        ]);

        // Lagos Cashier records ₦30,000 payment to clear Lagos balance
        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouseA->id,
        ])->post(route('debts.pay', ['id' => $customer->id]), [
            'amount' => 30000.00,
            'payment_method' => 'CASH',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $customer->refresh();
        $saleLagos->refresh();
        $saleAbuja->refresh();

        // Lagos sale is fully paid and COMPLETED
        $this->assertEquals(50000.00, (float) $saleLagos->paidAmount);
        $this->assertEquals('COMPLETED', $saleLagos->status);

        // Abuja sale is COMPLETELY UNTOUCHED! (Still ₦20,000 paid, status PARTIAL)
        $this->assertEquals(20000.00, (float) $saleAbuja->paidAmount);
        $this->assertEquals('PARTIAL', $saleAbuja->status);

        // Customer total debt is reduced from ₦80,000 to ₦50,000
        $this->assertEquals(50000.00, (float) $customer->total_debt);

        // Assert CustomerLedger has warehouse_id stamped
        $latestLedger = CustomerLedger::where('customer_id', $customer->id)->latest('created_at')->first();
        $this->assertNotNull($latestLedger);
        $this->assertEquals($this->warehouseA->id, $latestLedger->warehouse_id);
        $this->assertEquals($saleLagos->id, $latestLedger->sale_id);
    }

    public function test_branch_debt_payment_exceeding_branch_outstanding_is_rejected()
    {
        // Customer has ₦10,000 at Lagos and ₦40,000 at Abuja (Total ₦50,000)
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Madam Bimbo',
            'phone' => '08077112233',
            'total_debt' => 50000.00,
        ]);

        Sale::create([
            'id' => 'SALE-LAG-DEBT-02',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 30000.00,
            'paidAmount' => 20000.00, // ₦10,000 unpaid
            'cashAmount' => 20000.00,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashierA->id,
            'userName' => $this->cashierA->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => 'SALE-LAG-DEBT-02',
            'amount' => 20000.00,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->cashierA->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        Sale::create([
            'id' => 'SALE-ABJ-DEBT-03',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 60000.00,
            'paidAmount' => 20000.00, // ₦40,000 unpaid
            'cashAmount' => 20000.00,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashierB->id,
            'userName' => $this->cashierB->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Lagos Cashier attempts to pay ₦25,000 (exceeding Lagos ₦10,000 balance, even though total is ₦50,000)
        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouseA->id,
        ])->post(route('debts.pay', ['id' => $customer->id]), [
            'amount' => 25000.00,
            'payment_method' => 'CASH',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('error');
        $this->assertStringContainsString('exceeds customer\'s outstanding balance at this branch', session('errors')->first('error'));

        // Ensure total debt remains intact
        $customer->refresh();
        $this->assertEquals(50000.00, (float) $customer->total_debt);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. PERIOD SUMMARY & CUSTOMER LEDGER BRANCH HARDENING (P1)
    // ─────────────────────────────────────────────────────────────

    public function test_accounting_period_summary_strictly_scopes_customer_ledgers_by_branch()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ledger Scope Customer',
            'phone' => '08055443322',
            'total_debt' => 60000.00,
        ]);

        // Branch A Ledger Payment
        CustomerLedger::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouseA->id,
            'type' => 'PAYMENT',
            'amount' => 20000.00,
            'balance_after' => 40000.00,
            'payment_method' => 'CASH',
            'reference_no' => 'REF-P6-A',
            'recorded_by' => $this->cashierA->name,
            'notes' => 'Payment Lagos',
            'created_at' => now(),
        ]);

        // Branch B Ledger Payment (WITHOUT sale_id to test that previous orWhereNull('sale_id') leakage is fixed!)
        CustomerLedger::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouseB->id,
            'sale_id' => null, // Legacy unlinked ledger at Branch B
            'type' => 'PAYMENT',
            'amount' => 40000.00,
            'balance_after' => 0.00,
            'payment_method' => 'CASH',
            'reference_no' => 'REF-P6-B',
            'recorded_by' => $this->cashierB->name,
            'notes' => 'Payment Abuja unlinked',
            'created_at' => now(),
        ]);

        // Query Period Summary specifically scoped to Branch A (Lagos)
        $summaryA = $this->accountingService->getPeriodSummary(
            ['from_date' => now()->subDay()->toDateString(), 'to_date' => now()->toDateString(), 'warehouse_id' => $this->warehouseA->id]
        );

        // Branch A debt recovered must be exactly ₦20,000, NOT contaminated with Branch B's ₦40,000
        $this->assertEquals(20000.00, (float) $summaryA['debtRecovered']);

        // Query Period Summary scoped to Branch B (Abuja)
        $summaryB = $this->accountingService->getPeriodSummary(
            ['from_date' => now()->subDay()->toDateString(), 'to_date' => now()->toDateString(), 'warehouse_id' => $this->warehouseB->id]
        );
        $this->assertEquals(40000.00, (float) $summaryB['debtRecovered']);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. REPORT CONTROLLER BRANCH INVENTORY & DAMAGED MOVEMENTS (P1)
    // ─────────────────────────────────────────────────────────────

    public function test_report_inventory_export_json_strictly_filters_to_branch_warehouse()
    {
        // Branch Manager A requests export-json for inventory
        $response = $this->actingAs($this->branchManagerA)->withSession([
            'user_id' => $this->branchManagerA->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouseA->id,
        ])->get(route('reports.export.json', ['type' => 'inventory']));

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('data', $json);
        $itemCodes = collect($json['data'])->pluck('code')->all();

        // Must contain Product A (Lagos stock = 50)
        $this->assertContains('GEN-5KVA', $itemCodes);

        // Product B exists only at Abuja (Warehouse B) with stock 30; at Lagos it has no stock level so must NOT appear
        $this->assertNotContains('SOLAR-35KVA', $itemCodes, "Product B (Abuja inventory) must NOT appear in Lagos branch inventory export.");
    }

    public function test_stock_movements_query_is_strictly_scoped_to_branch_for_damages()
    {
        // Create stock adjustment at Branch A
        $this->stockService->recordStockAdjustment(
            $this->productA->id,
            $this->warehouseA->id,
            'DAMAGED',
            2,
            'Water damage in Lagos warehouse',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        // Create stock adjustment at Branch B
        $this->stockService->recordStockAdjustment(
            $this->productB->id,
            $this->warehouseB->id,
            'DAMAGED',
            1,
            'Dropped in Abuja warehouse',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        // Branch Manager A runs export-json for damages
        $response = $this->actingAs($this->branchManagerA)->withSession([
            'user_id' => $this->branchManagerA->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouseA->id,
        ])->get(route('reports.export.json', ['type' => 'damages']));

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('data', $json);
        $descriptions = collect($json['data'])->pluck('description')->all();

        // Must include Lagos damage
        $hasLagosDamage = collect($descriptions)->contains(fn($d) => str_contains($d, 'Water damage in Lagos'));
        $this->assertTrue($hasLagosDamage);

        // Must NOT include Abuja damage
        $hasAbujaDamage = collect($descriptions)->contains(fn($d) => str_contains($d, 'Dropped in Abuja'));
        $this->assertFalse($hasAbujaDamage, "Branch A manager must not see Branch B stock movements.");
    }

    // ─────────────────────────────────────────────────────────────
    // 4. REPORT SALES QUERY & DASHBOARD TOTAL DEBT ALL-TIME (P1)
    // ─────────────────────────────────────────────────────────────

    public function test_report_sales_query_isolates_purely_by_warehouse_id()
    {
        // Sale at Branch A
        Sale::create([
            'id' => 'SALE-REP-LAG-01',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => 'Lagos Buyer',
            'totalAmount' => 200000.00,
            'paidAmount' => 200000.00,
            'cashAmount' => 200000.00,
            'posAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashierA->id,
            'userName' => $this->cashierA->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Sale at Branch B (even if cashierA somehow was associated with another role, warehouse_id isolates)
        Sale::create([
            'id' => 'SALE-REP-ABJ-01',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => 'Abuja Buyer',
            'totalAmount' => 150000.00,
            'paidAmount' => 150000.00,
            'cashAmount' => 150000.00,
            'posAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashierB->id,
            'userName' => $this->cashierB->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Branch Manager A visits Reports Dashboard
        $response = $this->actingAs($this->branchManagerA)->withSession([
            'user_id' => $this->branchManagerA->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouseA->id,
        ])->get(route('reports.index'));

        $response->assertStatus(200);

        // Sales count in report must be 1 (only Branch A)
        $sales = $response->viewData('sales');
        $saleIds = $sales->pluck('id')->all();

        $this->assertContains('SALE-REP-LAG-01', $saleIds);
        $this->assertNotContains('SALE-REP-ABJ-01', $saleIds, "Branch B sale must NOT appear in Branch A report.");
    }

    public function test_report_dashboard_calculates_branch_debt_strictly_from_branch_invoices()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Multi-Branch Debtor Corp',
            'phone' => '08012345678',
            'total_debt' => 100000.00, // ₦25,000 at Lagos, ₦75,000 at Abuja
        ]);

        // Branch A open invoice: ₦25,000 debt
        $saleLag = Sale::create([
            'id' => 'SALE-DASH-LAG',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 50000.00,
            'paidAmount' => 25000.00,
            'cashAmount' => 25000.00,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashierA->id,
            'userName' => $this->cashierA->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleLag->id,
            'amount' => 25000.00,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->cashierA->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Branch B open invoice: ₦75,000 debt
        $saleAbj = Sale::create([
            'id' => 'SALE-DASH-ABJ',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 100000.00,
            'paidAmount' => 25000.00,
            'cashAmount' => 25000.00,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashierB->id,
            'userName' => $this->cashierB->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleAbj->id,
            'amount' => 25000.00,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->cashierB->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Branch Manager A loads the reports dashboard
        $response = $this->actingAs($this->branchManagerA)->withSession([
            'user_id' => $this->branchManagerA->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouseA->id,
        ])->get(route('reports.index'));

        $response->assertStatus(200);

        // $totalDebtOwedAllTime must reflect ONLY Branch A's open debt (₦25,000), NOT tenant-wide ₦100,000!
        $totalDebtOwedAllTime = (float) $response->viewData('totalDebtOwedAllTime');
        $this->assertEquals(25000.00, $totalDebtOwedAllTime, "Branch A manager must see only Branch A debt balance (₦25,000), not tenant-wide customer total debt.");
    }

    // ─────────────────────────────────────────────────────────────
    // 5. BACKUP CANONICAL ENVELOPE HMAC INTEGRITY (P2)
    // ─────────────────────────────────────────────────────────────

    public function test_backup_envelope_hmac_covers_manifest_and_metadata_and_backward_compatible()
    {
        $backupController = app(BackupController::class);

        $backupData = [
            'tenants' => [$this->tenant->toArray()],
            'warehouses' => [$this->warehouseA->toArray(), $this->warehouseB->toArray()],
            'users' => [$this->cashierA->toArray()],
        ];

        $manifest = [
            'tenants' => 1,
            'warehouses' => 2,
            'users' => 1,
        ];

        $envelope = [
            'version' => '2.1.0',
            'type' => 'TENANT',
            'tenant_id' => $this->tenant->id,
            'timestamp' => now()->toIso8601String(),
            'manifest' => $manifest,
            'data' => $backupData,
        ];

        // 1. Compute canonical envelope checksum
        $validChecksum = BackupController::computeEnvelopeChecksum($envelope);
        $this->assertNotEmpty($validChecksum);

        $envelope['checksum'] = $validChecksum;

        // 2. Validate integrity with intact envelope -> Must pass (returns null error)
        $this->assertNull($backupController->validateBackupIntegrity($envelope, 'TENANT', $this->tenant->id));

        // 3. Tamper attack 1: Attacker alters manifest metadata
        $tamperedManifest = $envelope;
        $tamperedManifest['manifest']['users'] = 999;
        $this->assertNotNull($backupController->validateBackupIntegrity($tamperedManifest, 'TENANT', $this->tenant->id), "Tampered manifest must fail validation.");

        // 4. Tamper attack 2: Attacker alters backup type
        $tamperedType = $envelope;
        $tamperedType['type'] = 'PLATFORM';
        $this->assertNotNull($backupController->validateBackupIntegrity($tamperedType, 'TENANT', $this->tenant->id), "Tampered backup type must fail validation.");

        // 5. Tamper attack 3: Attacker alters data contents
        $tamperedData = $envelope;
        $tamperedData['data']['users'][0]['name'] = 'Malicious Hacker';
        $this->assertNotNull($backupController->validateBackupIntegrity($tamperedData, 'TENANT', $this->tenant->id), "Tampered data payload must fail validation.");

        // 6. Backward Compatibility: Legacy backup signed only over 'data' payload
        $legacyEnvelope = [
            'version' => '2.1.0',
            'type' => 'TENANT',
            'tenant_id' => $this->tenant->id,
            'timestamp' => now()->toIso8601String(),
            'manifest' => $manifest,
            'data' => $backupData,
        ];
        $legacyChecksum = hash_hmac('sha256', json_encode($backupData), config('app.key'));
        $legacyEnvelope['checksum'] = $legacyChecksum;

        $this->assertNull($backupController->validateBackupIntegrity($legacyEnvelope, 'TENANT', $this->tenant->id), "Legacy data-only signed backup must pass validation.");
    }
}
