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
use App\Models\InventoryLog;
use App\Services\StockService;
use App\Services\TransactionVoidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TransactionVoidAndReturnEnhancementTest extends TestCase
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
            'name' => 'Lagos Superstore',
            'slug' => 'lagos-superstore-' . Str::random(5),
            'owner_email' => 'admin@lagos.test',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH',
            'address' => '12 Marina Road',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Store Owner',
            'email' => 'owner@lagos.test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Counter Cashier',
            'email' => 'cashier@lagos.test',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Wireless Bluetooth Speaker',
            'code' => 'SPK-001',
            'category' => 'Electronics',
            'unitPrice' => 70000.0,
            'costPrice' => 50000.0,
            'currentStock' => 10,
            'tenant_id' => $this->tenant->id,
        ]);

        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 10,
            'allocated_stock' => 0,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test: Non-admin is blocked from voiding transactions (403).
     */
    public function test_cashier_is_forbidden_from_voiding_sale()
    {
        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'customerName' => 'Walk-in Customer',
            'totalAmount' => 70000,
            'paidAmount' => 70000,
            'cashAmount' => 70000,
            'posAmount' => 0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'userId' => (string) $this->cashier->id,
            'userName' => $this->cashier->name,
        ]);

        $response = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('transactions.void.sale', $sale->id), [
                'reason' => 'Cashier attempting void without permission',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test: Admin can void sale, restoring physical stock and logging activity.
     */
    public function test_admin_can_void_sale_and_restore_physical_stock()
    {
        session(['tenant_id' => $this->tenant->id]);
        $stockService = app(StockService::class);
        $sale = $stockService->recordSale([
            'totalAmount' => 70000,
            'paidAmount' => 70000,
            'cashAmount' => 0,
            'posAmount' => 70000,
            'customerName' => 'Chidi Okonkwo',
            'sale_type' => 'RETAIL',
        ], [
            ['productId' => $this->product->id, 'quantity' => 2, 'unitPrice' => 70000],
        ], $this->warehouse->id, true, (string) $this->admin->id, $this->admin->name);

        // Stock was 10, sold 2 -> physical stock should now be 8
        $stock = StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(8, $stock->physical_stock);

        // Now Admin voids this sale
        $response = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('transactions.void.sale', $sale->id), [
                'reason' => 'Customer changed mind and returned item on spot',
            ]);

        $response->assertSessionHas('success');

        // Stock should be restored back to 10
        $stock->refresh();
        $this->assertEquals(10, $stock->physical_stock);

        // Sale record should be deleted
        $this->assertNull(Sale::find($sale->id));

        // Security activity log should be recorded
        $activity = Activity::where('type', 'TRANSACTION_VOIDED')->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('Store Owner', $activity->description);
    }

    /**
     * Test: Returns with POS_TRANSFER_REFUND succeed for card payments without ₦0.00 cash error.
     */
    public function test_pos_card_payment_can_be_refunded_using_pos_transfer_refund()
    {
        session(['tenant_id' => $this->tenant->id]);
        $stockService = app(StockService::class);
        $sale = $stockService->recordSale([
            'totalAmount' => 70000,
            'paidAmount' => 70000,
            'cashAmount' => 0,
            'posAmount' => 70000,
            'customerName' => 'Emeka Johnson',
            'sale_type' => 'RETAIL',
        ], [
            ['productId' => $this->product->id, 'quantity' => 1, 'unitPrice' => 70000],
        ], $this->warehouse->id, true, (string) $this->admin->id, $this->admin->name);

        // Stock is now 9
        $stock = StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(9, $stock->physical_stock);

        // Process Return via POS_TRANSFER_REFUND
        $response = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('pos.returns.process'), [
                'sale_id' => $sale->id,
                'warehouse_id' => $this->warehouse->id,
                'refund_method' => 'POS_TRANSFER_REFUND',
                'reason' => 'Customer requested card refund / transfer reversal',
                'items' => [
                    ['productId' => $this->product->id, 'quantity' => 1, 'unitPrice' => 70000],
                ],
            ]);

        $response->assertJson(['success' => true]);

        // Stock restored back to 10
        $stock->refresh();
        $this->assertEquals(10, $stock->physical_stock);
    }

    /**
     * Test: Multi-SKU product exchange with inventory restock, double-return prevention, and tender accounting.
     */
    public function test_multi_sku_product_exchange_with_restock_and_double_return_prevention()
    {
        session(['tenant_id' => $this->tenant->id]);
        $stockService = app(StockService::class);

        // Create Product B and Product C
        $productB = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Studio Wireless Headset',
            'code' => 'HDS-002',
            'category' => 'Electronics',
            'unitPrice' => 20000.0,
            'costPrice' => 12000.0,
            'currentStock' => 10,
            'tenant_id' => $this->tenant->id,
        ]);
        StockLevel::create([
            'product_id' => $productB->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 10,
            'allocated_stock' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        $productC = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Luxury Smart Watch',
            'code' => 'WTC-003',
            'category' => 'Electronics',
            'unitPrice' => 50000.0,
            'costPrice' => 30000.0,
            'currentStock' => 5,
            'tenant_id' => $this->tenant->id,
        ]);
        StockLevel::create([
            'product_id' => $productC->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 5,
            'allocated_stock' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        // 1. Initial multi-item sale: 3 of Product A (@ ₦10,000) + 2 of Product B (@ ₦20,000) = ₦70,000
        $origSale = $stockService->recordSale([
            'totalAmount' => 70000,
            'paidAmount' => 70000,
            'cashAmount' => 70000,
            'posAmount' => 0,
            'customerName' => 'Walk-in Customer',
            'sale_type' => 'RETAIL',
        ], [
            ['productId' => $this->product->id, 'quantity' => 3, 'unitPrice' => 10000],
            ['productId' => $productB->id, 'quantity' => 2, 'unitPrice' => 20000],
        ], $this->warehouse->id, true, (string) $this->admin->id, $this->admin->name);

        // Product A stock: 10 - 3 = 7; Product B stock: 10 - 2 = 8
        $this->assertEquals(7, StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->value('physical_stock'));
        $this->assertEquals(8, StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $productB->id)->value('physical_stock'));

        // 2. Lookup original sale via endpoint
        $lookupResp = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->getJson(route('pos.lookup_sale', ['term' => substr($origSale->id, 0, 8)]));

        $lookupResp->assertOk();
        $lookupResp->assertJson(['success' => true]);
        $items = $lookupResp->json('sale.items');
        $this->assertCount(2, $items);
        $itemA = collect($items)->firstWhere('productId', $this->product->id);
        $itemB = collect($items)->firstWhere('productId', $productB->id);
        $this->assertEquals(3, $itemA['eligibleQty']);
        $this->assertEquals(2, $itemB['eligibleQty']);
        $this->assertEquals(10000, $itemA['unitPrice']);
        $this->assertEquals(20000, $itemB['unitPrice']);

        // 3. Customer returns 1 of Product A (₦10,000) + 1 of Product B (₦20,000) = ₦30,000 credit
        // Buys 1 of Product C (₦50,000) -> Top-up due = ₦20,000 paid via POS.
        // Uses physical receipt reference instead of phone number for walk-in identification.
        $checkoutResp = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('pos.checkout'), [
                'warehouse_id' => $this->warehouse->id,
                'is_supplied' => 'yes',
                'customerName' => 'Walk-in Customer',
                'receipt_ref' => '#' . substr($origSale->id, 0, 8),
                'items' => [
                    ['productId' => $productC->id, 'quantity' => 1, 'unitPrice' => 50000],
                ],
                'posAmount' => 20000,
                'cashAmount' => 0,
                'paidAmount' => 20000,
                'exchange_returns' => [
                    [
                        'saleId' => $origSale->id,
                        'productId' => $this->product->id,
                        'quantity' => 1,
                    ],
                    [
                        'saleId' => $origSale->id,
                        'productId' => $productB->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $checkoutResp->assertOk();
        $checkoutResp->assertJson(['success' => true]);
        $newSaleId = $checkoutResp->json('saleId');
        $newSale = Sale::find($newSaleId);

        // 4. Verify financial accounting on new sale
        $this->assertEquals(50000, $newSale->totalAmount);
        $this->assertEquals(50000, $newSale->paidAmount);
        $this->assertEquals('COMPLETED', $newSale->status);

        // Payments table: ₦20,000 POS and ₦30,000 EXCHANGE_CREDIT
        $this->assertDatabaseHas('payments', [
            'saleId' => $newSaleId,
            'amount' => 20000,
            'method' => 'POS',
        ]);
        $this->assertDatabaseHas('payments', [
            'saleId' => $newSaleId,
            'amount' => 30000,
            'method' => 'EXCHANGE_CREDIT',
        ]);

        // 5. Verify physical stock restock
        // Product A was 7 -> restocked 1 -> now 8
        // Product B was 8 -> restocked 1 -> now 9
        // Product C was 5 -> sold 1 -> now 4
        $this->assertEquals(8, StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->value('physical_stock'));
        $this->assertEquals(9, StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $productB->id)->value('physical_stock'));
        $this->assertEquals(4, StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $productC->id)->value('physical_stock'));

        // 6. Verify double-return prevention on original sale
        $afterLookup = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->getJson(route('pos.lookup_sale', ['term' => substr($origSale->id, 0, 8)]));

        $afterItems = $afterLookup->json('sale.items');
        $afterItemA = collect($afterItems)->firstWhere('productId', $this->product->id);
        $afterItemB = collect($afterItems)->firstWhere('productId', $productB->id);
        // Product A eligible should now be 2
        $this->assertEquals(2, $afterItemA['eligibleQty']);
        $this->assertEquals(1, $afterItemA['alreadyReturnedQty']);
        // Product B eligible should now be 1
        $this->assertEquals(1, $afterItemB['eligibleQty']);
        $this->assertEquals(1, $afterItemB['alreadyReturnedQty']);

        // 7. Verify fraud rejection if cashier tries to return 2 of Product B (only 1 eligible remains)
        $fraudResp = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('pos.checkout'), [
                'warehouse_id' => $this->warehouse->id,
                'is_supplied' => 'yes',
                'customerName' => 'Walk-in Customer',
                'receipt_ref' => '#' . substr($origSale->id, 0, 8),
                'items' => [
                    ['productId' => $productC->id, 'quantity' => 1, 'unitPrice' => 50000],
                ],
                'posAmount' => 10000,
                'cashAmount' => 0,
                'exchange_returns' => [
                    [
                        'saleId' => $origSale->id,
                        'productId' => $productB->id,
                        'quantity' => 2, // Exceeds remaining 1!
                    ],
                ],
            ]);

        $fraudResp->assertStatus(422);
        $fraudResp->assertJsonFragment(['success' => false]);
    }

    /**
     * Test Option A: Physical Receipt Slip is the primary mandatory identifier for Not Supplied and Debt,
     * while Phone Number is completely optional.
     */
    public function test_option_a_physical_receipt_primary_and_phone_optional_validation(): void
    {
        $tenantId = $this->tenant->id;
        $user = $this->admin;
        $warehouse = $this->warehouse;
        $product = $this->product;

        // Ensure stock is available
        StockLevel::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->update(['physical_stock' => 50, 'allocated_stock' => 0]);

        // 1. BLOCKED: Walk-in Not Supplied without Physical Receipt Ref and without Phone
        $blockedPickup = $this->actingAs($user)
            ->postJson(route('pos.checkout'), [
                'warehouse_id' => $warehouse->id,
                'is_supplied' => 'no',
                'customerName' => 'Walk-in Customer',
                'customerPhone' => '',
                'receipt_ref' => '',
                'posAmount' => 70000,
                'items' => [
                    ['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 70000],
                ],
            ]);
        $blockedPickup->assertStatus(422);
        $this->assertStringContainsString('Physical Receipt Number required', $blockedPickup->json('error'));

        // 2. SUCCESS: Walk-in Not Supplied with ONLY Physical Receipt Slip #4082 (NO phone number)
        $successPickup = $this->actingAs($user)
            ->postJson(route('pos.checkout'), [
                'warehouse_id' => $warehouse->id,
                'is_supplied' => 'no',
                'customerName' => 'Walk-in Customer',
                'customerPhone' => '',
                'receipt_ref' => 'SLIP-4082',
                'posAmount' => 70000,
                'items' => [
                    ['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 70000],
                ],
            ]);
        $successPickup->assertStatus(200);
        $pickupSaleId = $successPickup->json('saleId');
        $pickupSale = Sale::findOrFail($pickupSaleId);
        $this->assertEquals('Customer (Receipt #SLIP-4082)', $pickupSale->customerName);
        $this->assertStringContainsString('[RECEIPT REF: #SLIP-4082]', $pickupSale->note);
        $this->assertEquals('UNSUPPLIED', $pickupSale->deliveryStatus);

        // Verify lookupSale can find this sale by the physical receipt slip number!
        $lookup = $this->actingAs($user)->getJson(route('pos.lookup_sale', ['term' => 'SLIP-4082']));
        $lookup->assertStatus(200);
        $this->assertEquals($pickupSaleId, $lookup->json('sale.id'));

        // 3. BLOCKED: Walk-in Debt without Physical Receipt Ref and without Phone
        $blockedDebt = $this->actingAs($user)
            ->postJson(route('pos.checkout'), [
                'warehouse_id' => $warehouse->id,
                'is_supplied' => 'yes',
                'customerName' => 'Walk-in Customer',
                'customerPhone' => '',
                'receipt_ref' => '',
                'posAmount' => 20000, // Partial payment (₦50,000 debt)
                'items' => [
                    ['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 70000],
                ],
            ]);
        $blockedDebt->assertStatus(422);
        $this->assertStringContainsString('Physical Receipt Number required', $blockedDebt->json('error'));

        // 4. SUCCESS: Walk-in Debt with ONLY Physical Receipt Slip #9901 (NO phone number)
        $successDebt = $this->actingAs($user)
            ->postJson(route('pos.checkout'), [
                'warehouse_id' => $warehouse->id,
                'is_supplied' => 'yes',
                'customerName' => 'Walk-in Customer',
                'customerPhone' => '',
                'receipt_ref' => 'SLIP-9901',
                'posAmount' => 20000,
                'items' => [
                    ['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 70000],
                ],
            ]);
        $successDebt->assertStatus(200);
        $debtSaleId = $successDebt->json('saleId');
        $debtSale = Sale::findOrFail($debtSaleId);
        $this->assertEquals('Customer (Receipt #SLIP-9901)', $debtSale->customerName);
        $this->assertStringContainsString('[RECEIPT REF: #SLIP-9901]', $debtSale->note);
    }

    /**
     * Test that Admin can delete/void transactions and archive/delete catalog products.
     */
    public function test_admin_can_delete_and_void_transactions_and_products(): void
    {
        $user = $this->admin;
        $warehouse = $this->warehouse;
        $product = $this->product;

        // 1. Admin can void/delete a sale
        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $warehouse->id,
            'customerName' => 'Test Void Customer',
            'totalAmount' => 70000,
            'paidAmount' => 70000,
            'cashAmount' => 70000,
            'posAmount' => 0,
            'transferAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $user->id,
            'userName' => $user->name,
        ]);

        \App\Models\SaleItem::create([
            'saleId' => $sale->id,
            'productId' => $product->id,
            'productName' => $product->name,
            'quantity' => 1,
            'unitPrice' => 70000,
            'totalPrice' => 70000,
        ]);

        $voidResp = $this->actingAs($user)
            ->postJson(route('transactions.void.sale', $sale->id), [
                'reason' => 'Duplicate mistake entry by cashier',
            ]);
        $voidResp->assertStatus(200);
        $this->assertTrue($voidResp->json('success'));
        $this->assertNull(Sale::find($sale->id), 'Sale record must be deleted after voiding.');

        // 2. Admin can delete / archive a catalog product
        $delProdResp = $this->actingAs($user)
            ->post(route('products.destroy', $product->id));
        $delProdResp->assertRedirect(route('products.index'));
        $this->assertTrue((bool) Product::find($product->id)->archived, 'Product must be marked as archived.');
    }
}


