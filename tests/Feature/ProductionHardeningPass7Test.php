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
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Http\Controllers\BackupController;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass7Test extends TestCase
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
            'id' => 'tenant-pass7-hardening',
            'name' => 'Pass7 Vanguard Retail Ltd',
            'owner_email' => 'admin@pass7retail.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 10,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lagos Mainland Branch',
            'code' => 'P7-LOS-01',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Port Harcourt Branch',
            'code' => 'P7-PHC-02',
            'is_active' => true,
        ]);

        $this->tenantAdmin = User::create([
            'id' => 'user-p7-admin',
            'tenant_id' => $this->tenant->id,
            'name' => 'Executive Director Joy',
            'email' => 'joy@pass7retail.ng',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        $this->cashierA = User::create([
            'id' => 'user-p7-cashier-lagos',
            'tenant_id' => $this->tenant->id,
            'name' => 'Lagos Cashier Emeka',
            'email' => 'emeka@pass7retail.ng',
            'password' => bcrypt('CashierPassword123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
            'permissions' => ['pos.checkout', 'stock.in', 'stock.transfer', 'stock.adjust', 'returns.process', 'debts.manage', 'reports.view'],
        ]);

        $this->cashierB = User::create([
            'id' => 'user-p7-cashier-phc',
            'tenant_id' => $this->tenant->id,
            'name' => 'PHC Cashier Chioma',
            'email' => 'chioma@pass7retail.ng',
            'password' => bcrypt('CashierPassword123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseB->id,
            'disabled' => false,
            'permissions' => ['pos.checkout', 'stock.in', 'stock.transfer', 'stock.adjust', 'returns.process', 'debts.manage', 'reports.view'],
        ]);

        // Platform Super Admin under default-tenant
        config(['saas.super_admin_email' => 'superadmin@hysam.com']);
        $this->platformAdmin = User::create([
            'id' => 'user-p7-platform-root',
            'tenant_id' => 'default-tenant',
            'name' => 'Root Platform Overseer',
            'email' => 'superadmin@hysam.com',
            'password' => bcrypt('PlatformSecret123!'),
            'role' => 'super_admin',
            'disabled' => false,
        ]);

        $this->productA = Product::create([
            'id' => 'prod-p7-inverter',
            'tenant_id' => $this->tenant->id,
            'name' => 'Solar Inverter 3.5KVA',
            'code' => 'SOL-INV-35',
            'category' => 'Solar',
            'unitPrice' => 350000.00,
            'costPrice' => 290000.00,
            'currentStock' => 20,
            'warehouse_id' => $this->warehouseA->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseB->id,
            'physical_stock' => 15,
            'allocated_stock' => 0,
        ]);
    }

    /**
     * Test 1: Service-layer warehouse authority strictly rejects branch cashier mutating another branch.
     */
    public function test_service_assertUserWarehouseAuthority_blocks_branch_employee_from_operating_on_foreign_branch(): void
    {
        $this->actingAs($this->cashierA);

        // 1. Stock In on foreign warehouse B must throw AuthorizationException
        try {
            $this->stockService->recordStockIn(
                $this->productA->id,
                $this->warehouseB->id,
                5,
                'Supplier XYZ',
                $this->cashierA->id,
                $this->cashierA->name
            );
            $this->fail("Expected AuthorizationException when cashierA attempts recordStockIn at foreign warehouse B");
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString("cannot operate on Branch #{$this->warehouseB->id}", $e->getMessage());
        }

        // 2. Record Sale on foreign warehouse B must throw AuthorizationException
        try {
            $this->stockService->recordSale(
                [
                    'id' => (string) Str::uuid(),
                    'warehouse_id' => $this->warehouseB->id,
                    'customerName' => 'Walk-in Customer',
                    'totalAmount' => 350000.00,
                    'paidAmount' => 350000.00,
                    'paymentMethod' => 'CASH',
                ],
                [[
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                    'unitPrice' => 350000.00,
                    'totalPrice' => 350000.00,
                ]],
                $this->warehouseB->id,
                true,
                $this->cashierA->id,
                $this->cashierA->name
            );
            $this->fail("Expected AuthorizationException when cashierA attempts recordSale at foreign warehouse B");
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString("cannot operate on Branch #{$this->warehouseB->id}", $e->getMessage());
        }

        // 3. Stock Adjustment write-off on foreign warehouse B must throw AuthorizationException
        try {
            $this->stockService->recordStockAdjustment(
                $this->productA->id,
                $this->warehouseB->id,
                'DAMAGED',
                1,
                'Broken terminal during transit',
                $this->cashierA->id,
                $this->cashierA->name
            );
            $this->fail("Expected AuthorizationException when cashierA attempts recordStockAdjustment at foreign warehouse B");
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString("cannot operate on Branch #{$this->warehouseB->id}", $e->getMessage());
        }

        // 4. Initiate transfer originating from foreign warehouse B must throw AuthorizationException
        try {
            $this->stockService->initiateTransfer(
                $this->warehouseB->id,
                $this->warehouseA->id,
                [['product_id' => $this->productA->id, 'quantity' => 1]],
                'FedEx Intercity',
                $this->cashierA->id,
                $this->cashierA->name
            );
            $this->fail("Expected AuthorizationException when cashierA attempts initiateTransfer from foreign warehouse B");
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString("cannot operate on Branch #{$this->warehouseB->id}", $e->getMessage());
        }
    }

    /**
     * Test 2: Tenant Admin can operate on any branch within their tenant.
     */
    public function test_service_assertUserWarehouseAuthority_allows_tenant_admin_on_any_tenant_branch(): void
    {
        $this->actingAs($this->tenantAdmin);

        // Admin can record stock in on Warehouse A
        $stockA = $this->stockService->recordStockIn(
            $this->productA->id,
            $this->warehouseA->id,
            3,
            'Direct Import',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );
        $this->assertEquals(23, $stockA->physical_stock);

        // Admin can also record stock in on Warehouse B
        $stockB = $this->stockService->recordStockIn(
            $this->productA->id,
            $this->warehouseB->id,
            2,
            'Direct Import',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );
        $this->assertEquals(17, $stockB->physical_stock);
    }

    /**
     * Test 3: Platform Super Admin is forbidden from tenant warehouse mutations.
     */
    public function test_service_assertUserWarehouseAuthority_blocks_platform_user_from_tenant_mutations(): void
    {
        $this->actingAs($this->platformAdmin);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Platform user 'Root Platform Overseer' cannot perform tenant business operations on Branch #{$this->warehouseA->id}");

        $this->stockService->assertUserWarehouseAuthority($this->warehouseA->id);
    }

    /**
     * Test 4: Caller-supplied userId cannot spoof identity or bypass branch authority.
     */
    public function test_caller_supplied_user_id_cannot_spoof_authorization_in_service(): void
    {
        // Cashier A is authenticated, but supplies Admin's userId to recordStockIn on Warehouse B
        $this->actingAs($this->cashierA);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("cannot operate on Branch #{$this->warehouseB->id}");

        // Supplying $this->tenantAdmin->id MUST NOT bypass the fact that the session actor is cashierA
        $this->stockService->recordStockIn(
            $this->productA->id,
            $this->warehouseB->id,
            5,
            'Spoof Supplier',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );
    }

    /**
     * Test 5: DebtController index strictly isolates customer list, brackets, and metrics for branch employees.
     */
    public function test_debt_controller_index_strictly_isolates_customers_and_brackets_for_branch_employee(): void
    {
        // Customer 1: Has 150,000 debt originating at Warehouse B (Port Harcourt)
        $customerPHC = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chief Okon (Port Harcourt)',
            'phone' => '08033333333',
            'total_debt' => 150000.00,
        ]);
        $salePHC = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerId' => $customerPHC->id,
            'customerName' => $customerPHC->name,
            'userId' => $this->cashierB->id,
            'userName' => $this->cashierB->name,
            'totalAmount' => 200000.00,
            'paidAmount' => 50000.00,
            'cashAmount' => 50000.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'PART_PAYMENT',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $salePHC->id,
            'amount' => 50000.00,
            'method' => 'CASH',
            'recordedBy' => $this->cashierB->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Customer 2: Has 15,000 debt originating at Warehouse A (Lagos Mainland)
        $customerLOS = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mr. Babatunde (Lagos)',
            'phone' => '08022222222',
            'total_debt' => 150000.00, // Even if total_debt has legacy or cross-branch numbers
        ]);
        $saleLOS = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerId' => $customerLOS->id,
            'customerName' => $customerLOS->name,
            'userId' => $this->cashierA->id,
            'userName' => $this->cashierA->name,
            'totalAmount' => 50000.00,
            'paidAmount' => 35000.00, // 15,000 debt at Lagos branch
            'cashAmount' => 35000.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'PART_PAYMENT',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleLOS->id,
            'amount' => 35000.00,
            'method' => 'CASH',
            'recordedBy' => $this->cashierA->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Acting as Lagos Cashier
        $this->actingAs($this->cashierA);

        $response = $this->get(route('debts.index'));
        $response->assertOk();

        // View data assertions:
        // 1. $allCustomers in dropdown MUST NOT include Chief Okon (who only has debt at PHC branch)
        $allCustomers = $response->viewData('allCustomers');
        $this->assertFalse($allCustomers->contains('id', $customerPHC->id), "PHC customer must not leak into Lagos cashier customer dropdown");
        $this->assertTrue($allCustomers->contains('id', $customerLOS->id), "Lagos customer must be in Lagos cashier customer dropdown");

        // 2. $debtors list only contains Lagos debtor
        $debtors = $response->viewData('debtors');
        $this->assertCount(1, $debtors);
        $this->assertEquals($customerLOS->id, $debtors->first()->id);
        $this->assertEquals(15000.00, $debtors->first()->branch_debt);

        // 3. Branch metrics
        $this->assertEquals(15000.00, $response->viewData('totalOutstandingDebt'));
        $this->assertEquals(0, $response->viewData('highRiskDebtorsCount'), "Lagos branch debt is 15k (<100k), so highRisk count must be 0");

        // 4. Debt bracket filtering for LOW (<20k)
        $responseLow = $this->get(route('debts.index', ['debt_bracket' => 'LOW']));
        $responseLow->assertOk();
        $this->assertCount(1, $responseLow->viewData('debtors'));

        // 5. Debt bracket filtering for HIGH (>=100k) should return 0 for Lagos cashier
        $responseHigh = $this->get(route('debts.index', ['debt_bracket' => 'HIGH']));
        $responseHigh->assertOk();
        $this->assertCount(0, $responseHigh->viewData('debtors'));
    }

    /**
     * Test 6: Database migration backfills customer_ledgers.warehouse_id from sales.warehouse_id.
     */
    public function test_customer_ledgers_warehouse_id_backfilled_correctly(): void
    {
        $cust = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Backfill Test Customer',
            'total_debt' => 40000.00,
        ]);

        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerId' => $cust->id,
            'customerName' => $cust->name,
            'userId' => $this->cashierB->id,
            'userName' => $this->cashierB->name,
            'totalAmount' => 40000.00,
            'paidAmount' => 0.00,
            'cashAmount' => 0.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'CREDIT',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Insert unbackfilled ledger directly with null warehouse_id
        $ledgerId = DB::table('customer_ledgers')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $cust->id,
            'sale_id' => $sale->id,
            'warehouse_id' => null,
            'type' => 'DEBT_CREATED',
            'amount' => 40000.00,
            'balance_after' => 40000.00,
            'payment_method' => 'CREDIT',
            'reference_no' => 'BF-001',
            'recorded_by' => 'System',
            'notes' => 'Unbackfilled ledger record',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Execute migration up()
        $migration = require database_path('migrations/2026_09_05_090000_backfill_customer_ledgers_warehouse_id.php');
        $migration->up();

        $updatedLedger = DB::table('customer_ledgers')->where('id', $ledgerId)->first();
        $this->assertEquals($this->warehouseB->id, $updatedLedger->warehouse_id, "warehouse_id must be backfilled from sales.warehouse_id");
    }

    /**
     * Test 7: ReportController topStaff uses event-authoritative payments rather than cached paidAmount.
     */
    public function test_report_controller_top_staff_uses_event_authoritative_payments(): void
    {
        $this->actingAs($this->tenantAdmin);

        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => 'Walk-in Client',
            'userName' => 'Lagos Cashier Emeka',
            'userId' => $this->cashierA->id,
            'totalAmount' => 100000.00,
            'paidAmount' => 100000.00, // Cached field claims 100k
            'cashAmount' => 100000.00,
            'posAmount' => 0.00,
            'paymentMethod' => 'CASH',
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // But event-authoritative payments record 60k cash payment and 10k cash refund -> Net 50k
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale->id,
            'amount' => 60000.00,
            'method' => 'CASH',
            'recordedBy' => $this->cashierA->name,
            'timestamp' => now()->toIso8601String(),
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale->id,
            'amount' => -10000.00,
            'method' => 'REFUND_CASH',
            'recordedBy' => $this->cashierA->name,
            'timestamp' => now()->toIso8601String(),
        ]);



        $response = $this->get(route('reports.index'));
        $response->assertOk();

        $topStaff = $response->viewData('topStaff');
        $emekaStats = $topStaff->firstWhere('name', 'Lagos Cashier Emeka');
        $this->assertNotNull($emekaStats);
        $this->assertEquals(50000.00, $emekaStats['collected'], "Collected amount must match event-authoritative payments (60k - 10k = 50k), not cached 100k");
    }

    /**
     * Test 8: Version 2.0+ backups strictly reject envelope metadata tampering even if data HMAC matches.
     */
    public function test_backup_envelope_hmac_fails_on_metadata_tampering_for_v2_backups(): void
    {
        $backupController = app(BackupController::class);
        $backup = BackupController::generateTenantBackup('Automated System', $this->tenantAdmin, $this->tenant->id);
        $json = Storage::disk('local')->get('backups/' . $backup->filename);
        $envelope = json_decode($json, true);

        // 1. Genuine v2.1.0 envelope passes
        $this->assertNull($backupController->validateBackupIntegrity($envelope, 'TENANT', $this->tenant->id));

        // 2. Tampering with envelope (e.g., swapping tenant_id) but providing data-only HMAC
        $tamperedEnvelope = $envelope;
        $tamperedEnvelope['tenant_id'] = 'another-tenant-999';
        $dataOnlyChecksum = hash_hmac('sha256', json_encode($tamperedEnvelope['data']), config('app.key'));
        $tamperedEnvelope['checksum'] = $dataOnlyChecksum;

        $error = $backupController->validateBackupIntegrity($tamperedEnvelope, 'TENANT', 'another-tenant-999');
        $this->assertNotNull($error);
        $this->assertStringContainsString("checksum mismatch", $error['error'], "v2+ envelope with data-only checksum must fail verification");

        // 3. Genuine legacy v1.0 backup with data-only HMAC passes
        $legacyEnvelope = [
            'version' => '1.0.0',
            'type' => 'TENANT',
            'tenant_id' => $this->tenant->id,
            'timestamp' => now()->toIso8601String(),
            'manifest' => $envelope['manifest'],
            'data' => $envelope['data'],
            'checksum' => $dataOnlyChecksum,
        ];
        $this->assertNull($backupController->validateBackupIntegrity($legacyEnvelope, 'TENANT', $this->tenant->id), "Legacy v1.0 backup with data-only HMAC should pass");
    }
}
