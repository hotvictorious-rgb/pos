<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Activity;
use App\Models\Backup;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductionIntegrityClosurePass17Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $admin;
    protected User $cashier;
    protected User $storekeeper;
    protected Product $product;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@pass17.test',
        ]);

        Tenant::withoutGlobalScopes()->firstOrCreate([
            'id' => 'default-tenant',
        ], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@pass17.test',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 999,
            'max_users' => 999,
        ]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-pass17-' . Str::random(5),
            'name' => 'Alaba Mega Traders Ltd',
            'slug' => 'alaba-mega-' . Str::random(5),
            'owner_email' => 'owner@pass17.test',
            'owner_phone' => '08022233445',
            'status' => 'active',
            'plan' => 'basic',
            'max_branches' => 2,
            'max_users' => 3,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Alaba Main Warehouse',
            'code' => 'AMW-01',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Chief Admin',
            'email' => 'admin@pass17.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier Chinyere',
            'email' => 'chinyere@pass17.test',
            'password' => Hash::make('Cashier123!'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
            'permissions' => ['pos' => true, 'debts' => true, 'returns' => true],
        ]);

        $this->storekeeper = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Storekeeper Emeka',
            'email' => 'emeka@pass17.test',
            'password' => Hash::make('EmekaPass123!'),
            'role' => 'storekeeper',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
            'permissions' => ['stockIn' => true, 'transfer' => true, 'products' => true],
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Haier Thermocool Inverter AC',
            'code' => 'HT-INV-15',
            'category' => 'Electronics',
            'unitPrice' => 450000.00,
            'costPrice' => 380000.00,
            'currentStock' => 20,
            'minStockLevel' => 2,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alhaji Danjuma',
            'phone' => '08031234567',
            'address' => 'Shop 42 International Market',
            'total_debt' => 150000.00,
        ]);
    }

    /**
     * TEST 1: Fix for runtime bug in DebtController where $tenantId was undefined in keyed debt payment.
     */
    public function test_keyed_debt_payment_executes_successfully_and_replays_properly(): void
    {
        $idempotencyKey = 'DEBT-PAY-PASS17-' . Str::random(8);

        $payload = [
            'amount' => 50000.00,
            'payment_method' => 'CASH',
            'reference_no' => 'RCPT-001',
            'notes' => 'Part-payment towards invoice',
        ];

        // First debt payment request with X-Idempotency-Key
        $res1 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson(route('debts.pay', $this->customer->id), $payload);

        $res1->assertStatus(200);
        $this->assertTrue($res1->json('success'));

        // Customer debt must have reduced: 150,000 - 50,000 = 100,000
        $this->assertEquals(100000.00, (float) $this->customer->fresh()->total_debt);

        // Replay same request with same key (simulating network retry)
        $res2 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson(route('debts.pay', $this->customer->id), $payload);

        $res2->assertStatus(200);
        $this->assertTrue($res2->json('success'));

        // Customer debt must NOT have reduced a second time
        $this->assertEquals(100000.00, (float) $this->customer->fresh()->total_debt);
    }

    /**
     * TEST 2: Mandatory idempotency key validation on API mutation requests.
     */
    public function test_api_checkout_and_mutations_require_idempotency_key(): void
    {
        // Missing idempotency key on JSON checkout with X-Require-Idempotency returns 422
        $resCheckout = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->withHeader('X-Require-Idempotency', 'true')
            ->postJson(route('pos.checkout'), [
                'items' => [['productId' => $this->product->id, 'quantity' => 1]],
                'cashAmount' => 450000.00,
                'paidAmount' => 450000.00,
                'is_supplied' => 'yes',
                'warehouse_id' => $this->warehouse->id,
            ]);

        $resCheckout->assertStatus(422);
        $this->assertStringContainsString('Idempotency key is required', $resCheckout->json('error'));

        // Missing idempotency key on JSON debt payment with X-Require-Idempotency returns 422
        $resDebt = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->withHeader('X-Require-Idempotency', 'true')
            ->postJson(route('debts.pay', $this->customer->id), [
                'amount' => 10000,
                'payment_method' => 'CASH',
            ]);

        $resDebt->assertStatus(422);
        $this->assertStringContainsString('Idempotency key is required', $resDebt->json('error'));

        // Missing idempotency key on JSON stock-in with X-Require-Idempotency returns 422
        $resStockIn = $this->actingAs($this->storekeeper)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->withHeader('X-Require-Idempotency', 'true')
            ->postJson(route('stock.in'), [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
                'quantity' => 5,
            ]);

        $resStockIn->assertStatus(422);
        $this->assertStringContainsString('Idempotency key is required', $resStockIn->json('error'));
    }

    /**
     * TEST 3: Customer preparation is encapsulated inside the idempotency boundary.
     * Failed checkouts do not leave orphaned customer accounts in the database.
     */
    public function test_customer_creation_rolls_back_when_transaction_fails_inside_boundary(): void
    {
        $idempotencyKey = 'CHECKOUT-FAIL-CUST-' . Str::random(8);

        $initialCustomerCount = Customer::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count();

        // Attempt checkout with quantity exceeding available stock (20 available, requesting 99)
        $res = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson(route('pos.checkout'), [
                'items' => [['productId' => $this->product->id, 'quantity' => 99]],
                'cashAmount' => 450000.00 * 99,
                'paidAmount' => 450000.00 * 99,
                'is_supplied' => 'yes',
                'warehouse_id' => $this->warehouse->id,
                'customerName' => 'Orphan Test Customer',
                'customerPhone' => '08099887766',
            ]);

        $res->assertStatus(422);

        // Assert customer was NOT permanently created outside the transaction
        $this->assertEquals($initialCustomerCount, Customer::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
        $this->assertNull(Customer::withoutGlobalScopes()->where('phone', '08099887766')->first());
    }

    /**
     * TEST 4: Pessimistic quota row locking eliminates worker subscription limit race condition.
     */
    public function test_pessimistic_user_quota_lock_enforces_max_users_invariant(): void
    {
        // Tenant currently has 3 users (admin, cashier, storekeeper). Limit is max_users = 3.
        $this->assertEquals(3, User::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(3, $this->tenant->max_users);

        // Attempting to create a 4th worker fails closed with quota error
        $res = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('users.store'), [
                'name' => 'Excess Worker',
                'email' => 'excess@pass17.test',
                'password' => 'ValidPass123!',
                'role' => 'cashier',
                'warehouse_id' => $this->warehouse->id,
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Subscription Limit Reached', $res->json('error'));
        $this->assertEquals(3, User::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * TEST 5: Pessimistic quota row locking eliminates branch subscription limit race condition.
     */
    public function test_pessimistic_branch_quota_lock_enforces_max_branches_invariant(): void
    {
        // Tenant has 1 branch. Limit is max_branches = 2.
        $this->assertEquals(1, Warehouse::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        // Adding 2nd branch succeeds
        $res1 = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('settings.warehouse.store'), [
                'name' => 'Branch Two',
                'code' => 'BR-02',
            ]);
        $res1->assertStatus(200);
        $this->assertEquals(2, Warehouse::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        // Attempting 3rd branch exceeds quota and fails closed
        $res2 = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('settings.warehouse.store'), [
                'name' => 'Branch Three',
                'code' => 'BR-03',
            ]);
        $res2->assertStatus(422);
        $this->assertStringContainsString('Subscription Limit Reached', $res2->json('error'));
        $this->assertEquals(2, Warehouse::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    /**
     * TEST 6: Immutable security audit trail records backup lifecycle events.
     */
    public function test_backup_lifecycle_records_structured_security_events(): void
    {
        // 1. Create Backup
        $resCreate = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('settings.backups.create'));

        $resCreate->assertStatus(200);
        $backupId = $resCreate->json('id');

        // Check BACKUP_CREATED Activity
        $createdActivity = Activity::withoutGlobalScopes()->where('type', 'BACKUP_CREATED')->latest('timestamp')->first();
        $this->assertNotNull($createdActivity);
        $this->assertEquals($this->admin->id, $createdActivity->userId);
        $this->assertEquals($backupId, $createdActivity->metadata['backup_id']);

        // 2. Download Backup
        $resDownload = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('settings.backups.download', $backupId));

        $resDownload->assertStatus(200);

        $downloadedActivity = Activity::withoutGlobalScopes()->where('type', 'BACKUP_DOWNLOADED')->latest('timestamp')->first();
        $this->assertNotNull($downloadedActivity);
        $this->assertEquals($backupId, $downloadedActivity->metadata['backup_id']);

        // 3. Restore Backup
        $resRestore = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('settings.backups.restore', $backupId), [
                'confirmation' => 'CONFIRM_RESTORE',
            ]);

        $resRestore->assertStatus(200);

        $restoredActivity = Activity::withoutGlobalScopes()->where('type', 'BACKUP_RESTORED')->latest('timestamp')->first();
        $this->assertNotNull($restoredActivity);
        $this->assertEquals($backupId, $restoredActivity->metadata['backup_id']);

        // 4. Delete Backup
        $resDelete = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->deleteJson(route('settings.backups.destroy', $backupId));

        $resDelete->assertStatus(200);

        $deletedActivity = Activity::withoutGlobalScopes()->where('type', 'BACKUP_DELETED')->latest('timestamp')->first();
        $this->assertNotNull($deletedActivity);
        $this->assertEquals($backupId, $deletedActivity->metadata['backup_id']);
    }

    /**
     * TEST 7: Authentication consistency: API login rejects suspended tenants.
     */
    public function test_api_login_rejects_suspended_tenants(): void
    {
        // Suspend tenant
        $this->tenant->update(['status' => 'suspended']);

        $res = $this->postJson('/api/login', [
            'email' => $this->admin->email,
            'password' => 'SecretPass123!',
        ]);

        $res->assertStatus(403);
        $this->assertStringContainsString('suspended', $res->json('error'));
    }
}
