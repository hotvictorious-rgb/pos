<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\SaaSSetting;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use App\Exceptions\SecurityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass9Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $warehouseA1;
    protected Warehouse $warehouseA2;
    protected Warehouse $warehouseB1;
    protected User $cashierA1;
    protected User $cashierA2;
    protected User $tenantAdminA;
    protected User $platformAdmin;
    protected User $platformEmployeeHealth;
    protected User $platformEmployeeTenants;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@pass9platform.ng',
        ]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);

        // Seed default-tenant for platform users
        Tenant::withoutGlobalScopes()->firstOrCreate([
            'id' => 'default-tenant',
        ], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@pass9platform.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 999,
            'max_users' => 999,
        ]);

        // Platform Admin
        $this->platformAdmin = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@pass9platform.ng',
            'password' => Hash::make('SecretPass9Platform!'),
            'role' => 'admin',
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);

        // Platform Employee with ONLY platform.health
        $this->platformEmployeeHealth = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Health Ops Staff',
            'email' => 'health.ops@pass9platform.ng',
            'password' => Hash::make('SecretPass9Ops!'),
            'role' => 'staff',
            'disabled' => false,
            'permissions' => ['platform.health'],
        ]);

        // Platform Employee with ONLY platform.tenants
        $this->platformEmployeeTenants = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Tenant Manager',
            'email' => 'tenants.manager@pass9platform.ng',
            'password' => Hash::make('SecretPass9Manager!'),
            'role' => 'staff',
            'disabled' => false,
            'permissions' => ['platform.tenants'],
        ]);

        // Tenant A
        $this->tenantA = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-pass9-alpha',
            'name' => 'Alpha Business Enterprises Ltd',
            'owner_email' => 'alpha.owner@pass9alpha.ng',
            'owner_phone' => '08011223344',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        // Tenant B
        $this->tenantB = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-pass9-beta',
            'name' => 'Beta Megamart Ltd',
            'owner_email' => 'beta.owner@pass9beta.ng',
            'owner_phone' => '08055667788',
            'status' => 'active',
            'plan' => 'pro',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        // Warehouses
        $this->warehouseA1 = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Ikeja Shop',
            'code' => 'WH-A1',
            'is_active' => true,
        ]);

        $this->warehouseA2 = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Victoria Island Shop',
            'code' => 'WH-A2',
            'is_active' => true,
        ]);

        $this->warehouseB1 = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Abuja Hub',
            'code' => 'WH-B1',
            'is_active' => true,
        ]);

        // Tenant Users
        $this->tenantAdminA = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Tenant Admin',
            'email' => 'admin@pass9alpha.ng',
            'password' => Hash::make('TenantAdminA!'),
            'role' => 'admin',
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);

        $this->cashierA1 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'name' => 'Alpha Ikeja Cashier',
            'email' => 'cashier1@pass9alpha.ng',
            'password' => Hash::make('CashierA1!'),
            'role' => 'cashier',
            'disabled' => false,
            'permissions' => ['pos' => true, 'stockIn' => true, 'debts' => true],
        ]);

        $this->cashierA2 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA2->id,
            'name' => 'Alpha VI Cashier',
            'email' => 'cashier2@pass9alpha.ng',
            'password' => Hash::make('CashierA2!'),
            'role' => 'cashier',
            'disabled' => false,
            'permissions' => ['pos' => true, 'stockIn' => true, 'debts' => true],
        ]);
    }

    /**
     * Test 1: Platform Employee with ONLY platform.health is strictly isolated from tenant directory, owner PII, MRR, settings, and backups.
     */
    public function test_platform_employee_with_health_capability_cannot_see_tenants_mrr_or_settings(): void
    {
        $response = $this->actingAs($this->platformEmployeeHealth)
            ->withSession([
                'user_id' => $this->platformEmployeeHealth->id,
                'tenant_id' => 'default-tenant',
            ])
            ->get('/saas/admin');

        $response->assertStatus(200);

        // Must NOT see tenant business directory or names
        $response->assertDontSee('Alpha Business Enterprises Ltd');
        $response->assertDontSee('Beta Megamart Ltd');
        $response->assertDontSee('alpha.owner@pass9alpha.ng');
        $response->assertDontSee('08011223344');
        $response->assertDontSee('Business Tenants Directory');
        $response->assertDontSee('Create New Business Tenant');

        // Must NOT see MRR
        $response->assertDontSee('Estimated Monthly Revenue');

        // Must NOT see SaaS global settings or configuration form
        $response->assertDontSee('SaaS Global Platform Settings');
        $response->assertDontSee('Paystack Payment Gateway Integration');
        $response->assertDontSee('Bank Account Details');

        // Must NOT see Backups table
        $response->assertDontSee('Platform Database Backups');

        // Must see Platform Health & System Status panel
        $response->assertSee('Platform Health');
        $response->assertSee('PHP Runtime');
        $response->assertSee('Database Engine');
    }

    /**
     * Test 2: Platform Employee with ONLY platform.health cannot perform tenant or settings mutations.
     */
    public function test_platform_employee_with_health_cannot_mutate_tenants_or_settings(): void
    {
        // Attempt to create tenant -> 403
        $this->actingAs($this->platformEmployeeHealth)
            ->withSession(['user_id' => $this->platformEmployeeHealth->id, 'tenant_id' => 'default-tenant'])
            ->post('/saas/admin/tenant', [
                'business_name' => 'Malicious Tenant',
                'owner_name' => 'Hacker',
                'owner_email' => 'hacker@evil.com',
                'owner_phone' => '08000000000',
                'plan' => 'pro',
                'status' => 'active',
            ])->assertStatus(403);

        // Attempt to toggle tenant status -> 403
        $this->actingAs($this->platformEmployeeHealth)
            ->withSession(['user_id' => $this->platformEmployeeHealth->id, 'tenant_id' => 'default-tenant'])
            ->post("/saas/admin/toggle/{$this->tenantA->id}", ['status' => 'suspended'])
            ->assertStatus(403);

        // Attempt to update platform settings -> 403
        $this->actingAs($this->platformEmployeeHealth)
            ->withSession(['user_id' => $this->platformEmployeeHealth->id, 'tenant_id' => 'default-tenant'])
            ->post('/saas/admin/settings', ['platform_name' => 'Defaced Platform'])
            ->assertStatus(403);

        // Attempt to create backup -> 403
        $this->actingAs($this->platformEmployeeHealth)
            ->withSession(['user_id' => $this->platformEmployeeHealth->id, 'tenant_id' => 'default-tenant'])
            ->post('/api/backups')
            ->assertStatus(403);
    }

    /**
     * Test 3: Platform Employee with platform.tenants can see directory but cannot see or modify settings.
     */
    public function test_platform_employee_with_tenants_capability_cannot_access_settings_or_backups(): void
    {
        $response = $this->actingAs($this->platformEmployeeTenants)
            ->withSession([
                'user_id' => $this->platformEmployeeTenants->id,
                'tenant_id' => 'default-tenant',
            ])
            ->get('/saas/admin');

        $response->assertStatus(200);

        // Can see tenant directory and names
        $response->assertSee('Alpha Business Enterprises Ltd');
        $response->assertSee('Business Tenants Directory');
        $response->assertSee('Create New Business Tenant');

        // Cannot see settings form
        $response->assertDontSee('SaaS Global Platform Settings');
        $response->assertDontSee('Bank Account Details');

        // Cannot see backups table
        $response->assertDontSee('Platform Database Backups');

        // Attempt to post settings -> 403
        $this->actingAs($this->platformEmployeeTenants)
            ->withSession(['user_id' => $this->platformEmployeeTenants->id, 'tenant_id' => 'default-tenant'])
            ->post('/saas/admin/settings', ['platform_name' => 'Defaced Platform'])
            ->assertStatus(403);
    }

    /**
     * Test 4: Paystack Secret Key is NEVER rendered in HTML, JSON, or view data.
     */
    public function test_paystack_secret_key_is_never_rendered_in_html_or_view_payloads(): void
    {
        $secretKey = 'sk_test_SUPER_CONFIDENTIAL_PAYSTACK_KEY_999888';
        SaaSSetting::set('paystack_secret_key', $secretKey);

        $response = $this->actingAs($this->platformAdmin)
            ->withSession([
                'user_id' => $this->platformAdmin->id,
                'tenant_id' => 'default-tenant',
            ])
            ->get('/saas/admin');

        $response->assertStatus(200);

        // Invariant: Secret key MUST NOT exist anywhere in HTML response body
        $response->assertDontSee($secretKey);
        $this->assertStringNotContainsString($secretKey, $response->getContent());

        // View displays masked indicator
        $response->assertSee('Configured');

        // Blank/Mask update preserves existing secret key
        $this->actingAs($this->platformAdmin)
            ->withSession(['user_id' => $this->platformAdmin->id, 'tenant_id' => 'default-tenant'])
            ->post('/saas/admin/settings', [
                'platform_name' => 'Updated Platform Name',
                'paystack_secret_key' => '', // Blank should NOT erase key
            ])->assertRedirect();

        $this->assertEquals($secretKey, SaaSSetting::get('paystack_secret_key'));

        // Mask bullets should NOT overwrite key
        $this->actingAs($this->platformAdmin)
            ->withSession(['user_id' => $this->platformAdmin->id, 'tenant_id' => 'default-tenant'])
            ->post('/saas/admin/settings', [
                'paystack_secret_key' => '••••••••••••••••',
            ])->assertRedirect();

        $this->assertEquals($secretKey, SaaSSetting::get('paystack_secret_key'));

        // Explicit new secret updates successfully
        $newSecret = 'sk_live_NEW_AUTHENTIC_PRODUCTION_KEY_111222';
        $this->actingAs($this->platformAdmin)
            ->withSession(['user_id' => $this->platformAdmin->id, 'tenant_id' => 'default-tenant'])
            ->post('/saas/admin/settings', [
                'paystack_secret_key' => $newSecret,
            ])->assertRedirect();

        $this->assertEquals($newSecret, SaaSSetting::get('paystack_secret_key'));
    }

    /**
     * Test 5: Tenant ID Immutability: Once created, tenant_id cannot be changed on any model.
     */
    public function test_tenant_id_is_strictly_immutable_on_existing_records(): void
    {
        // 1. Product tenant immutability
        $product = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Exclusive Rice',
            'category' => 'Groceries',
            'code' => 'RICE-A01',
            'unitPrice' => 50000,
        ]);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Cross-Tenant Security Violation: Immutable attribute 'tenant_id' cannot be altered");

        $product->tenant_id = $this->tenantB->id;
        $product->save();
    }

    /**
     * Test 6: Mass assignment update cannot alter tenant_id across tenants.
     */
    public function test_mass_assignment_update_cannot_alter_tenant_id(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alhaji Musa Alpha',
            'phone' => '08033333333',
            'total_debt' => 10000,
        ]);

        $this->expectException(SecurityException::class);
        $customer->update(['tenant_id' => $this->tenantB->id]);
    }

    /**
     * Test 7: User model cannot have tenant_id changed after creation.
     */
    public function test_user_tenant_id_is_immutable(): void
    {
        $this->expectException(SecurityException::class);
        $this->cashierA1->tenant_id = $this->tenantB->id;
        $this->cashierA1->save();
    }

    /**
     * Test 8: DatabaseSeeder preserves existing administrator credentials on re-seeding.
     */
    public function test_database_seeder_preserves_existing_user_passwords(): void
    {
        // 1. Set custom password on SuperAdmin
        $customPassword = 'CustomAdminPassword#2026';
        $this->platformAdmin->update([
            'password' => Hash::make($customPassword),
        ]);

        // 2. Run Seeder
        $seeder = new \Database\Seeders\DatabaseSeeder();
        $seeder->run();

        // 3. Verify password was NOT overwritten
        $refreshedAdmin = User::withoutGlobalScopes()->find($this->platformAdmin->id);
        $this->assertTrue(Hash::check($customPassword, $refreshedAdmin->password), "Seeder must not reset existing admin password.");
    }

    /**
     * Test 9: DatabaseSeeder rejects known default passwords when in production environment.
     */
    public function test_database_seeder_rejects_known_default_passwords_in_production(): void
    {
        // Mock production environment
        $this->app->detectEnvironment(fn() => 'production');
        putenv('SUPER_ADMIN_PASSWORD=changeme123');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Production seeding requires a secure, non-default SUPER_ADMIN_PASSWORD");

        $seeder = new \Database\Seeders\DatabaseSeeder();
        $seeder->run();
    }

    /**
     * Test 10: Service-layer authority closure rejects direct foreign branch mutations.
     */
    public function test_service_layer_rejects_cross_branch_inventory_and_sales_mutations(): void
    {
        // Authenticate Cashier A1 (assigned to Warehouse A1)
        $this->actingAs($this->cashierA1);
        session(['tenant_id' => $this->tenantA->id]);

        $product = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Milk',
            'category' => 'Beverages',
            'code' => 'MILK-A01',
            'unitPrice' => 1000,
        ]);

        // Attempting to ensure stock level or mutate on Warehouse A2 (foreign branch) must fail!
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("cannot operate on Branch #{$this->warehouseA2->id}");

        $this->stockService->ensureStockLevelForAuthorizedMutation($product->id, $this->warehouseA2->id);
    }

    /**
     * Test 11: Service-layer debt correction rejects branch-scoped employee.
     */
    public function test_service_layer_debt_correction_rejects_branch_scoped_employee(): void
    {
        $this->actingAs($this->cashierA1);
        session(['tenant_id' => $this->tenantA->id]);

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Debtor Customer',
            'phone' => '08099990000',
            'total_debt' => 50000,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Branch-scoped employees are not authorized to unilaterally modify company-wide customer debt");

        $this->accountingService->correctCustomerDebt($customer, 10000, 'Unauthorized employee reduction');
    }

    /**
     * Test 12: Platform Employee cannot perform tenant business mutations via service layer.
     */
    public function test_service_layer_blocks_platform_employee_from_tenant_mutations(): void
    {
        $this->actingAs($this->platformEmployeeHealth);
        session(['tenant_id' => $this->tenantA->id]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("Platform user '{$this->platformEmployeeHealth->name}' cannot perform tenant business operations");

        $this->stockService->assertUserWarehouseAuthority($this->warehouseA1->id);
    }

    /**
     * Test 13: Tenant user cannot access platform control panel or operations.
     */
    public function test_tenant_user_cannot_access_platform_control_panel(): void
    {
        // Tenant Admin attempts to enter SaaS control panel -> 403 or redirect
        $response = $this->actingAs($this->tenantAdminA)
            ->withSession(['user_id' => $this->tenantAdminA->id, 'tenant_id' => $this->tenantA->id])
            ->get('/saas/admin');

        $response->assertRedirect();
    }
}
