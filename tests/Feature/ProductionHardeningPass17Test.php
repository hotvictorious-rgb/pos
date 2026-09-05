<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\IdempotencyService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass17Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $admin;
    protected User $cashier;
    protected Customer $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-pass17',
            'name' => 'Pass 17 Enterprise Store',
            'owner_email' => 'owner@pass17.test',
            'owner_phone' => '08012345678',
            'status' => 'active',
            'plan' => 'pro',
            'max_branches' => 5,
            'max_users' => 4,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pass 17 Main Branch',
            'code' => 'P17-WH',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => 'admin-p17',
            'tenant_id' => $this->tenant->id,
            'name' => 'Pass 17 Admin',
            'email' => 'admin@pass17.test',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
        ]);

        $this->cashier = User::create([
            'id' => 'cashier-p17',
            'tenant_id' => $this->tenant->id,
            'name' => 'Pass 17 Cashier',
            'email' => 'cashier@pass17.test',
            'password' => bcrypt('Password123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
        ]);

        $this->cashier2 = User::create([
            'id' => 'cashier2-p17',
            'tenant_id' => $this->tenant->id,
            'name' => 'Pass 17 Cashier Two',
            'email' => 'cashier2@pass17.test',
            'password' => bcrypt('Password123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alhaji Musa',
            'phone' => '08099887766',
            'total_debt' => 100000.00, // ₦100,000 initial debt
        ]);

        $this->product = Product::create([
            'id' => 'p17-prod-01',
            'tenant_id' => $this->tenant->id,
            'code' => 'P17-PROD-01',
            'name' => 'Pass 17 Test Product',
            'category' => 'General',
            'costPrice' => 2000.00,
            'unitPrice' => 3000.00,
            'currentStock' => 50,
            'warehouse_id' => $this->warehouse->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);
    }

    /**
     * TEST 1: Debt payment executes cleanly with resolved $tenantId and mandatory IdempotencyService.
     */
    public function test_debt_payment_clean_tenant_id_resolution_and_idempotent_execution(): void
    {
        $idempotencyKey = 'IDEM-DEBT-PAY-' . Str::random(8);

        $response = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->postJson(route('debts.pay', $this->customer->id), [
            'amount' => 25000.00,
            'payment_method' => 'CASH',
            'reference_no' => $idempotencyKey,
            'idempotency_key' => $idempotencyKey,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Customer debt reduced from 100,000 to 75,000
        $this->assertEquals(75000.00, (float) $this->customer->fresh()->total_debt);

        // Replaying with identical key must return the cached result without reducing debt again
        $replayResponse = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->postJson(route('debts.pay', $this->customer->id), [
            'amount' => 25000.00,
            'payment_method' => 'CASH',
            'reference_no' => $idempotencyKey,
            'idempotency_key' => $idempotencyKey,
        ]);

        $replayResponse->assertStatus(200);
        $this->assertEquals(75000.00, (float) $this->customer->fresh()->total_debt, 'Replay must not deduct debt a second time');
    }

    /**
     * TEST 2: Strict idempotency rejection when key is missing on financial mutation endpoint.
     */
    public function test_strict_idempotency_rejects_unkeyed_financial_mutations(): void
    {
        // 1. Debt payment with X-Strict-Idempotency but no key
        $resDebt = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->withHeader('X-Strict-Idempotency', '1')
          ->postJson(route('debts.pay', $this->customer->id), [
              'amount' => 5000.00,
              'payment_method' => 'CASH',
          ]);

        $resDebt->assertStatus(422);
        $this->assertStringContainsString('Idempotency key', $resDebt->json('error'));

        // 2. POS checkout with X-Strict-Idempotency but no key
        $resPos = $this->actingAs($this->cashier)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'user_role' => 'cashier',
        ])->withHeader('X-Strict-Idempotency', '1')
          ->postJson('/pos/checkout', [
              'warehouse_id' => $this->warehouse->id,
              'items' => [['productId' => $this->product->id, 'quantity' => 1]],
              'paidAmount' => 3000.00,
              'cashAmount' => 3000.00,
              'is_supplied' => 'yes',
              'tender_type' => 'CASH',
          ]);

        $resPos->assertStatus(422);
        $this->assertStringContainsString('Idempotency key', $resPos->json('error'));

        // 3. Stock In with X-Strict-Idempotency but no key
        $resStock = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->withHeader('X-Strict-Idempotency', '1')
          ->postJson('/stock/in', [
              'warehouse_id' => $this->warehouse->id,
              'product_id' => $this->product->id,
              'quantity' => 10,
              'supplier_name' => 'Acme Supplies',
          ]);

        $resStock->assertStatus(422);
        $this->assertStringContainsString('Idempotency key', $resStock->json('error'));
    }

    /**
     * TEST 3: Idempotency conflict returns 422 when key is reused with altered payload.
     */
    public function test_idempotency_payload_conflict_returns_422(): void
    {
        $idempotencyKey = 'IDEM-CONFLICT-' . Str::random(8);

        // First payment: ₦10,000
        $res1 = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->postJson(route('debts.pay', $this->customer->id), [
            'amount' => 10000.00,
            'payment_method' => 'CASH',
            'idempotency_key' => $idempotencyKey,
        ]);
        $res1->assertStatus(200);

        // Tampered second payment: same key, but amount is ₦20,000
        $res2 = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->postJson(route('debts.pay', $this->customer->id), [
            'amount' => 20000.00,
            'payment_method' => 'CASH',
            'idempotency_key' => $idempotencyKey,
        ]);

        $res2->assertStatus(422);
        $this->assertStringContainsString('Idempotency Conflict', $res2->json('error'));
    }

    /**
     * TEST 4: Idempotency user isolation returns 422 when different user reuses key.
     */
    public function test_idempotency_cross_user_isolation_returns_422(): void
    {
        $idempotencyKey = 'IDEM-CROSS-USER-' . Str::random(8);

        // Cashier 1 executes with key
        $res1 = $this->actingAs($this->cashier)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'user_role' => 'cashier',
        ])->postJson(route('debts.pay', $this->customer->id), [
            'amount' => 5000.00,
            'payment_method' => 'CASH',
            'idempotency_key' => $idempotencyKey,
        ]);
        $res1->assertStatus(200);

        // Cashier 2 at same branch attempts to execute with Cashier 1's key
        $res2 = $this->actingAs($this->cashier2)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier2->id,
            'user_role' => 'cashier',
        ])->postJson(route('debts.pay', $this->customer->id), [
            'amount' => 5000.00,
            'payment_method' => 'CASH',
            'idempotency_key' => $idempotencyKey,
        ]);

        $res2->assertStatus(422);
        $this->assertStringContainsString('Idempotency Authorization Violation', $res2->json('error'));
    }

    /**
     * TEST 5: Atomic tenant row lock protects against user quota over-provisioning races.
     */
    public function test_atomic_user_quota_lock_enforces_max_users(): void
    {
        // Tenant has max_users = 4. Current count = 3 (admin + cashier + cashier2).
        $this->assertEquals(3, User::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        // 1. Creating 4th user reaches the limit exactly
        $res4 = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->postJson(route('users.store'), [
            'name' => 'Fourth Worker',
            'email' => 'worker4@pass17.test',
            'password' => 'Password123!',
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res4->assertSuccessful();
        $this->assertEquals(4, User::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        // 2. Creating 5th user must be blocked atomically by tenant row lock
        $res5 = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->postJson(route('users.store'), [
            'name' => 'Fifth Worker (Blocked)',
            'email' => 'worker5@pass17.test',
            'password' => 'Password123!',
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res5->assertStatus(422);
        $this->assertStringContainsString('Subscription Limit Reached', $res5->json('error'));
        $this->assertEquals(4, User::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count(), 'Worker count must remain capped at max_users');
    }

    /**
     * TEST 6: Unified password policy across worker creation, update, and reset.
     */
    public function test_unified_password_policy_across_all_surfaces(): void
    {
        // 1. Rejects short password (< 8 chars)
        $resShort = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->post(route('users.store'), [
            'name' => 'Weak Pass Worker',
            'email' => 'weak1@pass17.test',
            'password' => 'Pass1',
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
        ]);
        $resShort->assertSessionHasErrors(['password']);

        // 2. Rejects password without letters
        $resNoLetters = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->post(route('users.store'), [
            'name' => 'No Letters Worker',
            'email' => 'weak2@pass17.test',
            'password' => '12345678',
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
        ]);
        $resNoLetters->assertSessionHasErrors(['password']);

        // 3. Rejects password without number
        $resNoNumber = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->post(route('users.store'), [
            'name' => 'No Number Worker',
            'email' => 'weak3@pass17.test',
            'password' => 'PasswordOnly',
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
        ]);
        $resNoNumber->assertSessionHasErrors(['password']);

        // 4. Password reset enforces identical policy (rejects no numbers)
        $resResetWeak = $this->actingAs($this->admin)->withSession([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
        ])->post(route('users.reset.password', $this->cashier->id), [
            'new_password' => 'weakpasswordonly', // missing numbers
        ]);
        $resResetWeak->assertSessionHasErrors(['new_password']);
    }

    /**
     * TEST 7: Architectural Invariant VM-032: Zero direct mutation bypasses exist in controller layer.
     */
    public function test_zero_direct_mutation_bypasses_in_financial_controllers(): void
    {
        $controllers = [
            app_path('Http/Controllers/Web/DebtController.php'),
            app_path('Http/Controllers/Web/PosController.php'),
            app_path('Http/Controllers/Web/StockController.php'),
        ];

        foreach ($controllers as $filePath) {
            $this->assertFileExists($filePath);
            $content = file_get_contents($filePath);

            // Assert that there are no "else { $this->stockService->record..." bypass blocks
            $this->assertDoesNotMatchRegularExpression(
                '/else\s*\{\s*\$this->stockService->record/i',
                $content,
                "File {$filePath} contains a direct stockService bypass in an else block!"
            );

            // Assert that there are no "else { $stockService->record..." bypass blocks
            $this->assertDoesNotMatchRegularExpression(
                '/else\s*\{\s*\$stockService->record/i',
                $content,
                "File {$filePath} contains a direct stockService bypass in an else block!"
            );
        }
    }
}
