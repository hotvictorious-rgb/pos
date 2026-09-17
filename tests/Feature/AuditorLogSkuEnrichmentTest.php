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
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use App\Services\TransactionVoidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuditorLogSkuEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $admin;
    protected User $cashier;
    protected Product $productA;
    protected Product $productB;

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
            'name' => 'Kano Central Mega Stores',
            'slug' => 'kano-store-' . Str::random(5),
            'owner_email' => 'kano@mega.ng',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse Branch 1',
            'code' => 'MAIN-KN1',
            'address' => 'Kano City Center',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Auditor Aminu',
            'email' => 'aminu@kano.ng',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Cashier Fatima',
            'email' => 'fatima@kano.ng',
            'password' => Hash::make('Password123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Dangote Sugar 50kg',
            'code' => 'SUG-DANG-50KG',
            'category' => 'Commodities',
            'unitPrice' => 85000.00,
            'costPrice' => 78000.00,
            'minStockLevel' => 5,
            'currentStock' => 50,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->productA->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);

        $this->productB = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Golden Penny Flour 50kg',
            'code' => 'FLR-GP-50KG',
            'category' => 'Commodities',
            'unitPrice' => 65000.00,
            'costPrice' => 58000.00,
            'minStockLevel' => 5,
            'currentStock' => 30,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->productB->id,
            'physical_stock' => 30,
            'allocated_stock' => 0,
        ]);
    }

    public function test_sales_checkout_captures_sku_and_line_items_in_audit_log(): void
    {
        $this->actingAs($this->cashier);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'customerName' => 'Alhaji Musa',
            'customerPhone' => '08039998877',
            'is_supplied' => 'yes',
            'paidAmount' => 85000.00,
            'cashAmount' => 85000.00,
            'posAmount' => 0,
            'items' => [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                    'unitPrice' => 85000.00,
                ],
            ],
            'idempotency_key' => 'sale-sku-test-' . Str::random(8),
        ];

        $resp = $this->post(route('pos.checkout'), $payload);
        $resp->assertSessionHasNoErrors();

        $activity = Activity::where('type', 'POS_SALE_COMPLETED')->latest('timestamp')->first();
        $this->assertNotNull($activity);
        $meta = $activity->metadata;

        $this->assertEquals(85000.00, $meta['total_amount']);
        $this->assertEquals(1, $meta['items_count']);
        $this->assertIsArray($meta['items']);
        $this->assertCount(1, $meta['items']);
        $this->assertEquals('SUG-DANG-50KG', $meta['items'][0]['sku']);
        $this->assertEquals('Dangote Sugar 50kg', $meta['items'][0]['name']);
        $this->assertEquals(1, $meta['items'][0]['quantity']);
        $this->assertEquals(85000.00, $meta['items'][0]['unit_price']);
    }

    public function test_stock_in_captures_sku_and_before_after_balances(): void
    {
        $this->actingAs($this->admin);

        $stockService = app(StockService::class);
        $stockService->recordStockIn(
            $this->productA->id,
            $this->warehouse->id,
            10,
            'Dangote Refinery Logistics',
            $this->admin->id,
            $this->admin->name,
            'Weekly truck delivery'
        );

        $activity = Activity::where('type', 'STOCK_IN')->latest('timestamp')->first();
        $this->assertNotNull($activity);
        $meta = $activity->metadata;

        $this->assertEquals('SUG-DANG-50KG', $meta['sku']);
        $this->assertEquals('Dangote Sugar 50kg', $meta['product_name']);
        $this->assertEquals(10, $meta['quantity']);
        $this->assertEquals(50, $meta['stock_before']);
        $this->assertEquals(60, $meta['stock_after']);
        $this->assertEquals('Dangote Refinery Logistics', $meta['supplier']);
    }

    public function test_stock_adjustment_captures_sku_writeoff_type_and_variance(): void
    {
        $this->actingAs($this->admin);

        $stockService = app(StockService::class);
        $stockService->recordStockAdjustment(
            $this->productB->id,
            $this->warehouse->id,
            'DAMAGED',
            3,
            'Rainwater leak damage',
            $this->admin->id,
            $this->admin->name
        );

        $activity = Activity::where('type', 'STOCK_ADJUSTMENT')->latest('timestamp')->first();
        $this->assertNotNull($activity);
        $meta = $activity->metadata;

        $this->assertEquals('FLR-GP-50KG', $meta['sku']);
        $this->assertEquals('DAMAGED', $meta['adjustment_type']);
        $this->assertEquals(3, $meta['quantity']);
        $this->assertEquals(30, $meta['stock_before']);
        $this->assertEquals(27, $meta['stock_after']);
        $this->assertStringContainsString('Rainwater leak', $meta['reason']);
    }

    public function test_debt_payment_captures_customer_balances_and_channel(): void
    {
        $this->actingAs($this->admin);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mallam Garba',
            'phone' => '08021113355',
            'total_debt' => 150000.00,
        ]);

        $stockService = app(StockService::class);
        $stockService->recordCustomerPayment(
            $customer->id,
            50000.00,
            'CASH',
            'CASH-REC-001',
            $this->admin->id,
            $this->admin->name,
            'Part payment recovery',
            $this->warehouse->id
        );

        $activity = Activity::where('type', 'DEBT_PAYMENT')->latest('timestamp')->first();
        $this->assertNotNull($activity);
        $meta = $activity->metadata;

        $this->assertEquals('Mallam Garba', $meta['customer_name']);
        $this->assertEquals(50000.00, $meta['amount_paid']);
        $this->assertEquals('CASH', $meta['payment_method']);
        $this->assertEquals(150000.00, $meta['previous_debt']);
        $this->assertEquals(100000.00, $meta['new_balance']);
    }

    public function test_sales_return_captures_returned_skus(): void
    {
        $this->actingAs($this->admin);

        // 1. Create original sale
        $stockService = app(StockService::class);
        $sale = $stockService->recordSale(
            [
                'totalAmount' => 85000.00,
                'paidAmount' => 85000.00,
                'cashAmount' => 85000.00,
                'posAmount' => 0,
                'customerName' => 'Alhaji Sani',
                'customerPhone' => '08031234567',
                'sale_type' => 'RETAIL',
            ],
            [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                    'unitPrice' => 85000.00,
                ]
            ],
            $this->warehouse->id,
            true,
            $this->admin->id,
            $this->admin->name
        );

        // 2. Process return
        $returnResp = $this->postJson(route('pos.returns.process'), [
            'warehouse_id' => $this->warehouse->id,
            'sale_id' => $sale->id,
            'refund_method' => 'CASH_REFUND',
            'reason' => 'Defective packaging',
            'items' => [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                    'refundAmount' => 85000.00,
                ]
            ],
            'idempotency_key' => 'return-sku-test-' . Str::random(8),
        ]);

        $this->assertTrue($returnResp->isOk(), (string) $returnResp->getContent());

        $activity = Activity::where('type', 'SALES_RETURN_REFUNDED')->latest('timestamp')->first();
        $this->assertNotNull($activity);
        $meta = $activity->metadata;

        $this->assertEquals(85000.00, $meta['refund_amount']);
        $this->assertEquals('CASH_REFUND', $meta['refund_method']);
        $this->assertIsArray($meta['items']);
        $this->assertEquals('SUG-DANG-50KG', $meta['items'][0]['sku']);
        $this->assertEquals('Dangote Sugar 50kg', $meta['items'][0]['name']);
        $this->assertEquals(1, $meta['items'][0]['quantity']);
    }

    public function test_unsupplied_goods_dispatch_captures_sku_and_customer(): void
    {
        $this->actingAs($this->admin);

        $stockService = app(StockService::class);
        $sale = $stockService->recordSale(
            [
                'totalAmount' => 65000.00,
                'paidAmount' => 65000.00,
                'cashAmount' => 65000.00,
                'posAmount' => 0,
                'customerName' => 'Chief Emeka',
                'customerPhone' => '08035557799',
                'sale_type' => 'RETAIL',
            ],
            [
                [
                    'productId' => $this->productB->id,
                    'quantity' => 2,
                    'unitPrice' => 65000.00,
                ]
            ],
            $this->warehouse->id,
            false, // unsupplied
            $this->admin->id,
            $this->admin->name
        );

        $stockService->dispatchUnsuppliedSale(
            $sale->id,
            $this->warehouse->id,
            $this->admin->id,
            $this->admin->name
        );

        $activity = Activity::where('type', 'DISPATCH_FULFILLED')->latest('timestamp')->first();
        $this->assertNotNull($activity);
        $meta = $activity->metadata;

        $this->assertEquals($sale->id, $meta['sale_id']);
        $this->assertEquals('Chief Emeka', $meta['customer_name']);
        $this->assertIsArray($meta['items']);
        $this->assertEquals('FLR-GP-50KG', $meta['items'][0]['sku']);
        $this->assertEquals('Golden Penny Flour 50kg', $meta['items'][0]['name']);
        $this->assertEquals(2, $meta['items'][0]['quantity']);
    }

    public function test_customer_debt_correction_captures_old_new_balance_and_reason(): void
    {
        $this->actingAs($this->admin);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Nkechi',
            'phone' => '08012345678',
            'total_debt' => 25000.00,
        ]);

        $accountingService = app(AccountingReportService::class);
        $accountingService->correctCustomerDebt(
            $customer,
            15000.00,
            'Reconciliation adjustment after bank statement audit',
            $this->admin->id,
            $this->admin->name,
            $this->admin
        );

        $activity = Activity::where('type', 'DEBT_CORRECTION')->latest('timestamp')->first();
        $this->assertNotNull($activity);
        $meta = $activity->metadata;

        $this->assertEquals('Mama Nkechi', $meta['customer_name']);
        $this->assertEquals(25000.00, $meta['old_debt']);
        $this->assertEquals(15000.00, $meta['new_debt']);
        $this->assertEquals(-10000.00, $meta['variance']);
        $this->assertStringContainsString('Reconciliation adjustment', $meta['reason']);
    }

    public function test_transaction_void_captures_restored_skus(): void
    {
        $this->actingAs($this->admin);

        $stockService = app(StockService::class);
        $sale = $stockService->recordSale(
            [
                'totalAmount' => 85000.00,
                'paidAmount' => 85000.00,
                'cashAmount' => 85000.00,
                'posAmount' => 0,
                'customerName' => 'Alhaji Void Test',
                'customerPhone' => '08098765432',
                'sale_type' => 'RETAIL',
            ],
            [
                [
                    'productId' => $this->productA->id,
                    'quantity' => 1,
                    'unitPrice' => 85000.00,
                ]
            ],
            $this->warehouse->id,
            true,
            $this->admin->id,
            $this->admin->name
        );

        $voidService = app(TransactionVoidService::class);
        $voidService->voidSale($sale->id, 'Double entry cashier mistake', $this->admin);

        $activity = Activity::where('type', 'TRANSACTION_VOIDED')->latest('timestamp')->first();
        $this->assertNotNull($activity);
        $meta = $activity->metadata;

        $this->assertEquals($sale->id, $meta['sale_id']);
        $this->assertEquals(85000.00, $meta['total_amount']);
        $this->assertIsArray($meta['items']);
        $this->assertEquals('SUG-DANG-50KG', $meta['items'][0]['sku']);
        $this->assertEquals('Dangote Sugar 50kg', $meta['items'][0]['name']);
        $this->assertEquals(1, $meta['items'][0]['quantity']);
    }
}

