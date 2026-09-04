<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Backup;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MultiTenantSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $masterTenant;
    protected User $superAdmin;
    protected Tenant $tenantA;
    protected User $adminA;
    protected Warehouse $warehouseA;
    protected Tenant $tenantB;
    protected User $adminB;
    protected Warehouse $warehouseB;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true]);

        // 1. Seed Platform Master Tenant & Super Admin
        $this->masterTenant = Tenant::firstOrCreate(
            ['id' => 'default-tenant'],
            [
                'name' => 'Default Platform Master Tenant',
                'owner_email' => 'admin@hysam.com',
                'owner_phone' => '0800000000',
                'plan' => 'enterprise',
                'status' => 'active',
            ]
        );

        $this->superAdmin = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => 'super-admin-user',
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@hysam.com',
            'password' => Hash::make('supersecret'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        // 2. Seed Customer Tenant A
        $this->tenantA = Tenant::create([
            'id' => 'tenant-alpha',
            'name' => 'Alpha Corporation',
            'owner_email' => 'alpha@test.com',
            'owner_phone' => '0801111111',
            'plan' => 'pro',
            'status' => 'active',
        ]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Main Warehouse',
            'code' => 'WH-ALPHA-01',
            'is_active' => true,
        ]);

        $this->adminA = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Admin',
            'email' => 'alpha@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);

        // 3. Seed Customer Tenant B
        $this->tenantB = Tenant::create([
            'id' => 'tenant-beta',
            'name' => 'Beta Corporation',
            'owner_email' => 'beta@test.com',
            'owner_phone' => '0802222222',
            'plan' => 'pro',
            'status' => 'active',
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Main Warehouse',
            'code' => 'WH-BETA-01',
            'is_active' => true,
        ]);

        $this->adminB = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Admin',
            'email' => 'beta@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseB->id,
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);
    }

    public function test_tenant_scope_fails_closed_when_session_tenant_is_absent()
    {
        session()->forget('tenant_id');

        Product::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-alpha',
            'name' => 'Test Product A',
            'code' => 'PROD-A',
            'category' => 'General',
            'unitPrice' => 1000,
            'currentStock' => 10,
            'minStockLevel' => 2,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $this->assertEquals(0, Product::count());
    }

    public function test_authentication_resolves_user_globally_and_establishes_tenant_context()
    {
        session()->forget('tenant_id');

        $response = $this->post(route('login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertEquals($this->tenantA->id, session('tenant_id'));
        $this->assertAuthenticatedAs($this->adminA);
    }

    public function test_wrong_password_does_not_establish_tenant_session()
    {
        session()->forget('tenant_id');

        $response = $this->post(route('login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'wrong-password-here',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_disabled_user_cannot_log_in()
    {
        $this->adminA->update(['disabled' => true]);
        session()->forget('tenant_id');

        $response = $this->post(route('login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_disabled_authenticated_user_cannot_access_protected_api()
    {
        // User starts active then gets disabled
        $this->adminA->update(['disabled' => true]);

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get('/api/me');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Your session has expired or your account is disabled.']);
    }

    public function test_orphaned_user_cannot_authenticate_in_saas_mode()
    {
        $orphanedUser = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => null,
            'name' => 'Orphan User',
            'email' => 'orphan@test.com',
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'disabled' => false,
        ]);

        $response = $this->post(route('login.post'), [
            'email' => 'orphan@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_deleted_tenant_cannot_access_application()
    {
        // Delete Tenant A
        $this->tenantA->delete();

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'tenant_id' => 'tenant-alpha',
        ])->get('/');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_suspended_tenant_cannot_access_protected_api()
    {
        $this->tenantA->update(['status' => 'suspended']);

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'tenant_id' => $this->tenantA->id,
        ])->get('/api/data');

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden: Your business subscription has expired or been suspended.']);
    }

    public function test_tenant_a_cannot_update_tenant_b_product()
    {
        // Product created in Tenant B
        $prodB = Product::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Secret Formula',
            'code' => 'BETA-01',
            'category' => 'Chemicals',
            'unitPrice' => 5000,
            'currentStock' => 20,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        // Tenant A admin attempts to update Tenant B product
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->post("/products/{$prodB->id}", [
            'name' => 'Hacked Formula',
            'category' => 'Hacked',
            'unitPrice' => 10,
        ]);

        $response->assertStatus(404);
        $this->assertEquals('Beta Secret Formula', $prodB->fresh()->name);
    }

    public function test_tenant_a_cannot_delete_tenant_b_product()
    {
        $prodB = Product::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta To Delete',
            'code' => 'BETA-DEL',
            'category' => 'Chemicals',
            'unitPrice' => 5000,
            'currentStock' => 20,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->post("/products/{$prodB->id}/delete");

        $response->assertStatus(404);
        $this->assertNotNull(Product::withoutGlobalScope(TenantScope::class)->find($prodB->id));
    }

    public function test_tenant_a_cannot_access_tenant_b_sale()
    {
        $saleB = Sale::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'customerName' => 'VIP Beta Client',
            'totalAmount' => 50000,
            'paidAmount' => 50000,
            'cashAmount' => 50000,
            'posAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'SUPPLIED',
            'userId' => $this->adminB->id,
            'userName' => $this->adminB->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->get("/pos/receipt/{$saleB->id}");

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_access_tenant_b_branch()
    {
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->post("/settings/warehouse/update/{$this->warehouseB->id}", [
            'name' => 'Hijacked Warehouse',
            'code' => 'HIJACK-01',
        ]);

        $response->assertStatus(404);
        $this->assertEquals('Beta Main Warehouse', $this->warehouseB->fresh()->name);
    }

    public function test_tenant_a_cannot_list_tenant_b_backups()
    {
        // Backup for Tenant B
        Backup::create([
            'id' => 'BK-BETA-LIST-1',
            'filename' => 'backup_tenant-beta_admin_2026-09-01_12-00-00.json',
            'size' => 1024,
            'created_by' => 'Admin Beta [tenant-beta]',
        ]);

        // Tenant admin is forbidden from accessing backups entirely
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->get('/api/backups');

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden. Tenant users cannot access platform management.']);

        // Super Admin CAN access backups
        $superResponse = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_role' => 'admin',
            'tenant_id' => 'default-tenant',
        ])->get('/api/backups');

        $superResponse->assertStatus(200);
    }

    public function test_tenant_a_cannot_download_tenant_b_backup()
    {
        $backupB = Backup::create([
            'id' => 'BK-BETA-DL-1',
            'filename' => 'backup_tenant-beta_admin_2026-09-01_12-00-00.json',
            'size' => 1024,
            'created_by' => 'Admin Beta [tenant-beta]',
        ]);

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->get("/api/backups/{$backupB->id}/download");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden. Tenant users cannot access platform management.']);
    }

    public function test_tenant_a_cannot_restore_tenant_b_backup()
    {
        $backupB = Backup::create([
            'id' => 'BK-BETA-REST-1',
            'filename' => 'backup_tenant-beta_admin_2026-09-01_12-00-00.json',
            'size' => 1024,
            'created_by' => 'Admin Beta [tenant-beta]',
        ]);

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->post("/api/backups/{$backupB->id}/restore");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden. Tenant users cannot access platform management.']);
    }

    public function test_tenant_a_cannot_delete_tenant_b_backup()
    {
        $backupB = Backup::create([
            'id' => 'BK-BETA-DEL-1',
            'filename' => 'backup_tenant-beta_admin_2026-09-01_12-00-00.json',
            'size' => 1024,
            'created_by' => 'Admin Beta [tenant-beta]',
        ]);

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->delete("/api/backups/{$backupB->id}");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden. Tenant users cannot access platform management.']);
        $this->assertNotNull(Backup::find('BK-BETA-DEL-1'));
    }

    public function test_normal_tenant_admin_cannot_access_saas_master_control_panel()
    {
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_name' => $this->adminA->name,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->get(route('saas.admin.index'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_tenant_impersonation_subsystem_is_permanently_eliminated()
    {
        // 1. Tenant admin attempting to hit impersonate route gets 404 (route eliminated)
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_name' => $this->adminA->name,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->post('/saas/admin/impersonate/' . $this->tenantB->id);

        $response->assertStatus(404);

        // 2. Platform super admin attempting to hit impersonate route gets 404
        $superResponse = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_name' => $this->superAdmin->name,
            'user_role' => 'admin',
            'tenant_id' => 'default-tenant',
        ])->post('/saas/admin/impersonate/' . $this->tenantA->id);

        $superResponse->assertStatus(404);

        // 3. Stop impersonation route is also permanently gone
        $stopResponse = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_name' => $this->superAdmin->name,
            'user_role' => 'admin',
            'tenant_id' => 'default-tenant',
        ])->post('/saas/admin/stop-impersonate');

        $stopResponse->assertStatus(404);
    }

    public function test_client_supplied_tenant_id_cannot_create_record_in_another_tenant()
    {
        // Tenant A admin attempts to post a product with 'tenant_id' => 'tenant-beta'
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->post('/products', [
            'name' => 'Injected Tenant Product',
            'code' => 'INJECT-01',
            'category' => 'Test',
            'unitPrice' => 200,
            'initial_stock' => 10,
            'tenant_id' => $this->tenantB->id,
        ]);

        $response->assertRedirect(route('products.index'));

        // Verify product was created under Tenant A, NOT Tenant B
        $product = Product::withoutGlobalScope(TenantScope::class)->where('code', 'INJECT-01')->first();
        $this->assertNotNull($product);
        $this->assertEquals($this->tenantA->id, $product->tenant_id);
    }

    public function test_client_supplied_tenant_id_cannot_update_record_into_another_tenant()
    {
        $prodA = Product::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Original Alpha Product',
            'code' => 'ORIG-A-01',
            'category' => 'General',
            'unitPrice' => 100,
            'currentStock' => 5,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->post("/products/{$prodA->id}", [
            'name' => 'Attempted Move',
            'category' => 'General',
            'unitPrice' => 150,
            'tenant_id' => $this->tenantB->id,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertEquals($this->tenantA->id, $prodA->fresh()->tenant_id);
    }

    public function test_tenant_staff_cannot_create_or_modify_users()
    {
        // 1. Create a staff/cashier user under Tenant A
        $staffUser = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Cashier',
            'email' => 'cashier@test.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
            'permissions' => ['pos' => true],
        ]);

        // 2. Staff attempts to create another user
        $createResponse = $this->actingAs($staffUser)->withSession([
            'user_id' => $staffUser->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
        ])->post('/users', [
            'name' => 'Unauthorized User',
            'email' => 'unauth@test.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $this->assertTrue(in_array($createResponse->status(), [302, 403]));
        $this->assertNull(User::withoutGlobalScope(TenantScope::class)->where('email', 'unauth@test.com')->first());

        // 3. Staff attempts to elevate self to admin via /users/update/{id}
        $updateResponse = $this->actingAs($staffUser)->withSession([
            'user_id' => $staffUser->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
        ])->post("/users/update/{$staffUser->id}", [
            'name' => 'Alpha Cashier Promoted',
            'email' => 'cashier@test.com',
            'role' => 'admin',
        ]);

        $this->assertTrue(in_array($updateResponse->status(), [302, 403]));
        $this->assertEquals('cashier', $staffUser->fresh()->role);
    }

    public function test_tenant_staff_cannot_escalate_role_via_api_data()
    {
        $staffUser = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Staff 2',
            'email' => 'staff2@test.com',
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        $response = $this->actingAs($staffUser)->withSession([
            'user_id' => $staffUser->id,
            'user_role' => 'staff',
            'tenant_id' => $this->tenantA->id,
        ])->postJson('/api/data', [
            'users' => [
                [
                    'id' => $staffUser->id,
                    'role' => 'admin',
                ]
            ]
        ]);

        $response->assertStatus(403);
        $this->assertEquals('staff', $staffUser->fresh()->role);
    }

    public function test_tenant_admin_cannot_call_api_reset()
    {
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->postJson('/api/reset');

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden. Tenant users cannot access platform management.']);
    }

    public function test_tenant_admin_cannot_assign_cross_tenant_warehouse_to_user()
    {
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->post('/users', [
            'name' => 'Spy Worker',
            'email' => 'spy@test.com',
            'password' => 'password123',
            'role' => 'staff',
            'warehouse_id' => $this->warehouseB->id, // Belongs to Tenant B
        ]);

        $response->assertSessionHasErrors(['warehouse_id']);
        $this->assertNull(User::withoutGlobalScope(TenantScope::class)->where('email', 'spy@test.com')->first());
    }

    public function test_tenant_admin_cannot_assign_super_admin_role()
    {
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->post('/users', [
            'name' => 'Fake Super Admin',
            'email' => 'fakesuper@test.com',
            'password' => 'password123',
            'role' => 'super_admin',
        ]);

        $response->assertSessionHasErrors(['role']);
        $this->assertNull(User::withoutGlobalScope(TenantScope::class)->where('email', 'fakesuper@test.com')->first());
    }

    public function test_branch_scoped_cashier_cannot_process_return_for_another_branch()
    {
        // 1. Create a second warehouse for Tenant A
        $warehouseA2 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Ikeja Branch',
            'code' => 'WH-ALPHA-02',
            'is_active' => true,
        ]);

        // 2. Create branch-scoped cashier assigned to Alpha Main Warehouse (warehouseA)
        $cashierA = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Lekki Cashier',
            'email' => 'lekkicashier@test.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        // 3. Create a product and a sale under Tenant A
        $prodA = Product::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Rice Bag',
            'code' => 'RICE-01',
            'category' => 'Grains',
            'unitPrice' => 1000,
            'currentStock' => 10,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $saleA = Sale::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'customerName' => 'Walk-in',
            'totalAmount' => 2000,
            'paidAmount' => 2000,
            'cashAmount' => 2000,
            'posAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'SUPPLIED',
            'userId' => $cashierA->id,
            'userName' => $cashierA->name,
            'warehouse_id' => $this->warehouseA->id,
            'createdAt' => now()->toIso8601String(),
        ]);

        \App\Models\SaleItem::create([
            'saleId' => $saleA->id,
            'productId' => $prodA->id,
            'productName' => $prodA->name,
            'quantity' => 2,
            'unitPrice' => 1000,
            'totalPrice' => 2000,
            'code' => $prodA->code,
            'productCode' => $prodA->code,
        ]);

        // 4. Cashier attempts to process a return and supply warehouseA2 instead of assigned warehouseA
        $response = $this->actingAs($cashierA)->withSession([
            'user_id' => $cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
        ])->post('/pos/returns', [
            'sale_id' => $saleA->id,
            'warehouse_id' => $warehouseA2->id, // Attempt to divert stock to branch 2
            'items' => [
                ['productId' => $prodA->id, 'quantity' => 1, 'unitPrice' => 1000]
            ],
            'refund_method' => 'CASH_REFUND',
            'reason' => 'Customer return',
        ]);

        $response->assertRedirect(route('pos.returns'));

        // Verify stock was restored to cashier's assigned branch ($this->warehouseA->id), NOT warehouseA2
        $stockLevelA = \App\Models\StockLevel::where('product_id', $prodA->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->first();
        $stockLevelA2 = \App\Models\StockLevel::where('product_id', $prodA->id)
            ->where('warehouse_id', $warehouseA2->id)
            ->first();

        $this->assertNotNull($stockLevelA);
        $this->assertEquals(1, $stockLevelA->physical_stock);
        $this->assertTrue($stockLevelA2 === null || $stockLevelA2->physical_stock == 0);
    }

    // ─────────────────────────────────────────────────────────
    // FOUR-PORTAL AUTHENTICATION ADVERSARIAL TESTS
    // ─────────────────────────────────────────────────────────

    public function test_tenant_employee_cannot_login_through_tenant_admin_portal()
    {
        $cashier = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Store Cashier',
            'email' => 'cashier.test@test.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        session()->forget('tenant_id');

        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'cashier.test@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_tenant_admin_cannot_login_through_tenant_employee_portal()
    {
        session()->forget('tenant_id');

        $response = $this->post(route('portal.tenant_employee.login.post'), [
            'email' => 'alpha@test.com', // Tenant Admin
            'password' => 'password123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_tenant_admin_cannot_login_through_super_admin_portal()
    {
        session()->forget('tenant_id');

        $response = $this->post(route('portal.super_admin.login.post'), [
            'email' => 'alpha@test.com', // Tenant Admin
            'password' => 'password123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_tenant_employee_cannot_login_through_super_admin_portal()
    {
        $cashier = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Store Cashier 2',
            'email' => 'cashier2@test.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        session()->forget('tenant_id');

        $response = $this->post(route('portal.super_admin.login.post'), [
            'email' => 'cashier2@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_super_admin_cannot_login_through_tenant_portal()
    {
        session()->forget('tenant_id');

        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_super_admin_cannot_login_through_tenant_employee_portal()
    {
        session()->forget('tenant_id');

        $response = $this->post(route('portal.tenant_employee.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_platform_employee_cannot_login_through_tenant_portal()
    {
        $platformStaff = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Tech Support',
            'email' => 'tech@hysam.com',
            'password' => Hash::make('support123'),
            'role' => 'staff',
            'disabled' => false,
        ]);

        session()->forget('tenant_id');

        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'tech@hysam.com',
            'password' => 'support123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_platform_employee_cannot_login_through_super_admin_portal()
    {
        $platformStaff = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Tech Support 2',
            'email' => 'tech2@hysam.com',
            'password' => Hash::make('support123'),
            'role' => 'staff',
            'disabled' => false,
        ]);

        session()->forget('tenant_id');

        $response = $this->post(route('portal.super_admin.login.post'), [
            'email' => 'tech2@hysam.com',
            'password' => 'support123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_super_admin_cannot_login_through_platform_employee_portal()
    {
        session()->forget('tenant_id');

        $response = $this->post(route('portal.super_admin_employee.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_platform_employee_can_login_through_platform_employee_portal()
    {
        $platformStaff = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Tech Support 3',
            'email' => 'tech3@hysam.com',
            'password' => Hash::make('support123'),
            'role' => 'staff',
            'disabled' => false,
        ]);

        session()->forget('tenant_id');

        $response = $this->post(route('portal.super_admin_employee.login.post'), [
            'email' => 'tech3@hysam.com',
            'password' => 'support123',
        ]);

        $response->assertRedirect(route('saas.admin.index'));
        $this->assertEquals('default-tenant', session('tenant_id'));
        $this->assertEquals('super-admin-employee', session('portal'));
        $this->assertAuthenticatedAs($platformStaff);
    }

    public function test_tenant_admin_can_login_through_tenant_portal()
    {
        session()->forget('tenant_id');

        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertEquals($this->tenantA->id, session('tenant_id'));
        $this->assertEquals('tenant', session('portal'));
        $this->assertAuthenticatedAs($this->adminA);
    }

    public function test_tenant_employee_can_login_through_tenant_employee_portal()
    {
        $cashier = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Store Cashier 4',
            'email' => 'cashier4@test.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        session()->forget('tenant_id');

        $response = $this->post(route('portal.tenant_employee.login.post'), [
            'email' => 'cashier4@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertEquals($this->tenantA->id, session('tenant_id'));
        $this->assertEquals('tenant-employee', session('portal'));
        $this->assertAuthenticatedAs($cashier);
    }

    public function test_super_admin_can_login_through_super_admin_portal()
    {
        session()->forget('tenant_id');

        $response = $this->post(route('portal.super_admin.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);

        $response->assertRedirect(route('saas.admin.index'));
        $this->assertEquals('default-tenant', session('tenant_id'));
        $this->assertEquals('super-admin', session('portal'));
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_portal_login_regenerates_session_id()
    {
        $this->get(route('portal.tenant.login'));
        $initialSessionId = session()->getId();

        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $newSessionId = session()->getId();
        $this->assertNotEquals($initialSessionId, $newSessionId);
    }

    public function test_portal_logout_clears_session_and_redirects_to_portal_login()
    {
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant',
        ])->post(route('portal.tenant.logout'));

        $response->assertRedirect(route('portal.tenant.login'));
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('user_id'));
        $this->assertGuest();
    }

    public function test_disabled_user_rejected_at_portal_login()
    {
        $this->adminA->update(['disabled' => true]);
        session()->forget('tenant_id');

        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    public function test_suspended_tenant_rejected_at_portal_login()
    {
        $this->tenantA->update(['status' => 'suspended']);
        session()->forget('tenant_id');

        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }
}
