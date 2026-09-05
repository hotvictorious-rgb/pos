<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\StockAdjustment;
use App\Models\Backup;
use App\Http\Controllers\BackupController;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductionHardeningPass2Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $tenantAdmin;
    protected User $platformAdmin;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        config(['saas.enabled' => true]);
        config(['saas.super_admin_email' => 'superadmin@hysam.com']);

        // Default Tenant & Platform Admin
        $defaultTenant = Tenant::firstOrCreate(
            ['id' => 'default-tenant'],
            [
                'name' => 'Victorious Platform System',
                'owner_email' => 'superadmin@hysam.com',
                'status' => 'active',
                'plan' => 'enterprise',
            ]
        );

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

        // Tenant A & Tenant Admin
        $this->tenant = Tenant::create([
            'id' => 'tenant-hardening-alpha',
            'name' => 'Hardening Alpha Superstores',
            'status' => 'active',
            'plan' => 'pro',
            'owner_email' => 'alpha@owner.com',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Main Depot',
            'code' => 'ALPHA-MAIN',
            'is_active' => true,
        ]);

        $this->tenantAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha Admin',
            'email' => 'admin@alpha.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouse->id,
            'permissions' => ['all' => true],
        ]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);
    }

    /**
     * TEST 1: StockReservation schema & PK/FK string UUID product_id
     */
    public function test_stock_reservation_supports_string_uuid_product_id()
    {
        session(['tenant_id' => $this->tenant->id]);

        $productUuid = (string) Str::uuid();
        $product = Product::create([
            'id' => $productUuid,
            'tenant_id' => $this->tenant->id,
            'name' => 'Premium Basmati Rice',
            'code' => 'RICE-UUID-99',
            'category' => 'Food',
            'unitPrice' => 45000.0,
            'currentStock' => 10,
        ]);

        $reservationId = (string) Str::uuid();
        $reservation = StockReservation::create([
            'id' => $reservationId,
            'tenant_id' => $this->tenant->id,
            'sale_id' => (string) Str::uuid(),
            'product_id' => $product->id, // string UUID
            'warehouse_id' => $this->warehouse->id,
            'reserved_qty' => 5,
            'fulfilled_qty' => 0,
            'status' => 'ACTIVE',
        ]);

        $this->assertIsString($reservation->product_id);
        $this->assertEquals($productUuid, $reservation->product_id);

        $fetched = StockReservation::find($reservationId);
        $this->assertNotNull($fetched);
        $this->assertEquals($productUuid, $fetched->product_id);
        $this->assertEquals($product->name, $fetched->product->name);
    }

    /**
     * TEST 2: Tenant backup contains StockReservation, CustomerLedger, StockAdjustment, and excludes CustomRole
     */
    public function test_tenant_backup_contains_all_business_entities_and_excludes_custom_roles()
    {
        session(['tenant_id' => $this->tenant->id]);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Emeka Wholesale',
            'phone' => '08011223344',
            'total_debt' => 20000.0,
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Generator 5KVA',
            'code' => 'GEN-5KVA',
            'category' => 'Equipment',
            'unitPrice' => 150000.0,
            'currentStock' => 2,
        ]);

        StockReservation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => 'SALE-RES-1',
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'reserved_qty' => 1,
            'customer_id' => (string) $customer->id,
            'status' => 'ACTIVE',
        ]);

        CustomerLedger::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'type' => 'INVOICE',
            'amount' => 20000.0,
            'balance_after' => 20000.0,
            'payment_method' => 'DEBT_ISSUED',
            'reference_no' => 'SALE-RES-1',
            'recorded_by' => 'Cashier',
        ]);

        StockAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'type' => 'DAMAGE',
            'quantity' => 1,
            'reason' => 'Damaged during transit',
            'recorded_by' => $this->tenantAdmin->name,
        ]);

        $backup = BackupController::generateTenantBackup('Admin', $this->tenantAdmin, $this->tenant->id);
        $json = Storage::disk('local')->get('backups/' . $backup->filename);
        $content = json_decode($json, true);

        $this->assertEquals('2.1.0', $content['version']);
        $this->assertNotEmpty($content['checksum']);
        $this->assertArrayHasKey('stock_reservations', $content['data']);
        $this->assertArrayHasKey('customer_ledgers', $content['data']);
        $this->assertArrayHasKey('stock_adjustments', $content['data']);
        $this->assertArrayNotHasKey('custom_roles', $content['data']);

        $this->assertCount(1, $content['data']['stock_reservations']);
        $this->assertCount(1, $content['data']['customer_ledgers']);
        $this->assertCount(1, $content['data']['stock_adjustments']);
    }

    /**
     * TEST 3: Tenant restore remaps customer IDs across Sale, CustomerLedger, StockReservation
     */
    public function test_tenant_restore_remaps_customer_ids_to_prevent_corruption()
    {
        session(['tenant_id' => $this->tenant->id]);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chukwudi General Merchant',
            'phone' => '08099887766',
            'total_debt' => 15000.0,
        ]);
        $origCustomerId = $customer->id;

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Solar Battery 200Ah',
            'code' => 'SOLAR-200AH',
            'category' => 'Power',
            'unitPrice' => 85000.0,
            'currentStock' => 5,
        ]);

        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerName' => $customer->name,
            'customerId' => (string) $origCustomerId,
            'totalAmount' => 85000.0,
            'paidAmount' => 70000.0,
            'cashAmount' => 70000.0,
            'posAmount' => 0.0,
            'userId' => $this->tenantAdmin->id,
            'deliveryStatus' => 'DELIVERED',
            'status' => 'PARTIAL',
        ]);

        $ledger = CustomerLedger::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $origCustomerId,
            'sale_id' => $sale->id,
            'type' => 'INVOICE',
            'amount' => 15000.0,
            'balance_after' => 15000.0,
            'payment_method' => 'DEBT_ISSUED',
            'reference_no' => $sale->id,
            'recorded_by' => 'Admin',
        ]);

        $reservation = StockReservation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'reserved_qty' => 1,
            'customer_id' => (string) $origCustomerId,
            'status' => 'ACTIVE',
        ]);

        // Generate backup snapshot
        $backup = BackupController::generateTenantBackup('Admin', $this->tenantAdmin, $this->tenant->id);
        $json = Storage::disk('local')->get('backups/' . $backup->filename);

        // Wipe records from DB
        StockReservation::where('tenant_id', $this->tenant->id)->delete();
        CustomerLedger::where('tenant_id', $this->tenant->id)->delete();
        Sale::where('tenant_id', $this->tenant->id)->delete();
        Customer::withTrashed()->where('tenant_id', $this->tenant->id)->forceDelete();

        // Create a dummy customer so auto-increment ID shifts
        Customer::create([
            'tenant_id' => 'other-tenant',
            'name' => 'Foreign Customer To Shift Auto-Increment',
            'phone' => '09000000000',
        ]);

        // Execute Restore
        $controller = app(BackupController::class);
        $refMethod = new \ReflectionMethod($controller, 'restoreTenantFromJson');
        $refMethod->setAccessible(true);
        $result = $refMethod->invoke($controller, $json, $this->tenantAdmin, $this->tenant->id);

        $this->assertEquals(['status' => 'ok'], $result);

        // Assert restored customer has a new auto-increment ID
        $restoredCustomer = Customer::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($restoredCustomer);
        $newCustomerId = $restoredCustomer->id;
        $this->assertNotEquals($origCustomerId, $newCustomerId);

        // Assert Sale.customerId points to newCustomerId
        $restoredSale = Sale::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($restoredSale);
        $this->assertEquals((string)$newCustomerId, (string)$restoredSale->customerId);

        // Assert CustomerLedger.customer_id points to newCustomerId
        $restoredLedger = CustomerLedger::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($restoredLedger);
        $this->assertEquals($newCustomerId, $restoredLedger->customer_id);

        // Assert StockReservation.customer_id points to newCustomerId
        $restoredReservation = StockReservation::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($restoredReservation);
        $this->assertEquals((string)$newCustomerId, (string)$restoredReservation->customer_id);
    }

    /**
     * TEST 4: SaaS admin dashboard contains zero tenant business data
     */
    public function test_saas_admin_dashboard_excludes_all_tenant_business_data()
    {
        session(['tenant_id' => 'default-tenant']);

        // Create tenant business data
        Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Secret Product',
            'code' => 'SECRET-1',
            'category' => 'Secret',
            'unitPrice' => 1000.0,
            'currentStock' => 10,
        ]);

        Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerName' => 'Walk-in',
            'totalAmount' => 1000.0,
            'paidAmount' => 1000.0,
            'cashAmount' => 1000.0,
            'posAmount' => 0.0,
            'userId' => $this->tenantAdmin->id,
            'deliveryStatus' => 'DELIVERED',
            'status' => 'COMPLETED',
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->withSession(['tenant_id' => 'default-tenant'])
            ->get('/saas/admin');

        $response->assertStatus(200);
        // The view data must not include totalProductsPlatform or totalSalesPlatform
        $this->assertArrayNotHasKey('totalProductsPlatform', $response->original->getData());
        $this->assertArrayNotHasKey('totalSalesPlatform', $response->original->getData());

        // The tenants in view data must not count tenant sales
        $tenantsData = $response->original->getData()['tenants'];
        $firstTenant = $tenantsData->where('id', $this->tenant->id)->first();
        $this->assertNull($firstTenant->sales_count);
    }

    /**
     * TEST 5: Cash drawer expected cash has zero debt recovery double-counting
     */
    public function test_cash_drawer_reconciliation_has_zero_debt_recovery_double_counting()
    {
        session(['tenant_id' => $this->tenant->id]);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alhaji Gambo',
            'phone' => '08022334455',
            'total_debt' => 0.0,
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Cement Dangote',
            'code' => 'DANGOTE-CEMENT',
            'category' => 'Building',
            'unitPrice' => 10000.0,
            'currentStock' => 100,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 100,
            'allocated_stock' => 0,
        ]);

        // Sale 1: ₦20,000, paid ₦10,000 Cash, ₦10,000 debt
        $sale = $this->stockService->recordSale(
            [
                'customerId' => $customer->id,
                'customerName' => $customer->name,
                'cashAmount' => 10000.0,
                'posAmount' => 0.0,
            ],
            [['productId' => $product->id, 'quantity' => 2]],
            $this->warehouse->id,
            true,
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        $customer->refresh();
        $this->assertEquals(10000.0, $customer->total_debt);

        // Repayment: Customer pays ₦10,000 CASH debt recovery
        $this->stockService->recordCustomerPayment(
            $customer->id,
            10000.0,
            'CASH',
            'CASH-REC-1',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        $summary = $this->accountingService->getPeriodSummary(['date_preset' => 'today']);

        // Cash collected = ₦10,000 (checkout) + ₦10,000 (debt recovery) = ₦20,000
        $this->assertEquals(20000.0, $summary['cashCollected']);
        $this->assertEquals(10000.0, $summary['cashDebtRecovered']);

        // Crucial invariant: Expected Cash in drawer must equal EXACT physical cash inflow ₦20,000, NOT ₦30,000!
        $this->assertEquals(20000.0, $summary['expectedCashInDrawer']);
    }

    /**
     * TEST 6: Debt payment allocation uses return-adjusted calculateInvoiceBalance
     */
    public function test_debt_payment_allocation_uses_return_adjusted_invoice_balance()
    {
        session(['tenant_id' => $this->tenant->id]);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Tola Boutique',
            'phone' => '08033445566',
            'total_debt' => 0.0,
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Designer Gown',
            'code' => 'GOWN-01',
            'category' => 'Fashion',
            'unitPrice' => 50000.0,
            'currentStock' => 10,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 10,
            'allocated_stock' => 0,
        ]);

        // Invoice: 2 gowns = ₦100,000. Paid ₦40,000 cash. Debt = ₦60,000
        $sale = $this->stockService->recordSale(
            [
                'customerId' => $customer->id,
                'customerName' => $customer->name,
                'cashAmount' => 40000.0,
                'posAmount' => 0.0,
            ],
            [['productId' => $product->id, 'quantity' => 2]],
            $this->warehouse->id,
            true,
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        // Process Return Credit: 1 gown returned with DEBT_REDUCTION of ₦50,000
        $saleItem = $sale->items->first();
        $this->stockService->recordSaleReturn(
            $sale->id,
            [
                [
                    'productId' => $product->id,
                    'quantity' => 1,
                ]
            ],
            $this->warehouse->id,
            'DEBT_REDUCTION',
            'Size exchange',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        $sale->refresh();
        $customer->refresh();

        // Customer debt is now ₦60,000 - ₦50,000 = ₦10,000
        $this->assertEquals(10000.0, $customer->total_debt);

        // Derived balance is exactly ₦10,000
        $this->assertEquals(10000.0, $this->accountingService->calculateInvoiceBalance($sale));

        // Now customer repays ₦10,000 via CASH
        $this->stockService->recordCustomerPayment(
            $customer->id,
            10000.0,
            'CASH',
            'REP-FINAL-10K',
            $this->tenantAdmin->id,
            $this->tenantAdmin->name
        );

        $sale->refresh();
        $customer->refresh();

        $this->assertEquals(0.0, $customer->total_debt);
        $this->assertEquals(0.0, $this->accountingService->calculateInvoiceBalance($sale));
        $this->assertEquals('COMPLETED', $sale->status);
    }

    /**
     * TEST 7: ProductController store enforces warehouse assertion and branch locking
     */
    public function test_product_controller_store_asserts_tenant_warehouse_and_locks_branch()
    {
        // Branch worker assigned to Alpha Main Depot
        $branchWorker = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Storekeeper Branch 1',
            'email' => 'storekeeper@alpha.com',
            'password' => bcrypt('secret123'),
            'role' => 'storekeeper',
            'warehouse_id' => $this->warehouse->id,
            'permissions' => ['products.write' => true],
        ]);

        // Attempting to supply a foreign warehouse ID is overridden to user's assigned warehouse
        $response = $this->actingAs($branchWorker)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.store'), [
                'name' => 'Branch Verified Product',
                'code' => 'BVP-001',
                'category' => 'General',
                'unitPrice' => 5000.0,
                'initial_stock' => 10,
                'warehouse_id' => 9999, // spoofed warehouse ID
            ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::where('code', 'BVP-001')->first();
        $this->assertNotNull($product);

        $stockLevel = StockLevel::where('product_id', $product->id)->first();
        $this->assertNotNull($stockLevel);
        // Assert stock level was created for user's assigned warehouse, NOT 9999
        $this->assertEquals($this->warehouse->id, $stockLevel->warehouse_id);
    }

    /**
     * TEST 8: Tenant purge cascade deletes StockReservation and tenant Backups
     */
    public function test_tenant_purge_deletes_stock_reservations_and_backups()
    {
        $purgeTenant = Tenant::create([
            'id' => 'tenant-to-purge',
            'name' => 'Purge Target Ltd',
            'owner_email' => 'purge@target.com',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $wh = Warehouse::create([
            'tenant_id' => $purgeTenant->id,
            'name' => 'Purge Depot',
            'code' => 'PURGE-01',
            'is_active' => true,
        ]);

        $prod = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $purgeTenant->id,
            'name' => 'Purge Product',
            'code' => 'PURGE-1',
            'category' => 'Test',
            'unitPrice' => 100.0,
            'currentStock' => 5,
        ]);

        StockReservation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $purgeTenant->id,
            'sale_id' => 'SALE-PURGE-1',
            'product_id' => $prod->id,
            'warehouse_id' => $wh->id,
            'reserved_qty' => 5,
            'status' => 'ACTIVE',
        ]);

        Backup::create([
            'id' => 'BK-PURGE-1',
            'tenant_id' => $purgeTenant->id,
            'filename' => 'purge_backup.json',
            'size' => 100,
            'created_by' => 'Purge Test',
        ]);

        $this->assertEquals(1, StockReservation::withoutGlobalScopes()->where('tenant_id', $purgeTenant->id)->count());
        $this->assertEquals(1, Backup::where('tenant_id', $purgeTenant->id)->count());

        $saasController = app(\App\Http\Controllers\SaaS\SaaSController::class);
        $saasController->deleteTenant($purgeTenant->id);

        $this->assertEquals(0, StockReservation::withoutGlobalScopes()->where('tenant_id', $purgeTenant->id)->count());
        $this->assertEquals(0, Backup::where('tenant_id', $purgeTenant->id)->count());
        $this->assertNull(Tenant::find($purgeTenant->id));
    }
}
