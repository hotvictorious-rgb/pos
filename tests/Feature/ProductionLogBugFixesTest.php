<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Setting;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionLogBugFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true]);
    }

    /**
     * Fix 1 Verification:
     * Multiple tenants can create or load their settings without
     * SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1' for key 'settings.PRIMARY'.
     */
    public function test_multiple_tenants_can_initialize_settings_without_primary_key_collision(): void
    {
        // 1. First Tenant (e.g. default-tenant)
        $tenant1 = Tenant::create([
            'id' => 'tenant-one',
            'name' => 'Store One',
            'owner_email' => 'one@store.com',
            'status' => 'active',
        ]);

        $user1 = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Owner One',
            'email' => 'one@store.com',
            'password' => Hash::make('Secret123#'),
            'role' => 'admin',
            'tenant_id' => $tenant1->id,
            'disabled' => false,
        ]);

        $response1 = $this->actingAs($user1)
            ->withSession(['user_id' => $user1->id, 'tenant_id' => $tenant1->id])
            ->get(route('settings.index'));
        $response1->assertStatus(200);

        $setting1 = Setting::withoutGlobalScopes()->where('tenant_id', $tenant1->id)->first();
        $this->assertNotNull($setting1);
        $this->assertEquals(1, $setting1->id);

        // 2. Second Tenant (e.g. tenant-vic-fashion-XKyBM)
        $tenant2 = Tenant::create([
            'id' => 'tenant-vic-fashion-XKyBM',
            'name' => 'Vic Fashion',
            'owner_email' => 'vic@fashion.com',
            'status' => 'active',
        ]);

        $user2 = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Vic Owner',
            'email' => 'vic@fashion.com',
            'password' => Hash::make('Secret123#'),
            'role' => 'admin',
            'tenant_id' => $tenant2->id,
            'disabled' => false,
        ]);

        $response2 = $this->actingAs($user2)
            ->withSession(['user_id' => $user2->id, 'tenant_id' => $tenant2->id])
            ->get(route('settings.index'));
        $response2->assertStatus(200);

        $setting2 = Setting::withoutGlobalScopes()->where('tenant_id', $tenant2->id)->first();
        $this->assertNotNull($setting2);
        $this->assertEquals(2, $setting2->id);

        // 3. Third Tenant (Model-level direct creation without ID provided)
        $tenant3 = Tenant::create([
            'id' => 'tenant-three-direct',
            'name' => 'Store Three Direct',
            'owner_email' => 'three@store.com',
            'status' => 'active',
        ]);

        $setting3 = Setting::create([
            'tenant_id' => $tenant3->id,
            'businessName' => 'Store Three Direct',
            'currency' => '₦',
            'categories' => ['General'],
            'lowStockThreshold' => 5,
            'transactionEditLimitDays' => 0,
            'fontFamily' => 'Inter',
        ]);

        $this->assertEquals(3, $setting3->id);
    }

    /**
     * Fix 2 Verification:
     * Searching transactions by query (including customer phone) works cleanly without
     * SQLSTATE[42S22]: Unknown column 'customerPhone' in 'where clause'.
     */
    public function test_sales_search_by_query_or_phone_does_not_crash_when_column_is_absent(): void
    {
        $tenant = Tenant::create([
            'id' => 'tenant-search-test',
            'name' => 'Search Test Supermarket',
            'owner_email' => 'search@store.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin Cashier',
            'email' => 'search@store.com',
            'password' => Hash::make('Secret123#'),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
            'disabled' => false,
        ]);

        $warehouse = Warehouse::create([
            'tenant_id' => $tenant->id,
            'code' => 'MAIN-01',
            'name' => 'Main Branch',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Emeka Okafor',
            'phone' => '08031234567',
        ]);

        $sale = Sale::create([
            'id' => 'SALE-SEARCH-TEST-001',
            'tenant_id' => $tenant->id,
            'warehouse_id' => $warehouse->id,
            'customerName' => 'Emeka Okafor',
            'customerId' => $customer->id,
            'totalAmount' => 15000,
            'paidAmount' => 15000,
            'cashAmount' => 0,
            'posAmount' => 15000,
            'transferAmount' => 0,
            'status' => 'COMPLETED',
            'sale_type' => 'RETAIL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $user->id,
            'userName' => $user->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Search transactions with search query containing random text or phone
        $response = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'active_warehouse_id' => $warehouse->id])
            ->get(route('transactions.index', ['search' => '08031234567']));

        $response->assertStatus(200);
        $response->assertSee('SALE-SEARCH-TEST-001');

        // Search with non-matching query like the one in production error log ('m32de')
        $responseEmpty = $this->actingAs($user)
            ->withSession(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'active_warehouse_id' => $warehouse->id])
            ->get(route('transactions.index', ['search' => 'm32de']));

        $responseEmpty->assertStatus(200);
        $responseEmpty->assertDontSee('SALE-SEARCH-TEST-001');
    }
}
