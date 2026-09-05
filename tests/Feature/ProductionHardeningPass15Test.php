<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionHardeningPass15Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $branch;
    protected User $cashier;
    protected User $manager;
    protected User $superAdmin;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@ikeja.test',
        ]);

        Tenant::withoutGlobalScopes()->firstOrCreate([
            'id' => 'default-tenant',
        ], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@ikeja.test',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 999,
            'max_users' => 999,
        ]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-ikeja-' . Str::random(5),
            'name' => 'Ikeja Digital Mart Ltd',
            'slug' => 'ikeja-digital-' . Str::random(5),
            'owner_email' => 'owner@ikeja.test',
            'status' => 'active',
        ]);

        $this->branch = Warehouse::create([
            'name' => 'Computer Village Hub',
            'code' => 'CVH-01',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Bimbo Cashier',
            'email' => 'bimbo@ikeja.test',
            'password' => Hash::make('StrongP@ssw0rd!'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch->id,
            'disabled' => false,
        ]);

        $this->manager = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tunde Manager',
            'email' => 'tunde@ikeja.test',
            'password' => Hash::make('StrongP@ssw0rd!'),
            'role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch->id,
            'disabled' => false,
        ]);

        $this->superAdmin = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Superadmin',
            'email' => 'superadmin@ikeja.test',
            'password' => Hash::make('SuperAdm1nSecr3t!'),
            'role' => 'super_admin',
            'disabled' => false,
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Anker 65W GaN Charger',
            'code' => 'ANK-65W',
            'category' => 'Accessories',
            'unitPrice' => 45000.00,
            'costPrice' => 32000.00,
            'currentStock' => 50,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->branch->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);
    }

    /**
     * PASS 15 - MANDATORY IDEMPOTENCY KEY VALIDATION:
     * Asserts that IdempotencyService rejects null, empty, or whitespace keys.
     */
    public function test_idempotency_service_rejects_empty_or_whitespace_key(): void
    {
        $service = app(IdempotencyService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Idempotency key is required for operation 'TEST_OPERATION'.");

        $service->execute('TEST_OPERATION', '   ', $this->tenant->id, (string) $this->cashier->id, [], function () {
            return ['status' => 'ok'];
        });
    }

    /**
     * PASS 15 - IDEMPOTENCY REPLAY PROTECTION ON POS CHECKOUT:
     * Verifies that duplicate checkout submissions with identical X-Idempotency-Key
     * replay the cached sale instead of creating a second sale or deducting stock twice.
     */
    public function test_pos_checkout_replay_protection_with_idempotency_key(): void
    {
        $payload = [
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 2,
                    'unitPrice' => 45000.00,
                    'name' => $this->product->name,
                ]
            ],
            'cashAmount' => 90000.00,
            'posAmount' => 0,
            'paidAmount' => 90000.00,
            'is_supplied' => 'yes',
            'warehouse_id' => $this->branch->id,
            'customerName' => 'Chief Adeyemi',
        ];

        $headers = [
            'X-Idempotency-Key' => 'idempotency-key-' . Str::random(10),
        ];

        // First checkout request
        $response1 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('pos.checkout'), $payload, $headers);

        $response1->assertStatus(200);
        $data1 = $response1->json();
        $this->assertTrue($data1['success']);
        $saleId = $data1['saleId'];

        // Second checkout request with identical idempotency key (simulating network retry / double-click)
        $response2 = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('pos.checkout'), $payload, $headers);

        $response2->assertStatus(200);
        $data2 = $response2->json();
        $this->assertTrue($data2['success']);
        $this->assertEquals($saleId, $data2['saleId']);

        // Stock must have been deducted exactly once (50 - 2 = 48, NOT 46)
        $stock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->branch->id)
            ->first();
        $this->assertEquals(48, $stock->physical_stock);

        // Exactly one sale record should exist in database
        $salesCount = Sale::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(1, $salesCount);
    }

    /**
     * PASS 15 - UNIFIED PASSWORD POLICY IN USER MANAGEMENT:
     * Verifies that worker creation and update reject passwords shorter than 8 characters.
     */
    public function test_user_creation_and_update_enforces_minimum_eight_char_password(): void
    {
        // Manager creating worker with short password (5 chars)
        $response = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('users.store'), [
                'name' => 'Short Pass Cashier',
                'email' => 'short@ikeja.test',
                'password' => '12345',
                'role' => 'cashier',
                'warehouse_id' => $this->branch->id,
            ]);

        $response->assertSessionHasErrors(['password']);

        // Manager updating user password to short password (< 8 chars)
        $responseUpdate = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->put(route('users.update', $this->cashier->id), [
                'name' => $this->cashier->name,
                'email' => $this->cashier->email,
                'password' => 'short7',
                'role' => 'cashier',
                'warehouse_id' => $this->branch->id,
            ]);

        $responseUpdate->assertSessionHasErrors(['password']);
    }

    /**
     * PASS 15 - STRICT TYPED VALIDATION ON PRIVILEGED SAAS SETTINGS:
     * Verifies that SaaSController::updateSettings rejects negative trial days and invalid fields.
     */
    public function test_saas_update_settings_rejects_negative_trial_days_and_invalid_values(): void
    {
        // Attempting negative trial days
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['tenant_id' => 'default-tenant'])
            ->post(route('saas.admin.settings'), [
                'trial_days' => -10,
                'currency_symbol' => 'NGN',
                'platform_name' => 'Valid POS',
                'allow_registration' => '1',
            ]);

        $response->assertSessionHasErrors(['trial_days']);

        // Attempting invalid currency symbol (> 10 chars)
        $responseCurrency = $this->actingAs($this->superAdmin)
            ->withSession(['tenant_id' => 'default-tenant'])
            ->post(route('saas.admin.settings'), [
                'trial_days' => 14,
                'currency_symbol' => 'VERYLONGINVALIDCURRENCYSTRING',
                'platform_name' => 'Valid POS',
            ]);

        $responseCurrency->assertSessionHasErrors(['currency_symbol']);
    }

    /**
     * PASS 15 - ATOMIC TENANT PROVISIONING:
     * Verifies that when tenant registration encounters validation failure on duplicate email,
     * no tenant is persisted.
     */
    public function test_atomic_tenant_registration_rollback_on_failure(): void
    {
        $initialTenantCount = Tenant::count();

        // Pass an already existing user email during registration to trigger unique validation failure
        $response = $this->post(route('saas.register.post'), [
            'business_name' => 'Rollback Electronics Ltd',
            'owner_name' => 'Alaba Trader',
            'owner_email' => $this->cashier->email, // duplicate email
            'owner_phone' => '08012345678',
            'password' => 'StrongPass123!',
            'plan' => 'basic',
        ]);

        $response->assertSessionHasErrors(['owner_email']);

        // Ensure no orphaned tenant was committed
        $this->assertEquals($initialTenantCount, Tenant::count());
    }

    /**
     * PASS 15 - PRODUCT CSV IMPORT RESOURCE LIMITS:
     * Rejects CSVs with > 20 columns or > 500 rows to prevent DoS resource exhaustion.
     */
    public function test_product_csv_import_enforces_column_and_row_limits(): void
    {
        // 1. CSV with 25 columns (> 20 allowed)
        $tooManyColsHeader = implode(',', array_map(fn($i) => "Col_$i", range(1, 25))) . "\n";
        $tooManyColsRow = implode(',', array_map(fn($i) => "Val_$i", range(1, 25))) . "\n";
        $csv25 = UploadedFile::fake()->createWithContent('wide.csv', $tooManyColsHeader . $tooManyColsRow);

        $responseCols = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.import.csv'), [
                'csv_file' => $csv25,
                'warehouse_id' => $this->branch->id,
            ]);

        $responseCols->assertRedirect(route('products.index'));
        $responseCols->assertSessionHas('error', 'Uploaded CSV has too many columns. Maximum allowed is 20 columns.');

        // 2. CSV with 501 rows (> 500 limit)
        $csvContent = "Name,Code,Category,UnitPrice,InitialStock\n";
        for ($i = 1; $i <= 501; $i++) {
            $csvContent .= "Product $i,CODE-$i,Category,1000,5\n";
        }
        $csv501 = UploadedFile::fake()->createWithContent('long.csv', $csvContent);

        $responseRows = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.import.csv'), [
                'csv_file' => $csv501,
                'warehouse_id' => $this->branch->id,
            ]);

        $responseRows->assertRedirect(route('products.index'));
        $responseRows->assertSessionHas('error', 'Uploaded CSV exceeds maximum limit of 500 rows per batch import.');
    }
}
