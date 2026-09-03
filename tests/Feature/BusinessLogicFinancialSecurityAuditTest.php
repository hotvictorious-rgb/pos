<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLogicFinancialSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $cashier;
    protected User $admin;
    protected Product $product;
    protected StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);

        $this->stockService = app(StockService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-biz-audit',
            'name' => 'Financial Security Mart',
            'owner_email' => 'owner@bizsecurity.com',
            'owner_phone' => '08031112233',
            'status' => 'active',
            'plan' => 'basic',
            'max_branches' => 1,
            'max_users' => 2,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Financial Branch',
            'code' => 'FIN-01',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => 'admin-biz-audit',
            'tenant_id' => $this->tenant->id,
            'name' => 'Mart Admin',
            'email' => 'admin@bizsecurity.com',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->cashier = User::create([
            'id' => 'cashier-biz-audit',
            'tenant_id' => $this->tenant->id,
            'name' => 'Mart Cashier',
            'email' => 'cashier@bizsecurity.com',
            'password' => bcrypt('CashierPassword123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->product = Product::create([
            'id' => 'prod-rice-50kg',
            'tenant_id' => $this->tenant->id,
            'name' => 'Premium Golden Rice 50kg',
            'code' => 'RICE-50KG',
            'unitPrice' => 50000.00,
            'costPrice' => 45000.00,
            'category' => 'Grains',
            'currentStock' => 100,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 100,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        session(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->cashier);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // ─────────────────────────────────────────────────────────
    // 1. PRICING & FINANCIAL TOTALS AUTHORITY
    // ─────────────────────────────────────────────────────────

    public function test_client_cannot_tamper_unit_price_or_total_amount_in_checkout()
    {
        // Attacker attempts to checkout 2 bags of ₦50,000 rice (worth ₦100,000) for ₦1,000
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'is_supplied' => 'yes',
            'totalAmount' => 1000.00, // Forged total
            'paidAmount' => 1000.00,
            'cashAmount' => 1000.00,
            'posAmount' => 0,
            'transferAmount' => 0,
            'customerName' => 'Walk-in Customer',
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 2,
                    'unitPrice' => 50000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->withSession([
            'user_id' => $this->cashier->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $response->assertStatus(200);

        // Verify the created sale record in DB has the true calculated total of ₦100,000
        $sale = Sale::latest('createdAt')->first();
        $this->assertNotNull($sale);
        $this->assertEquals(100000.00, $sale->totalAmount);
        $this->assertEquals(1000.00, $sale->paidAmount);
        $this->assertEquals('PARTIAL', $sale->status);
    }

    public function test_negative_or_zero_quantity_is_rejected()
    {
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'is_supplied' => 'yes',
            'totalAmount' => 0,
            'paidAmount' => 0,
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => -5, // Negative quantity to manufacture stock
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->withSession([
            'user_id' => $this->cashier->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $response->assertStatus(422);

        // Verify physical stock was NOT incremented
        $stock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(100, $stock->physical_stock);
    }

    public function test_payment_tender_mismatch_is_rejected()
    {
        // Attacker claims paidAmount is ₦50,000, but cash tendered is only ₦5,000
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'is_supplied' => 'yes',
            'totalAmount' => 50000.00,
            'paidAmount' => 50000.00,
            'cashAmount' => 5000.00, // Short by ₦45,000
            'posAmount' => 0,
            'transferAmount' => 0,
            'customerName' => 'Walk-in Customer',
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)->withSession([
            'user_id' => $this->cashier->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $response->assertStatus(422);
        $this->assertStringContainsString('Payment mismatch', $response->json('error'));
    }

    // ─────────────────────────────────────────────────────────
    // 2. INVENTORY INTEGRITY & RACE CONDITION GUARDS
    // ─────────────────────────────────────────────────────────

    public function test_insufficient_stock_prevents_checkout_and_negative_stock()
    {
        $this->expectException(\App\Exceptions\InsufficientStockException::class);

        $saleData = [
            'totalAmount' => 50000.00 * 500,
            'paidAmount' => 50000.00 * 500,
        ];
        $items = [
            [
                'productId' => $this->product->id,
                'quantity' => 500, // Available is only 100
            ],
        ];

        $this->stockService->recordSale(
            $saleData,
            $items,
            $this->warehouse->id,
            true, // Supplied now
            $this->cashier->id,
            $this->cashier->name
        );
    }

    public function test_dispatch_unsupplied_cannot_be_dispatched_twice()
    {
        // 1. Record an unsupplied sale
        $sale = $this->stockService->recordSale(
            ['totalAmount' => 50000.00, 'paidAmount' => 50000.00],
            [['productId' => $this->product->id, 'quantity' => 2]],
            $this->warehouse->id,
            false, // Unsupplied
            $this->cashier->id,
            $this->cashier->name
        );

        $this->assertEquals('UNSUPPLIED', $sale->deliveryStatus);

        // 2. First dispatch succeeds
        $dispatchedSale = $this->stockService->dispatchUnsuppliedSale(
            $sale->id,
            $this->warehouse->id,
            $this->cashier->id,
            $this->cashier->name
        );
        $this->assertEquals('DELIVERED', $dispatchedSale->deliveryStatus);

        // Physical stock should be 100 - 2 = 98
        $stock = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(98, $stock->physical_stock);

        // 3. Second dispatch attempt must throw Exception and NOT subtract stock again
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Sale has already been fully delivered/dispatched.');

        $this->stockService->dispatchUnsuppliedSale(
            $sale->id,
            $this->warehouse->id,
            $this->cashier->id,
            $this->cashier->name
        );
    }

    // ─────────────────────────────────────────────────────────
    // 3. RETURNS & REFUNDS INTEGRITY
    // ─────────────────────────────────────────────────────────

    public function test_cannot_return_more_units_than_were_sold()
    {
        // 1. Record a sale of 2 units
        $sale = $this->stockService->recordSale(
            ['totalAmount' => 100000.00, 'paidAmount' => 100000.00],
            [['productId' => $this->product->id, 'quantity' => 2]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        // 2. Attempt to return 10 units on a 2-unit sale
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot return 10 units of '{$this->product->name}'. Sold: 2");

        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->product->id, 'quantity' => 10]],
            $this->warehouse->id,
            'CASH_REFUND',
            'Defective batch',
            $this->cashier->id,
            $this->cashier->name
        );
    }

    public function test_cash_refund_cannot_exceed_actual_amount_paid()
    {
        // Sale worth ₦100,000, customer only paid ₦20,000 on credit
        $sale = $this->stockService->recordSale(
            ['totalAmount' => 100000.00, 'paidAmount' => 20000.00],
            [['productId' => $this->product->id, 'quantity' => 2]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        // Customer attempts to return both bags (worth ₦100,000) for a CASH refund of ₦100,000
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum refundable cash for Sale #' . $sale->id . ' based on actual payments made is ₦20,000.00');

        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->product->id, 'quantity' => 2]],
            $this->warehouse->id,
            'CASH_REFUND',
            'Customer cancelled purchase',
            $this->cashier->id,
            $this->cashier->name
        );
    }

    // ─────────────────────────────────────────────────────────
    // 4. PAYMENTS & DEBTS INTEGRITY
    // ─────────────────────────────────────────────────────────

    public function test_customer_payment_cannot_exceed_debt()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Okonkwo Holdings',
            'phone' => '08099887766',
            'total_debt' => 5000.00,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("exceeds customer's total outstanding debt");

        $this->stockService->recordCustomerPayment(
            $customer->id,
            50000.00, // Attacker attempts to pay ₦50,000 when debt is only ₦5,000
            'CASH',
            'REF-999',
            $this->cashier->id,
            $this->cashier->name
        );
    }

    public function test_customer_payment_reconciles_partial_sale_status_to_completed()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Musa Trading',
            'phone' => '08011223344',
            'total_debt' => 10000.00,
        ]);

        $sale = Sale::create([
            'id' => 'SALE-PARTIAL-001',
            'tenant_id' => $this->tenant->id,
            'customerName' => $customer->name,
            'customerId' => $customer->id,
            'totalAmount' => 50000.00,
            'paidAmount' => 40000.00, // ₦10,000 debt
            'cashAmount' => 40000.00,
            'posAmount' => 0,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->cashier->id,
            'userName' => $this->cashier->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        // Customer pays remaining ₦10,000 debt
        $this->stockService->recordCustomerPayment(
            $customer->id,
            10000.00,
            'TRANSFER',
            'TRF-777',
            $this->cashier->id,
            $this->cashier->name
        );

        $customer->refresh();
        $this->assertEquals(0, $customer->total_debt);

        $sale->refresh();
        $this->assertEquals(50000.00, $sale->paidAmount);
        $this->assertEquals('COMPLETED', $sale->status);
    }

    // ─────────────────────────────────────────────────────────
    // 5. INTER-BRANCH TRANSFERS INTEGRITY
    // ─────────────────────────────────────────────────────────

    public function test_transfer_cannot_receive_inflated_counted_quantity()
    {
        $destWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Second Shop',
            'code' => 'FIN-02',
            'is_active' => true,
        ]);

        $transfer = Transfer::create([
            'tenant_id' => $this->tenant->id,
            'transfer_no' => 'TRF-TEST-001',
            'source_warehouse_id' => $this->warehouse->id,
            'destination_warehouse_id' => $destWarehouse->id,
            'carrier_name' => 'Speed Delivery',
            'status' => 'DISPATCHED',
            'dispatched_by' => $this->admin->name,
            'dispatched_at' => now(),
        ]);

        TransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_code' => $this->product->code,
            'dispatched_qty' => 5, // 5 units sent
            'received_qty' => 0,
            'discrepancy_qty' => 0,
        ]);

        // Attacker claims they counted 1,000 units on destination arrival
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed dispatched quantity (5)');

        $this->stockService->receiveTransfer(
            $transfer->id,
            [$this->product->id => 1000],
            $this->admin->id,
            $this->admin->name
        );
    }

    public function test_transfer_cannot_be_recalled_after_already_received()
    {
        $destWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Third Shop',
            'code' => 'FIN-03',
            'is_active' => true,
        ]);

        $transfer = Transfer::create([
            'tenant_id' => $this->tenant->id,
            'transfer_no' => 'TRF-TEST-002',
            'source_warehouse_id' => $this->warehouse->id,
            'destination_warehouse_id' => $destWarehouse->id,
            'carrier_name' => 'Speed Delivery',
            'status' => 'DISPATCHED',
            'dispatched_by' => $this->admin->name,
            'dispatched_at' => now(),
        ]);

        TransferItem::create([
            'transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_code' => $this->product->code,
            'dispatched_qty' => 4,
            'received_qty' => 0,
            'discrepancy_qty' => 0,
        ]);

        // Receive transfer
        $this->stockService->receiveTransfer(
            $transfer->id,
            [$this->product->id => 4],
            $this->admin->id,
            $this->admin->name
        );

        // Attempting recall on a RECEIVED transfer must throw Exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("only in-transit 'DISPATCHED' transfers can be recalled");

        $this->stockService->recallTransfer(
            $transfer->id,
            $this->admin->id,
            $this->admin->name,
            'Fraudulent recall attempt'
        );
    }

    // ─────────────────────────────────────────────────────────
    // 6. SUBSCRIPTION ENFORCEMENT
    // ─────────────────────────────────────────────────────────

    public function test_tenant_cannot_exceed_max_branches_limit()
    {
        // Tenant is on basic plan with max_branches = 1
        $this->assertEquals(1, $this->tenant->max_branches);
        $this->assertEquals(1, Warehouse::count());

        $response = $this->actingAs($this->admin)->withSession([
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant',
        ])->post('/settings/warehouse', [
            'name' => 'Unauthorized Extra Branch',
            'code' => 'EXTRA-01',
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertEquals(1, Warehouse::count()); // Still 1 branch
    }

    public function test_tenant_cannot_exceed_max_users_limit()
    {
        // Tenant is on basic plan with max_users = 2 ($this->admin, $this->cashier)
        $this->assertEquals(2, $this->tenant->max_users);
        $this->assertEquals(2, User::count());

        $response = $this->actingAs($this->admin)->withSession([
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant',
        ])->post('/users', [
            'name' => 'Illegal 3rd Staff',
            'email' => 'illegal3rd@bizsecurity.com',
            'password' => 'Password123!',
            'role' => 'cashier',
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertEquals(2, User::count()); // Still 2 users
    }

    // ─────────────────────────────────────────────────────────
    // 7. BACKUP ENCRYPTION POSTURE FACTUAL VERIFICATION
    // ─────────────────────────────────────────────────────────

    public function test_backup_file_on_disk_is_plain_json_truth_verification()
    {
        $superAdmin = User::create([
            'id' => 'super-audit-user',
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'super@hysamventures.com',
            'password' => bcrypt('SuperPassword123!'),
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($superAdmin)->withSession([
            'user_id' => $superAdmin->id,
            'user_role' => 'super_admin',
            'tenant_id' => 'default-tenant',
            'portal' => 'super-admin',
        ])->postJson('/api/backups');

        $response->assertStatus(200);
        $filename = $response->json('filename');
        $this->assertNotEmpty($filename);

        // Verify physical file content in storage
        $rawDiskContent = \Illuminate\Support\Facades\Storage::disk('local')->get('backups/' . $filename);
        $this->assertNotNull($rawDiskContent);

        // Fact Check: Confirm it is readable JSON and NOT encrypted ciphertext
        $decoded = json_decode($rawDiskContent, true);
        $this->assertIsArray($decoded, "Backup on disk is plain JSON text, confirming factual posture: not encrypted at rest.");
        $this->assertArrayHasKey('version', $decoded);
        $this->assertArrayHasKey('data', $decoded);
    }
}
