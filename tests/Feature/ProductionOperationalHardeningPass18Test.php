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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionOperationalHardeningPass18Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $cashier;
    protected Product $product;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@pass18.test',
        ]);

        Tenant::withoutGlobalScopes()->firstOrCreate([
            'id' => 'default-tenant',
        ], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@pass18.test',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 999,
            'max_users' => 999,
        ]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-pass18-' . Str::random(5),
            'name' => 'Pass 18 Pilot Merchant Ltd',
            'slug' => 'pass18-pilot-' . Str::random(5),
            'owner_email' => 'pilot@pass18.test',
            'owner_phone' => '08033344455',
            'status' => 'active',
            'plan' => 'standard',
            'max_branches' => 3,
            'max_users' => 5,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Pilot Main Branch',
            'code' => 'PMB-01',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier Ade',
            'email' => 'ade@pass18.test',
            'password' => Hash::make('ValidPass123!'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
            'permissions' => ['pos' => true, 'debts' => true],
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Standing Fan 18 Inch',
            'code' => 'FAN-18',
            'category' => 'Appliances',
            'unitPrice' => 35000.00,
            'costPrice' => 28000.00,
            'currentStock' => 50,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ibrahim Babangida',
            'phone' => '08022334455',
            'address' => 'Balogun Market, Lagos Island',
            'total_debt' => 0.00,
        ]);
    }

    /**
     * TEST 1: The POS checkout blade view renders the persistent idempotency_key input and script.
     */
    public function test_pos_checkout_form_renders_persistent_idempotency_key_input(): void
    {
        $response = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('pos.index'));

        $response->assertStatus(200);
        $response->assertSee('<input type="hidden" name="idempotency_key" id="idempotencyKeyInput" value="">', false);
        $response->assertSee('ensureCheckoutIdempotencyKey', false);
        $response->assertSee('resetCheckoutIdempotencyKey', false);
    }

    /**
     * TEST 2: POS Web Form submission retains idempotency key and prevents double stock decrement on retry.
     */
    public function test_pos_checkout_retains_client_supplied_idempotency_key_across_retries(): void
    {
        $clientKey = 'pos_cart_' . Str::random(12);

        $payload = [
            'idempotency_key' => $clientKey,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 2,
                    'unitPrice' => 35000.00,
                ]
            ],
            'totalAmount' => 70000.00,
            'paidAmount' => 70000.00,
            'cashAmount' => 70000.00,
            'posAmount' => 0.0,
            'is_supplied' => 'yes',
            'customerId' => $this->customer->id,
            'customerName' => $this->customer->name,
            'customerPhone' => $this->customer->phone,
        ];

        // Initial submission
        $res1 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('pos.checkout'), $payload);

        $res1->assertRedirect();
        $this->assertEquals(1, Sale::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        $stockAfterFirst = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first()->physical_stock;
        $this->assertEquals(48, $stockAfterFirst, 'First checkout must deduct 2 units from 50 = 48');

        // Client retry submission with exact same idempotency_key (simulating network retry/double-click)
        $res2 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('pos.checkout'), $payload);

        $res2->assertRedirect();

        // Exactly one sale record must exist in DB (no duplicates)
        $this->assertEquals(1, Sale::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        // Stock must remain 48 (not decremented a second time)
        $stockAfterSecond = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first()->physical_stock;
        $this->assertEquals(48, $stockAfterSecond, 'Retried checkout with same idempotency key must not deduct stock again');
    }

    /**
     * TEST 3: CI workflow contains mandatory frontend-build job and publishes status context.
     */
    public function test_ci_workflow_enforces_frontend_build_and_laravel_tests(): void
    {
        $ciPath = base_path('.github/workflows/ci.yml');
        $this->assertFileExists($ciPath);

        $content = file_get_contents($ciPath);
        $this->assertStringContainsString('frontend-build:', $content);
        $this->assertStringContainsString('npm ci', $content);
        $this->assertStringContainsString('npm run lint', $content);
        $this->assertStringContainsString('npm run build', $content);
        $this->assertStringContainsString('ci/frontend-build', $content);
        $this->assertStringContainsString('ci/laravel-tests', $content);
    }

    /**
     * TEST 4: storage.ts disarms direct saveData writes.
     */
    public function test_storage_ts_direct_storage_writes_are_disarmed(): void
    {
        $storagePath = resource_path('js/lib/storage.ts');
        $this->assertFileExists($storagePath);

        $content = file_get_contents($storagePath);
        $this->assertStringContainsString('saveData: <T>(_key: string, _data: T[]) => {', $content);
        $this->assertStringContainsString('Direct client-side storage writes are disabled', $content);
    }
}
