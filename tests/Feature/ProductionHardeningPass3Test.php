<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\InventoryLog;
use App\Models\StockReservation;
use App\Models\StockAdjustment;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Backup;
use App\Models\Setting;
use App\Models\Activity;
use App\Models\CustomRole;
use App\Http\Controllers\BackupController;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductionHardeningPass3Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected User $tenantAdmin;
    protected User $branchWorker;
    protected User $platformAdmin;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@hysam.com',
            'app.key' => 'base64:' . base64_encode('12345678901234567890123456789012'),
        ]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);

        // Platform Admin
        $this->platformAdmin = User::firstOrCreate(
            ['email' => 'superadmin@hysam.com'],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => 'default-tenant',
                'name' => 'Platform Super Admin',
                'password' => bcrypt('secret123'),
                'role' => 'super_admin',
                'permissions' => ['all' => true],
            ]
        );

        // Tenant Alpha
        $this->tenant = Tenant::create([
            'id' => 'tenant-hardening-pass3',
            'name' => 'Pass3 Superstores',
            'status' => 'active',
            'plan' => 'pro',
            'owner_email' => 'pass3@owner.com',
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch Alpha Depot',
            'code' => 'WH-ALPHA-01',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch Beta Depot',
            'code' => 'WH-BETA-02',
            'is_active' => true,
        ]);

        $this->tenantAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant Administrator',
            'email' => 'admin@pass3.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseA->id,
            'permissions' => ['all' => true],
        ]);

        $this->branchWorker = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch A Worker',
            'email' => 'worker@pass3.com',
            'password' => bcrypt('secret123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'permissions' => [
                'pos.access' => true,
                'reports.view' => true,
                'products.write' => true,
                'debt.view' => true,
                'settings.manage' => true,
            ],
        ]);
    }

    /**
     * TEST 1: Tenant restore completely restores warehouses and remaps IDs across all business entities
     */
    public function test_tenant_restore_completely_restores_warehouses_and_remaps_ids()
    {
        $this->actingAs($this->tenantAdmin);
        session(['tenant_id' => $this->tenant->id]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Solar Inverter 3.5kVA',
            'code' => 'SOL-INV-35',
            'category' => 'Power',
            'unitPrice' => 350000.0,
            'currentStock' => 5,
        ]);

        $stockLevel = StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 5,
            'allocated_stock' => 1,
            'min_stock_alert' => 2,
        ]);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chief Okonkwo',
            'phone' => '08033445566',
            'total_debt' => 50000.0,
        ]);

        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => $customer->name,
            'customerId' => (string) $customer->id,
            'totalAmount' => 350000.0,
            'paidAmount' => 300000.0,
            'cashAmount' => 300000.0,
            'posAmount' => 0.0,
            'userId' => $this->tenantAdmin->id,
            'userName' => $this->tenantAdmin->name,
            'deliveryStatus' => 'UNSUPPLIED',
            'status' => 'PARTIAL',
        ]);

        $reservation = StockReservation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouseA->id,
            'customer_id' => (string) $customer->id,
            'reserved_qty' => 1,
            'status' => 'ACTIVE',
        ]);

        $transfer = Transfer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'source_warehouse_id' => $this->warehouseA->id,
            'destination_warehouse_id' => $this->warehouseB->id,
            'status' => 'PENDING',
            'transfer_no' => 'TRF-TEST-001',
            'dispatched_by' => $this->tenantAdmin->id,
            'initiated_by' => $this->tenantAdmin->id,
        ]);

        $adjustment = StockAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'type' => 'DAMAGE',
            'quantity' => 1,
            'reason' => 'Dropped box',
            'recorded_by' => $this->tenantAdmin->name,
        ]);

        $origWhAId = $this->warehouseA->id;
        $origWhBId = $this->warehouseB->id;

        // Generate tenant backup
        $backup = BackupController::generateTenantBackup('Admin', $this->tenantAdmin, $this->tenant->id);
        $json = Storage::disk('local')->get('backups/' . $backup->filename);

        // Wipe records and shift auto-increment primary keys by creating a foreign warehouse
        StockReservation::where('tenant_id', $this->tenant->id)->delete();
        StockAdjustment::where('tenant_id', $this->tenant->id)->delete();
        Transfer::where('tenant_id', $this->tenant->id)->delete();
        Sale::where('tenant_id', $this->tenant->id)->delete();
        StockLevel::where('tenant_id', $this->tenant->id)->delete();
        Customer::withTrashed()->where('tenant_id', $this->tenant->id)->forceDelete();
        Warehouse::withTrashed()->where('tenant_id', $this->tenant->id)->forceDelete();

        // Foreign warehouse shift
        $foreignWh = Warehouse::create([
            'tenant_id' => 'foreign-tenant-xyz',
            'name' => 'Foreign Shifting Depot',
            'code' => 'FOREIGN-SHIFT',
            'is_active' => true,
        ]);

        // Restore Tenant from JSON
        $controller = app(BackupController::class);
        $refMethod = new \ReflectionMethod($controller, 'restoreTenantFromJson');
        $refMethod->setAccessible(true);
        $result = $refMethod->invoke($controller, $json, $this->tenantAdmin, $this->tenant->id);

        $this->assertEquals(['status' => 'ok'], $result);

        // Assert warehouses were restored with NEW IDs
        $restoredWarehouses = Warehouse::where('tenant_id', $this->tenant->id)->get();
        $this->assertCount(2, $restoredWarehouses);
        $restoredWhA = $restoredWarehouses->where('code', 'WH-ALPHA-01')->first();
        $restoredWhB = $restoredWarehouses->where('code', 'WH-BETA-02')->first();
        $this->assertNotNull($restoredWhA);
        $this->assertNotNull($restoredWhB);
        $this->assertNotEquals($origWhAId, $restoredWhA->id);

        // Assert remapping on Sale
        $restoredSale = Sale::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals($restoredWhA->id, $restoredSale->warehouse_id);

        // Assert remapping on StockLevel
        $restoredStockLevel = StockLevel::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals($restoredWhA->id, $restoredStockLevel->warehouse_id);

        // Assert remapping on StockReservation
        $restoredReservation = StockReservation::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals($restoredWhA->id, $restoredReservation->warehouse_id);

        // Assert remapping on StockAdjustment
        $restoredAdjustment = StockAdjustment::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals($restoredWhA->id, $restoredAdjustment->warehouse_id);

        // Assert remapping on Transfer
        $restoredTransfer = Transfer::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals($restoredWhA->id, $restoredTransfer->source_warehouse_id);
        $this->assertEquals($restoredWhB->id, $restoredTransfer->destination_warehouse_id);
    }

    /**
     * TEST 2: Platform restore verifies HMAC checksum and restores tenants and custom roles
     */
    public function test_platform_restore_verifies_hmac_and_restores_tenants_and_custom_roles()
    {
        $this->actingAs($this->platformAdmin);

        CustomRole::create([
            'id' => 'platform-logistics',
            'label' => 'Platform Logistics Officer',
            'description' => 'Platform wide role',
            'isSystem' => false,
        ]);

        $backup = BackupController::generatePlatformBackup('Root SuperAdmin', $this->platformAdmin);
        $json = Storage::disk('local')->get('backups/' . $backup->filename);

        $controller = app(BackupController::class);
        $refMethod = new \ReflectionMethod($controller, 'restorePlatformFromJson');
        $refMethod->setAccessible(true);

        // 1. Tamper test: Corrupt payload should fail HMAC verification
        $corruptedJson = str_replace('Pass3 Superstores', 'Tampered Superstores', $json);
        $failResult = $refMethod->invoke($controller, $corruptedJson, $this->platformAdmin);
        $this->assertArrayHasKey('error', $failResult);
        $this->assertStringContainsString('integrity verification failed', $failResult['error']);

        // 2. Untampered payload restores successfully
        $okResult = $refMethod->invoke($controller, $json, $this->platformAdmin);
        $this->assertEquals(['status' => 'ok'], $okResult);

        $this->assertNotNull(CustomRole::where('label', 'Platform Logistics Officer')->first());
        $this->assertNotNull(Tenant::find($this->tenant->id));
    }

    /**
     * TEST 3: calculateInvoiceBalance strictly derives from Payment events without paidAmount fallback
     */
    public function test_calculate_invoice_balance_strictly_derives_from_payment_events_without_paid_amount_fallback()
    {
        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => 'Stale State Customer',
            'totalAmount' => 100000.0,
            'paidAmount' => 90000.0, // Stale/corrupt client value in DB
            'cashAmount' => 0.0,
            'posAmount' => 0.0,
            'userId' => $this->tenantAdmin->id,
            'userName' => $this->tenantAdmin->name,
            'deliveryStatus' => 'DELIVERED',
            'status' => 'PARTIAL',
        ]);

        // Only one authoritative Payment event exists of ₦30,000
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

        // Authoritative balance must be ₦100,000 - ₦30,000 = ₦70,000 (NOT ₦10,000 based on stale paidAmount 90k)
        $balance = $this->accountingService->calculateInvoiceBalance($sale);
        $this->assertEquals(70000.0, $balance);
    }

    /**
     * TEST 4: Auditor controller isolates displayed customer debt to user's assigned warehouse
     */
    public function test_auditor_branch_scoping_strictly_isolates_debt_amounts_to_assigned_branch()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Multi-Branch Customer Danladi',
            'phone' => '08055667788',
            'total_debt' => 100000.0, // ₦20,000 at Branch A, ₦80,000 at Branch B
        ]);

        // Branch A Sale: ₦30,000 total, ₦10,000 paid -> ₦20,000 open debt
        $saleA = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 30000.0,
            'paidAmount' => 10000.0,
            'cashAmount' => 10000.0,
            'posAmount' => 0.0,
            'userId' => $this->branchWorker->id,
            'userName' => $this->branchWorker->name,
            'deliveryStatus' => 'DELIVERED',
            'status' => 'PARTIAL',
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleA->id,
            'amount' => 10000.0,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->branchWorker->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Branch B Sale: ₦100,000 total, ₦20,000 paid -> ₦80,000 open debt
        $saleB = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 100000.0,
            'paidAmount' => 20000.0,
            'cashAmount' => 20000.0,
            'posAmount' => 0.0,
            'userId' => $this->tenantAdmin->id,
            'userName' => $this->tenantAdmin->name,
            'deliveryStatus' => 'DELIVERED',
            'status' => 'PARTIAL',
        ]);
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleB->id,
            'amount' => 20000.0,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->tenantAdmin->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Acting as Branch A Worker: must only see ₦20,000 debt for Danladi, NOT ₦100,000!
        $response = $this->actingAs($this->branchWorker)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('auditor.index'));

        $response->assertStatus(200);
        $debtors = $response->viewData('debtors');
        $totalCustomerDebt = $response->viewData('totalCustomerDebt');

        $this->assertCount(1, $debtors);
        $this->assertEquals(20000.0, $debtors->first()->total_debt);
        $this->assertEquals(20000.0, $totalCustomerDebt);
    }

    /**
     * TEST 5: getPeriodSummary() branch scopes debt recoveries and outstanding liabilities
     */
    public function test_get_period_summary_branch_scopes_debt_recoveries_and_outstanding_liabilities()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch Scoped Customer',
            'phone' => '08099881122',
            'total_debt' => 150000.0,
        ]);

        // Branch A Sale with ₦50,000 debt
        $saleA = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 50000.0,
            'paidAmount' => 0.0,
            'cashAmount' => 0.0,
            'posAmount' => 0.0,
            'userId' => $this->branchWorker->id,
            'userName' => $this->branchWorker->name,
            'deliveryStatus' => 'DELIVERED',
            'status' => 'UNPAID',
        ]);

        // Branch B Sale with ₦100,000 debt
        $saleB = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 100000.0,
            'paidAmount' => 0.0,
            'cashAmount' => 0.0,
            'posAmount' => 0.0,
            'userId' => $this->tenantAdmin->id,
            'userName' => $this->tenantAdmin->name,
            'deliveryStatus' => 'DELIVERED',
            'status' => 'UNPAID',
        ]);

        // Branch A debt summary must show ₦50,000 outstanding (NOT ₦150,000!)
        $this->actingAs($this->branchWorker);
        session(['tenant_id' => $this->tenant->id]);

        $summary = $this->accountingService->getPeriodSummary(['warehouse_id' => $this->warehouseA->id]);
        $this->assertEquals(50000.0, $summary['currentOutstanding']);
    }

    /**
     * TEST 6: ProductController::importCsv() enforces branch lock and asserts tenant warehouse
     */
    public function test_csv_import_enforces_branch_locking_and_asserts_tenant_warehouse()
    {
        $csvContent = "name,code,category,unit_price,cost_price,current_stock\nTest Item,TST-CSV-01,General,1000,700,10\n";
        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        // Branch Worker attempts to import into foreign warehouse ID 9999
        $response = $this->actingAs($this->branchWorker)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.import.csv'), [
                'csv_file' => $file,
                'warehouse_id' => 9999, // Attempted spoof
            ]);

        $response->assertRedirect(route('products.index'));

        // Stock was imported into user's assigned warehouse (Branch A), NOT 9999
        $product = Product::where('code', 'TST-CSV-01')->first();
        $this->assertNotNull($product);

        $stockLevel = StockLevel::where('product_id', $product->id)->first();
        $this->assertNotNull($stockLevel);
        $this->assertEquals($this->warehouseA->id, $stockLevel->warehouse_id);
    }

    /**
     * TEST 7: ProductController::store() routes initial stock through StockService::recordStockIn()
     */
    public function test_product_store_routes_initial_stock_through_canonical_stock_service()
    {
        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.store'), [
                'name' => 'Unified Engine Product',
                'code' => 'UEP-001',
                'category' => 'Hardware',
                'unitPrice' => 15000.0,
                'costPrice' => 10000.0,
                'initial_stock' => 12,
                'warehouse_id' => $this->warehouseA->id,
            ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::where('code', 'UEP-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals(12, $product->currentStock);

        // Verified that canonical StockService inventory log was recorded with type STOCK_IN and description
        $log = InventoryLog::where('productId', $product->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('STOCK_IN', $log->type);
        $this->assertStringContainsString('Initial catalog registration stock', $log->description);
        $this->assertEquals(12, $log->quantity);
    }

    /**
     * TEST 8: Tenant purge physically deletes backup files from disk
     */
    public function test_tenant_purge_physically_deletes_backup_files_from_disk()
    {
        $purgeTenant = Tenant::create([
            'id' => 'tenant-file-purge-target',
            'name' => 'Purge Target File Ltd',
            'owner_email' => 'filepurge@target.com',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $filename = 'backup_tenant-file-purge-target_test.json';
        Storage::disk('local')->put('backups/' . $filename, json_encode(['test' => 'data']));
        $this->assertTrue(Storage::disk('local')->exists('backups/' . $filename));

        Backup::create([
            'id' => 'BK-FILE-PURGE-01',
            'tenant_id' => $purgeTenant->id,
            'filename' => $filename,
            'size' => 100,
            'created_by' => 'Purge Test',
        ]);

        $this->actingAs($this->platformAdmin);
        $saasController = app(\App\Http\Controllers\SaaS\SaaSController::class);
        $saasController->deleteTenant($purgeTenant->id);

        // Database record deleted
        $this->assertEquals(0, Backup::where('tenant_id', $purgeTenant->id)->count());
        // Physical file deleted from local storage disk
        $this->assertFalse(Storage::disk('local')->exists('backups/' . $filename));
    }
}
