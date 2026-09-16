<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockReservation;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UnsuppliedReturnAndSearchableInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $branchA;
    protected Warehouse $branchB;
    protected User $cashierBranchA;
    protected User $cashierBranchB;
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
            'name' => 'Lagos Retail Hub',
            'slug' => 'lagos-retail-' . Str::random(5),
            'owner_email' => 'boss@lagosretail.test',
            'status' => 'active',
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->branchA = Warehouse::create([
            'name' => 'Ikeja Branch',
            'code' => 'IKJ',
            'address' => '10 Allen Ave',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->branchB = Warehouse::create([
            'name' => 'Victoria Island Branch',
            'code' => 'VI',
            'address' => '25 Adeola Odeku',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->cashierBranchA = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier Ikeja',
            'email' => 'ikeja@test.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branchA->id,
        ]);

        $this->cashierBranchB = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier VI',
            'email' => 'vi@test.com',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branchB->id,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Generator 3.5kVA',
            'code' => 'GEN-35',
            'category' => 'Power Equipment',
            'unitPrice' => 250000.0,
            'costPrice' => 200000.0,
            'currentStock' => 20,
            'tenant_id' => $this->tenant->id,
        ]);

        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->branchA->id,
            'physical_stock' => 10,
            'allocated_stock' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->branchB->id,
            'physical_stock' => 10,
            'allocated_stock' => 0,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test 1: Lookup sale is searchable across invoice ID, paper slip ref, and customer phone,
     * returning deliveryStatus and isSupplied flags.
     */
    public function test_lookup_sale_searches_by_invoice_id_slip_ref_and_phone(): void
    {
        $sale = Sale::create([
            'id' => 'INV-IKJ-1001',
            'warehouse_id' => $this->branchA->id,
            'tenant_id' => $this->tenant->id,
            'userId' => $this->cashierBranchA->id,
            'userName' => $this->cashierBranchA->name,
            'customerName' => 'Alhaji Dangote',
            'customerPhone' => '08031234567',
            'totalAmount' => 250000.0,
            'paidAmount' => 250000.0,
            'cashAmount' => 250000.0,
            'posAmount' => 0.0,
            'status' => 'PAID',
            'deliveryStatus' => 'NOT_SUPPLIED',
            'note' => 'Customer to pick up tomorrow [RECEIPT REF: #SLIP-4082]',
        ]);

        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'productCode' => $this->product->code,
            'quantity' => 1,
            'unitPrice' => 250000.0,
            'totalPrice' => 250000.0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Search by Invoice ID
        $response1 = $this->actingAs($this->cashierBranchA)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->getJson(route('pos.lookup_sale', ['term' => 'INV-IKJ-1001']));
        $response1->assertOk();
        $response1->assertJson([
            'success' => true,
            'sale' => [
                'id' => 'INV-IKJ-1001',
                'deliveryStatus' => 'NOT_SUPPLIED',
                'isSupplied' => false,
            ],
        ]);

        // Search by Paper Slip #
        $response2 = $this->actingAs($this->cashierBranchA)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->getJson(route('pos.lookup_sale', ['term' => 'SLIP-4082']));
        $response2->assertOk();
        $response2->assertJson([
            'success' => true,
            'sale' => [
                'id' => 'INV-IKJ-1001',
                'paperReceiptRef' => 'SLIP-4082',
            ],
        ]);

        // Search by Phone
        $response3 = $this->actingAs($this->cashierBranchA)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->getJson(route('pos.lookup_sale', ['term' => '08031234567']));
        $response3->assertOk();
        $response3->assertJson([
            'success' => true,
            'sale' => [
                'id' => 'INV-IKJ-1001',
            ],
        ]);
    }

    /**
     * Test 2: Verify strict branch warehouse isolation in lookupSale.
     * Cashier at Branch B cannot search or return an invoice from Branch A.
     */
    public function test_lookup_sale_enforces_strict_branch_warehouse_isolation(): void
    {
        $sale = Sale::create([
            'id' => 'INV-IKJ-SECRET',
            'warehouse_id' => $this->branchA->id,
            'tenant_id' => $this->tenant->id,
            'userId' => $this->cashierBranchA->id,
            'userName' => $this->cashierBranchA->name,
            'customerName' => 'Branch A Customer',
            'customerPhone' => '08099998888',
            'totalAmount' => 250000.0,
            'paidAmount' => 250000.0,
            'cashAmount' => 250000.0,
            'posAmount' => 0.0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'note' => '[RECEIPT REF: #SLIP-BRANCH-A]',
        ]);

        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'productCode' => $this->product->code,
            'quantity' => 1,
            'unitPrice' => 250000.0,
            'totalPrice' => 250000.0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Branch B attempts to lookup Branch A invoice by ID
        $response = $this->actingAs($this->cashierBranchB)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->getJson(route('pos.lookup_sale', ['term' => 'INV-IKJ-SECRET']));
        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
        ]);

        // Branch B attempts to lookup Branch A invoice by Paper Slip #
        $responseSlip = $this->actingAs($this->cashierBranchB)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->getJson(route('pos.lookup_sale', ['term' => 'SLIP-BRANCH-A']));
        $responseSlip->assertStatus(404);
        $responseSlip->assertJson([
            'success' => false,
        ]);
    }

    /**
     * Test 3: Supplied / Delivered Sale Return:
     * Restocks physical inventory and refunds money.
     */
    public function test_return_on_supplied_sale_restocks_physical_inventory_and_refunds_money(): void
    {
        session(['tenant_id' => $this->tenant->id]);
        $stockService = app(StockService::class);

        // Initially: physical_stock = 10, allocated = 0
        $sale = Sale::create([
            'id' => 'INV-DELIVERED-01',
            'warehouse_id' => $this->branchA->id,
            'tenant_id' => $this->tenant->id,
            'userId' => $this->cashierBranchA->id,
            'userName' => $this->cashierBranchA->name,
            'customerName' => 'Supplied Buyer',
            'totalAmount' => 250000.0,
            'paidAmount' => 250000.0,
            'cashAmount' => 250000.0,
            'posAmount' => 0.0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED', // Supplied!
        ]);

        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'productCode' => $this->product->code,
            'quantity' => 2,
            'unitPrice' => 125000.0,
            'totalPrice' => 250000.0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Simulating return of 1 unit
        $returnItems = [
            [
                'productId' => $this->product->id,
                'quantity' => 1,
                'refundAmount' => 125000.0,
                'reason' => 'Customer changed mind',
            ]
        ];

        $salesReturn = $stockService->recordSaleReturn(
            $sale->id,
            $returnItems,
            $this->branchA->id,
            'CASH_REFUND',
            'Customer returned 1 unit',
            (string) $this->cashierBranchA->id,
            $this->cashierBranchA->name
        );

        $this->assertEquals(125000.0, $salesReturn->refundAmount);
        $this->assertTrue((bool) $salesReturn->wasDelivered);

        // Check stock level: physical stock should increase from 10 to 11
        $stockLevel = StockLevel::withoutGlobalScopes()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->branchA->id)
            ->first();
        $this->assertEquals(11, $stockLevel->physical_stock);
        $this->assertEquals(0, $stockLevel->allocated_stock);
    }

    /**
     * Test 4: Unsupplied Sale Return:
     * Does NOT restock physical inventory, releases allocated reservation buffer, and refunds money.
     */
    public function test_return_on_unsupplied_sale_does_not_restock_physical_inventory_releases_reservation_and_refunds_money(): void
    {
        session(['tenant_id' => $this->tenant->id]);
        $stockService = app(StockService::class);

        // Update initial stock: physical = 10, allocated = 2
        $stockLevel = StockLevel::withoutGlobalScopes()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->branchA->id)
            ->first();
        $stockLevel->update(['allocated_stock' => 2]);

        $sale = Sale::create([
            'id' => 'INV-UNSUPPLIED-01',
            'warehouse_id' => $this->branchA->id,
            'tenant_id' => $this->tenant->id,
            'userId' => $this->cashierBranchA->id,
            'userName' => $this->cashierBranchA->name,
            'customerName' => 'Delayed Pickup Buyer',
            'totalAmount' => 250000.0,
            'paidAmount' => 250000.0,
            'cashAmount' => 250000.0,
            'posAmount' => 0.0,
            'status' => 'PAID',
            'deliveryStatus' => 'NOT_SUPPLIED', // Unsupplied!
        ]);

        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'productCode' => $this->product->code,
            'quantity' => 2,
            'unitPrice' => 125000.0,
            'totalPrice' => 250000.0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create stock reservation record for this unsupplied sale
        $reservation = StockReservation::create([
            'id' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->branchA->id,
            'reserved_qty' => 2,
            'fulfilled_qty' => 0,
            'cancelled_qty' => 0,
            'status' => 'ACTIVE',
            'tenant_id' => $this->tenant->id,
        ]);

        // Simulating return / cancellation of 2 unsupplied units
        $returnItems = [
            [
                'productId' => $this->product->id,
                'quantity' => 2,
                'refundAmount' => 250000.0,
                'reason' => 'Customer cancelled pickup order',
            ]
        ];

        $salesReturn = $stockService->recordSaleReturn(
            $sale->id,
            $returnItems,
            $this->branchA->id,
            'CASH_REFUND',
            'Cancelling unsupplied order',
            (string) $this->cashierBranchA->id,
            $this->cashierBranchA->name
        );

        // Verify result invariants
        $this->assertEquals(250000.0, $salesReturn->refundAmount, 'Full refund money must be refunded');
        $this->assertFalse((bool) $salesReturn->wasDelivered, 'Flag wasDelivered must be false (0 physical units restocked)');

        // Check stock level:
        // Physical stock MUST STAY 10 (did not leave store, so should not phantom-restock)
        // Allocated stock MUST be reduced from 2 to 0
        $stockLevel->refresh();
        $this->assertEquals(10, $stockLevel->physical_stock, 'Physical stock must remain 10 without phantom inflation');
        $this->assertEquals(0, $stockLevel->allocated_stock, 'Allocated buffer must be released down to 0');

        // Check reservation:
        $reservation->refresh();
        $this->assertEquals(2, $reservation->cancelled_qty);
        $this->assertEquals('CANCELLED', $reservation->status);
    }
}
