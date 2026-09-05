<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Activity;
use App\Exceptions\SecurityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass10Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $warehouseA;
    protected User $tenantAdminA;
    protected User $staffA;
    protected User $platformAdmin;
    protected Product $productA;
    protected Customer $customerA;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@pass10platform.ng',
        ]);

        // Default Platform Tenant
        Tenant::withoutGlobalScopes()->firstOrCreate([
            'id' => 'default-tenant',
        ], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@pass10platform.ng',
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
            'email' => 'superadmin@pass10platform.ng',
            'password' => Hash::make('SuperSecretPass10!'),
            'role' => 'admin',
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);

        // Tenant A
        $this->tenantA = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-pass10-alpha',
            'name' => 'Pass10 Alpha Retail Ltd',
            'owner_email' => 'owner@pass10alpha.ng',
            'owner_phone' => '08011223344',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        // Tenant B
        $this->tenantB = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-pass10-beta',
            'name' => 'Pass10 Beta Retail Ltd',
            'owner_email' => 'owner@pass10beta.ng',
            'owner_phone' => '08055667788',
            'status' => 'active',
            'plan' => 'pro',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        $this->warehouseA = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pass10 Main Branch',
            'code' => 'WH-P10',
            'is_active' => true,
        ]);

        $this->tenantAdminA = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Admin',
            'email' => 'admin@pass10alpha.ng',
            'password' => Hash::make('AdminPass10!'),
            'role' => 'admin',
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);

        $this->staffA = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => $this->warehouseA->id,
            'name' => 'Alpha Cashier',
            'email' => 'cashier@pass10alpha.ng',
            'password' => Hash::make('CashierPass10!'),
            'role' => 'cashier',
            'disabled' => false,
            'permissions' => ['pos' => true],
        ]);

        $this->productA = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Sugar 50kg',
            'category' => 'Groceries',
            'code' => 'SUGAR-P10',
            'unitPrice' => 80000,
        ]);

        $this->customerA = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Chief Okoro',
            'phone' => '08099887766',
            'total_debt' => 25000,
        ]);
    }

    /**
     * Test 1: Bulk query update on Product attempting to mutate tenant_id throws SecurityException.
     */
    public function test_bulk_query_update_cannot_mutate_tenant_id_on_product(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Bulk query mutation of immutable attribute 'tenant_id' is strictly forbidden");

        Product::where('category', 'Groceries')->update([
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Test 2: Bulk query update on Customer attempting to mutate tenant_id throws SecurityException.
     */
    public function test_bulk_query_update_cannot_mutate_tenant_id_on_customer(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Bulk query mutation of immutable attribute 'tenant_id' is strictly forbidden");

        Customer::where('id', $this->customerA->id)->update([
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Test 3: Bulk query update withoutGlobalScopes() attempting to mutate tenant_id throws SecurityException.
     */
    public function test_bulk_query_update_without_global_scopes_cannot_mutate_tenant_id(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Bulk query mutation of immutable attribute 'tenant_id' is strictly forbidden");

        Product::withoutGlobalScopes()->where('id', $this->productA->id)->update([
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Test 4: Platform tenant creation (storeTenant) does not expose temporary password in session or HTML.
     */
    public function test_platform_tenant_creation_does_not_render_temporary_password(): void
    {
        $response = $this->actingAs($this->platformAdmin)
            ->withSession(['user_id' => $this->platformAdmin->id, 'tenant_id' => 'default-tenant'])
            ->post('/saas/admin/tenant', [
                'business_name' => 'Gamma Provisions Ltd',
                'owner_name'    => 'Elder Gamma',
                'owner_email'   => 'gamma@provisions.ng',
                'owner_phone'   => '08099881122',
                'plan'          => 'pro',
                'status'        => 'active',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Invariant: Success flash message MUST NOT contain temporary passwords or credentials
        $flashSuccess = session('success');
        $this->assertNotNull($flashSuccess);
        $this->assertStringNotContainsString('password:', strtolower($flashSuccess));
        $this->assertStringNotContainsString('Generated one-time temporary password', $flashSuccess);
        $this->assertStringContainsString('activation notice has been recorded', $flashSuccess);

        // Follow redirect to dashboard and ensure HTML response body has zero passwords
        $dashboardResponse = $this->actingAs($this->platformAdmin)
            ->withSession(['user_id' => $this->platformAdmin->id, 'tenant_id' => 'default-tenant'])
            ->get('/saas/admin');

        $dashboardResponse->assertDontSee('Generated one-time temporary password');
    }

    /**
     * Test 5: Tenant status mutation (toggleStatus) strictly validates in:active,trial,suspended.
     */
    public function test_tenant_status_mutation_strictly_validates_allowed_states(): void
    {
        // 1. Invalid status value is rejected with validation error
        $response = $this->actingAs($this->platformAdmin)
            ->withSession(['user_id' => $this->platformAdmin->id, 'tenant_id' => 'default-tenant'])
            ->post("/saas/admin/toggle/{$this->tenantA->id}", [
                'status' => 'hacked_status_corrupted',
            ]);

        $response->assertSessionHasErrors(['status']);

        // Verify status was NOT altered in database
        $this->assertEquals('active', $this->tenantA->fresh()->status);

        // 2. Valid status value is accepted
        $validResponse = $this->actingAs($this->platformAdmin)
            ->withSession(['user_id' => $this->platformAdmin->id, 'tenant_id' => 'default-tenant'])
            ->post("/saas/admin/toggle/{$this->tenantA->id}", [
                'status' => 'suspended',
            ]);

        $validResponse->assertSessionHasNoErrors();
        $this->assertEquals('suspended', $this->tenantA->fresh()->status);
    }

    /**
     * Test 6: Public self-registration rejects weak passwords.
     */
    public function test_public_registration_rejects_weak_passwords(): void
    {
        // Too short (< 8)
        $this->post(route('saas.register.post'), [
            'business_name' => 'Short Pw Store',
            'owner_name'    => 'Tester',
            'owner_email'   => 'short@pw.ng',
            'owner_phone'   => '08011111111',
            'password'      => 'Weak1!',
            'plan'          => 'basic',
        ])->assertSessionHasErrors(['password']);

        // Missing uppercase
        $this->post(route('saas.register.post'), [
            'business_name' => 'No Upper Store',
            'owner_name'    => 'Tester',
            'owner_email'   => 'noupper@pw.ng',
            'owner_phone'   => '08011111111',
            'password'      => 'alllowercase123',
            'plan'          => 'basic',
        ])->assertSessionHasErrors(['password']);

        // Missing number
        $this->post(route('saas.register.post'), [
            'business_name' => 'No Number Store',
            'owner_name'    => 'Tester',
            'owner_email'   => 'nonumber@pw.ng',
            'owner_phone'   => '08011111111',
            'password'      => 'AllLettersOnly!',
            'plan'          => 'basic',
        ])->assertSessionHasErrors(['password']);
    }

    /**
     * Test 7: Public self-registration rejects honeypot bot submissions with 422.
     */
    public function test_public_registration_rejects_honeypot_bot_submissions(): void
    {
        $response = $this->post(route('saas.register.post'), [
            'registration_hp_check' => 'I_AM_A_SPAM_BOT',
            'business_name'         => 'Spam Store',
            'owner_name'            => 'Bot',
            'owner_email'           => 'bot@spam.ng',
            'owner_phone'           => '08011111111',
            'password'              => 'ValidSecret#2026',
            'plan'                  => 'basic',
        ]);

        $response->assertStatus(422);
        $this->assertNull(Tenant::withoutGlobalScopes()->where('owner_email', 'bot@spam.ng')->first());
    }

    /**
     * Test 8: Privileged password reset audits activity with complete context.
     */
    public function test_privileged_password_reset_creates_activity_audit_record(): void
    {
        $this->actingAs($this->tenantAdminA);
        session(['tenant_id' => $this->tenantA->id]);

        $response = $this->post(route('users.reset.password', $this->staffA->id), [
            'new_password' => 'NewAuthorizedPassword#2026',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('users.index'));

        // Verify password was updated
        $freshStaff = User::withoutGlobalScopes()->find($this->staffA->id);
        $this->assertTrue(Hash::check('NewAuthorizedPassword#2026', $freshStaff->password));

        // Verify Activity audit record was created
        $audit = Activity::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)
            ->where('type', 'PASSWORD_RESET')
            ->first();

        $this->assertNotNull($audit, "Password reset must create an Activity audit record.");
        $this->assertEquals($this->tenantAdminA->id, $audit->userId);
        $this->assertStringContainsString($this->staffA->name, $audit->description);
        $this->assertStringContainsString($this->staffA->email, $audit->description);
        $this->assertStringContainsString($this->tenantAdminA->name, $audit->description);
    }

    /**
     * Test 9: Privileged password reset rejects weak passwords.
     */
    public function test_privileged_password_reset_rejects_weak_passwords(): void
    {
        $this->actingAs($this->tenantAdminA);
        session(['tenant_id' => $this->tenantA->id]);

        $response = $this->post(route('users.reset.password', $this->staffA->id), [
            'new_password' => 'weak',
        ]);

        $response->assertSessionHasErrors(['new_password']);
    }
}
