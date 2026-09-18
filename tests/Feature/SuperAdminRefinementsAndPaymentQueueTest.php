<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminRefinementsAndPaymentQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Tenant $tenantAlpha;
    protected User $tenantAdminAlpha;
    protected User $cashierAlpha;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'master.superadmin@vmarketplatform.com',
        ]);

        // Platform Master Tenant
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

        // Platform Super Admin
        $this->superAdmin = User::firstOrCreate(
            ['email' => 'master.superadmin@vmarketplatform.com'],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => 'default-tenant',
                'name' => 'Platform Super Admin',
                'password' => Hash::make('SuperSecret#123'),
                'role' => 'super_admin',
                'disabled' => false,
            ]
        );

        // Standard Business Tenant
        $this->tenantAlpha = Tenant::create([
            'id' => 'tenant-alpha-' . Str::random(5),
            'name' => 'Alpha Commercial Store',
            'owner_email' => 'owner@alphastore.ng',
            'owner_phone' => '08011112222',
            'plan' => 'basic',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(5),
            'max_branches' => 1,
            'max_users' => 3,
        ]);

        $mainBranch = Warehouse::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Alpha Main HQ',
            'code' => 'ALPHA-HQ',
            'is_active' => true,
        ]);

        $this->tenantAdminAlpha = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Alpha Store Owner',
            'email' => 'owner@alphastore.ng',
            'password' => Hash::make('OldPassword123#'),
            'role' => 'admin',
            'warehouse_id' => $mainBranch->id,
            'disabled' => false,
        ]);

        $this->cashierAlpha = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Frontline Cashier',
            'email' => 'cashier@alphastore.ng',
            'password' => Hash::make('SecretCashier#123'),
            'role' => 'cashier',
            'warehouse_id' => $mainBranch->id,
            'disabled' => false,
        ]);
    }

    public function test_super_admin_can_view_pending_bank_payment_verifications(): void
    {
        // Tenant submits manual payment notice
        $activity = Activity::recordSecurityEvent(
            'MANUAL_SUBSCRIPTION_PAYMENT_SUBMITTED',
            "Submitted bank transfer of ₦35,000.00 from Zenith Bank (Ref: REF-ZENITH-998811) for pro plan.",
            [
                'status' => 'pending',
                'amount' => 35000,
                'bank' => 'Zenith Bank',
                'reference' => 'REF-ZENITH-998811',
                'plan' => 'pro',
                'tenant_id' => $this->tenantAlpha->id,
                'tenant_name' => $this->tenantAlpha->name,
                'owner_email' => $this->tenantAlpha->owner_email,
                'submitted_at' => now()->toIso8601String(),
            ],
            $this->tenantAdminAlpha
        );

        $response = $this->actingAs($this->superAdmin)
            ->withSession([
                'user_id' => $this->superAdmin->id,
                'user_name' => $this->superAdmin->name,
                'user_role' => 'super_admin',
                'tenant_id' => 'default-tenant',
            ])
            ->get(route('saas.admin.index'));

        $response->assertStatus(200);
        $response->assertSee('Pending Bank Transfer Verifications');
        $response->assertSee('Alpha Commercial Store');
        $response->assertSee('35,000.00');
        $response->assertSee('Zenith Bank');
        $response->assertSee('REF-ZENITH-998811');
        $response->assertSee('Approve & Activate');
    }

    public function test_super_admin_can_approve_payment_notice_and_activate_tenant(): void
    {
        $this->assertEquals('trial', $this->tenantAlpha->status);
        $this->assertEquals('basic', $this->tenantAlpha->plan);
        $this->assertEquals(1, $this->tenantAlpha->max_branches);

        $activity = Activity::recordSecurityEvent(
            'MANUAL_SUBSCRIPTION_PAYMENT_SUBMITTED',
            "Submitted bank transfer of ₦35,000.00 from GTBank (Ref: REF-GTB-774411) for pro plan.",
            [
                'status' => 'pending',
                'amount' => 35000,
                'bank' => 'GTBank',
                'reference' => 'REF-GTB-774411',
                'plan' => 'pro',
                'tenant_id' => $this->tenantAlpha->id,
                'tenant_name' => $this->tenantAlpha->name,
                'owner_email' => $this->tenantAlpha->owner_email,
                'submitted_at' => now()->toIso8601String(),
            ],
            $this->tenantAdminAlpha
        );

        $response = $this->actingAs($this->superAdmin)
            ->withSession([
                'user_id' => $this->superAdmin->id,
                'user_name' => $this->superAdmin->name,
                'user_role' => 'super_admin',
                'tenant_id' => 'default-tenant',
            ])
            ->post(route('saas.admin.payments.approve', $activity->id), [
                'months' => 3,
                'notes' => 'Confirmed on Zenith platform corporate statement.',
            ]);

        $response->assertSessionHas('success');

        $this->tenantAlpha->refresh();
        $this->assertEquals('active', $this->tenantAlpha->status);
        $this->assertEquals('pro', $this->tenantAlpha->plan);
        $this->assertEquals(5, $this->tenantAlpha->max_branches);
        $this->assertEquals(15, $this->tenantAlpha->max_users);

        $activity->refresh();
        $this->assertEquals('approved', $activity->metadata['status']);

        $this->assertDatabaseHas('activities', [
            'type' => 'MANUAL_SUBSCRIPTION_PAYMENT_APPROVED',
        ]);
    }

    public function test_super_admin_can_reject_payment_notice(): void
    {
        $activity = Activity::recordSecurityEvent(
            'MANUAL_SUBSCRIPTION_PAYMENT_SUBMITTED',
            "Fake payment submission",
            [
                'status' => 'pending',
                'amount' => 50000,
                'bank' => 'Access Bank',
                'reference' => 'REF-FAKE-0000',
                'plan' => 'pro',
                'tenant_id' => $this->tenantAlpha->id,
                'tenant_name' => $this->tenantAlpha->name,
            ],
            $this->tenantAdminAlpha
        );

        $response = $this->actingAs($this->superAdmin)
            ->withSession([
                'user_id' => $this->superAdmin->id,
                'user_name' => $this->superAdmin->name,
                'user_role' => 'super_admin',
                'tenant_id' => 'default-tenant',
            ])
            ->post(route('saas.admin.payments.reject', $activity->id), [
                'reason' => 'Invalid transaction reference. Not found in statement.',
            ]);

        $response->assertSessionHas('success');

        $activity->refresh();
        $this->assertEquals('rejected', $activity->metadata['status']);
        $this->assertEquals('Invalid transaction reference. Not found in statement.', $activity->metadata['rejection_reason']);
    }

    public function test_super_admin_can_update_tenant_custom_branch_and_user_quotas(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession([
                'user_id' => $this->superAdmin->id,
                'user_name' => $this->superAdmin->name,
                'user_role' => 'super_admin',
                'tenant_id' => 'default-tenant',
            ])
            ->post(route('saas.admin.limits', $this->tenantAlpha->id), [
                'plan' => 'pro',
                'max_branches' => 8,
                'max_users' => 25,
                'status' => 'active',
                'extend_days' => 30,
            ]);

        $response->assertSessionHas('success');

        $this->tenantAlpha->refresh();
        $this->assertEquals('pro', $this->tenantAlpha->plan);
        $this->assertEquals(8, $this->tenantAlpha->max_branches);
        $this->assertEquals(25, $this->tenantAlpha->max_users);
        $this->assertEquals('active', $this->tenantAlpha->status);
    }

    public function test_super_admin_can_reset_tenant_admin_password(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession([
                'user_id' => $this->superAdmin->id,
                'user_name' => $this->superAdmin->name,
                'user_role' => 'super_admin',
                'tenant_id' => 'default-tenant',
            ])
            ->post(route('saas.admin.tenant.reset_password', $this->tenantAlpha->id), [
                'custom_password' => 'NewStorePass#2026',
            ]);

        $response->assertSessionHas('success');

        $this->tenantAdminAlpha->refresh();
        $this->assertTrue(Hash::check('NewStorePass#2026', $this->tenantAdminAlpha->password));

        $this->assertDatabaseHas('activities', [
            'type' => 'TENANT_ADMIN_PASSWORD_RESET_BY_SUPER_ADMIN',
        ]);
    }

    public function test_non_platform_users_are_forbidden_from_super_admin_actions(): void
    {
        // 1. Web browser GET requests are redirected with access restricted message
        $response = $this->actingAs($this->cashierAlpha)
            ->withSession([
                'user_id' => $this->cashierAlpha->id,
                'user_name' => $this->cashierAlpha->name,
                'user_role' => 'cashier',
                'tenant_id' => $this->tenantAlpha->id,
            ])
            ->get(route('saas.admin.index'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        // 2. Direct JSON/API requests are strictly forbidden with 403
        $responseJson = $this->actingAs($this->cashierAlpha)
            ->withSession([
                'user_id' => $this->cashierAlpha->id,
                'user_name' => $this->cashierAlpha->name,
                'user_role' => 'cashier',
                'tenant_id' => $this->tenantAlpha->id,
            ])
            ->getJson(route('saas.admin.index'));

        $responseJson->assertStatus(403);
    }
}
