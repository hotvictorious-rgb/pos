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
use App\Models\Backup;
use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass12Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $warehouseA1;
    protected Warehouse $warehouseA2;
    protected Warehouse $warehouseB1;
    protected User $adminA;
    protected User $cashierA1;
    protected User $cashierA2;
    protected User $storekeeperA1;
    protected User $storekeeperA2;
    protected User $adminB;
    protected User $cashierB1;
    protected User $storekeeperB1;
    protected User $platformAdmin;
    protected Product $productA;
    protected Product $productB;
    protected Customer $customerA;
    protected Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@vmarketplatform.ng',
        ]);

        // Platform Admin
        Tenant::withoutGlobalScopes()->firstOrCreate(['id' => 'default-tenant'], [
            'name' => 'Platform Infrastructure',
            'owner_email' => 'superadmin@vmarketplatform.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 999,
            'max_users' => 999,
        ]);

        $this->platformAdmin = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@vmarketplatform.ng',
            'password' => Hash::make('SuperAdminPass123!'),
            'role' => 'admin',
            'disabled' => false,
            'permissions' => ['all' => true, 'platform.backup' => true],
        ]);

        // Tenant A (Enterprise)
        $this->tenantA = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-alpha-corp',
            'name' => 'Alpha Corporation',
            'owner_email' => 'owner@alphacorp.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 20,
        ]);

        // Tenant B (Competitor)
        $this->tenantB = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-beta-retail',
            'name' => 'Beta Retail Ltd',
            'owner_email' => 'owner@betaretail.ng',
            'status' => 'active',
            'plan' => 'starter',
            'max_branches' => 1,
            'max_users' => 5,
        ]);

        // Warehouses
        $this->warehouseA1 = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Ikeja Branch (Shop 1)',
            'code' => 'IKJ-01',
            'is_active' => true,
        ]);

        $this->warehouseA2 = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Lekki Branch (Shop 2)',
            'code' => 'LKK-02',
            'is_active' => true,
        ]);

        $this->warehouseB1 = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Victoria Island Store',
            'code' => 'BVI-01',
            'is_active' => true,
        ]);

        // Workers for Tenant A
        $this->adminA = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Managing Director',
            'email' => 'md@alphacorp.ng',
            'password' => Hash::make('AlphaPass123!'),
            'role' => 'admin',
            'disabled' => false,
            'warehouse_id' => null,
            'permissions' => ['all' => true, 'tenant.backup' => true, 'settings.manage' => true],
        ]);

        $this->cashierA1 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Ikeja Cashier',
            'email' => 'cashier.ikeja@alphacorp.ng',
            'password' => Hash::make('IkejaPass123!'),
            'role' => 'cashier',
            'disabled' => false,
            'warehouse_id' => $this->warehouseA1->id,
            'permissions' => ['pos.view', 'pos.checkout', 'customer.write', 'transactions.view', 'transactions.export', 'debt.view', 'debt.pay', 'returns.view', 'returns.process', 'reports.view', 'reports.export'],
        ]);

        $this->cashierA2 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Lekki Cashier',
            'email' => 'cashier.lekki@alphacorp.ng',
            'password' => Hash::make('LekkiPass123!'),
            'role' => 'cashier',
            'disabled' => false,
            'warehouse_id' => $this->warehouseA2->id,
            'permissions' => ['pos.view', 'pos.checkout', 'customer.write', 'transactions.view', 'transactions.export', 'debt.view', 'debt.pay', 'returns.view', 'returns.process'],
        ]);

        $this->storekeeperA1 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Ikeja Storekeeper',
            'email' => 'storekeeper.ikeja@alphacorp.ng',
            'password' => Hash::make('IkejaStore123!'),
            'role' => 'storekeeper',
            'disabled' => false,
            'warehouse_id' => $this->warehouseA1->id,
            'permissions' => ['stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.adjust'],
        ]);

        $this->storekeeperA2 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Lekki Storekeeper',
            'email' => 'storekeeper.lekki@alphacorp.ng',
            'password' => Hash::make('LekkiStore123!'),
            'role' => 'storekeeper',
            'disabled' => false,
            'warehouse_id' => $this->warehouseA2->id,
            'permissions' => ['stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.adjust'],
        ]);

        // Workers for Tenant B
        $this->adminB = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Executive Attacker',
            'email' => 'admin@betaretail.ng',
            'password' => Hash::make('BetaPass123!'),
            'role' => 'admin',
            'disabled' => false,
            'warehouse_id' => $this->warehouseB1->id,
            'permissions' => ['all' => true, 'tenant.backup' => true, 'settings.manage' => true],
        ]);

        $this->cashierB1 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Cashier Attacker',
            'email' => 'cashier@betaretail.ng',
            'password' => Hash::make('BetaCashier123!'),
            'role' => 'cashier',
            'disabled' => false,
            'warehouse_id' => $this->warehouseB1->id,
            'permissions' => ['pos.view', 'pos.checkout', 'customer.write', 'transactions.view', 'transactions.export', 'debt.view', 'debt.pay', 'returns.view', 'returns.process'],
        ]);

        $this->storekeeperB1 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Storekeeper Attacker',
            'email' => 'storekeeper@betaretail.ng',
            'password' => Hash::make('BetaStore123!'),
            'role' => 'storekeeper',
            'disabled' => false,
            'warehouse_id' => $this->warehouseB1->id,
            'permissions' => ['stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.adjust'],
        ]);

        // Products
        $this->productA = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'code' => 'SKU-ALPHA-99',
            'name' => 'Alpha Industrial Generator (5KVA)',
            'category' => 'Power Equipment',
            'unitPrice' => 450000,
            'currentStock' => 15,
            'minStockLevel' => 2,
            'archived' => false,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'physical_stock' => 10,
            'allocated_stock' => 0,
            'min_stock_alert' => 2,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseA2->id,
            'physical_stock' => 5,
            'allocated_stock' => 0,
            'min_stock_alert' => 1,
        ]);

        $this->productB = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'code' => 'SKU-BETA-11',
            'name' => 'Beta Portable Inverter (1KVA)',
            'category' => 'Electronics',
            'unitPrice' => 120000,
            'currentStock' => 8,
            'minStockLevel' => 1,
            'archived' => false,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'product_id' => $this->productB->id,
            'warehouse_id' => $this->warehouseB1->id,
            'physical_stock' => 8,
            'allocated_stock' => 0,
            'min_stock_alert' => 1,
        ]);

        // Customers
        $this->customerA = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_code' => 'CUST-A-CORP',
            'name' => 'Dangote Logistics Hub',
            'phone' => '08037778899',
            'total_debt' => 0,
        ]);

        $this->customerB = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_code' => 'CUST-B-LTD',
            'name' => 'Mikano Sales Partner',
            'phone' => '08026665544',
            'total_debt' => 0,
        ]);

        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA1->id,
        ]);
    }

    /**
     * PASS 12: TEST 1 - Cross-Tenant & Cross-Branch Sales Returns (Restitution) via HTTP.
     */
    public function test_http_cross_tenant_and_cross_branch_sales_returns_penetration(): void
    {
        // 1. Create legitimate sale at Tenant A Lekki (Branch 2)
        $this->actingAs($this->cashierA2);
        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA2->id,
        ]);

        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseA2->id,
            'items' => [['productId' => $this->productA->id, 'quantity' => 1]],
            'cashAmount' => 450000,
            'posAmount' => 0,
            'paidAmount' => 450000,
            'is_supplied' => 'yes',
        ]);
        $saleLekki = Sale::latest()->first();

        // Attack 1: Tenant B Cashier attempts to return Tenant A Lekki sale
        $this->actingAs($this->cashierB1);
        session([
            'tenant_id' => $this->tenantB->id,
            'active_warehouse_id' => $this->warehouseB1->id,
        ]);

        $resReturnTenantB = $this->post(route('pos.returns.process'), [
            'sale_id' => $saleLekki->id,
            'warehouse_id' => $this->warehouseB1->id,
            'items' => [['productId' => $this->productA->id, 'quantity' => 1]],
            'refund_method' => 'CASH_REFUND',
            'reason' => 'Cross-tenant illegal refund attempt',
        ]);
        // TenantScope prevents finding foreign sale record
        $resReturnTenantB->assertSessionHasErrors('error');

        // Attack 2: Tenant A Ikeja Cashier (Branch 1) attempts to return Lekki sale (Branch 2)
        $this->actingAs($this->cashierA1);
        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA1->id,
        ]);

        $resReturnBranch1 = $this->post(route('pos.returns.process'), [
            'sale_id' => $saleLekki->id,
            'warehouse_id' => $this->warehouseA1->id,
            'items' => [['productId' => $this->productA->id, 'quantity' => 1]],
            'refund_method' => 'CASH_REFUND',
            'reason' => 'Cross-branch return attempt at wrong shop',
        ]);
        $resReturnBranch1->assertSessionHasErrors('error');
        $this->assertStringContainsString('Cross-branch return rejected', session('errors')->first('error'));

        // Legitimate verification: Tenant A Lekki Cashier (Branch 2) processes the return
        $this->actingAs($this->cashierA2);
        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA2->id,
        ]);

        $resLegitReturn = $this->post(route('pos.returns.process'), [
            'sale_id' => $saleLekki->id,
            'warehouse_id' => $this->warehouseA2->id,
            'items' => [['productId' => $this->productA->id, 'quantity' => 1]],
            'refund_method' => 'CASH_REFUND',
            'reason' => 'Customer defect return at originating shop',
        ]);
        $resLegitReturn->assertSessionHasNoErrors();
        $this->assertEquals(0, (float) $saleLekki->fresh()->paidAmount);
    }

    /**
     * PASS 12: TEST 2 - Cross-Tenant & Cross-Branch Stock Adjustments via HTTP.
     */
    public function test_http_cross_tenant_and_cross_branch_stock_adjustments_penetration(): void
    {
        // Attack 1: Tenant B Storekeeper attempts to write off Tenant A Product
        $this->actingAs($this->storekeeperB1);
        session([
            'tenant_id' => $this->tenantB->id,
            'active_warehouse_id' => $this->warehouseB1->id,
        ]);

        $resAdjTenantB = $this->post(route('stock.adjustments.record'), [
            'warehouse_id' => $this->warehouseB1->id,
            'product_id' => $this->productA->id,
            'type' => 'DAMAGE',
            'quantity' => 1,
            'reason' => 'Cross tenant write-off attack',
        ]);
        $resAdjTenantB->assertSessionHasErrors('error');

        // Attack 2: Tenant A Ikeja Storekeeper attempts to adjust Lekki Warehouse (Branch 2)
        $this->actingAs($this->storekeeperA1);
        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA1->id,
        ]);

        $initialLekkiStock = StockLevel::where('product_id', $this->productA->id)
            ->where('warehouse_id', $this->warehouseA2->id)
            ->first()->physical_stock;

        $resAdjBranch = $this->post(route('stock.adjustments.record'), [
            'warehouse_id' => $this->warehouseA2->id, // Targeted foreign branch
            'product_id' => $this->productA->id,
            'type' => 'DAMAGE',
            'quantity' => 2,
            'reason' => 'Attempted write-off at unassigned branch',
        ]);

        // Branch-scoped user warehouse_id is strictly locked to Ikeja (Branch 1)
        $finalLekkiStock = StockLevel::where('product_id', $this->productA->id)
            ->where('warehouse_id', $this->warehouseA2->id)
            ->first()->physical_stock;
        $this->assertEquals($initialLekkiStock, $finalLekkiStock, 'Lekki stock must remain completely untouched by Ikeja storekeeper');
    }

    /**
     * PASS 12: TEST 3 - Cross-Tenant & Platform Super-Admin Backup Isolation.
     */
    public function test_http_cross_tenant_and_platform_admin_backup_isolation(): void
    {
        // Create mock backup file and record for Tenant A
        $backupFile = 'backup_alpha_2026_09_05.json';
        Storage::disk('local')->put('backups/' . $backupFile, json_encode(['tenant' => 'tenant-alpha-corp', 'sales' => []]));

        $backupA = Backup::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'filename' => $backupFile,
            'size' => 1024,
            'status' => 'completed',
            'created_by' => $this->adminA->id,
        ]);

        // Attack 1: Tenant B Admin attacks Tenant A backup download via GET /settings/backups/{id}/download
        $this->actingAs($this->adminB);
        session(['tenant_id' => $this->tenantB->id]);

        $resB = $this->getJson(route('settings.backups.download', $backupA->id));
        $resB->assertStatus(403);

        // Attack 2: Platform Super Admin attacks Tenant A business backup download
        $this->actingAs($this->platformAdmin);
        session(['tenant_id' => 'default-tenant']);

        $resPlatform = $this->getJson(route('settings.backups.download', $backupA->id));
        $resPlatform->assertStatus(403);

        // Verification: Tenant A Admin downloads own backup successfully
        $this->actingAs($this->adminA);
        session(['tenant_id' => $this->tenantA->id]);

        $resLegit = $this->get(route('settings.backups.download', $backupA->id));
        $resLegit->assertOk();
    }

    /**
     * PASS 12: TEST 4 - Cross-Branch & Cross-Tenant Financial Transaction Exports via HTTP.
     */
    public function test_http_cross_branch_and_cross_tenant_transaction_export_privacy(): void
    {
        // Create Sale 1 at Tenant A Branch 1 (Ikeja) by Cashier A1
        $this->actingAs($this->cashierA1);
        session(['tenant_id' => $this->tenantA->id, 'active_warehouse_id' => $this->warehouseA1->id]);
        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseA1->id,
            'items' => [['productId' => $this->productA->id, 'quantity' => 1]],
            'customerName' => 'Ikeja Customer Alpha',
            'cashAmount' => 450000,
            'posAmount' => 0,
            'paidAmount' => 450000,
            'is_supplied' => 'yes',
        ]);
        $sale1 = Sale::where('customerName', 'Ikeja Customer Alpha')->firstOrFail();

        // Create Sale 2 at Tenant A Branch 2 (Lekki) by Cashier A2
        $this->actingAs($this->cashierA2);
        session(['tenant_id' => $this->tenantA->id, 'active_warehouse_id' => $this->warehouseA2->id]);
        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseA2->id,
            'items' => [['productId' => $this->productA->id, 'quantity' => 1]],
            'customerName' => 'Lekki Customer Alpha',
            'cashAmount' => 450000,
            'posAmount' => 0,
            'paidAmount' => 450000,
            'is_supplied' => 'yes',
        ]);
        $sale2 = Sale::where('customerName', 'Lekki Customer Alpha')->firstOrFail();

        // Create Sale 3 at Tenant B Branch 1 (VI) by Cashier B1
        $this->actingAs($this->cashierB1);
        session(['tenant_id' => $this->tenantB->id, 'active_warehouse_id' => $this->warehouseB1->id]);
        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseB1->id,
            'items' => [['productId' => $this->productB->id, 'quantity' => 1]],
            'customerName' => 'VI Customer Beta',
            'cashAmount' => 120000,
            'posAmount' => 0,
            'paidAmount' => 120000,
            'is_supplied' => 'yes',
        ]);
        $sale3 = Sale::where('customerName', 'VI Customer Beta')->firstOrFail();

        // 1. Tenant A Branch 1 Cashier exports transactions
        $this->actingAs($this->cashierA1);
        session(['tenant_id' => $this->tenantA->id, 'active_warehouse_id' => $this->warehouseA1->id]);

        $resExportA1 = $this->get(route('transactions.export.csv', 'sales'));
        $resExportA1->assertOk();

        ob_start();
        $resExportA1->sendContent();
        $csvContentA1 = ob_get_clean();

        // Must contain Sale 1 (own branch), but strictly omit Sale 2 (Lekki) and Sale 3 (Tenant B)
        $this->assertStringContainsString($sale1->id, $csvContentA1);
        $this->assertStringNotContainsString($sale2->id, $csvContentA1, 'Export must never leak sales from other branches to a branch cashier');
        $this->assertStringNotContainsString($sale3->id, $csvContentA1, 'Export must never leak sales from foreign tenants');

        // 2. Tenant B Cashier exports transactions
        $this->actingAs($this->cashierB1);
        session(['tenant_id' => $this->tenantB->id, 'active_warehouse_id' => $this->warehouseB1->id]);

        $resExportB1 = $this->get(route('transactions.export.csv', 'sales'));
        $resExportB1->assertOk();

        ob_start();
        $resExportB1->sendContent();
        $csvContentB1 = ob_get_clean();

        $this->assertStringContainsString($sale3->id, $csvContentB1);
        $this->assertStringNotContainsString($sale1->id, $csvContentB1);
        $this->assertStringNotContainsString($sale2->id, $csvContentB1);
    }

    /**
     * PASS 12: TEST 5 - Canonical Debt Route Enforcement & Elimination of Duplicate Endpoint.
     */
    public function test_http_canonical_debt_endpoint_enforcement_and_duplicate_removal(): void
    {
        // 1. Create legitimate debt sale at Tenant A Ikeja
        $this->actingAs($this->cashierA1);
        session(['tenant_id' => $this->tenantA->id, 'active_warehouse_id' => $this->warehouseA1->id]);

        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseA1->id,
            'items' => [['productId' => $this->productA->id, 'quantity' => 1]], // 450,000
            'customerId' => $this->customerA->id,
            'customerName' => $this->customerA->name,
            'customerPhone' => $this->customerA->phone,
            'cashAmount' => 200000,
            'posAmount' => 0,
            'paidAmount' => 200000,
            'is_supplied' => 'yes',
        ]);
        $this->assertEquals(250000, (float) $this->customerA->fresh()->total_debt);

        // 2. Canonical route POST /debts/pay/{id} succeeds
        $resCanonical = $this->post(route('debts.pay', $this->customerA->id), [
            'amount' => 50000,
            'payment_method' => 'CASH',
        ]);
        $resCanonical->assertSessionHasNoErrors();
        $this->assertEquals(200000, (float) $this->customerA->fresh()->total_debt);

        // 3. Removed duplicate endpoint POST /debts/{id}/payment must return 404 Not Found
        $resDuplicate = $this->post("/debts/{$this->customerA->id}/payment", [
            'amount' => 50000,
            'payment_method' => 'CASH',
        ]);
        $resDuplicate->assertNotFound();
    }
}
