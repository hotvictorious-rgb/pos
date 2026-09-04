<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\SaaSSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SaaSConfigurablePricingAndRegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $tenantAdmin;
    protected $cashier;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'master.superadmin@vmarketplatform.com',
        ]);

        Tenant::firstOrCreate(
            ['id' => 'default-tenant'],
            [
                'name' => 'Platform Master Tenant',
                'owner_email' => 'master.superadmin@vmarketplatform.com',
                'owner_phone' => '08000000000',
                'plan' => 'enterprise',
                'status' => 'active',
                'max_branches' => 999,
                'max_users' => 999,
            ]
        );

        // Create platform Super Admin (master tenant)
        $this->superAdmin = User::firstOrCreate(
            ['email' => 'master.superadmin@vmarketplatform.com'],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => 'default-tenant',
                'name' => 'Platform Super Admin',
                'password' => Hash::make('SuperSecretPlatform#2026'),
                'role' => 'super_admin',
                'disabled' => false,
            ]
        );

        // Create standard Tenant
        $this->tenant = Tenant::firstOrCreate(
            ['id' => 'tenant-lekki-mart-test'],
            [
                'name' => 'Lekki Supermarket Mart',
                'owner_email' => 'owner@lekkimart.ng',
                'owner_phone' => '08099887766',
                'plan' => 'basic',
                'status' => 'active',
                'max_branches' => 3,
                'max_users' => 5,
            ]
        );

        $warehouse = Warehouse::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'Lekki Main'],
            [
                'code' => 'WH-LEKKI-01',
                'is_active' => true,
            ]
        );

        // Create Tenant Admin
        $this->tenantAdmin = User::firstOrCreate(
            ['email' => 'owner@lekkimart.ng'],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'name' => 'Madam Lekki Owner',
                'password' => Hash::make('StoreSecret#2026'),
                'role' => 'admin',
                'warehouse_id' => $warehouse->id,
                'disabled' => false,
            ]
        );

        // Create Cashier
        $this->cashier = User::firstOrCreate(
            ['email' => 'cashier@lekkimart.ng'],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'name' => 'Lekki Cashier 1',
                'password' => Hash::make('CashierSecret#2026'),
                'role' => 'cashier',
                'warehouse_id' => $warehouse->id,
                'disabled' => false,
            ]
        );
    }

    /**
     * TEST 1: Super Admin can configure subscription amounts and settings in SaaS settings
     */
    public function test_super_admin_can_update_subscription_pricing_and_settings()
    {
        $response = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_role' => 'super_admin',
            'tenant_id' => 'default-tenant',
        ])->post(route('saas.admin.settings'), [
            'monthly_price_basic' => '22500',
            'monthly_price_pro' => '48000',
            'monthly_price_enterprise' => '98000',
            'trial_days' => '21',
            'currency_symbol' => '₦',
            'allow_registration' => '1',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('22500', SaaSSetting::get('monthly_price_basic'));
        $this->assertEquals('48000', SaaSSetting::get('monthly_price_pro'));
        $this->assertEquals('98000', SaaSSetting::get('monthly_price_enterprise'));
        $this->assertEquals('21', SaaSSetting::get('trial_days'));
    }

    /**
     * TEST 2: Landing page dynamically reflects newly configured pricing and trial duration
     */
    public function test_landing_page_dynamically_reflects_updated_saas_pricing()
    {
        SaaSSetting::set('monthly_price_basic', '22500');
        SaaSSetting::set('monthly_price_pro', '48000');
        SaaSSetting::set('monthly_price_enterprise', '98000');
        SaaSSetting::set('trial_days', '21');

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('22,500');
        $response->assertSee('48,000');
        $response->assertSee('98,000');
        $response->assertSee('21-day free trial');
    }

    /**
     * TEST 3: Tenant Admin, Cashier, and Guests cannot access or modify SaaS Settings
     */
    public function test_tenant_admin_and_cashier_cannot_access_or_modify_saas_settings()
    {
        // 1. Tenant Admin attempt
        $responseAdmin = $this->actingAs($this->tenantAdmin)->withSession([
            'user_id' => $this->tenantAdmin->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenant->id,
        ])->post(route('saas.admin.settings'), [
            'monthly_price_basic' => '100',
        ]);
        $responseAdmin->assertRedirect(route('dashboard'));
        $this->assertNotEquals('100', SaaSSetting::get('monthly_price_basic'));

        // 2. Cashier attempt
        $responseCashier = $this->actingAs($this->cashier)->withSession([
            'user_id' => $this->cashier->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
        ])->post(route('saas.admin.settings'), [
            'monthly_price_basic' => '100',
        ]);
        $responseCashier->assertRedirect(route('dashboard'));
        $this->assertNotEquals('100', SaaSSetting::get('monthly_price_basic'));

        // 3. Guest attempt
        $responseGuest = $this->post(route('saas.admin.settings'), [
            'monthly_price_basic' => '100',
        ]);
        $responseGuest->assertRedirect(route('dashboard'));
    }

    /**
     * TEST 4: Tenant self-registration strictly creates Tenant Admin, NEVER Super Admin
     */
    public function test_tenant_self_registration_only_creates_tenant_admin_never_super_admin()
    {
        $uniqueEmail = strtolower('newowner.' . Str::random(6) . '@nigerianretail.ng');

        $response = $this->post(route('saas.register.post'), [
            'business_name' => 'Abuja Mega Store',
            'owner_name' => 'Chief Okon',
            'owner_email' => $uniqueEmail,
            'owner_phone' => '08033221100',
            'password' => 'SecurePass#2026',
            'plan' => 'pro',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');

        // Verify the created user
        $newUser = User::withoutGlobalScopes()->where('email', $uniqueEmail)->first();
        $this->assertNotNull($newUser);

        // Security Invariant Assertions
        $this->assertEquals('admin', $newUser->role);
        $this->assertNotEquals('default-tenant', $newUser->tenant_id);
        $this->assertStringStartsWith('tenant-abuja-mega-store-', $newUser->tenant_id);

        // Verify User role helper methods
        $this->assertFalse($newUser->isSuperAdmin(), 'New registrant must NEVER be a Super Admin');
        $this->assertTrue($newUser->isTenantAdmin(), 'New registrant must be a Tenant Admin');
        $this->assertFalse($newUser->isTenantEmployee(), 'New registrant is the tenant owner, not employee');
        $this->assertFalse($newUser->isSuperAdminEmployee(), 'New registrant is not platform staff');

        // Verify they cannot access /saas/admin
        $adminAccess = $this->actingAs($newUser)->withSession([
            'user_id' => $newUser->id,
            'user_role' => $newUser->role,
            'tenant_id' => $newUser->tenant_id,
        ])->get(route('saas.admin.index'));

        $adminAccess->assertRedirect(route('dashboard'));
    }

    /**
     * TEST 5: Tenant Owner login view has registration link, but other portals do not
     */
    public function test_registration_link_only_on_tenant_owner_login()
    {
        // Tenant Owner Login: MUST have registration prompt
        $tenantLogin = $this->get(route('portal.tenant.login'));
        $tenantLogin->assertStatus(200);
        $tenantLogin->assertSee('Register Your Business (14-Day Free Trial)');

        // Staff & Cashier Login: MUST NOT have registration prompt
        $staffLogin = $this->get(route('portal.tenant_employee.login'));
        $staffLogin->assertStatus(200);
        $staffLogin->assertDontSee('Register Your Business (14-Day Free Trial)');

        // Super-Admin Login: MUST NOT have registration prompt
        $superAdminLogin = $this->get(route('portal.super_admin.login'));
        $superAdminLogin->assertStatus(200);
        $superAdminLogin->assertDontSee('Register Your Business (14-Day Free Trial)');
    }

    /**
     * TEST 6: Super Admin can pause public self-registrations via SaaS settings
     */
    public function test_super_admin_can_pause_and_resume_registrations()
    {
        // Pause registration
        SaaSSetting::set('allow_registration', '0');

        $response = $this->post(route('saas.register.post'), [
            'business_name' => 'Blocked Store',
            'owner_name' => 'Blocked Owner',
            'owner_email' => 'blocked@store.ng',
            'owner_phone' => '08000000000',
            'password' => 'Secret123!',
            'plan' => 'basic',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(User::where('email', 'blocked@store.ng')->first());

        // Resume registration
        SaaSSetting::set('allow_registration', '1');
    }
}
