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
}
