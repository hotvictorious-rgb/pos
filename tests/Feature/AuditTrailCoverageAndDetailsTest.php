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
use App\Models\Activity;
use App\Models\SalesReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuditTrailCoverageAndDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $admin;
    protected User $cashier;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@test.com',
        ]);

        Tenant::withoutGlobalScopes()->firstOrCreate(['id' => 'default-tenant'], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@test.com',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-' . Str::random(6),
            'name' => 'Alaba International Tech Store',
            'slug' => 'alaba-store-' . Str::random(5),
            'owner_email' => 'owner@alaba.ng',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Showroom Shop 1',
            'code' => 'MAIN-01',
            'address' => 'Alaba Market, Ojo, Lagos',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Auditor Chinedu',
            'email' => 'chinedu@alaba.ng',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Cashier Ngozi',
            'email' => 'ngozi@alaba.ng',
            'password' => Hash::make('Cashier123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Haier Thermocool Refrigerator 200L',
            'code' => 'HTR-200L',
            'category' => 'Appliances',
            'unitPrice' => 285000.00,
            'minStockLevel' => 2,
            'stock' => 15,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'physical_stock' => 15,
            'allocated_stock' => 0,
        ]);
    }

    /**
     * Verify that successful and failed logins generate structured audit events.
     */
    public function test_login_audit_logging_success_and_failure(): void
    {
        // 1. Failed Login with Non-Existent User
        $failResp = $this->post('/tenant/login', [
            'email' => 'ghost@unknown.com',
            'password' => 'WrongPassword',
        ]);
        $failResp->assertSessionHas('error');

        $failedLog = Activity::withoutGlobalScopes()
            ->where('type', 'AUTH_LOGIN_FAILED')
            ->where('description', 'like', '%ghost@unknown.com%')
            ->first();
        $this->assertNotNull($failedLog, 'Expected AUTH_LOGIN_FAILED audit record for non-existent user.');
        $this->assertEquals('user_not_found', $failedLog->metadata['reason']);

        // 2. Failed Login with Wrong Password
        $wrongPassResp = $this->post('/tenant/login', [
            'email' => 'chinedu@alaba.ng',
            'password' => 'CompletelyWrong!',
        ]);
        $wrongPassResp->assertSessionHas('error');

        $wrongPassLog = Activity::withoutGlobalScopes()
            ->where('type', 'AUTH_LOGIN_FAILED')
            ->where('description', 'like', '%chinedu@alaba.ng%')
            ->where('metadata->reason', 'invalid_password')
            ->first();
        $this->assertNotNull($wrongPassLog, 'Expected AUTH_LOGIN_FAILED audit record for wrong password.');

        // 3. Successful Login
        $successResp = $this->post('/tenant/login', [
            'email' => 'chinedu@alaba.ng',
            'password' => 'SecretPass123!',
        ]);
        $successResp->assertRedirect();

        $successLog = Activity::where('type', 'AUTH_LOGIN_SUCCESS')
            ->where('userId', $this->admin->id)
            ->first();
        $this->assertNotNull($successLog, 'Expected AUTH_LOGIN_SUCCESS audit record.');
        $this->assertEquals($this->tenant->id, $successLog->tenant_id);
        $this->assertEquals('chinedu@alaba.ng', $successLog->metadata['email']);
        $this->assertEquals('admin', $successLog->metadata['role']);
    }

    /**
     * Verify that completing a POS sale writes a fail-safe POS_SALE_COMPLETED audit event.
     */
    public function test_pos_checkout_records_pos_sale_completed_audit_event(): void
    {
        $this->actingAs($this->cashier);
        session([
            'user_id' => $this->cashier->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouse->id,
        ]);

        $checkoutData = [
            'warehouse_id' => $this->warehouse->id,
            'customerName' => 'Chief Emeka Eze',
            'customerPhone' => '08031234567',
            'cashAmount' => 100000.00,
            'posAmount' => 185000.00,
            'paidAmount' => 285000.00,
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 1,
                    'unitPrice' => 285000.00,
                ]
            ],
            'is_supplied' => '1',
            'idempotency_key' => 'idemp-pos-' . Str::random(8),
        ];

        $response = $this->postJson(route('pos.checkout'), $checkoutData);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $saleId = $response->json('saleId');
        $this->assertNotNull($saleId);

        $saleActivity = Activity::where('type', 'POS_SALE_COMPLETED')
            ->where('metadata->sale_id', $saleId)
            ->first();

        $this->assertNotNull($saleActivity, 'Expected POS_SALE_COMPLETED audit event in activity log.');
        $this->assertEquals($this->tenant->id, $saleActivity->tenant_id);
        $this->assertEquals(285000.00, (float) $saleActivity->metadata['total_amount']);
        $this->assertEquals(100000.00, (float) $saleActivity->metadata['cash_amount']);
        $this->assertEquals(185000.00, (float) $saleActivity->metadata['pos_amount']);
        $this->assertEquals('Chief Emeka Eze', $saleActivity->metadata['customer_name']);
        $this->assertEquals(1, $saleActivity->metadata['items_count']);
        $this->assertTrue($saleActivity->metadata['is_supplied']);
    }

    /**
     * Verify that processing a return records a fail-safe SALES_RETURN_REFUNDED audit event.
     */
    public function test_sales_return_records_sales_return_refunded_audit_event(): void
    {
        $this->actingAs($this->cashier);
        session([
            'user_id' => $this->cashier->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouse->id,
        ]);

        // Create an original sale via StockService
        $stockService = app(\App\Services\StockService::class);
        $sale = $stockService->recordSale([
            'totalAmount' => 285000.00,
            'paidAmount' => 285000.00,
            'cashAmount' => 285000.00,
            'posAmount' => 0.0,
            'customerName' => 'Alhaji Danladi',
            'sale_type' => 'RETAIL',
        ], [
            ['productId' => $this->product->id, 'quantity' => 1, 'unitPrice' => 285000.00],
        ], $this->warehouse->id, true, (string) $this->cashier->id, $this->cashier->name);

        $returnData = [
            'sale_id' => $sale->id,
            'warehouse_id' => $this->warehouse->id,
            'refund_method' => 'CASH_REFUND',
            'reason' => 'Customer requested swap before unboxing',
            'idempotency_key' => 'idemp-ret-' . Str::random(8),
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 1,
                    'condition' => 'good',
                ]
            ],
        ];

        $resp = $this->postJson(route('pos.returns.process'), $returnData);
        $resp->assertStatus(200);
        $resp->assertJsonPath('success', true);

        $returnActivity = Activity::where('type', 'SALES_RETURN_REFUNDED')
            ->where('metadata->sale_id', $sale->id)
            ->first();

        $this->assertNotNull($returnActivity, 'Expected SALES_RETURN_REFUNDED audit event in activity log.');
        $this->assertEquals(285000.00, (float) $returnActivity->metadata['refund_amount']);
        $this->assertEquals('CASH_REFUND', $returnActivity->metadata['refund_method']);
        $this->assertEquals('Customer requested swap before unboxing', $returnActivity->metadata['reason']);
        $this->assertEquals(1, $returnActivity->metadata['items_count']);
    }

    /**
     * Verify that archiving a product records a PRODUCT_ARCHIVED audit event.
     */
    public function test_product_archived_records_audit_event(): void
    {
        $this->actingAs($this->admin);
        session([
            'user_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
            'user_role' => 'admin',
        ]);

        $productToArchive = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Discontinued Standing Fan 18 Inch',
            'code' => 'FAN-18-OLD',
            'category' => 'Electronics',
            'unitPrice' => 25000.00,
            'stock' => 2,
        ]);

        $resp = $this->post(route('products.destroy', $productToArchive->id));
        $resp->assertRedirect(route('products.index'));

        $this->assertTrue((bool) $productToArchive->fresh()->archived);

        $archiveActivity = Activity::where('type', 'PRODUCT_ARCHIVED')
            ->where('metadata->product_id', $productToArchive->id)
            ->first();

        $this->assertNotNull($archiveActivity, 'Expected PRODUCT_ARCHIVED audit record.');
        $this->assertEquals('FAN-18-OLD', $archiveActivity->metadata['product_code']);
        $this->assertEquals(25000.00, (float) $archiveActivity->metadata['unit_price']);
    }

    /**
     * Verify POS quick customer registration generates CUSTOMER_REGISTERED event.
     */
    public function test_pos_quick_customer_registration_records_customer_registered_audit_event(): void
    {
        $this->actingAs($this->cashier);
        session([
            'user_id' => $this->cashier->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $customerData = [
            'name' => 'Mrs. Folake Adeleke',
            'phone' => '08098765432',
            'address' => 'Surulere, Lagos',
        ];

        $resp = $this->postJson(route('pos.customer.quick_register'), $customerData);
        $resp->assertStatus(200);
        $resp->assertJsonPath('success', true);

        $custActivity = Activity::where('type', 'CUSTOMER_REGISTERED')
            ->where('metadata->phone', '08098765432')
            ->first();

        $this->assertNotNull($custActivity, 'Expected CUSTOMER_REGISTERED audit event.');
        $this->assertEquals('Mrs. Folake Adeleke', $custActivity->metadata['name']);
        $this->assertTrue((bool) $custActivity->metadata['was_new']);
    }

    /**
     * Verify strict fail-safe isolation:
     * If an anomaly occurs during audit logging, checkout and login do NOT crash or fail.
     */
    public function test_audit_logging_is_strictly_fail_safe_and_isolated(): void
    {
        $this->actingAs($this->cashier);
        session([
            'user_id' => $this->cashier->id,
            'tenant_id' => $this->tenant->id,
            'active_warehouse_id' => $this->warehouse->id,
        ]);

        // Intercept Activity saving and force an unhandled exception inside logging
        Activity::saving(function () {
            throw new \RuntimeException("Simulated database failure inside activity logger subsystem");
        });

        $checkoutData = [
            'warehouse_id' => $this->warehouse->id,
            'customerName' => 'Resilient Buyer',
            'cashAmount' => 285000.00,
            'posAmount' => 0.0,
            'paidAmount' => 285000.00,
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 1,
                    'unitPrice' => 285000.00,
                ]
            ],
            'is_supplied' => '1',
            'idempotency_key' => 'idemp-fail-safe-' . Str::random(8),
        ];

        // Core business transaction MUST succeed despite logging failure
        $response = $this->postJson(route('pos.checkout'), $checkoutData);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    /**
     * Verify the Auditor dashboard renders successfully with the new action filter and details buttons.
     */
    public function test_auditor_dashboard_renders_with_filter_and_details_button(): void
    {
        $this->actingAs($this->admin);
        session([
            'user_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
            'user_role' => 'admin',
        ]);

        // Create dummy audit logs
        Activity::recordSecurityEvent('POS_SALE_COMPLETED', 'Test sale #1 completed', [
            'sale_id' => 'TEST-001',
            'total_amount' => 75000.0,
        ], $this->admin);

        Activity::recordSecurityEvent('AUTH_LOGIN_SUCCESS', 'User logged in', [
            'email' => 'chinedu@alaba.ng',
        ], $this->admin);

        // 1. Visit full auditor dashboard
        $resp = $this->get(route('auditor.index'));
        $resp->assertStatus(200);
        $resp->assertSee('System Activity Audit Log (Immutable)');
        $resp->assertSee('Action Filter:');
        $resp->assertSee('modalActivityDetails');
        $resp->assertSee('btn-act-details');
        $resp->assertSee('POS_SALE_COMPLETED');
        $resp->assertSee('AUTH_LOGIN_SUCCESS');

        // 2. Filter by specific action type
        $filterResp = $this->get(route('auditor.index', ['activity_type' => 'POS_SALE_COMPLETED']));
        $filterResp->assertStatus(200);
        $filterResp->assertSee('Test sale #1 completed');
        $filterResp->assertDontSee('User logged in');
    }
}
