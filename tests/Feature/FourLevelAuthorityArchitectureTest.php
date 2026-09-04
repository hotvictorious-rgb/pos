<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Customer;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class FourLevelAuthorityArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $warehouseA1;
    protected Warehouse $warehouseA2;
    protected Warehouse $warehouseB1;
    protected Product $productA1;
    protected Product $productB1;
    protected Customer $customerA;
    protected Customer $customerB;

    protected User $platformAdmin;
    protected User $platformEmployeeAssigned;
    protected User $platformEmployeeUnassigned;
    protected User $tenantAdminA;
    protected User $tenantEmployeeCashierA;
    protected User $tenantEmployeeStorekeeperA;
    protected User $tenantAdminB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);
        config(['saas.super_admin_email' => 'superadmin@hysam.com']);

        // 1. Tenants Setup
        // Tenant A represents the platform owner's normal registered business (Victorious Retail)
        $this->tenantA = Tenant::create([
            'id' => 'victorious-retail',
            'name' => 'Victorious Retail Hub',
            'owner_email' => 'hysam.owner@victorious.com',
            'domain' => 'victorious.vmarket.test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        // Tenant B represents an ordinary customer tenant (Beta Solar)
        $this->tenantB = Tenant::create([
            'id' => 'customer-beta',
            'name' => 'Beta Customer Store',
            'owner_email' => 'owner@beta.com',
            'domain' => 'beta.vmarket.test',
            'status' => 'active',
            'plan' => 'starter',
        ]);

        // 2. Warehouses
        $this->warehouseA1 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Victorious Main Store',
            'code' => 'VIC-01',
            'is_active' => true,
        ]);

        $this->warehouseA2 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Victorious Annex Branch',
            'code' => 'VIC-02',
            'is_active' => true,
        ]);

        $this->warehouseB1 = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Branch 1',
            'code' => 'BET-01',
            'is_active' => true,
        ]);

        // 3. Products & Stock
        $this->productA1 = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'code' => 'VIC-PROD-1',
            'name' => 'Victorious Product 1',
            'category' => 'Retail',
            'unitPrice' => 15000,
            'currentStock' => 100,
            'minStockLevel' => 5,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA1->id,
            'warehouse_id' => $this->warehouseA1->id,
            'physical_stock' => 100,
            'allocated_stock' => 0,
        ]);

        $this->productB1 = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'code' => 'BET-PROD-1',
            'name' => 'Beta Product 1',
            'category' => 'Retail',
            'unitPrice' => 25000,
            'currentStock' => 50,
            'minStockLevel' => 5,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantB->id,
            'product_id' => $this->productB1->id,
            'warehouse_id' => $this->warehouseB1->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);

        // 4. Customers (auto-increment integer ID)
        $this->customerA = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Victorious Customer A',
            'phone' => '08011111111',
            'total_debt' => 20000,
        ]);

        $this->customerB = Customer::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Customer B',
            'phone' => '08022222222',
            'total_debt' => 10000,
        ]);

        // 5. Users Setup Covering Exactly the Four Authority Categories:

        // Category 1: PLATFORM ADMIN
        $this->platformAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Hysam Platform Super Admin',
            'email' => 'superadmin@hysam.com',
            'password' => bcrypt('supersecret'),
            'role' => 'admin',
        ]);

        // Category 2A: PLATFORM EMPLOYEE (Assigned platform.health & platform.tenants)
        $this->platformEmployeeAssigned = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Support Ops',
            'email' => 'ops@hysam.com',
            'password' => bcrypt('opssecret'),
            'role' => 'platform_employee',
            'permissions' => ['platform.health', 'platform.tenants'],
        ]);

        // Category 2B: PLATFORM EMPLOYEE (Unassigned)
        $this->platformEmployeeUnassigned = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Trainee',
            'email' => 'trainee@hysam.com',
            'password' => bcrypt('traineesecret'),
            'role' => 'platform_employee',
            'permissions' => [],
        ]);

        // Category 3: TENANT ADMIN (Controls own business: Victorious Retail)
        $this->tenantAdminA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Victorious Business Owner',
            'email' => 'hysam.owner@victorious.com',
            'password' => bcrypt('ownersecret'),
            'role' => 'admin',
        ]);

        // Category 4A: TENANT EMPLOYEE (Cashier in Victorious Main Store)
        $this->tenantEmployeeCashierA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'name' => 'Victorious Cashier',
            'email' => 'cashier@victorious.com',
            'password' => bcrypt('cashiersecret'),
            'role' => 'cashier',
        ]);

        // Category 4B: TENANT EMPLOYEE (Storekeeper in Victorious Main Store)
        $this->tenantEmployeeStorekeeperA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'name' => 'Victorious Storekeeper',
            'email' => 'storekeeper@victorious.com',
            'password' => bcrypt('storesecret'),
            'role' => 'storekeeper',
        ]);

        // Category 3: TENANT ADMIN of Tenant B (Cross-Tenant Boundary Target)
        $this->tenantAdminB = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Owner',
            'email' => 'owner@beta.com',
            'password' => bcrypt('betasecret'),
            'role' => 'admin',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // 1. IDENTITY & CATEGORY DETERMINATION TESTS
    // ─────────────────────────────────────────────────────────

    public function test_authority_category_resolution()
    {
        $this->assertEquals(User::AUTH_PLATFORM_ADMIN, $this->platformAdmin->getAuthorityCategory());
        $this->assertTrue($this->platformAdmin->isPlatformAdmin());
        $this->assertTrue($this->platformAdmin->isPlatformUser());
        $this->assertFalse($this->platformAdmin->isTenantUser());
        $this->assertFalse($this->platformAdmin->isTenantAdmin());

        $this->assertEquals(User::AUTH_PLATFORM_EMPLOYEE, $this->platformEmployeeAssigned->getAuthorityCategory());
        $this->assertTrue($this->platformEmployeeAssigned->isPlatformEmployee());
        $this->assertTrue($this->platformEmployeeAssigned->isPlatformUser());
        $this->assertFalse($this->platformEmployeeAssigned->isPlatformAdmin());
        $this->assertFalse($this->platformEmployeeAssigned->isTenantUser());

        $this->assertEquals(User::AUTH_TENANT_ADMIN, $this->tenantAdminA->getAuthorityCategory());
        $this->assertTrue($this->tenantAdminA->isTenantAdmin());
        $this->assertTrue($this->tenantAdminA->isTenantUser());
        $this->assertFalse($this->tenantAdminA->isPlatformUser());
        $this->assertFalse($this->tenantAdminA->isPlatformAdmin());

        $this->assertEquals(User::AUTH_TENANT_EMPLOYEE, $this->tenantEmployeeCashierA->getAuthorityCategory());
        $this->assertTrue($this->tenantEmployeeCashierA->isTenantEmployee());
        $this->assertTrue($this->tenantEmployeeCashierA->isTenantUser());
        $this->assertFalse($this->tenantEmployeeCashierA->isTenantAdmin());
        $this->assertFalse($this->tenantEmployeeCashierA->isPlatformUser());
    }

    // ─────────────────────────────────────────────────────────
    // 2. ROW 1: SAAS SETTINGS (/saas/admin/settings)
    // ─────────────────────────────────────────────────────────

    public function test_matrix_saas_settings_access()
    {
        // Platform Admin: Allowed
        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->post(route('saas.admin.settings'), [
            'allow_registration' => 1,
            'default_trial_days' => 14,
        ])->assertStatus(302);

        // Platform Employee (unassigned platform.settings): Blocked with 403
        $this->actingAs($this->platformEmployeeAssigned)->withSession([
            'user_id' => $this->platformEmployeeAssigned->id,
            'tenant_id' => 'default-tenant',
        ])->postJson(route('saas.admin.settings'), [
            'allow_registration' => 1,
        ])->assertStatus(403);

        // Tenant Admin: Blocked with 403
        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->postJson(route('saas.admin.settings'), [
            'allow_registration' => 1,
        ])->assertStatus(403);

        // Tenant Employee: Blocked with 403
        $this->actingAs($this->tenantEmployeeCashierA)->withSession([
            'user_id' => $this->tenantEmployeeCashierA->id,
            'tenant_id' => $this->tenantA->id,
        ])->postJson(route('saas.admin.settings'), [
            'allow_registration' => 1,
        ])->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────
    // 3. ROW 2: TENANT LIFECYCLE (/saas/admin/toggle, limits)
    // ─────────────────────────────────────────────────────────

    public function test_matrix_tenant_lifecycle_access()
    {
        // Platform Admin: Allowed
        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->post(route('saas.admin.toggle', ['id' => $this->tenantB->id]), [
            'status' => 'suspended',
        ])->assertStatus(302);

        $this->assertEquals('suspended', $this->tenantB->fresh()->status);

        // Platform Employee (assigned platform.tenants): Allowed to toggle status
        $this->actingAs($this->platformEmployeeAssigned)->withSession([
            'user_id' => $this->platformEmployeeAssigned->id,
            'tenant_id' => 'default-tenant',
        ])->post(route('saas.admin.toggle', ['id' => $this->tenantB->id]), [
            'status' => 'active',
        ])->assertStatus(302);

        $this->assertEquals('active', $this->tenantB->fresh()->status);

        // Platform Employee (unassigned): Blocked with 403
        $this->actingAs($this->platformEmployeeUnassigned)->withSession([
            'user_id' => $this->platformEmployeeUnassigned->id,
            'tenant_id' => 'default-tenant',
        ])->postJson(route('saas.admin.toggle', ['id' => $this->tenantB->id]), [
            'status' => 'suspended',
        ])->assertStatus(403);

        // Tenant Admin: Blocked with 403
        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->postJson(route('saas.admin.toggle', ['id' => $this->tenantB->id]), [
            'status' => 'suspended',
        ])->assertStatus(403);

        // Tenant Employee: Blocked with 403
        $this->actingAs($this->tenantEmployeeCashierA)->withSession([
            'user_id' => $this->tenantEmployeeCashierA->id,
            'tenant_id' => $this->tenantA->id,
        ])->postJson(route('saas.admin.toggle', ['id' => $this->tenantB->id]), [
            'status' => 'suspended',
        ])->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────
    // 4. ROW 3: PLATFORM HEALTH (/saas/admin)
    // ─────────────────────────────────────────────────────────

    public function test_matrix_platform_health_access()
    {
        // Platform Admin: Allowed (200)
        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('saas.admin.index'))->assertStatus(200);

        // Platform Employee (assigned platform.health): Allowed (200)
        $this->actingAs($this->platformEmployeeAssigned)->withSession([
            'user_id' => $this->platformEmployeeAssigned->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('saas.admin.index'))->assertStatus(200);

        // Platform Employee (unassigned): Blocked with 403
        $this->actingAs($this->platformEmployeeUnassigned)->withSession([
            'user_id' => $this->platformEmployeeUnassigned->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('saas.admin.index'))->assertStatus(403);

        // Tenant Admin: Redirected / blocked from platform admin
        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('saas.admin.index'))->assertRedirect(route('dashboard'));

        // Tenant Employee: Redirected / blocked from platform admin
        $this->actingAs($this->tenantEmployeeCashierA)->withSession([
            'user_id' => $this->tenantEmployeeCashierA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('saas.admin.index'))->assertRedirect(route('dashboard'));
    }

    // ─────────────────────────────────────────────────────────
    // 5. ROW 4: OWN PRODUCTS (/products, /products/store)
    // ─────────────────────────────────────────────────────────

    public function test_matrix_own_products_access()
    {
        // Platform Admin: FORBIDDEN from accessing tenant catalog (403)
        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('products.index'))->assertStatus(403);

        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->postJson(route('products.store'), [
            'code' => 'PLAT-FAIL',
            'name' => 'Illegal Platform Product',
            'unitPrice' => 1000,
        ])->assertStatus(403);

        // Platform Employee: FORBIDDEN (403)
        $this->actingAs($this->platformEmployeeAssigned)->withSession([
            'user_id' => $this->platformEmployeeAssigned->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('products.index'))->assertStatus(403);

        // Tenant Admin: ALLOWED (200)
        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('products.index'))->assertStatus(200);

        // Tenant Employee (Storekeeper with products.write): ALLOWED
        $this->actingAs($this->tenantEmployeeStorekeeperA)->withSession([
            'user_id' => $this->tenantEmployeeStorekeeperA->id,
            'tenant_id' => $this->tenantA->id,
        ])->post(route('products.store'), [
            'code' => 'SK-PROD-1',
            'name' => 'Storekeeper Added Product',
            'category' => 'Retail',
            'unitPrice' => 5000,
            'initial_stock' => 10,
            'warehouse_id' => $this->warehouseA1->id,
        ])->assertStatus(302);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'SK-PROD-1',
        ]);

        // Tenant Employee (Cashier without products.write): Blocked with 403 on store
        $this->actingAs($this->tenantEmployeeCashierA)->withSession([
            'user_id' => $this->tenantEmployeeCashierA->id,
            'tenant_id' => $this->tenantA->id,
        ])->postJson(route('products.store'), [
            'code' => 'CASH-FAIL',
            'name' => 'Cashier Illegally Adding Product',
            'unitPrice' => 5000,
        ])->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────
    // 6. ROW 5: OWN INVENTORY (/stock, /stock/in)
    // ─────────────────────────────────────────────────────────

    public function test_matrix_own_inventory_access()
    {
        // Platform Admin: FORBIDDEN (403)
        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('stock.index'))->assertStatus(403);

        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->postJson(route('stock.in'), [
            'product_id' => $this->productA1->id,
            'warehouse_id' => $this->warehouseA1->id,
            'quantity' => 10,
        ])->assertStatus(403);

        // Platform Employee: FORBIDDEN (403)
        $this->actingAs($this->platformEmployeeAssigned)->withSession([
            'user_id' => $this->platformEmployeeAssigned->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('stock.index'))->assertStatus(403);

        // Tenant Admin: ALLOWED
        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('stock.index'))->assertStatus(200);

        // Tenant Employee (Storekeeper with stock.in): ALLOWED
        $this->actingAs($this->tenantEmployeeStorekeeperA)->withSession([
            'user_id' => $this->tenantEmployeeStorekeeperA->id,
            'tenant_id' => $this->tenantA->id,
        ])->post(route('stock.in'), [
            'product_id' => $this->productA1->id,
            'warehouse_id' => $this->warehouseA1->id,
            'quantity' => 25,
            'supplier' => 'Direct Vendor',
        ])->assertStatus(302);

        // Tenant Employee (Cashier without stock.in): Blocked with 403
        $this->actingAs($this->tenantEmployeeCashierA)->withSession([
            'user_id' => $this->tenantEmployeeCashierA->id,
            'tenant_id' => $this->tenantA->id,
        ])->postJson(route('stock.in'), [
            'product_id' => $this->productA1->id,
            'warehouse_id' => $this->warehouseA1->id,
            'quantity' => 10,
        ])->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────
    // 7. ROW 6: OWN SALES (/pos, /pos/checkout)
    // ─────────────────────────────────────────────────────────

    public function test_matrix_own_sales_access()
    {
        // Platform Admin: FORBIDDEN from accessing tenant POS (403)
        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('pos.index'))->assertStatus(403);

        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->postJson(route('pos.checkout'), [
            'customer_name' => 'Walk In',
            'warehouse_id' => $this->warehouseA1->id,
            'items' => [['productId' => $this->productA1->id, 'quantity' => 1]],
        ])->assertStatus(403);

        // Platform Employee: FORBIDDEN (403)
        $this->actingAs($this->platformEmployeeAssigned)->withSession([
            'user_id' => $this->platformEmployeeAssigned->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('pos.index'))->assertStatus(403);

        // Tenant Admin: ALLOWED
        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('pos.index'))->assertStatus(200);

        // Tenant Employee (Cashier with pos.checkout): ALLOWED
        $res = $this->actingAs($this->tenantEmployeeCashierA)->withSession([
            'user_id' => $this->tenantEmployeeCashierA->id,
            'tenant_id' => $this->tenantA->id,
        ])->postJson(route('pos.checkout'), [
            'customer_name' => 'Retail Shopper',
            'payment_method' => 'CASH',
            'warehouse_id' => $this->warehouseA1->id,
            'items' => [
                ['productId' => $this->productA1->id, 'quantity' => 2, 'unitPrice' => 15000]
            ],
            'paidAmount' => 30000,
            'is_supplied' => true,
        ]);

        $res->assertStatus(200);
        $this->assertTrue($res->json('success'));

        // Tenant Employee (Storekeeper without pos.checkout): Blocked with 403
        $this->actingAs($this->tenantEmployeeStorekeeperA)->withSession([
            'user_id' => $this->tenantEmployeeStorekeeperA->id,
            'tenant_id' => $this->tenantA->id,
        ])->postJson(route('pos.checkout'), [
            'customer_name' => 'Retail Shopper',
            'payment_method' => 'CASH',
            'warehouse_id' => $this->warehouseA1->id,
            'items' => [['productId' => $this->productA1->id, 'quantity' => 1]],
            'paidAmount' => 15000,
            'is_supplied' => true,
        ])->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────
    // 8. ROW 7: OWN CUSTOMERS & DEBTS (/debts, /debts/pay)
    // ─────────────────────────────────────────────────────────

    public function test_matrix_own_customers_and_debts_access()
    {
        // Platform Admin: FORBIDDEN (403)
        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('debts.index'))->assertStatus(403);

        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->postJson(route('debts.pay', ['id' => $this->customerA->id]), [
            'amount' => 5000,
            'payment_method' => 'CASH',
        ])->assertStatus(403);

        // Platform Employee: FORBIDDEN (403)
        $this->actingAs($this->platformEmployeeAssigned)->withSession([
            'user_id' => $this->platformEmployeeAssigned->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('debts.index'))->assertStatus(403);

        // Tenant Admin: ALLOWED
        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('debts.index'))->assertStatus(200);

        // Tenant Employee (Cashier with debt.pay): ALLOWED
        $this->actingAs($this->tenantEmployeeCashierA)->withSession([
            'user_id' => $this->tenantEmployeeCashierA->id,
            'tenant_id' => $this->tenantA->id,
        ])->post(route('debts.pay', ['id' => $this->customerA->id]), [
            'amount' => 5000,
            'payment_method' => 'CASH',
        ])->assertStatus(302);

        $this->assertEquals(15000, (float) $this->customerA->fresh()->total_debt);

        // Tenant Employee (Storekeeper without debt.pay): Blocked with 403
        $this->actingAs($this->tenantEmployeeStorekeeperA)->withSession([
            'user_id' => $this->tenantEmployeeStorekeeperA->id,
            'tenant_id' => $this->tenantA->id,
        ])->postJson(route('debts.pay', ['id' => $this->customerA->id]), [
            'amount' => 5000,
            'payment_method' => 'CASH',
        ])->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────
    // 9. ROW 8: OWN ACTIVITIES & AUDITOR (/auditor, /reports)
    // ─────────────────────────────────────────────────────────

    public function test_matrix_own_activities_and_audit_access()
    {
        // Platform Admin: FORBIDDEN from tenant auditor and reports (403)
        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('auditor.index'))->assertStatus(403);

        $this->actingAs($this->platformAdmin)->withSession([
            'user_id' => $this->platformAdmin->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('reports.index'))->assertStatus(403);

        // Platform Employee: FORBIDDEN (403)
        $this->actingAs($this->platformEmployeeAssigned)->withSession([
            'user_id' => $this->platformEmployeeAssigned->id,
            'tenant_id' => 'default-tenant',
        ])->get(route('auditor.index'))->assertStatus(403);

        // Tenant Admin: ALLOWED
        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('auditor.index'))->assertStatus(200);

        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('reports.index'))->assertStatus(200);

        // Tenant Employee (Cashier without settings.manage): Blocked on Auditor
        $this->actingAs($this->tenantEmployeeCashierA)->withSession([
            'user_id' => $this->tenantEmployeeCashierA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('auditor.index'))->assertRedirect(route('dashboard'));

        // Tenant Employee (Storekeeper without reports.view): Blocked on Reports
        $this->actingAs($this->tenantEmployeeStorekeeperA)->withSession([
            'user_id' => $this->tenantEmployeeStorekeeperA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get(route('reports.index'))->assertRedirect(route('dashboard'));
    }

    // ─────────────────────────────────────────────────────────
    // 10. ROW 9: OTHER TENANT DATA (STRICT CROSS-TENANT ISOLATION)
    // ─────────────────────────────────────────────────────────

    public function test_tenant_a_cannot_mutate_tenant_b_product()
    {
        // Tenant Admin A cannot delete Tenant B's product
        $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->post("/products/{$this->productB1->id}/delete")
          ->assertStatus(404);

        $this->assertNotNull(Product::withoutGlobalScopes()->find($this->productB1->id));
    }

    public function test_tenant_a_cannot_pay_tenant_b_debt()
    {
        $response = $this->actingAs($this->tenantAdminA)->withSession([
            'user_id' => $this->tenantAdminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->post("/debts/pay/{$this->customerB->id}", [
            'amount' => 1000,
            'payment_method' => 'CASH',
        ]);

        // Cross-tenant customer lookup returns null under TenantScope, rejecting the payment
        $response->assertStatus(302);
        $this->assertEquals(10000, (float) $this->customerB->fresh()->total_debt);
    }

    public function test_tenant_a_cannot_operate_stock_in_tenant_b_warehouse()
    {
        $stockService = app(StockService::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("does not belong to active tenant");

        session(['tenant_id' => $this->tenantA->id]);
        $stockService->recordStockIn(
            $this->productA1->id,
            $this->warehouseB1->id, // Foreign warehouse
            10,
            'Vendor',
            $this->tenantAdminA->id,
            $this->tenantAdminA->name
        );
    }

    // ─────────────────────────────────────────────────────────
    // 11. PLATFORM OWNER OPERATES AS A NORMAL REGISTERED TENANT
    // ─────────────────────────────────────────────────────────

    public function test_platform_owner_business_functions_strictly_as_normal_tenant()
    {
        // 1. Owner logs into Tenant Admin portal with business credentials
        $loginRes = $this->post(route('portal.tenant.login.post'), [
            'email' => 'hysam.owner@victorious.com',
            'password' => 'ownersecret',
        ]);

        $loginRes->assertRedirect('/');
        $this->assertEquals($this->tenantA->id, session('tenant_id'));
        $this->assertEquals($this->tenantAdminA->id, session('user_id'));

        // 2. Owner can operate own store POS, products, and reports
        $this->actingAs($this->tenantAdminA)->get(route('dashboard'))->assertStatus(200);
        $this->actingAs($this->tenantAdminA)->get(route('pos.index'))->assertStatus(200);
        $this->actingAs($this->tenantAdminA)->get(route('reports.index'))->assertStatus(200);

        // 3. In this tenant session, Owner CANNOT access platform control panel (no bypass)
        $this->actingAs($this->tenantAdminA)->get(route('saas.admin.index'))
             ->assertRedirect(route('dashboard'));

        $this->actingAs($this->tenantAdminA)->postJson(route('saas.admin.settings'), [
            'allow_registration' => 1,
        ])->assertStatus(403);

        // 4. Platform management requires logging into Super Admin portal with platform admin credentials
        $this->post(route('portal.tenant.logout'));

        $superLoginRes = $this->post(route('portal.super_admin.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);

        $superLoginRes->assertRedirect(route('saas.admin.index'));
        $this->assertEquals('default-tenant', session('tenant_id'));
        $this->assertEquals($this->platformAdmin->id, session('user_id'));

        // 5. In platform session, Super Admin CANNOT access tenant business operations
        $this->actingAs($this->platformAdmin)->get(route('dashboard'))->assertStatus(403);
        $this->actingAs($this->platformAdmin)->get(route('pos.index'))->assertStatus(403);
        $this->actingAs($this->platformAdmin)->get(route('products.index'))->assertStatus(403);
    }
}
