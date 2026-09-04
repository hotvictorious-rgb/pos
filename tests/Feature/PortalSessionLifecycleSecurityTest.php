<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PortalSessionLifecycleSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $masterTenant;
    protected User $superAdmin;
    protected User $platformStaff;
    protected Tenant $tenantA;
    protected User $adminA;
    protected User $cashierA;
    protected Warehouse $warehouseA;
    protected Tenant $tenantB;
    protected User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true]);

        // 1. Platform Master Tenant & Accounts
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
            'id' => 'super-admin-root',
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@hysam.com',
            'password' => Hash::make('supersecret'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        $this->platformStaff = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => 'platform-staff-1',
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Tech Support',
            'email' => 'tech@hysam.com',
            'password' => Hash::make('support123'),
            'role' => 'staff',
            'disabled' => false,
        ]);

        // 2. Customer Tenant A
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

        $this->cashierA = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Cashier',
            'email' => 'cashier@test.com',
            'password' => Hash::make('cashierpass'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
            'permissions' => ['pos' => true],
        ]);

        // 3. Customer Tenant B
        $this->tenantB = Tenant::create([
            'id' => 'tenant-beta',
            'name' => 'Beta Corporation',
            'owner_email' => 'beta@test.com',
            'owner_phone' => '0802222222',
            'plan' => 'pro',
            'status' => 'active',
        ]);

        $this->adminB = User::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Admin',
            'email' => 'beta@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'disabled' => false,
        ]);
    }

    /**
     * TEST: The Full Cross-Portal Sequential Transition Attack
     * Tenant Admin -> logout -> Super Admin -> logout -> Tenant Employee -> logout -> Platform Employee
     */
    public function test_full_cross_portal_transition_chain_leak_prevention()
    {
        // ── STEP 1: Tenant Admin Login ──
        $res1 = $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);
        $res1->assertRedirect('/');
        $this->assertEquals($this->adminA->id, session('user_id'));
        $this->assertEquals($this->tenantA->id, session('tenant_id'));
        $this->assertEquals('admin', session('user_role'));
        $this->assertEquals('tenant', session('portal'));

        // ── LOGOUT Tenant Admin ──
        $out1 = $this->post(route('portal.tenant.logout'));
        $out1->assertRedirect(route('portal.tenant.login'));
        $this->assertNull(session('user_id'));
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('user_role'));
        $this->assertNull(session('portal'));
        $this->assertNull(session('impersonator_id'));
        $this->assertFalse(session()->has('is_impersonating'));

        // ── STEP 2: Super Admin Login ──
        $res2 = $this->post(route('portal.super_admin.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);
        $res2->assertRedirect(route('saas.admin.index'));
        $this->assertEquals($this->superAdmin->id, session('user_id'));
        $this->assertEquals('default-tenant', session('tenant_id'));
        $this->assertEquals('admin', session('user_role'));
        $this->assertEquals('super-admin', session('portal'));
        $this->assertNotEquals($this->tenantA->id, session('tenant_id'));

        // ── LOGOUT Super Admin ──
        $out2 = $this->post(route('portal.super_admin.logout'));
        $out2->assertRedirect(route('portal.super_admin.login'));
        $this->assertNull(session('user_id'));
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('user_role'));
        $this->assertNull(session('portal'));

        // ── STEP 3: Tenant Employee Login ──
        $res3 = $this->post(route('portal.tenant_employee.login.post'), [
            'email' => 'cashier@test.com',
            'password' => 'cashierpass',
        ]);
        $res3->assertRedirect('/');
        $this->assertEquals($this->cashierA->id, session('user_id'));
        $this->assertEquals($this->tenantA->id, session('tenant_id'));
        $this->assertEquals('cashier', session('user_role'));
        $this->assertEquals('tenant-employee', session('portal'));

        // ── LOGOUT Tenant Employee ──
        $out3 = $this->post(route('portal.tenant_employee.logout'));
        $out3->assertRedirect(route('portal.tenant_employee.login'));
        $this->assertNull(session('user_id'));
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('user_role'));
        $this->assertNull(session('portal'));

        // ── STEP 4: Platform Employee Login ──
        $res4 = $this->post(route('portal.super_admin_employee.login.post'), [
            'email' => 'tech@hysam.com',
            'password' => 'support123',
        ]);
        $res4->assertRedirect(route('saas.admin.index'));
        $this->assertEquals($this->platformStaff->id, session('user_id'));
        $this->assertEquals('default-tenant', session('tenant_id'));
        $this->assertEquals('staff', session('user_role'));
        $this->assertEquals('super-admin-employee', session('portal'));

        // ── LOGOUT Platform Employee ──
        $out4 = $this->post(route('portal.super_admin_employee.logout'));
        $out4->assertRedirect(route('portal.super_admin_employee.login'));
        $this->assertNull(session('user_id'));
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('portal'));
    }

    /**
     * TEST 1: Tenant employee -> logout -> super-admin login
     * Ensures employee cannot carry over branch or staff restrictions into super-admin.
     */
    public function test_tenant_employee_logout_then_super_admin_login()
    {
        $this->post(route('portal.tenant_employee.login.post'), [
            'email' => 'cashier@test.com',
            'password' => 'cashierpass',
        ]);
        $this->post(route('portal.tenant_employee.logout'));

        $response = $this->post(route('portal.super_admin.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);

        $response->assertRedirect(route('saas.admin.index'));
        $this->assertEquals('default-tenant', session('tenant_id'));
        $this->assertEquals('super-admin', session('portal'));
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    /**
     * TEST 2: Super admin -> logout -> tenant login
     * Ensures tenant user does NOT retain super-admin authority.
     */
    public function test_super_admin_logout_then_tenant_login()
    {
        $this->post(route('portal.super_admin.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);
        $this->post(route('portal.super_admin.logout'));

        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertEquals($this->tenantA->id, session('tenant_id'));
        $this->assertEquals('tenant', session('portal'));

        // Attempt to access super-admin panel
        $adminAttempt = $this->get(route('saas.admin.index'));
        $adminAttempt->assertRedirect(route('dashboard'));
    }

    /**
     * TEST 3: Super admin -> impersonate tenant -> stop impersonation
     * Clean state restoration verified.
     */
    public function test_super_admin_impersonate_and_stop_lifecycle()
    {
        $this->post(route('portal.super_admin.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);

        // Start impersonation
        $impRes = $this->post(route('saas.admin.impersonate', ['id' => $this->tenantA->id]));
        $impRes->assertRedirect('/');
        $this->assertEquals($this->tenantA->id, session('tenant_id'));
        $this->assertTrue(session('is_impersonating'));
        $this->assertEquals($this->superAdmin->id, session('impersonator_id'));

        // Stop impersonation
        $stopRes = $this->post(route('saas.admin.stop_impersonate'));
        $stopRes->assertRedirect(route('saas.admin.index'));
        $this->assertEquals('default-tenant', session('tenant_id'));
        $this->assertFalse(session()->has('is_impersonating'));
        $this->assertFalse(session()->has('impersonator_id'));
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    /**
     * TEST 4: Impersonating admin -> logout
     * Logging out during impersonation must cleanly purge all impersonation flags.
     */
    public function test_logout_during_active_impersonation_purges_all_keys()
    {
        $this->post(route('portal.super_admin.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);
        $this->post(route('saas.admin.impersonate', ['id' => $this->tenantA->id]));

        $this->assertTrue(session('is_impersonating'));

        // User logs out while impersonation is active
        $logoutRes = $this->post(route('portal.tenant.logout'));
        $logoutRes->assertRedirect(route('portal.tenant.login'));

        $this->assertFalse(session()->has('is_impersonating'));
        $this->assertFalse(session()->has('impersonator_id'));
        $this->assertNull(session('user_id'));
        $this->assertNull(session('tenant_id'));
        $this->assertGuest();
    }

    /**
     * TEST 5: Disabled user with existing active session
     * Verified immediate termination on subsequent request.
     */
    public function test_disabled_user_with_active_session_is_terminated_immediately()
    {
        $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);
        $this->assertAuthenticated();

        // Administrator disables account in database
        $this->adminA->update(['disabled' => true]);

        // User makes subsequent request
        $response = $this->get('/');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('user_id'));
    }

    /**
     * TEST 6: Suspended tenant with existing active session
     * Verified immediate lockout on subsequent request.
     */
    public function test_suspended_tenant_with_active_session_is_blocked_immediately()
    {
        $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);
        $this->assertAuthenticated();

        // Tenant subscription is suspended
        $this->tenantA->update(['status' => 'suspended']);

        // User makes subsequent request
        $response = $this->get('/');
        $response->assertRedirect(route('saas.suspended'));
    }

    /**
     * TEST 7: Session ID reuse / fixation prevention
     * Session ID must regenerate on login and invalidate on logout.
     */
    public function test_session_id_regeneration_and_invalidation()
    {
        $this->get(route('portal.tenant.login'));
        $guestSessionId = session()->getId();

        $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);
        $authenticatedSessionId = session()->getId();

        $this->assertNotEquals($guestSessionId, $authenticatedSessionId);

        $this->post(route('portal.tenant.logout'));
        $loggedOutSessionId = session()->getId();

        $this->assertNotEquals($authenticatedSessionId, $loggedOutSessionId);
    }

    /**
     * TEST 8: Open protected tenant URL after switching portals
     * User in Tenant B cannot view Tenant A receipt or product.
     */
    public function test_cannot_access_other_tenant_protected_url_after_portal_switch()
    {
        $prodA = Product::withoutGlobalScope(TenantScope::class)->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Secret Alpha Item',
            'code' => 'ALPHA-SEC-01',
            'category' => 'Test',
            'unitPrice' => 5000,
            'currentStock' => 10,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        // Login as Tenant B
        $this->post(route('portal.tenant.login.post'), [
            'email' => 'beta@test.com',
            'password' => 'password123',
        ]);

        // Attempt to update Tenant A product
        $response = $this->post("/products/{$prodA->id}", [
            'name' => 'Tampered Name',
            'category' => 'Hacked',
            'unitPrice' => 1,
        ]);

        $response->assertStatus(404);
        $this->assertEquals('Secret Alpha Item', $prodA->fresh()->name);
    }

    /**
     * TEST 9: Open /saas/admin after switching from super-admin to tenant session
     */
    public function test_open_saas_admin_blocked_after_switching_to_tenant()
    {
        // 1. Super admin logs in then logs out
        $this->post(route('portal.super_admin.login.post'), [
            'email' => 'superadmin@hysam.com',
            'password' => 'supersecret',
        ]);
        $this->post(route('portal.super_admin.logout'));

        // 2. Tenant admin logs in
        $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        // 3. Attempt to open /saas/admin
        $response = $this->get(route('saas.admin.index'));
        $response->assertRedirect(route('dashboard'));
    }

    /**
     * TEST 10: Try API with old session after logout
     */
    public function test_api_unauthenticated_after_portal_logout()
    {
        $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        // Verify API access works while authenticated
        $apiActive = $this->get('/api/me');
        $apiActive->assertStatus(200);

        // Logout
        $this->post(route('portal.tenant.logout'));

        // Attempt API access with wiped session
        $apiDead = $this->get('/api/me');
        $apiDead->assertStatus(401);
    }

    /**
     * TEST 11: Cross-portal intended URL sanitization
     * Visiting /saas/admin as guest then logging into Tenant portal must NOT redirect to /saas/admin.
     */
    public function test_intended_url_sanitization_prevents_redirect_to_saas_admin_on_tenant_login()
    {
        // Guest visits /saas/admin
        $this->get('/saas/admin');
        $this->assertEquals(url('/saas/admin'), session('url.intended'));

        // Guest logs in through Tenant Portal
        $response = $this->post(route('portal.tenant.login.post'), [
            'email' => 'alpha@test.com',
            'password' => 'password123',
        ]);

        // Must redirect to '/' and NOT '/saas/admin'
        $response->assertRedirect('/');
        $this->assertNotEquals('/saas/admin', session('url.intended'));
    }
}
