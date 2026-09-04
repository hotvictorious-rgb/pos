<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionStateMachineConcurrencyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected Warehouse $warehouse2;
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
            'id' => 'tenant-sm-audit',
            'name' => 'State Machine Mart',
            'owner_email' => 'owner@smaudit.com',
            'owner_phone' => '08022334455',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 10,
            'max_users' => 10,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'SM Main Hub',
            'code' => 'SM-HUB-01',
            'is_active' => true,
        ]);

        $this->warehouse2 = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'SM Outlet Two',
            'code' => 'SM-OUT-02',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => 'admin-sm-audit',
            'tenant_id' => $this->tenant->id,
            'name' => 'SM Admin',
            'email' => 'admin@smaudit.com',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->cashier = User::create([
            'id' => 'cashier-sm-audit',
            'tenant_id' => $this->tenant->id,
            'name' => 'SM Cashier',
            'email' => 'cashier@smaudit.com',
            'password' => bcrypt('CashierPassword123!'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->product = Product::create([
            'id' => 'prod-sm-sugar-50kg',
            'tenant_id' => $this->tenant->id,
            'name' => 'Refined Pure Sugar 50kg',
            'code' => 'SUGAR-50KG',
            'unitPrice' => 40000.00,
            'costPrice' => 36000.00,
            'category' => 'Commodities',
            'currentStock' => 50,
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

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse2->id,
            'physical_stock' => 0,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        session(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->cashier);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // ─────────────────────────────────────────────────────────
    // 1. SALES STATE MACHINE & REPLAY TESTS
    // ─────────────────────────────────────────────────────────

    public function test_checkout_replay_with_idempotency_key_does_not_double_deduct_stock()
    {
        $idempotencyKey = 'IDEM-CHECKOUT-001';

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'is_supplied' => 'yes',
            'paidAmount' => 40000.00,
            'cashAmount' => 40000.00,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'productId' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // First Request
        $res1 = $this->actingAs($this->cashier)->withSession([
            'user_id' => $this->cashier->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $res1->assertStatus(200);
        $saleId1 = $res1->json('saleId');
        $this->assertNotEmpty($saleId1);

        // Physical stock should now be 50 - 1 = 49
        $stock1 = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(49, $stock1->physical_stock);

        // Second Replayed Request (network retry or double click with same idempotency key)
        $res2 = $this->actingAs($this->cashier)->withSession([
            'user_id' => $this->cashier->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', $payload);

        $res2->assertStatus(200);
        $saleId2 = $res2->json('saleId');
        $this->assertEquals($saleId1, $saleId2);

        // Physical stock MUST STILL BE 49 (NO double deduction!)
        $stock2 = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(49, $stock2->physical_stock);
        $this->assertEquals(1, Sale::where('id', $saleId1)->count());
    }

    public function test_terminal_completed_wholesale_order_cannot_be_repriced()
    {
        // 1. Create a wholesale order
        $sale = Sale::create([
            'id' => 'WS-ORDER-001',
            'tenant_id' => $this->tenant->id,
            'customerName' => 'Alhaji Bature Wholesalers',
            'totalAmount' => 40000.00 * 5,
            'paidAmount' => 40000.00 * 5,
            'cashAmount' => 0,
            'posAmount' => 40000.00 * 5,
            'status' => 'COMPLETED', // Already settled!
            'sale_type' => 'WHOLESALE_DISPATCH',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        $item = SaleItem::create([
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'quantity' => 5,
            'unitPrice' => 40000.00,
            'totalPrice' => 200000.00,
            'code' => $this->product->code,
            'productCode' => $this->product->code,
        ]);

        // 2. Attempt to hit former wholesale price endpoint must return 404 (Wholesale completely removed)
        $payload = [
            'items' => [
                ['id' => $item->id, 'unit_price' => 50000.00],
            ],
            'payment_status' => 'PAID',
            'payment_method' => 'CASH',
        ];

        $response = $this->actingAs($this->admin)->withSession([
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant',
        ])->post("/wholesale/price/{$sale->id}", $payload);

        $response->assertNotFound();
        $this->assertEquals('COMPLETED', $sale->fresh()->status);
        $this->assertEquals(200000.00, $sale->fresh()->totalAmount);
    }

    public function test_wholesale_routes_are_completely_inaccessible_and_return_404()
    {
        $response = $this->actingAs($this->admin)->withSession([
            'user_id' => $this->admin->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant',
        ])->get('/wholesale');

        $response->assertNotFound();
    }

    // ─────────────────────────────────────────────────────────
    // 2. PAYMENT STATE MACHINE & CONCURRENT RACES
    // ─────────────────────────────────────────────────────────

    public function test_debt_payment_replay_after_clearance_is_rejected()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Emeka Logistics',
            'phone' => '08033445566',
            'total_debt' => 10000.00,
        ]);

        // 1. First payment of ₦10,000 clears the debt
        $this->stockService->recordCustomerPayment(
            $customer->id,
            10000.00,
            'CASH',
            'REF-101',
            $this->cashier->id,
            $this->cashier->name
        );

        $customer->refresh();
        $this->assertEquals(0, $customer->total_debt);

        // 2. Replay of same payment or second payment request must be rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("exceeds customer's total outstanding debt (₦0.00)");

        $this->stockService->recordCustomerPayment(
            $customer->id,
            10000.00,
            'CASH',
            'REF-101',
            $this->cashier->id,
            $this->cashier->name
        );
    }

    public function test_two_payments_cannot_both_claim_the_same_remaining_debt()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Garba Provisions',
            'phone' => '08077665544',
            'total_debt' => 15000.00,
        ]);

        // Request 1 pays ₦10,000 via POS
        $this->stockService->recordCustomerPayment(
            $customer->id,
            10000.00,
            'POS',
            'REF-A',
            $this->cashier->id,
            $this->cashier->name
        );

        $customer->refresh();
        $this->assertEquals(5000.00, $customer->total_debt);

        // Request 2 tries to pay ₦10,000 (was sent believing debt was still ₦15,000)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("exceeds customer's total outstanding debt (₦5,000.00)");

        $this->stockService->recordCustomerPayment(
            $customer->id,
            10000.00,
            'POS',
            'REF-B',
            $this->cashier->id,
            $this->cashier->name
        );
    }

    // ─────────────────────────────────────────────────────────
    // 3. RETURN / REFUND STATE MACHINE
    // ─────────────────────────────────────────────────────────

    public function test_two_returns_cannot_exceed_sold_quantity_combined()
    {
        // 1. Sale of 2 units
        $sale = $this->stockService->recordSale(
            ['totalAmount' => 80000.00, 'paidAmount' => 80000.00],
            [['productId' => $this->product->id, 'quantity' => 2]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        // 2. First return returns 1 unit
        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->product->id, 'quantity' => 1]],
            $this->warehouse->id,
            'CASH_REFUND',
            'Customer wanted only 1',
            $this->cashier->id,
            $this->cashier->name
        );

        // 3. Second return attempts to return 2 units (1 + 2 = 3 > 2 sold)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Sold: 2, Already returned: 1, Remaining eligible: 1");

        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->product->id, 'quantity' => 2]],
            $this->warehouse->id,
            'CASH_REFUND',
            'Attempting to return extra',
            $this->cashier->id,
            $this->cashier->name
        );
    }

    public function test_two_cash_refunds_cannot_exceed_total_cash_paid_combined()
    {
        // Sale of 2 units (₦80,000), but customer paid only ₦40,000 on part-payment
        $sale = $this->stockService->recordSale(
            ['totalAmount' => 80000.00, 'paidAmount' => 40000.00],
            [['productId' => $this->product->id, 'quantity' => 2]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        // 1. First return of 1 unit claims ₦40,000 cash refund (eligible cash becomes 0)
        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->product->id, 'quantity' => 1]],
            $this->warehouse->id,
            'CASH_REFUND',
            'Damaged bag',
            $this->cashier->id,
            $this->cashier->name
        );

        // 2. Second return of remaining 1 unit tries to claim another ₦40,000 CASH refund
        // It must be rejected because customer only paid ₦40,000 in cash total!
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Maximum refundable cash for Sale #{$sale->id} based on actual payments made is ₦0.00. Use DEBT_REDUCTION");

        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->product->id, 'quantity' => 1]],
            $this->warehouse->id,
            'CASH_REFUND',
            'Second bag return',
            $this->cashier->id,
            $this->cashier->name
        );
    }

    // ─────────────────────────────────────────────────────────
    // 4. INVENTORY CONSERVATION & BOUNDARY AUDIT
    // ─────────────────────────────────────────────────────────

    public function test_stock_in_with_negative_or_zero_quantity_is_rejected()
    {
        \Illuminate\Support\Facades\Auth::login($this->admin);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Stock in quantity must be at least 1 unit.");

        $this->stockService->recordStockIn(
            $this->product->id,
            $this->warehouse->id,
            -10, // Attacker attempts negative stock in
            'Bogus Supplier',
            $this->admin->id,
            $this->admin->name
        );
    }

    public function test_stock_adjustment_write_off_with_negative_quantity_is_rejected()
    {
        \Illuminate\Support\Facades\Auth::login($this->admin);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Write-off quantity must be at least 1 unit.");

        $this->stockService->recordStockAdjustment(
            $this->product->id,
            $this->warehouse->id,
            'DAMAGED',
            -5, // Attacker attempts negative write-off to manufacture stock
            'Sneaky adjustment',
            $this->admin->id,
            $this->admin->name
        );
    }

    public function test_inventory_conservation_equation()
    {
        \Illuminate\Support\Facades\Auth::login($this->admin);
        $initialStock = 50;
        $stock = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals($initialStock, $stock->physical_stock);

        // 1. Stock in 20 units
        $this->stockService->recordStockIn($this->product->id, $this->warehouse->id, 20, 'Dangote Mills', $this->admin->id, $this->admin->name);

        // 2. Sell 5 units
        $sale = $this->stockService->recordSale(
            ['totalAmount' => 40000.00 * 5, 'paidAmount' => 40000.00 * 5],
            [['productId' => $this->product->id, 'quantity' => 5]],
            $this->warehouse->id,
            true,
            $this->cashier->id,
            $this->cashier->name
        );

        // 3. Return 1 unit
        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->product->id, 'quantity' => 1]],
            $this->warehouse->id,
            'CASH_REFUND',
            'Customer return',
            $this->cashier->id,
            $this->cashier->name
        );

        // 4. Write off 2 units as damaged
        $this->stockService->recordStockAdjustment(
            $this->product->id,
            $this->warehouse->id,
            'DAMAGED',
            2,
            'Water leakage damage',
            $this->admin->id,
            $this->admin->name
        );

        // Mathematical Expectation:
        // 50 (opening) + 20 (stock in) - 5 (sold) + 1 (return) - 2 (write-off) = 64
        $stock->refresh();
        $this->assertEquals(64, $stock->physical_stock);
        $this->assertEquals(64, $this->product->fresh()->currentStock);
    }

    // ─────────────────────────────────────────────────────────
    // 5. TRANSFER STATE MACHINE REPLAY & CONFLICTS
    // ─────────────────────────────────────────────────────────

    public function test_transfer_receive_replay_is_rejected()
    {
        \Illuminate\Support\Facades\Auth::login($this->admin);
        // 1. Dispatch 10 units from Hub 1 to Outlet 2
        $transfer = $this->stockService->initiateTransfer(
            $this->warehouse->id,
            $this->warehouse2->id,
            [
                ['productId' => $this->product->id, 'quantity' => 10],
            ],
            'DHL Express',
            $this->admin->id,
            $this->admin->name,
            'TRF-REC-001'
        );

        $this->assertEquals('DISPATCHED', $transfer->status);

        // 2. First receive: receive 10 units
        $this->stockService->receiveTransfer(
            $transfer->id,
            [$this->product->id => 10],
            $this->admin->id,
            $this->admin->name
        );

        $transfer->refresh();
        $this->assertEquals('RECEIVED', $transfer->status);

        // Destination physical stock should be 10
        $destStock = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse2->id)->first();
        $this->assertEquals(10, $destStock->physical_stock);

        // 3. Second receive attempt (replay attack or network duplicate)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("only in-transit 'DISPATCHED' transfers can be received");

        $this->stockService->receiveTransfer(
            $transfer->id,
            [$this->product->id => 10],
            $this->admin->id,
            $this->admin->name
        );
    }

    public function test_transfer_recall_replay_is_rejected()
    {
        \Illuminate\Support\Facades\Auth::login($this->admin);
        // 1. Dispatch 5 units from Hub 1 to Outlet 2
        $transfer = $this->stockService->initiateTransfer(
            $this->warehouse->id,
            $this->warehouse2->id,
            [
                ['productId' => $this->product->id, 'quantity' => 5],
            ],
            'GIG Logistics',
            $this->admin->id,
            $this->admin->name,
            'TRF-RCL-001'
        );

        $this->assertEquals('DISPATCHED', $transfer->status);

        // 2. Recall transfer
        $this->stockService->recallTransfer(
            $transfer->id,
            $this->admin->id,
            $this->admin->name,
            'Driver broke down'
        );

        $transfer->refresh();
        $this->assertEquals('CANCELLED', $transfer->status);

        // 3. Second recall attempt must fail
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("only in-transit 'DISPATCHED' transfers can be recalled");

        $this->stockService->recallTransfer(
            $transfer->id,
            $this->admin->id,
            $this->admin->name,
            'Duplicate recall attempt'
        );
    }

    public function test_transfer_discrepancy_and_inventory_conservation()
    {
        \Illuminate\Support\Facades\Auth::login($this->admin);
        $originStockBefore = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first()->physical_stock;
        $destStockBefore = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse2->id)->first()->physical_stock;

        // 1. Dispatch 10 units
        $transfer = $this->stockService->initiateTransfer(
            $this->warehouse->id,
            $this->warehouse2->id,
            [
                ['productId' => $this->product->id, 'quantity' => 10],
            ],
            'Internal Van',
            $this->admin->id,
            $this->admin->name,
            'TRF-DISC-001'
        );

        // Origin stock decremented by 10
        $originStockAfterDispatch = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first()->physical_stock;
        $this->assertEquals($originStockBefore - 10, $originStockAfterDispatch);

        // 2. Destination counts only 8 units (2 lost in transit)
        $this->stockService->receiveTransfer(
            $transfer->id,
            [$this->product->id => 8],
            $this->admin->id,
            $this->admin->name
        );

        $transfer->refresh();
        $this->assertEquals('DISCREPANCY', $transfer->status);

        // Destination receives EXACTLY 8 units, NOT 10
        $destStockAfter = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse2->id)->first()->physical_stock;
        $this->assertEquals($destStockBefore + 8, $destStockAfter);

        // Check TransferItem discrepancy record
        $item = TransferItem::where('transfer_id', $transfer->id)->first();
        $this->assertEquals(10, $item->dispatched_qty);
        $this->assertEquals(8, $item->received_qty);
        $this->assertEquals(2, $item->discrepancy_qty);
    }
}
