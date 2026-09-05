<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\SalesReturn;
use App\Models\CustomRole;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductionHardeningPass5Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Warehouse $warehouseA;
    private Warehouse $warehouseB;
    private User $tenantAdmin;
    private User $branchWorkerA;
    private User $branchWorkerB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-hardening-pass5',
            'name' => 'Pass 5 Hardened Retailers Ltd',
            'owner_email' => 'admin@pass5.com',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 20,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Apapa Central Depot',
            'code' => 'APAPA-01',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ikeja Branch Store',
            'code' => 'IKEJA-02',
            'is_active' => true,
        ]);

        // Tenant Admin (HQ)
        $this->tenantAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Alhaji Maina (Tenant Admin)',
            'email' => 'admin@pass5.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'warehouse_id' => null, // HQ
            'disabled' => false,
            'permissions' => [
                'users.manage' => true,
                'settings.manage' => true,
                'pos' => true,
                'reports' => true,
            ],
        ]);

        // Branch-scoped worker with permissions override at Branch A
        $this->branchWorkerA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Bashir Apapa (Cashier Lead)',
            'email' => 'bashir@pass5.com',
            'password' => Hash::make('secret123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
            'permissions' => [
                'users.manage' => true,      // Explicit capability override
                'settings.manage' => true,   // Explicit capability override
                'pos' => true,
            ],
        ]);

        // Worker at Branch B
        $this->branchWorkerB = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Emeka Ikeja (Sales Staff)',
            'email' => 'emeka@pass5.com',
            'password' => Hash::make('secret123'),
            'role' => 'sales_officer',
            'warehouse_id' => $this->warehouseB->id,
            'disabled' => false,
            'permissions' => ['pos' => true],
        ]);
    }

    /**
     * TEST 1: Tenant Admin accessing /api/data receives ZERO platform CustomRole data
     */
    public function test_api_data_does_not_contain_platform_custom_roles()
    {
        CustomRole::create([
            'id' => (string) Str::uuid(),
            'label' => 'Platform Systems Auditor',
            'description' => 'Platform root role',
            'modulePermissions' => json_encode(['platform.audit' => true]),
        ]);

        $response = $this->actingAs($this->tenantAdmin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->tenantAdmin->id,
            'user_role' => 'admin',
            'portal' => 'tenant',
        ])->getJson('/api/data');

        $response->assertStatus(200);
        $response->assertJsonMissing(['custom_roles']);
        $this->assertArrayNotHasKey('custom_roles', $response->json());
    }

    /**
     * TEST 2: Branch-scoped worker with users.manage override only sees workers from own branch
     */
    public function test_branch_scoped_user_with_users_manage_cannot_see_other_branch_workers()
    {
        $response = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->get(route('users.index'));

        $response->assertStatus(200);
        $users = $response->viewData('users');
        $userIds = $users->pluck('id')->all();

        $this->assertContains($this->branchWorkerA->id, $userIds);
        $this->assertNotContains($this->branchWorkerB->id, $userIds, "Branch worker must not see workers from other branches in /users.");
        $this->assertNotContains($this->tenantAdmin->id, $userIds, "Branch worker must not see HQ admin in /users.");
    }

    /**
     * TEST 3: Branch-scoped worker with users.manage override cannot create worker for other branch
     */
    public function test_branch_scoped_user_with_users_manage_cannot_create_worker_for_other_branch()
    {
        $response = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->post(route('users.store'), [
            'name' => 'Injected Other Branch Staff',
            'email' => 'injected@pass5.com',
            'password' => 'password123',
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseB->id, // Target other branch
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'injected@pass5.com']);
    }

    /**
     * TEST 4: Branch-scoped worker with users.manage override cannot create admin account
     */
    public function test_branch_scoped_user_cannot_create_admin_or_super_admin()
    {
        $response = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->post(route('users.store'), [
            'name' => 'Escalated Admin Account',
            'email' => 'escalated@pass5.com',
            'password' => 'password123',
            'role' => 'admin', // Escalation attempt
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'escalated@pass5.com']);
    }

    /**
     * TEST 5: Branch-scoped worker cannot update or toggle worker belonging to another branch
     */
    public function test_branch_scoped_user_cannot_update_worker_belonging_to_other_branch()
    {
        // Update attempt
        $responseUpdate = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->post(route('users.update', $this->branchWorkerB->id), [
            'name' => 'Hacked Emeka',
            'email' => $this->branchWorkerB->email,
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseB->id,
        ]);

        $responseUpdate->assertStatus(403);

        // Toggle status attempt
        $responseToggle = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->post(route('users.toggle', $this->branchWorkerB->id));

        $responseToggle->assertStatus(403);
    }

    /**
     * TEST 6: Branch-scoped worker cannot reassign worker to another branch or promote to admin
     */
    public function test_branch_scoped_user_cannot_reassign_branch_or_promote_to_admin()
    {
        $peerWorker = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Peer Cashier at Branch A',
            'email' => 'peer@pass5.com',
            'password' => Hash::make('secret123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'disabled' => false,
        ]);

        // Attempt branch reassignment
        $responseReassign = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->post(route('users.update', $peerWorker->id), [
            'name' => $peerWorker->name,
            'email' => $peerWorker->email,
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseB->id, // Attempt reassignment
        ]);

        $responseReassign->assertStatus(403);

        // Attempt promotion to admin
        $responsePromote = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->post(route('users.update', $peerWorker->id), [
            'name' => $peerWorker->name,
            'email' => $peerWorker->email,
            'role' => 'admin', // Attempt promotion
            'warehouse_id' => $this->warehouseA->id,
        ]);

        $responsePromote->assertStatus(403);
    }

    /**
     * TEST 7: Branch-scoped worker with settings.manage override cannot alter business settings or warehouses
     */
    public function test_branch_scoped_user_with_settings_manage_cannot_modify_settings_or_warehouses()
    {
        // 1. Attempt business settings update
        $responseSettings = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->post(route('settings.update'), [
            'businessName' => 'Compromised Name Ltd',
            'currency' => 'USD',
            'lowStockThreshold' => 10,
        ]);

        $responseSettings->assertStatus(403);

        // 2. Attempt warehouse creation
        $responseWhCreate = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->post(route('settings.warehouse.store'), [
            'name' => 'Unauthorized New Branch',
            'code' => 'ROGUE-01',
        ]);

        $responseWhCreate->assertStatus(403);

        // 3. Attempt warehouse toggle
        $responseWhToggle = $this->actingAs($this->branchWorkerA)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->branchWorkerA->id,
            'user_role' => 'cashier',
            'portal' => 'tenant-employee',
        ])->post(route('settings.warehouse.toggle', $this->warehouseB->id));

        $responseWhToggle->assertStatus(403);
    }

    /**
     * TEST 8: Transaction payment_status filter derives status authoritatively from financial events
     */
    public function test_transactions_payment_status_filtering_derives_accurately_from_financial_events()
    {
        $this->actingAs($this->tenantAdmin);
        session(['tenant_id' => $this->tenant->id]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Industrial Hydraulic Press',
            'code' => 'PRESS-01',
            'category' => 'Machinery',
            'costPrice' => 80000.0,
            'unitPrice' => 100000.0,
            'currentStock' => 10,
        ]);

        // Create Sale A: total 100,000, initial paid 40,000
        $saleA = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->tenantAdmin->id,
            'userName' => $this->tenantAdmin->name,
            'customerName' => 'Dangote Industries',
            'totalAmount' => 100000.0,
            'paidAmount' => 40000.0, // Deliberately set cached paidAmount lower
            'cashAmount' => 40000.0,
            'posAmount' => 0.0,
            'tenderedAmount' => 40000.0,
            'changeAmount' => 0.0,
            'transferAmount' => 0.0,
            'status' => 'PARTIAL',  // Deliberately set cached status PARTIAL
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Add payment of 40,000
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleA->id,
            'amount' => 40000.0,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->tenantAdmin->name,
        ]);

        // Later, customer pays remaining 60,000! Total payments = 100,000.
        // SaleA cached columns (paidAmount/status) are NOT yet refreshed to test event derivation.
        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'saleId' => $saleA->id,
            'amount' => 60000.0,
            'method' => 'POS',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => $this->tenantAdmin->name,
        ]);

        // Create Sale B: total 50,000, uncollected, zero payments made
        $saleB = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->tenantAdmin->id,
            'userName' => $this->tenantAdmin->name,
            'customerName' => 'Bua Cement Ltd',
            'totalAmount' => 50000.0,
            'paidAmount' => 0.0,
            'cashAmount' => 0.0,
            'posAmount' => 0.0,
            'tenderedAmount' => 0.0,
            'changeAmount' => 0.0,
            'transferAmount' => 0.0,
            'status' => 'PENDING',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // 1. Query for PAID sales:
        // Must include SaleA because net balance = 100,000 - 100,000 = 0 (even though cached status is PARTIAL)!
        $responsePaid = $this->actingAs($this->tenantAdmin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->tenantAdmin->id,
            'user_role' => 'admin',
            'portal' => 'tenant',
        ])->get('/transactions?tab=sales&payment_status=PAID');

        $responsePaid->assertStatus(200);
        $salesPaid = $responsePaid->viewData('sales');
        $paidIds = collect($salesPaid->items())->pluck('id')->all();
        $this->assertContains($saleA->id, $paidIds, "SaleA must be classified as PAID via authoritative payment events.");
        $this->assertNotContains($saleB->id, $paidIds);

        // 2. Query for PARTIAL sales:
        // Must NOT include SaleA because balance is 0.
        $responsePartial = $this->actingAs($this->tenantAdmin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->tenantAdmin->id,
            'user_role' => 'admin',
            'portal' => 'tenant',
        ])->get('/transactions?tab=sales&payment_status=PARTIAL');

        $responsePartial->assertStatus(200);
        $salesPartial = $responsePartial->viewData('sales');
        $partialIds = collect($salesPartial->items())->pluck('id')->all();
        $this->assertNotContains($saleA->id, $partialIds, "SaleA must not appear in PARTIAL when fully settled via payment events.");

        // 3. Query for DEBT sales:
        // Must include SaleB, but NOT SaleA.
        $responseDebt = $this->actingAs($this->tenantAdmin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->tenantAdmin->id,
            'user_role' => 'admin',
            'portal' => 'tenant',
        ])->get('/transactions?tab=sales&payment_status=DEBT');

        $responseDebt->assertStatus(200);
        $salesDebt = $responseDebt->viewData('sales');
        $debtIds = collect($salesDebt->items())->pluck('id')->all();
        $this->assertContains($saleB->id, $debtIds, "SaleB must appear in DEBT.");
        $this->assertNotContains($saleA->id, $debtIds, "SaleA must not appear in DEBT.");
    }
}
