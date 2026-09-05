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
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass8Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected User $cashierA;
    protected User $cashierB;
    protected User $tenantAdmin;
    protected User $platformAdmin;
    protected Product $productA;
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
            'id' => 'tenant-pass8-hardening',
            'name' => 'Pass8 Fortress Stores Ltd',
            'owner_email' => 'admin@pass8fortress.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 10,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lagos Branch Hub',
            'code' => 'P8-LOS-01',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Abuja Branch Hub',
            'code' => 'P8-ABJ-02',
            'is_active' => true,
        ]);

        $this->tenantAdmin = User::create([
            'id' => 'user-p8-admin',
            'tenant_id' => $this->tenant->id,
            'name' => 'Managing Director Folake',
            'email' => 'folake@pass8fortress.ng',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        $this->cashierA = User::create([
            'id' => 'user-p8-cashier-lagos',
            'tenant_id' => $this->tenant->id,
            'name' => 'Lagos Cashier Tunde',
            'email' => 'tunde@pass8fortress.ng',
            'password' => bcrypt('CashierPassword123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
            'permissions' => ['pos.checkout', 'stock.in', 'stock.transfer', 'stock.recall', 'stock.adjust', 'returns.process', 'debts.manage', 'reports.view', 'reports.export'],
        ]);

        $this->cashierB = User::create([
            'id' => 'user-p8-cashier-abuja',
            'tenant_id' => $this->tenant->id,
            'name' => 'Abuja Cashier Zainab',
            'email' => 'zainab@pass8fortress.ng',
            'password' => bcrypt('CashierPassword123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseB->id,
            'disabled' => false,
            'permissions' => ['pos.checkout', 'stock.in', 'stock.transfer', 'stock.recall', 'stock.adjust', 'returns.process', 'debts.manage', 'reports.view', 'reports.export'],
        ]);

        config(['saas.super_admin_email' => 'superadmin@hysam.com']);
        $this->platformAdmin = User::create([
            'id' => 'user-p8-platform-root',
            'tenant_id' => 'default-tenant',
            'name' => 'Root Platform Overseer',
            'email' => 'superadmin@hysam.com',
            'password' => bcrypt('PlatformSecret123!'),
            'role' => 'super_admin',
            'disabled' => false,
        ]);

        $this->productA = Product::create([
            'id' => 'prod-p8-solar-panel',
            'tenant_id' => $this->tenant->id,
            'name' => 'Monocrystalline Solar Panel 450W',
            'code' => 'SOL-PAN-450',
            'category' => 'Solar',
            'unitPrice' => 85000.00,
            'costPrice' => 65000.00,
            'currentStock' => 10,
            'warehouse_id' => $this->warehouseA->id,
            'updatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Test 1: getStockLevel is strictly read-only and does not mutate DB when record is missing.
     */
    public function test_getStockLevel_is_read_only_and_does_not_mutate_db_on_missing_stock(): void
    {
        $this->actingAs($this->tenantAdmin);

        // Ensure no stock level exists for productA at warehouseB
        $this->assertDatabaseMissing('stock_levels', [
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseB->id,
        ]);

        // Call read-only getStockLevel
        $result = $this->stockService->getStockLevel($this->productA->id, $this->warehouseB->id);
        $this->assertNull($result);

        // Verify DB was NOT mutated
        $this->assertDatabaseMissing('stock_levels', [
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseB->id,
        ]);
    }

    /**
     * Test 2: ensureStockLevelForAuthorizedMutation enforces warehouse authority and creates record.
     */
    public function test_ensureStockLevelForAuthorizedMutation_enforces_authority(): void
    {
        // 1. Branch employee on foreign warehouse must be rejected
        $this->actingAs($this->cashierA);

        try {
            $this->stockService->ensureStockLevelForAuthorizedMutation($this->productA->id, $this->warehouseB->id);
            $this->fail("Expected AuthorizationException when cashierA attempts to mutate foreign warehouseB stock level");
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString("cannot operate on Branch #{$this->warehouseB->id}", $e->getMessage());
        }

        // 2. Tenant admin succeeds and safely creates record
        $this->actingAs($this->tenantAdmin);
        $stockB = $this->stockService->ensureStockLevelForAuthorizedMutation($this->productA->id, $this->warehouseB->id);
        $this->assertNotNull($stockB);
        $this->assertEquals(0, $stockB->physical_stock);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseB->id,
        ]);
    }

    /**
     * Test 3: In HTTP route context, service mutation strictly derives actor from Auth::user() and ignores caller $actor argument.
     */
    public function test_service_mutations_reject_spoofed_actor_object_in_http_context(): void
    {
        // Simulate HTTP route execution
        $this->actingAs($this->cashierA);

        // Pass $this->tenantAdmin as $actor parameter in assertUserWarehouseAuthority
        // Since session is cashierA, foreign warehouse B MUST still be rejected!
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("cannot operate on Branch #{$this->warehouseB->id}");

        $this->stockService->assertUserWarehouseAuthority($this->warehouseB->id, $this->tenantAdmin);
    }

    /**
     * Test 4: recordCustomerPayment strictly rejects null-warehouse legacy invoices for branch employees.
     */
    public function test_recordCustomerPayment_strictly_rejects_null_warehouse_legacy_invoices_for_branch_employee(): void
    {
        $this->actingAs($this->cashierA);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Legacy Customer Musa',
            'phone' => '08099887766',
            'total_debt' => 45000.00,
        ]);

        // Sale with warehouse_id = NULL (legacy unassigned sale)
        $legacySale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => null, // Legacy unassigned
            'customerId' => $customer->id,
            'customerName' => $customer->name,
            'userId' => 'LEGACY',
            'userName' => 'Legacy System',
            'totalAmount' => 45000.00,
            'paidAmount' => 0.00,
            'cashAmount' => 0.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'CREDIT',
            'status' => 'PENDING',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Lagos Cashier attempts to accept payment for this customer
        // Must throw InvalidArgumentException because customer has NO debt at Lagos branch (warehouseA)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("has no outstanding invoice debt at branch warehouse #{$this->warehouseA->id}");

        $this->stockService->recordCustomerPayment(
            $customer->id,
            20000.00,
            'CASH',
            null,
            $this->cashierA->id,
            $this->cashierA->name,
            null,
            $this->warehouseA->id
        );
    }

    /**
     * Test 5: correctCustomerDebt enforces modern capability, blocks branch staff, and blocks platform users.
     */
    public function test_correctCustomerDebt_enforces_modern_security_contract(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Adjustment Candidate Dele',
            'phone' => '08011223344',
            'total_debt' => 100000.00,
        ]);

        // 1. Branch cashier must be rejected
        $this->actingAs($this->cashierA);
        try {
            $this->accountingService->correctCustomerDebt(
                $customer,
                80000.00,
                'Disputed items adjusted',
                $this->cashierA->id,
                $this->cashierA->name
            );
            $this->fail("Expected AuthorizationException when branch cashier calls correctCustomerDebt");
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString("Branch-scoped employees are not authorized", $e->getMessage());
        }

        // 2. Platform admin must be rejected
        $this->actingAs($this->platformAdmin);
        try {
            $this->accountingService->correctCustomerDebt(
                $customer,
                80000.00,
                'Platform admin override',
                $this->platformAdmin->id,
                $this->platformAdmin->name
            );
            $this->fail("Expected AuthorizationException when platform admin calls correctCustomerDebt");
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString("Platform users cannot modify tenant customer debt records", $e->getMessage());
        }

        // 3. Tenant Admin succeeds
        $this->actingAs($this->tenantAdmin);
        $res = $this->accountingService->correctCustomerDebt(
            $customer,
            75000.00,
            'Authorized Board Waiver',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );
        $this->assertEquals(75000.00, $res['newDebt']);
        $this->assertEquals(75000.00, $customer->fresh()->total_debt);
    }

    /**
     * Test 6: ReportController web, CSV, and JSON endpoints never leak tenant-wide total_debt to branch staff.
     */
    public function test_report_controller_web_csv_and_json_never_leak_tenant_wide_total_debt_to_branch_staff(): void
    {
        // Customer with ₦165,000 global debt:
        // ₦150,000 at Abuja branch (warehouseB)
        // ₦15,000 at Lagos branch (warehouseA)
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alhaji Gambo',
            'phone' => '08055443322',
            'address' => 'Balogun Market, Lagos',
            'total_debt' => 165000.00,
        ]);

        // Abuja Sale: 150k unpaid
        $saleABJ = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerId' => $customer->id,
            'customerName' => $customer->name,
            'userId' => $this->cashierB->id,
            'userName' => $this->cashierB->name,
            'totalAmount' => 150000.00,
            'paidAmount' => 0.00,
            'cashAmount' => 0.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'CREDIT',
            'status' => 'PENDING',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Lagos Sale: 15k unpaid
        $saleLOS = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerId' => $customer->id,
            'customerName' => $customer->name,
            'userId' => $this->cashierA->id,
            'userName' => $this->cashierA->name,
            'totalAmount' => 15000.00,
            'paidAmount' => 0.00,
            'cashAmount' => 0.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'CREDIT',
            'status' => 'PENDING',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Acting as Lagos Cashier
        $this->actingAs($this->cashierA);

        // 1. Web Report Index View
        $responseWeb = $this->get(route('reports.index'));
        $responseWeb->assertOk();
        $debtors = $responseWeb->viewData('debtors');
        $this->assertCount(1, $debtors);
        $debtorGambo = $debtors->first();
        $this->assertEquals(15000.00, $debtorGambo->branch_debt, "Branch debt must strictly be ₦15,000");
        $this->assertEquals(15000.00, $debtorGambo->total_debt, "Web report total_debt attribute must be sanitized to branch debt (₦15,000)");

        // 2. CSV Export
        $responseCsv = $this->get(route('reports.export.csv', ['type' => 'debtors']));
        $responseCsv->assertOk();
        $csvContent = $responseCsv->streamedContent();
        $this->assertStringContainsString('Alhaji Gambo', $csvContent);
        $this->assertStringContainsString('15000', $csvContent, "CSV must contain branch debt ₦15,000");
        $this->assertStringNotContainsString('165000', $csvContent, "CSV must NEVER contain tenant-wide debt ₦165,000");

        // 3. JSON Export
        $responseJson = $this->get(route('reports.export.json', ['type' => 'debtors']));
        $responseJson->assertOk();
        $jsonData = $responseJson->json();
        $this->assertArrayHasKey('data', $jsonData);
        $firstDebtor = $jsonData['data'][0];
        $this->assertEquals(15000.00, $firstDebtor['branch_debt'], "JSON export branch_debt must be ₦15,000");
        $this->assertEquals(15000.00, $firstDebtor['total_debt'], "JSON export total_debt must be sanitized to ₦15,000");
        $this->assertNotEquals(165000.00, $firstDebtor['total_debt'], "JSON export must NEVER reveal global ₦165,000");
    }

    /**
     * Test 7: recallTransfer enforces that only source warehouse authority can recall in-transit transfers.
     */
    public function test_recallTransfer_asserts_source_warehouse_authority(): void
    {
        // Create an in-transit transfer from warehouse A to warehouse B
        $transfer = Transfer::create([
            'tenant_id' => $this->tenant->id,
            'transfer_no' => 'TRF-P8-001',
            'source_warehouse_id' => $this->warehouseA->id,
            'destination_warehouse_id' => $this->warehouseB->id,
            'status' => 'DISPATCHED',
            'dispatched_by' => $this->cashierA->name,
            'dispatched_at' => now(),
        ]);

        TransferItem::create([
            'tenant_id' => $this->tenant->id,
            'transfer_id' => $transfer->id,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'product_code' => $this->productA->code,
            'dispatched_qty' => 2,
            'received_qty' => 0,
            'discrepancy_qty' => 0,
        ]);

        // Cashier B (assigned to warehouse B) attempts to recall the transfer dispatched from warehouse A
        $this->actingAs($this->cashierB);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("cannot operate on Branch #{$this->warehouseA->id}");

        $this->stockService->recallTransfer($transfer->id, $this->cashierB->id, $this->cashierB->name, 'Unauthorized recall attempt');
    }

    /**
     * Test 8: Reporting query builders enforce scope-narrowing invariant: Branch A ∩ Branch B = Empty.
     */
    public function test_query_builders_enforce_scope_intersection_invariant(): void
    {
        // Sale at Lagos (warehouseA)
        Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => 'Lagos Buyer',
            'userId' => $this->cashierA->id,
            'userName' => $this->cashierA->name,
            'totalAmount' => 10000.00,
            'paidAmount' => 10000.00,
            'cashAmount' => 10000.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'CASH',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Sale at Abuja (warehouseB)
        Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => 'Abuja Buyer',
            'userId' => $this->cashierB->id,
            'userName' => $this->cashierB->name,
            'totalAmount' => 25000.00,
            'paidAmount' => 25000.00,
            'cashAmount' => 25000.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'CASH',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Acting as Lagos Cashier (warehouseA)
        $this->actingAs($this->cashierA);

        // 1. Filter specifying assigned branch returns matching records
        $qA = $this->accountingService->buildSalesQuery(['warehouse_id' => $this->warehouseA->id]);
        $this->assertEquals(1, $qA->count());

        // 2. Filter specifying foreign branch MUST yield EMPTY set (Branch A ∩ Branch B = ∅)
        $qB = $this->accountingService->buildSalesQuery(['warehouse_id' => $this->warehouseB->id]);
        $this->assertEquals(0, $qB->count(), "Foreign warehouse filter must yield empty result set, never foreign branch data");

        // 3. Payments query foreign branch filter yields EMPTY set
        $qPayB = $this->accountingService->buildPaymentsQuery(['warehouse_id' => $this->warehouseB->id]);
        $this->assertEquals(0, $qPayB->count());

        // 4. Returns query foreign branch filter yields EMPTY set
        $qRetB = $this->accountingService->buildReturnsQuery(['warehouse_id' => $this->warehouseB->id]);
        $this->assertEquals(0, $qRetB->count());

        // 5. Stock movements query foreign branch filter yields EMPTY set
        $qMoveB = $this->accountingService->buildStockMovementsQuery(['warehouse_id' => $this->warehouseB->id]);
        $this->assertEquals(0, $qMoveB->count());
    }

    /**
     * Test 9: correctCustomerDebt strictly fails closed when no authenticated actor exists.
     */
    public function test_correctCustomerDebt_strictly_fails_closed_when_no_authenticated_user(): void
    {
        \Illuminate\Support\Facades\Auth::logout();
        session()->flush();

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ghost Target',
            'phone' => '08077665544',
            'total_debt' => 50000.00,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("No authenticated actor provided for debt correction");

        $this->accountingService->correctCustomerDebt(
            $customer,
            20000.00,
            'Unauthenticated adjustment attempt',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );
    }

    /**
     * Test 10: All report endpoints derive financial revenue, debt, and staff collections strictly from Payment events, ignoring tampered cached paidAmount.
     */
    public function test_report_endpoints_derive_revenue_debt_and_staff_collections_strictly_from_payment_events_ignoring_cached_paidAmount(): void
    {
        $this->actingAs($this->tenantAdmin);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Event Authority Debtor',
            'phone' => '08098765432',
            'total_debt' => 25000.00,
        ]);

        // Create Sale with artificially tampered cached paidAmount = ₦99,999.00
        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerId' => $customer->id,
            'customerName' => $customer->name,
            'userId' => $this->tenantAdmin->id,
            'userName' => 'Audited Admin',
            'totalAmount' => 50000.00,
            'paidAmount' => 99999.00, // Tampered cached column!
            'cashAmount' => 99999.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'CASH',
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Genuine financial event: Inflow payment of ₦20,000.00
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale->id,
            'amount' => 20000.00,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => 'Audited Admin',
        ]);

        // Genuine financial event: SalesReturn credit of ₦5,000.00
        \App\Models\SalesReturn::create([
            'id' => (string) Str::uuid(),
            'code' => 'RET-P8-001',
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale->id,
            'productId' => $this->productA->id,
            'productName' => $this->productA->name,
            'productCode' => $this->productA->code,
            'quantity' => 1,
            'refundAmount' => 5000.00,
            'refundType' => 'DEBT_REDUCTION',
            'reason' => 'Defective packaging returned',
            'userId' => $this->tenantAdmin->id,
            'userName' => 'Audited Admin',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Expected authoritative values:
        // Net Payable: 50,000 - 5,000 = 45,000
        // Net Collected: 20,000
        // Authoritative Debt Balance: 45,000 - 20,000 = 25,000

        // 1. Web Report Dashboard View
        $responseWeb = $this->get(route('reports.index'));
        $responseWeb->assertOk();
        $this->assertEquals(20000.00, $responseWeb->viewData('totalCollected'), "Total collected must be strictly derived from Payment events (₦20,000), ignoring tampered ₦99,999!");
        $this->assertEquals(25000.00, $responseWeb->viewData('totalDebtCreated'), "Total debt created must be strictly derived from authoritative balance (₦25,000)!");
        $topStaff = $responseWeb->viewData('topStaff');
        $adminStaff = $topStaff->firstWhere('name', 'Audited Admin');
        $this->assertNotNull($adminStaff);
        $this->assertEquals(20000.00, $adminStaff['collected'], "Top staff collected must derive from Payment events (₦20,000)!");

        // 2. CSV Export for Sales
        $responseCsv = $this->get(route('reports.export.csv', ['type' => 'sales']));
        $responseCsv->assertOk();
        $csvContent = $responseCsv->streamedContent();
        $this->assertStringContainsString('20000', $csvContent, "CSV must contain authoritative paid amount ₦20,000");
        $this->assertStringContainsString('25000', $csvContent, "CSV must contain authoritative debt balance ₦25,000");
        $this->assertStringNotContainsString('99999', $csvContent, "CSV must NEVER output tampered cached paidAmount ₦99,999!");

        // 3. JSON Export for Sales
        $responseJson = $this->get(route('reports.export.json', ['type' => 'sales']));
        $responseJson->assertOk();
        $jsonSales = $responseJson->json()['data'];
        $this->assertNotEmpty($jsonSales);
        $matchedSale = collect($jsonSales)->firstWhere('id', $sale->id);
        $this->assertNotNull($matchedSale);
        $this->assertEquals(20000.00, $matchedSale['paidAmount'], "JSON paidAmount must be event-authoritative (₦20,000)");
        $this->assertEquals(25000.00, $matchedSale['invoice_balance'], "JSON invoice_balance must be authoritative (₦25,000)");
        $this->assertNotEquals(99999.00, $matchedSale['paidAmount']);
    }
}
