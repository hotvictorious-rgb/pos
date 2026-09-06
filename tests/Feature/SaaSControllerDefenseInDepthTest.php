<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Services\StockService;
use App\Http\Controllers\SaaS\SaaSController;
use App\Http\Middleware\RequirePlatformUser;
use App\Http\Middleware\RequireSuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SaaSControllerDefenseInDepthTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $platformTenant;
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $platformAdmin;
    protected User $platformEmployeeWithoutCaps;
    protected User $platformEmployeeWithCaps;
    protected User $tenantAdminA;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'platform-admin@system.local',
        ]);

        // Platform default tenant
        $this->platformTenant = Tenant::withoutGlobalScopes()->firstOrCreate([
            'id' => 'default-tenant',
        ], [
            'name' => 'Victorious Platform System',
            'owner_email' => 'platform-admin@system.local',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        // Platform Super Admin
        $this->platformAdmin = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'platform-admin@system.local',
            'password' => Hash::make('SecretRootPass123!'),
            'role' => 'admin',
            'permissions' => ['all' => true],
        ]);

        // Platform Employee without capabilities
        $this->platformEmployeeWithoutCaps = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Staff No Caps',
            'email' => 'nocaps@system.local',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'platform_employee',
            'permissions' => [],
        ]);

        // Platform Employee with platform.tenants capability
        $this->platformEmployeeWithCaps = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Staff Tenant Manager',
            'email' => 'tenantmgr@system.local',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'platform_employee',
            'permissions' => ['platform.tenants'],
        ]);

        // Tenant A
        $this->tenantA = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-alpha',
            'name' => 'Tenant Alpha Ltd',
            'owner_email' => 'owner@alpha.local',
            'status' => 'active',
            'plan' => 'pro',
            'max_branches' => 5,
        ]);

        // Tenant Admin A
        $this->tenantAdminA = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-alpha',
            'name' => 'Alpha Admin',
            'email' => 'admin@alpha.local',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'admin',
            'permissions' => ['all' => true],
        ]);

        // Tenant B
        $this->tenantB = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-bravo',
            'name' => 'Tenant Bravo Ltd',
            'owner_email' => 'owner@bravo.local',
            'status' => 'active',
            'plan' => 'basic',
            'max_branches' => 2,
        ]);

        $this->warehouseA = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => 'tenant-alpha',
            'name' => 'Alpha Warehouse',
            'code' => 'WH-A',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => 'tenant-bravo',
            'name' => 'Bravo Warehouse',
            'code' => 'WH-B',
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Direct invocation of SaaSController mutation methods without platform credentials fails with 403.
     */
    public function test_controller_defense_lock_rejects_unauthenticated_or_non_platform_invocations(): void
    {
        $controller = app(SaaSController::class);

        // Without authentication
        try {
            $controller->deleteTenant($this->tenantB->id);
            $this->fail('Expected 403 HttpException');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Platform credentials required', $e->getMessage());
        }

        // Authenticated as tenant user
        $this->actingAs($this->tenantAdminA);
        try {
            $controller->deleteTenant($this->tenantB->id);
            $this->fail('Expected 403 HttpException for tenant user');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Platform credentials required', $e->getMessage());
        }
    }

    /**
     * Test 2: Platform employee lacking required capability is rejected at controller level.
     */
    public function test_controller_defense_lock_rejects_platform_employee_lacking_specific_capability(): void
    {
        $controller = app(SaaSController::class);
        $this->actingAs($this->platformEmployeeWithoutCaps);

        try {
            $controller->deleteTenant($this->tenantB->id);
            $this->fail('Expected 403 HttpException for platform employee lacking platform.tenants');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString("Missing required platform capability 'platform.tenants'", $e->getMessage());
        }
    }

    /**
     * Test 3: Platform employee with platform.tenants can invoke deleteTenant at controller level.
     */
    public function test_controller_defense_lock_permits_authorized_platform_employee(): void
    {
        $controller = app(SaaSController::class);
        $this->actingAs($this->platformEmployeeWithCaps);

        $response = $controller->deleteTenant($this->tenantB->id);
        $this->assertTrue($response->isRedirection());
        $this->assertNull(Tenant::withoutGlobalScopes()->find($this->tenantB->id));
    }

    /**
     * Test 4: deleteTenant strictly refuses to delete default-tenant.
     */
    public function test_delete_tenant_protects_default_tenant(): void
    {
        $controller = app(SaaSController::class);
        $this->actingAs($this->platformAdmin);

        $response = $controller->deleteTenant('default-tenant');
        $this->assertTrue($response->isRedirection());
        $this->assertNotNull(Tenant::withoutGlobalScopes()->find('default-tenant'));
    }

    /**
     * Test 5: RequirePlatformUser middleware strictly gates platform admin area.
     */
    public function test_require_platform_user_middleware_enforces_access_control(): void
    {
        $middleware = new RequirePlatformUser();

        // 1. Unauthenticated request gets redirected
        $req1 = Request::create('/saas/admin', 'GET');
        $resp1 = $middleware->handle($req1, fn() => response('OK'));
        $this->assertTrue($resp1->isRedirection());

        // 2. Tenant user gets redirected with restricted message
        $this->actingAs($this->tenantAdminA);
        $req2 = Request::create('/saas/admin', 'GET');
        $resp2 = $middleware->handle($req2, fn() => response('OK'));
        $this->assertTrue($resp2->isRedirection());

        // 3. Platform employee without capabilities gets 403
        $this->actingAs($this->platformEmployeeWithoutCaps);
        $req3 = Request::create('/saas/admin', 'GET');
        try {
            $middleware->handle($req3, fn() => response('OK'));
            $this->fail('Expected 403 for employee with no capabilities');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        // 4. Platform employee with capabilities is passed through
        $this->actingAs($this->platformEmployeeWithCaps);
        $req4 = Request::create('/saas/admin', 'GET');
        $resp4 = $middleware->handle($req4, fn() => response('OK'));
        $this->assertEquals('OK', $resp4->getContent());

        // 5. Platform Super Admin is passed through
        $this->actingAs($this->platformAdmin);
        $req5 = Request::create('/saas/admin', 'GET');
        $resp5 = $middleware->handle($req5, fn() => response('OK'));
        $this->assertEquals('OK', $resp5->getContent());
    }

    /**
     * Test 6: RequireSuperAdmin maintains backwards compatibility as subclass.
     */
    public function test_require_super_admin_middleware_is_subclass_of_require_platform_user(): void
    {
        $middleware = new RequireSuperAdmin();
        $this->assertInstanceOf(RequirePlatformUser::class, $middleware);

        $this->actingAs($this->platformAdmin);
        $req = Request::create('/saas/admin', 'GET');
        $resp = $middleware->handle($req, fn() => response('OK'));
        $this->assertEquals('OK', $resp->getContent());
    }

    /**
     * Test 7: Financial IDOR defense: Cross-tenant customer payment is strictly rejected.
     */
    public function test_financial_idor_customer_payment_across_tenants_rejected(): void
    {
        $customerB = Customer::withoutGlobalScopes()->create([
            'tenant_id' => 'tenant-bravo',
            'name' => 'Bravo Debtor',
            'email' => 'debtor@bravo.local',
            'phone' => '08011223344',
            'total_debt' => 50000.0,
        ]);

        $stockService = app(StockService::class);
        $this->actingAs($this->tenantAdminA);

        // Tenant A attempting to pay Tenant B's customer:
        // Customer has BelongsToTenant (TenantScope), so Customer::where('id', $customerId)->lockForUpdate()->firstOrFail()
        // will not find the customer in Tenant A's scope, resulting in ModelNotFoundException.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $stockService->recordCustomerPayment(
            $customerB->id,
            10000.0,
            'CASH',
            'REF-123',
            $this->tenantAdminA->id,
            $this->tenantAdminA->name,
            'IDOR Attempt',
            $this->warehouseA->id
        );
    }

    /**
     * Test 8: Financial IDOR defense: Cross-tenant sale return is strictly rejected.
     */
    public function test_financial_idor_sale_return_across_tenants_rejected(): void
    {
        $productB = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-bravo',
            'name' => 'Bravo Gadget',
            'code' => 'BG-01',
            'category' => 'Electronics',
            'unitPrice' => 15000.0,
            'currentStock' => 10,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-bravo',
            'product_id' => $productB->id,
            'warehouse_id' => $this->warehouseB->id,
            'physical_stock' => 10,
            'allocated_stock' => 0,
        ]);

        $saleB = Sale::withoutGlobalScopes()->create([
            'id' => 'SALE-BRAVO-100',
            'tenant_id' => 'tenant-bravo',
            'warehouse_id' => $this->warehouseB->id,
            'customerName' => 'Walk-in',
            'totalAmount' => 15000.0,
            'paidAmount' => 15000.0,
            'cashAmount' => 15000.0,
            'posAmount' => 0.0,
            'transferAmount' => 0.0,
            'tenderedAmount' => 15000.0,
            'changeAmount' => 0.0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => (string) Str::uuid(),
            'userName' => 'Bravo Staff',
            'createdAt' => now()->toIso8601String(),
        ]);

        SaleItem::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-bravo',
            'saleId' => $saleB->id,
            'productId' => $productB->id,
            'productName' => $productB->name,
            'quantity' => 1,
            'unitPrice' => 15000.0,
            'totalPrice' => 15000.0,
        ]);

        $stockService = app(StockService::class);
        $this->actingAs($this->tenantAdminA);

        // Tenant A attempting to return Tenant B's sale:
        // Because Sale has TenantScope, Sale::with('items')->where('id', $saleId)->lockForUpdate()->firstOrFail()
        // will not find the sale in Tenant A's scope, resulting in ModelNotFoundException.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $stockService->recordSaleReturn(
            $saleB->id,
            [['productId' => $productB->id, 'quantity' => 1]],
            $this->warehouseA->id,
            'CASH_REFUND',
            'Cross-tenant return attempt',
            $this->tenantAdminA->id,
            $this->tenantAdminA->name
        );
    }
}
