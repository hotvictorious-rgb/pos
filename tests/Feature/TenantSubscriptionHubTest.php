<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantSubscriptionHubTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $branchA1;
    protected User $adminA;
    protected User $cashierA1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true]);

        // Tenant A
        $this->tenantA = Tenant::create([
            'id' => 'tenant-alpha-' . Str::random(5),
            'name' => 'Alpha Supermarket',
            'owner_email' => 'owner@alpha.com',
            'status' => 'trial',
            'plan' => 'basic',
            'max_branches' => 1,
            'max_users' => 3,
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->branchA1 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Main Store',
            'code' => 'ALPHA-01',
            'is_active' => true,
        ]);

        $this->adminA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Managing Director',
            'email' => 'owner@alpha.com',
            'password' => Hash::make('Secret123#'),
            'role' => 'admin',
            'disabled' => false,
        ]);

        $this->cashierA1 = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cashier Grace',
            'email' => 'grace@alpha.com',
            'password' => Hash::make('Secret123#'),
            'role' => 'cashier',
            'warehouse_id' => $this->branchA1->id,
            'disabled' => false,
        ]);

        // Tenant B
        $this->tenantB = Tenant::create([
            'id' => 'tenant-beta-' . Str::random(5),
            'name' => 'Beta Mega Pharmacy',
            'owner_email' => 'owner@beta.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 999,
            'max_users' => 999,
            'trial_ends_at' => now()->addDays(365),
        ]);
    }

    public function test_business_owner_can_view_subscription_hub_with_quotas_and_plans(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
            ])
            ->get(route('subscription.index'));

        $response->assertStatus(200);
        $response->assertSee('Subscription & Billing Hub');
        $response->assertSee('Alpha Supermarket');
        $response->assertSee('Starter Plan');
        $response->assertSee('Professional Growth');
        $response->assertSee('Enterprise Multi-Branch');
        $response->assertSee('14-Day Free Trial');
        $response->assertSee('RETAIL BRANCHES');
        $response->assertSee('STAFF & CASHIERS');
        $response->assertSee('Pay Now via Paystack');
        $response->assertSee('Official Corporate Bank Transfer');
    }

    public function test_sidebar_displays_subscription_link_for_admin(): void
    {
        $responseAdmin = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'user_name' => $this->adminA->name,
                'user_role' => 'admin',
                'tenant_id' => $this->tenantA->id,
            ])
            ->get(route('dashboard'));

        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Subscription & Plan');
    }

    public function test_sidebar_hides_subscription_link_for_cashier(): void
    {
        $responseCashier = $this->actingAs($this->cashierA1)
            ->withSession([
                'user_id' => $this->cashierA1->id,
                'user_name' => $this->cashierA1->name,
                'user_role' => 'cashier',
                'tenant_id' => $this->tenantA->id,
                'warehouse_id' => $this->branchA1->id,
            ])
            ->get(route('transactions.index'));

        $responseCashier->assertStatus(200);
        $responseCashier->assertDontSee('Subscription & Plan');
    }

    public function test_branch_cashier_cannot_access_subscription_page(): void
    {
        // 1. Browser GET requests redirected with security warning
        $response = $this->actingAs($this->cashierA1)
            ->withSession([
                'user_id' => $this->cashierA1->id,
                'user_name' => $this->cashierA1->name,
                'user_role' => 'cashier',
                'tenant_id' => $this->tenantA->id,
                'warehouse_id' => $this->branchA1->id,
            ])
            ->get(route('subscription.index'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('warning');

        // 2. Direct JSON/API requests blocked with 403 Forbidden
        $responseJson = $this->actingAs($this->cashierA1)
            ->withSession([
                'user_id' => $this->cashierA1->id,
                'user_name' => $this->cashierA1->name,
                'user_role' => 'cashier',
                'tenant_id' => $this->tenantA->id,
                'warehouse_id' => $this->branchA1->id,
            ])
            ->getJson(route('subscription.index'));

        $responseJson->assertStatus(403);
    }

    public function test_business_owner_can_upgrade_plan(): void
    {
        $this->assertEquals('basic', $this->tenantA->plan);
        $this->assertEquals(1, $this->tenantA->max_branches);

        $response = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
            ])
            ->post(route('subscription.change_plan'), [
                'plan' => 'pro',
            ]);

        $response->assertSessionHas('success');
        $this->tenantA->refresh();

        $this->assertEquals('pro', $this->tenantA->plan);
        $this->assertEquals(5, $this->tenantA->max_branches);
        $this->assertEquals(15, $this->tenantA->max_users);
    }

    public function test_business_owner_can_submit_manual_bank_payment_notice(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
            ])
            ->post(route('subscription.manual_payment'), [
                'bank_paid_from' => 'Access Bank Nigeria',
                'reference' => 'REF-99283741',
                'amount_paid' => 35000,
                'plan' => 'pro',
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('activities', [
            'userId' => $this->adminA->id,
            'type' => 'MANUAL_SUBSCRIPTION_PAYMENT_SUBMITTED',
        ]);
    }

    public function test_subscription_hub_strictly_enforces_tenant_isolation(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
            ])
            ->get(route('subscription.index'));

        $response->assertStatus(200);
        $response->assertSee('Alpha Supermarket');
        $response->assertDontSee('Beta Mega Pharmacy');
    }
}
