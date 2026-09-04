<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Http\Controllers\BackupController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupIsolationAndCsrfHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $superAdmin;
    protected User $adminA;
    protected User $adminB;
    protected Warehouse $warehouseA;
    protected Product $productA;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@hysam.com',
        ]);

        Storage::fake('local');

        $this->tenantA = Tenant::create([
            'id' => 'tenant-backup-alpha',
            'name' => 'Alpha Supermarket Ltd',
            'owner_email' => 'alpha@vmarketpos.com',
            'plan' => 'enterprise',
            'status' => 'active',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        $this->tenantB = Tenant::create([
            'id' => 'tenant-backup-beta',
            'name' => 'Beta Logistics Ltd',
            'owner_email' => 'beta@vmarketpos.com',
            'plan' => 'pro',
            'status' => 'active',
            'max_branches' => 3,
            'max_users' => 5,
        ]);

        $this->superAdmin = User::create([
            'id' => 'usr-platform-admin',
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Operator',
            'email' => 'superadmin@hysam.com',
            'password' => Hash::make('secret123'),
            'role' => 'super_admin',
            'warehouse_id' => null,
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha HQ Branch',
            'code' => 'ALPHA-HQ',
            'address' => '1 Alpha Way',
            'phone' => '08011223344',
            'is_active' => true,
        ]);

        $this->adminA = User::create([
            'id' => 'usr-admin-alpha',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Director Alpha',
            'email' => 'dir@alpha.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        $this->adminB = User::create([
            'id' => 'usr-admin-beta',
            'tenant_id' => $this->tenantB->id,
            'name' => 'Director Beta',
            'email' => 'dir@beta.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'warehouse_id' => null,
            'disabled' => false,
        ]);

        $this->productA = Product::create([
            'id' => 'prod-alpha-flour',
            'tenant_id' => $this->tenantA->id,
            'name' => 'Golden Penny Flour 50kg',
            'code' => 'FLOUR-50KG',
            'unitPrice' => 35000.00,
            'costPrice' => 31000.00,
            'category' => 'Bakery',
            'currentStock' => 50,
            'minStockLevel' => 5,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::updateOrCreate(
            ['product_id' => $this->productA->id, 'warehouse_id' => $this->warehouseA->id],
            ['tenant_id' => $this->tenantA->id, 'physical_stock' => 50, 'allocated_stock' => 0]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // 1. PLATFORM ADMIN SEES ONLY PLATFORM BACKUPS (ZERO TENANT ACCESS)
    // ─────────────────────────────────────────────────────────────

    public function test_platform_admin_sees_only_platform_backups_and_zero_tenant_backups()
    {
        // 1. Create a platform backup
        $platformBackup = Backup::create([
            'id' => 'BK-PLATFORM-01',
            'tenant_id' => null,
            'filename' => 'platform_backup_01.json',
            'size' => 2048,
            'created_by' => 'Platform Admin',
        ]);

        // 2. Create tenant business backups
        $tenantBackupA = Backup::create([
            'id' => 'BK-TENANT-ALPHA',
            'tenant_id' => $this->tenantA->id,
            'filename' => 'backup_tenant_alpha.json',
            'size' => 4096,
            'created_by' => 'Director Alpha',
        ]);

        $tenantBackupB = Backup::create([
            'id' => 'BK-TENANT-BETA',
            'tenant_id' => $this->tenantB->id,
            'filename' => 'backup_tenant_beta.json',
            'size' => 4096,
            'created_by' => 'Director Beta',
        ]);

        // Platform Admin queries /api/backups
        $response = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_role' => 'super_admin',
            'tenant_id' => 'default-tenant',
            'portal' => 'platform-admin',
        ])->getJson('/api/backups');

        $response->assertStatus(200);
        $backups = $response->json();
        $ids = collect($backups)->pluck('id')->all();

        // Platform Admin MUST see platform backup
        $this->assertContains('BK-PLATFORM-01', $ids);

        // Platform Admin MUST NOT see tenant backups!
        $this->assertNotContains('BK-TENANT-ALPHA', $ids, "Platform admin must have zero visibility of tenant business backups.");
        $this->assertNotContains('BK-TENANT-BETA', $ids, "Platform admin must have zero visibility of tenant business backups.");
    }

    public function test_platform_admin_cannot_download_or_restore_tenant_backup()
    {
        $tenantBackup = Backup::create([
            'id' => 'BK-TENANT-ALPHA-SECRET',
            'tenant_id' => $this->tenantA->id,
            'filename' => 'backup_alpha_secret.json',
            'size' => 1024,
            'created_by' => 'Director Alpha',
        ]);

        Storage::disk('local')->put('backups/backup_alpha_secret.json', json_encode([
            'tenant_id' => $this->tenantA->id,
            'data' => ['products' => [['id' => 'p1', 'name' => 'Secret Item']]]
        ]));

        // Platform Admin attempts download of tenant backup -> 403 Forbidden
        $dlResponse = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_role' => 'super_admin',
            'tenant_id' => 'default-tenant',
        ])->getJson("/api/backups/{$tenantBackup->id}/download");

        $dlResponse->assertStatus(403);
        $dlResponse->assertJson(['error' => 'Forbidden. Platform administrators cannot access or download tenant business backups.']);

        // Platform Admin attempts restore of tenant backup -> 403 Forbidden
        $rstResponse = $this->actingAs($this->superAdmin)->withSession([
            'user_id' => $this->superAdmin->id,
            'user_role' => 'super_admin',
            'tenant_id' => 'default-tenant',
        ])->postJson("/api/backups/{$tenantBackup->id}/restore", [
            'confirmation' => 'CONFIRM_RESTORE',
        ]);

        $rstResponse->assertStatus(403);
        $rstResponse->assertJson(['error' => 'Forbidden. Platform administrators cannot restore tenant business backups.']);
    }

    public function test_platform_backup_generation_contains_zero_tenant_business_data()
    {
        $backup = BackupController::generatePlatformBackup('Platform Operator', $this->superAdmin);

        $this->assertNull($backup->tenant_id);
        $this->assertTrue($backup->isPlatformBackup());
        $this->assertFalse($backup->isTenantBackup());

        $content = json_decode(Storage::disk('local')->get('backups/' . $backup->filename), true);

        $this->assertEquals('PLATFORM', $content['type']);
        $this->assertNull($content['tenant_id']);
        $this->assertArrayHasKey('tenants', $content['data']);
        $this->assertArrayHasKey('platform_settings', $content['data']);

        // Assert ZERO tenant business tables
        $this->assertArrayNotHasKey('products', $content['data']);
        $this->assertArrayNotHasKey('sales', $content['data']);
        $this->assertArrayNotHasKey('sale_items', $content['data']);
        $this->assertArrayNotHasKey('customers', $content['data']);
        $this->assertArrayNotHasKey('stock_levels', $content['data']);
        $this->assertArrayNotHasKey('payments', $content['data']);
        $this->assertArrayNotHasKey('inventory_logs', $content['data']);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. TENANT ADMIN STRICT ISOLATION & RESTORE PROTECTION
    // ─────────────────────────────────────────────────────────────

    public function test_tenant_admin_sees_only_own_backups_and_cannot_access_platform_or_other_tenant_backups()
    {
        $platformBackup = Backup::create([
            'id' => 'BK-PLATFORM-INFRA',
            'tenant_id' => null,
            'filename' => 'platform_infra.json',
            'size' => 1024,
            'created_by' => 'Platform Admin',
        ]);

        $backupA = Backup::create([
            'id' => 'BK-ALPHA-OWN',
            'tenant_id' => $this->tenantA->id,
            'filename' => 'backup_alpha_own.json',
            'size' => 1024,
            'created_by' => 'Director Alpha',
        ]);

        $backupB = Backup::create([
            'id' => 'BK-BETA-OWN',
            'tenant_id' => $this->tenantB->id,
            'filename' => 'backup_beta_own.json',
            'size' => 1024,
            'created_by' => 'Director Beta',
        ]);

        // Tenant A accesses /settings/backups
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
            'portal' => 'tenant-admin',
        ])->getJson('/settings/backups');

        $response->assertStatus(200);
        $ids = collect($response->json())->pluck('id')->all();

        $this->assertContains('BK-ALPHA-OWN', $ids);
        $this->assertNotContains('BK-PLATFORM-INFRA', $ids, "Tenant admin must not see platform backups.");
        $this->assertNotContains('BK-BETA-OWN', $ids, "Tenant admin must not see Tenant Beta's backups.");

        // Tenant A attempting to download Tenant B backup -> 403
        $dlResponse = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->getJson("/settings/backups/{$backupB->id}/download");

        $dlResponse->assertStatus(403);
    }

    public function test_tenant_restore_cannot_cross_tenant_restore()
    {
        $backupB = Backup::create([
            'id' => 'BK-BETA-PAYLOAD',
            'tenant_id' => $this->tenantB->id,
            'filename' => 'backup_beta_payload.json',
            'size' => 1024,
            'created_by' => 'Director Beta',
        ]);

        Storage::disk('local')->put('backups/backup_beta_payload.json', json_encode([
            'type' => 'TENANT',
            'tenant_id' => $this->tenantB->id,
            'data' => ['products' => []]
        ]));

        // Tenant A tries to restore Tenant B's backup into Tenant A
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->postJson("/settings/backups/{$backupB->id}/restore", [
            'confirmation' => 'CONFIRM_RESTORE',
        ]);

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. CSRF ENFORCEMENT ON STATE-MUTATING SESSION ENDPOINTS
    // ─────────────────────────────────────────────────────────────

    public function test_csrf_token_required_on_api_state_mutating_requests()
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            protected function runningUnitTests()
            {
                return false; // Test production HTTP behavior
            }
        };

        $request = \Illuminate\Http\Request::create('/api/backups', 'POST');
        $session = $this->app['session']->driver();
        $session->start();
        $session->put('_token', 'valid-server-token-12345');
        $request->setLaravelSession($session);

        // Submitting without or with invalid CSRF token throws TokenMismatchException
        $request->merge(['_token' => 'invalid-attacker-token']);

        $this->expectException(\Illuminate\Session\TokenMismatchException::class);
        $middleware->handle($request, function () {
            return response('ok');
        });
    }

    public function test_api_login_is_exempt_from_csrf()
    {
        $middleware = new \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken(
            $this->app,
            $this->app['encrypter']
        );

        $request = \Illuminate\Http\Request::create('/api/login', 'POST');
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);

        $executed = false;
        $response = $middleware->handle($request, function () use (&$executed) {
            $executed = true;
            return response('ok');
        });

        $this->assertTrue($executed, 'api/login must be exempt from CSRF token verification.');
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────
    // 4. CHECKOUT DISREGARDS CLIENT-SUPPLIED paidAmount
    // ─────────────────────────────────────────────────────────────

    public function test_checkout_disregards_client_paid_amount_in_favor_of_cash_and_pos()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Hajiya Fatima',
            'phone' => '08099887766',
            'total_debt' => 0.0,
        ]);

        // A. Attempting to pass paidAmount > tender must be strictly rejected with 422
        $responseMismatched = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->postJson('/pos/checkout', [
            'items' => [
                ['productId' => $this->productA->id, 'quantity' => 1]
            ],
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'customerId' => $customer->id,
            'customerName' => $customer->name,
            'customerPhone' => $customer->phone,
            'paidAmount' => 35000.00, // Legacy/spoof attempt with 0 cash/pos
            'cashAmount' => 0.00,
            'posAmount' => 0.00,
            'idempotency_key' => 'idemp-tender-reject-mismatch',
        ]);

        $responseMismatched->assertStatus(422);
        $responseMismatched->assertJson(['success' => false]);

        // B. Client supplies valid tender (Cash ₦20,000) and attempts to tamper paidAmount = 10,000
        // Server strictly derives paidAmount = 20,000 from tenders!
        $response = $this->actingAs($this->adminA)->withSession([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ])->postJson('/pos/checkout', [
            'items' => [
                ['productId' => $this->productA->id, 'quantity' => 1]
            ],
            'warehouse_id' => $this->warehouseA->id,
            'is_supplied' => 'yes',
            'customerId' => $customer->id,
            'customerName' => $customer->name,
            'customerPhone' => $customer->phone,
            'paidAmount' => 10000.00, // Client tampering attempt
            'cashAmount' => 20000.00,
            'posAmount' => 0.00,
            'idempotency_key' => 'idemp-tender-disregard-1',
        ]);

        $response->assertStatus(200);
        $sale = Sale::where('customerId', $customer->id)->first();
        $this->assertNotNull($sale);

        // Assert paidAmount is derived strictly from cash and pos: ₦20,000
        $this->assertEquals(20000.00, (float) $sale->paidAmount);
        $this->assertEquals(20000.00, (float) $sale->cashAmount);
        $this->assertEquals(0.00, (float) $sale->posAmount);
        $this->assertEquals('PARTIAL', $sale->status);

        // Customer debt is remaining ₦15,000 (35,000 - 20,000)
        $customer->refresh();
        $this->assertEquals(15000.00, (float) $customer->total_debt);
    }
}
