<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\InventoryLog;
use App\Services\StockService;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnsuppliedStockReservationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $stockService;
    protected Tenant $tenant;
    protected User $user;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = app(StockService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-unsupplied-test',
            'name' => 'Integrity Supermarket Ltd',
            'owner_email' => 'owner@integrity.ng',
            'plan' => 'enterprise',
            'status' => 'active',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lekki Main Branch',
            'code' => 'WH-LEKKI-UNSUPPLIED',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Cashier Ada',
            'email' => 'ada@integrity.ng',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouse->id,
            'disabled' => false,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Dangote Sugar 50kg',
            'code' => 'DANGOTE-SUGAR-50KG',
            'category' => 'Commodities',
            'unitPrice' => 60000,
            'costPrice' => 50000,
            'currentStock' => 5,
            'initial_stock' => 5,
            'warehouse_id' => $this->warehouse->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        // Physical stock = 5, Allocated = 0
        StockLevel::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id],
            [
                'tenant_id' => $this->tenant->id,
                'physical_stock' => 5,
                'allocated_stock' => 0,
            ]
        );
    }

    /**
     * TEST 1: Unsupplied sale decouples reservation, creates StockReservation, and tracks shortfall
     */
    public function test_unsupplied_sale_decouples_reservation_and_records_shortfall()
    {
        // Physical = 5, Customer buys 6 unsupplied units
        $sale = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 360000,
                'paidAmount' => 360000,
                'customerName' => 'Pending Delivery Buyer',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 6,
                ]
            ],
            $this->warehouse->id,
            false, // UN-SUPPLIED
            $this->user->id,
            $this->user->name
        );

        $stock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        // Under decoupled model: Physical is unchanged (5), Allocated is 6
        $this->assertEquals(5, $stock->physical_stock, "Physical shelf stock must remain unchanged on unsupplied sale.");
        $this->assertEquals(6, $stock->allocated_stock, "Allocated stock must track customer reservation.");
        $this->assertEquals(1, $stock->reservation_shortfall, "Shortfall must be max(0, allocated - physical) = 1.");

        // Authoritative StockReservation created
        $reservation = \App\Models\StockReservation::where('sale_id', $sale->id)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(6, $reservation->reserved_qty);
        $this->assertEquals(0, $reservation->fulfilled_qty);
        $this->assertEquals('ACTIVE', $reservation->status);
    }

    /**
     * TEST 2: Sequential reservations accumulate allocated stock cleanly
     */
    public function test_sequential_reservations_accumulate_allocated_stock()
    {
        // Reservation 1: Customer A reserves 3 units (out of 5 physical)
        $sale1 = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 180000,
                'paidAmount' => 180000,
                'customerName' => 'Customer A',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 3,
                ]
            ],
            $this->warehouse->id,
            false, // UN-SUPPLIED
            $this->user->id,
            $this->user->name
        );

        $stock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(5, $stock->physical_stock);
        $this->assertEquals(3, $stock->allocated_stock);
        $this->assertEquals(0, $stock->reservation_shortfall);

        // Reservation 2: Customer B reserves 4 units (total allocated becomes 7)
        $sale2 = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 240000,
                'paidAmount' => 240000,
                'customerName' => 'Customer B',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 4,
                ]
            ],
            $this->warehouse->id,
            false, // UN-SUPPLIED
            $this->user->id,
            $this->user->name
        );

        $stockAfter = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(5, $stockAfter->physical_stock, "Physical stock remains 5.");
        $this->assertEquals(7, $stockAfter->allocated_stock, "Total allocated must be 3 + 4 = 7.");
        $this->assertEquals(2, $stockAfter->reservation_shortfall, "Shortfall is 7 - 5 = 2.");
    }

    /**
     * TEST 3: Supplied walk-in sale can sell physical stock without being blocked by unsupplied reservations
     */
    public function test_supplied_sale_can_sell_physical_stock_without_being_blocked_by_reservations()
    {
        // Reserve 4 units unsupplied (Physical: 5, Allocated: 4)
        $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 240000,
                'paidAmount' => 240000,
                'customerName' => 'Unsupplied Reserved Buyer',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 4,
                ]
            ],
            $this->warehouse->id,
            false, // UN-SUPPLIED (reserved)
            $this->user->id,
            $this->user->name
        );

        // A walk-in buyer wants 3 units SUPPLIED NOW.
        // Physical is 5 >= 3, so sale MUST succeed! Immediate physical sales are never blocked by reservations.
        $saleSupplied = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 180000,
                'paidAmount' => 180000,
                'customerName' => 'Walk-in Buyer',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 3,
                ]
            ],
            $this->warehouse->id,
            true, // SUPPLIED NOW
            $this->user->id,
            $this->user->name
        );

        $stock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(2, $stock->physical_stock, "Physical stock decrements from 5 to 2.");
        $this->assertEquals(4, $stock->allocated_stock, "Allocated stock remains untouched at 4.");
        $this->assertEquals(2, $stock->reservation_shortfall, "Reservation shortfall is now max(0, 4 - 2) = 2.");
    }

    /**
     * TEST 4: Dispatching unsupplied sale requires physical shelf stock and decrements both physical and allocated
     */
    public function test_dispatch_unsupplied_sale_requires_physical_stock_and_decrements_both()
    {
        // Setup: Physical = 2, Allocated = 0
        $stock = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $stock->physical_stock = 2;
        $stock->allocated_stock = 0;
        $stock->save();

        // Customer reserves 4 units unsupplied
        $sale = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 240000,
                'paidAmount' => 240000,
                'customerName' => 'Future Pickup Buyer',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 4,
                ]
            ],
            $this->warehouse->id,
            false, // UN-SUPPLIED
            $this->user->id,
            $this->user->name
        );

        // Attempting to dispatch 4 units when physical stock is only 2 MUST throw InsufficientStockException!
        try {
            $this->stockService->dispatchUnsuppliedSale(
                $sale->id,
                $this->warehouse->id,
                $this->user->id,
                $this->user->name
            );
            $this->fail("Should have rejected dispatch due to physical stock deficit.");
        } catch (\App\Exceptions\InsufficientStockException $e) {
            $this->assertStringContainsString("Cannot fulfill dispatch", $e->getMessage());
        }

        // Warehouse receives fresh shipment of 10 units
        $this->stockService->recordStockIn(
            $this->product->id,
            $this->warehouse->id,
            10,
            'Supplier Shipment',
            $this->user->id,
            $this->user->name
        );

        $stock->refresh();
        $this->assertEquals(12, $stock->physical_stock); // 2 + 10 = 12
        $this->assertEquals(4, $stock->allocated_stock);

        // Now dispatch: Must succeed!
        $this->stockService->dispatchUnsuppliedSale(
            $sale->id,
            $this->warehouse->id,
            $this->user->id,
            $this->user->name
        );

        $stock->refresh();
        $this->assertEquals(8, $stock->physical_stock, "Physical stock must decrement: 12 - 4 = 8.");
        $this->assertEquals(0, $stock->allocated_stock, "Allocated stock must decrement: 4 - 4 = 0.");

        $reservation = \App\Models\StockReservation::where('sale_id', $sale->id)->first();
        $this->assertEquals('FULFILLED', $reservation->status);
        $this->assertEquals(4, $reservation->fulfilled_qty);
    }

    /**
     * TEST 5: Cannot double dispatch an already fulfilled unsupplied sale
     */
    public function test_cannot_double_dispatch_an_already_fulfilled_sale()
    {
        $stock = StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $stock->physical_stock = 20;
        $stock->allocated_stock = 0;
        $stock->save();

        $sale = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 120000,
                'paidAmount' => 120000,
                'customerName' => 'Exact Pickup Buyer',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 2,
                ]
            ],
            $this->warehouse->id,
            false,
            $this->user->id,
            $this->user->name
        );

        // First dispatch succeeds
        $this->stockService->dispatchUnsuppliedSale(
            $sale->id,
            $this->warehouse->id,
            $this->user->id,
            $this->user->name
        );

        // Second dispatch attempt must be rejected!
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Sale has already been fully delivered/dispatched.");

        $this->stockService->dispatchUnsuppliedSale(
            $sale->id,
            $this->warehouse->id,
            $this->user->id,
            $this->user->name
        );
    }

    /**
     * ADVERSARIAL TEST 6: Return of unsupplied sale releases reservation without manufacturing physical stock
     */
    public function test_return_of_unsupplied_sale_releases_allocation_without_manufacturing_physical_stock()
    {
        // Unsupplied sale of 3 units
        $sale = $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 180000,
                'paidAmount' => 180000,
                'customerName' => 'Cancelling Buyer',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 3,
                ]
            ],
            $this->warehouse->id,
            false, // UN-SUPPLIED
            $this->user->id,
            $this->user->name
        );

        $stockBeforeReturn = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(5, $stockBeforeReturn->physical_stock);
        $this->assertEquals(3, $stockBeforeReturn->allocated_stock);

        // Customer cancels before dispatch (Return unsupplied sale)
        $this->stockService->recordSaleReturn(
            $sale->id,
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 3,
                ]
            ],
            $this->warehouse->id,
            'CASH_REFUND',
            'Customer changed mind before goods pickup',
            $this->user->id,
            $this->user->name
        );

        $stockAfterReturn = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        // Critical Conservation Invariant:
        // Physical stock MUST stay 5 (NOT manufactured to 8!)
        // Allocated stock MUST drop to 0.
        // Available stock returns to 5.
        $this->assertEquals(5, $stockAfterReturn->physical_stock, "Physical stock must NOT increase on unsupplied return.");
        $this->assertEquals(0, $stockAfterReturn->allocated_stock, "Allocated stock must be released to 0.");
        $this->assertEquals(5, $stockAfterReturn->available_stock, "Available stock must return to 5.");
    }
}
