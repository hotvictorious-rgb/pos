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
use App\Models\StockReservation;
use App\Models\CustomRole;
use App\Http\Controllers\BackupController;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass4Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected User $tenantAdmin;
    protected User $branchWorker;
    protected User $branchStorekeeper;
    protected User $platformAdmin;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenant = Tenant::create([
            'id' => 'tenant-hardening-pass4',
            'name' => 'Pass4 Superstores Ltd',
            'owner_email' => 'admin@pass4.com',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch A Main Store',
            'code' => 'WH-P4-A',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch B Annex Store',
            'code' => 'WH-P4-B',
            'is_active' => true,
        ]);

        $this->tenantAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant Administrator',
            'email' => 'admin@pass4.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'permissions' => [
                'pos.access' => true,
                'pos.view' => true,
                'stock.manage' => true,
                'stock.in' => true,
                'stock.transfer' => true,
                'returns.process' => true,
                'settings.manage' => true,
                'tenant.backup' => true,
                'reports.view' => true,
                'transactions.view' => true,
                'debt.view' => true,
            ],
        ]);

        $this->branchWorker = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch A Cashier',
            'email' => 'cashier@pass4.com',
            'password' => bcrypt('secret123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'permissions' => [
                'pos.access' => true,
                'pos.view' => true,
                'reports.view' => true,
                'transactions.view' => true,
                'debt.view' => true,
                'returns.process' => true,
            ],
        ]);

        $this->branchStorekeeper = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch A Storekeeper',
            'email' => 'storekeeper@pass4.com',
            'password' => bcrypt('secret123'),
            'role' => 'storekeeper',
            'warehouse_id' => $this->warehouseA->id,
            'permissions' => [
                'pos.view' => true,
                'stock.manage' => true,
                'reports.view' => true,
                'transactions.view' => true,
                'returns.process' => true,
            ],
        ]);

        $this->platformAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => null,
            'name' => 'Platform SuperAdmin',
            'email' => 'root@platform.internal',
            'password' => bcrypt('secret123'),
            'role' => 'super_admin',
            'permissions' => [
                'platform.admin' => true,
                'platform.backup' => true,
                'platform.restore' => true,
            ],
        ]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);
    }

    /**
     * TEST 1: Partial unsupplied fulfillment -> return restores physical stock and preserves unfulfilled allocation
     */
    public function test_partial_fulfillment_return_restores_physical_stock_and_preserves_unfulfilled_allocation()
    {
        $this->actingAs($this->tenantAdmin);
        session(['tenant_id' => $this->tenant->id]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Industrial Cable Drum 100m',
            'code' => 'ICD-100M',
            'category' => 'Electrical',
            'costPrice' => 40000.0,
            'unitPrice' => 50000.0,
            'currentStock' => 20,
        ]);

        $stock = StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
            'min_stock_alert' => 2,
        ]);

        // 1. Create unsupplied sale for 10 units
        $sale = $this->stockService->recordSale(
            [
                'totalAmount' => 500000.0,
                'paidAmount' => 500000.0,
                'customerName' => 'Alhaji Gambo',
                'tender' => ['cashAmount' => 500000.0, 'posAmount' => 0.0],
            ],
            [
                ['productId' => $product->id, 'quantity' => 10, 'unitPrice' => 50000.0]
            ],
            $this->warehouseA->id,
            false,
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        $stock->refresh();
        $this->assertEquals(20, $stock->physical_stock);
        $this->assertEquals(10, $stock->allocated_stock);
        $this->assertEquals('UNSUPPLIED', $sale->deliveryStatus);

        // 2. Customer physically collects 5 units via fulfillment
        $this->stockService->fulfillStockReservation(
            $sale->id,
            $product->id,
            $this->warehouseA->id,
            5,
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        $stock->refresh();
        $this->assertEquals(15, $stock->physical_stock);
        $this->assertEquals(5, $stock->allocated_stock);

        $reservation = StockReservation::where('sale_id', $sale->id)->first();
        $this->assertEquals(5, $reservation->held_by_customer_qty);
        $this->assertEquals(5, $reservation->outstanding_qty);

        // 3. Customer returns those 5 already-collected units
        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $product->id, 'quantity' => 5]],
            $this->warehouseA->id,
            'CASH_REFUND',
            'Goods returned by customer',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        $stock->refresh();
        // INVARIANT VERIFIED: Physical stock is restored to 20! Allocated stock remains 5!
        $this->assertEquals(20, $stock->physical_stock);
        $this->assertEquals(5, $stock->allocated_stock);

        $reservation->refresh();
        $this->assertEquals(5, $reservation->returned_fulfilled_qty);
        $this->assertEquals(0, $reservation->held_by_customer_qty);
        $this->assertEquals(5, $reservation->outstanding_qty);
        $this->assertEquals('PARTIALLY_FULFILLED', $reservation->status);
    }

    /**
     * TEST 2: Mixed unsupplied return partitions physical stock restoration and allocated cancellation
     */
    public function test_mixed_unsupplied_return_partitions_physical_stock_and_allocated_cancellation()
    {
        $this->actingAs($this->tenantAdmin);
        session(['tenant_id' => $this->tenant->id]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Commercial Generator 5kVA',
            'code' => 'GEN-5KVA',
            'category' => 'Equipment',
            'costPrice' => 160000.0,
            'unitPrice' => 200000.0,
            'currentStock' => 20,
        ]);

        $stock = StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
            'min_stock_alert' => 2,
        ]);

        // Sale for 10 units unsupplied
        $sale = $this->stockService->recordSale(
            [
                'totalAmount' => 2000000.0,
                'paidAmount' => 2000000.0,
                'customerName' => 'Mixed Return Corp',
                'tender' => ['cashAmount' => 2000000.0, 'posAmount' => 0.0],
            ],
            [
                ['productId' => $product->id, 'quantity' => 10, 'unitPrice' => 200000.0]
            ],
            $this->warehouseA->id,
            false,
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        // Fulfill 4 units
        $this->stockService->fulfillStockReservation(
            $sale->id,
            $product->id,
            $this->warehouseA->id,
            4,
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        $stock->refresh();
        $this->assertEquals(16, $stock->physical_stock);
        $this->assertEquals(6, $stock->allocated_stock);

        // Customer returns 7 units (4 were collected, 3 were still reserved)
        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $product->id, 'quantity' => 7]],
            $this->warehouseA->id,
            'CASH_REFUND',
            'Customer returning 4 collected and cancelling 3 uncollected',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        $stock->refresh();
        // INVARIANT VERIFIED:
        // 4 collected units were restored to physical_stock (16 + 4 = 20)
        // 3 uncollected units released allocated_stock (6 - 3 = 3)
        $this->assertEquals(20, $stock->physical_stock);
        $this->assertEquals(3, $stock->allocated_stock);

        $reservation = StockReservation::where('sale_id', $sale->id)->first();
        $this->assertEquals(4, $reservation->returned_fulfilled_qty);
        $this->assertEquals(3, $reservation->cancelled_qty);
        $this->assertEquals(3, $reservation->outstanding_qty);
        $this->assertEquals(0, $reservation->held_by_customer_qty);
    }

    /**
     * TEST 3: Receipt endpoint restricts all branch-scoped roles, not just cashiers
     */
    public function test_receipt_endpoint_restricts_all_branch_scoped_roles_not_just_cashiers()
    {
        $saleBranchB = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => 'Customer of Branch B',
            'totalAmount' => 75000.0,
            'paidAmount' => 75000.0,
            'cashAmount' => 75000.0,
            'posAmount' => 0.0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->tenantAdmin->id,
            'userName' => $this->tenantAdmin->name,
        ]);

        // Branch A Storekeeper (isBranchScoped = true, role = storekeeper)
        $response = $this->actingAs($this->branchStorekeeper)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('pos.receipt', $saleBranchB->id));

        $response->assertStatus(403);
    }

    /**
     * TEST 4: Transaction history and total open debt are strictly isolated to assigned branch
     */
    public function test_transaction_history_and_total_debt_are_strictly_isolated_to_assigned_branch()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Multi-Branch Debtor',
            'phone' => '08077665544',
            'total_debt' => 100000.0, // ₦30k Branch A, ₦70k Branch B
        ]);

        $saleA = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 50000.0,
            'paidAmount' => 20000.0,
            'cashAmount' => 20000.0,
            'posAmount' => 0.0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->branchWorker->id,
            'userName' => $this->branchWorker->name,
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleA->id,
            'amount' => 20000.0,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->branchWorker->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        $saleB = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 100000.0,
            'paidAmount' => 30000.0,
            'cashAmount' => 30000.0,
            'posAmount' => 0.0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->tenantAdmin->id,
            'userName' => $this->tenantAdmin->name,
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleB->id,
            'amount' => 30000.0,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->tenantAdmin->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        $response = $this->actingAs($this->branchWorker)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('transactions.index', ['tab' => 'debts']));

        $response->assertStatus(200);

        // INVARIANT VERIFIED: Branch A worker sees ONLY Branch A debt (₦30,000), not global ₦100,000!
        $totalOpenDebt = $response->viewData('totalOpenDebt');
        $this->assertEquals(30000.0, $totalOpenDebt);
    }

    /**
     * TEST 5: Backup restore strictly rejects missing checksum and manifest discrepancies
     */
    public function test_backup_restore_strictly_rejects_missing_checksum_and_manifest_discrepancies()
    {
        $this->actingAs($this->platformAdmin);

        CustomRole::create([
            'id' => 'sec-ops-role',
            'label' => 'Security Operations Role',
            'description' => 'Platform security role',
            'isSystem' => false,
        ]);

        $backup = BackupController::generatePlatformBackup('Root Security', $this->platformAdmin);
        $json = Storage::disk('local')->get('backups/' . $backup->filename);

        $controller = app(BackupController::class);
        $refMethod = new \ReflectionMethod($controller, 'restorePlatformFromJson');
        $refMethod->setAccessible(true);

        // 1. Missing checksum must fail
        $noChecksumData = json_decode($json, true);
        unset($noChecksumData['checksum']);
        $failMissingChecksum = $refMethod->invoke($controller, json_encode($noChecksumData), $this->platformAdmin);
        $this->assertArrayHasKey('error', $failMissingChecksum);
        $this->assertStringContainsString('checksum is missing', $failMissingChecksum['error']);

        // 2. Manifest count mismatch must fail
        $badManifestData = json_decode($json, true);
        $badManifestData['manifest']['tenants'] = 999;
        $signingKey = config('app.key');
        $badManifestData['checksum'] = hash_hmac('sha256', json_encode($badManifestData['data']), $signingKey);
        $failManifest = $refMethod->invoke($controller, json_encode($badManifestData), $this->platformAdmin);
        $this->assertArrayHasKey('error', $failManifest);
        $this->assertStringContainsString('Manifest count mismatch', $failManifest['error']);
    }

    /**
     * TEST 6: Backup upload validates integrity before saving to disk
     */
    public function test_backup_upload_validates_integrity_before_saving_to_disk()
    {
        $this->actingAs($this->tenantAdmin);
        session(['tenant_id' => $this->tenant->id]);

        $corruptPayload = [
            'version' => '2.1.0',
            'type' => 'TENANT',
            'tenant_id' => $this->tenant->id,
            'checksum' => 'invalid-tampered-checksum',
            'manifest' => ['users' => 1],
            'data' => ['users' => []],
        ];

        $uploadedFile = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            'malicious_backup.json',
            json_encode($corruptPayload)
        );

        $response = $this->post(route('settings.backups.upload'), [
            'backup_file' => $uploadedFile,
        ]);

        $response->assertStatus(400);

        // INVARIANT VERIFIED: Corrupted backup was never persisted to disk storage!
        $allFiles = Storage::disk('local')->files('backups');
        foreach ($allFiles as $f) {
            $this->assertStringNotContainsString('malicious_backup', $f);
        }
    }

    /**
     * TEST 7: period_summary newDebtCreated derives from authoritative payment events
     */
    public function test_period_summary_new_debt_created_derives_from_authoritative_payment_events()
    {
        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => 'Payment Event Test Customer',
            'totalAmount' => 100000.0,
            'paidAmount' => 95000.0, // Stale/cached client amount in DB
            'cashAmount' => 30000.0,
            'posAmount' => 0.0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->tenantAdmin->id,
            'userName' => $this->tenantAdmin->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Actual payment event recorded on the ledger: ₦30,000
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $sale->id,
            'amount' => 30000.0,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->tenantAdmin->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        $summary = $this->accountingService->getPeriodSummary(['period' => 'ALL']);

        // INVARIANT VERIFIED:
        // New debt created is ₦100,000 - ₦30,000 = ₦70,000 (NOT ₦5,000 from stale paidAmount 95k!)
        $this->assertEquals(70000.0, $summary['newDebtCreated']);
    }

    /**
     * TEST 8: Sale creation strictly rejects foreign tenant customer_id
     */
    public function test_sale_creation_strictly_rejects_foreign_tenant_customer_id()
    {
        $this->actingAs($this->tenantAdmin);
        session(['tenant_id' => $this->tenant->id]);

        $foreignTenant = Tenant::create([
            'id' => 'tenant-foreign-victim',
            'name' => 'Foreign Victim Tenant',
            'owner_email' => 'victim@foreign.com',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $foreignCustomer = Customer::create([
            'tenant_id' => $foreignTenant->id,
            'name' => 'Foreign Customer Cross-Tenant',
            'phone' => '08099887766',
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Testing Item',
            'code' => 'TEST-001',
            'category' => 'General',
            'costPrice' => 800.0,
            'unitPrice' => 1000.0,
            'currentStock' => 5,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 5,
            'allocated_stock' => 0,
            'min_stock_alert' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Security Violation: Customer #' . $foreignCustomer->id . " belongs to tenant '{$foreignTenant->id}', not active tenant '{$this->tenant->id}'.");

        $this->stockService->recordSale(
            [
                'totalAmount' => 1000.0,
                'paidAmount' => 1000.0,
                'customerId' => $foreignCustomer->id,
                'tender' => ['cashAmount' => 1000.0, 'posAmount' => 0.0],
            ],
            [
                ['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 1000.0]
            ],
            $this->warehouseA->id,
            true,
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );
    }
}
