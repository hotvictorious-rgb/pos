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
     * ADVERSARIAL TEST 1: Cannot reserve more than available physical stock
     */
    public function test_cannot_reserve_more_than_available_stock()
    {
        $this->expectException(InsufficientStockException::class);

        // Physical = 5, Attempt to buy 6 unsupplied
        $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 360000,
                'paidAmount' => 360000,
                'customerName' => 'Greedy Buyer',
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
    }

    /**
     * ADVERSARIAL TEST 2: Two sequential reservations cannot exceed stock
     */
    public function test_two_sequential_reservations_cannot_exceed_stock()
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

        // Invariant after Sale 1: Physical = 5, Allocated = 3, Available = 2
        $this->assertEquals(5, $stock->physical_stock);
        $this->assertEquals(3, $stock->allocated_stock);
        $this->assertEquals(2, $stock->available_stock);

        // Reservation 2: Customer B attempts to reserve 3 units (only 2 available!)
        $this->expectException(InsufficientStockException::class);

        $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 180000,
                'paidAmount' => 180000,
                'customerName' => 'Customer B',
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

        // Re-query stock to verify state remained unchanged
        $stockAfter = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(5, $stockAfter->physical_stock);
        $this->assertEquals(3, $stockAfter->allocated_stock);
        $this->assertEquals(2, $stockAfter->available_stock);
    }

    /**
     * ADVERSARIAL TEST 3: Concurrent reservation attempts cannot collectively exceed stock
     */
    public function test_concurrent_reservation_attempts_under_lock_cannot_exceed_stock()
    {
        $attempts = 0;
        $successes = 0;
        $failures = 0;

        // Two transactions each trying to reserve 4 units when total physical stock is 5
        for ($i = 1; $i <= 2; $i++) {
            $attempts++;
            try {
                $this->stockService->recordSale(
                    [
                        'id' => (string) Str::uuid(),
                        'totalAmount' => 240000,
                        'paidAmount' => 240000,
                        'customerName' => "Concurrent Buyer {$i}",
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
                $successes++;
            } catch (InsufficientStockException $e) {
                $failures++;
            }
        }

        $this->assertEquals(2, $attempts);
        $this->assertEquals(1, $successes, "Only the first reservation should succeed.");
        $this->assertEquals(1, $failures, "Second reservation must be rejected for exceeding available stock.");

        $stock = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(5, $stock->physical_stock);
        $this->assertEquals(4, $stock->allocated_stock);
        $this->assertEquals(1, $stock->available_stock);
        $this->assertLessThanOrEqual($stock->physical_stock, $stock->allocated_stock);
    }

    /**
     * ADVERSARIAL TEST 4: Failed multi-item reservation must roll back completely
     */
    public function test_failed_reservation_must_roll_back_completely()
    {
        $product2 = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Royal Stallion Rice 50kg',
            'code' => 'ROYAL-RICE-50KG',
            'category' => 'Commodities',
            'unitPrice' => 75000,
            'costPrice' => 65000,
            'currentStock' => 1,
            'initial_stock' => 1,
            'warehouse_id' => $this->warehouse->id,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::updateOrCreate(
            ['product_id' => $product2->id, 'warehouse_id' => $this->warehouse->id],
            [
                'tenant_id' => $this->tenant->id,
                'physical_stock' => 1,
                'allocated_stock' => 0,
            ]
        );

        $initialSalesCount = Sale::count();
        $initialLogsCount = InventoryLog::count();

        try {
            // Basket: Product 1 has enough (requesting 2 out of 5), Product 2 does NOT (requesting 3 out of 1)
            $this->stockService->recordSale(
                [
                    'id' => (string) Str::uuid(),
                    'totalAmount' => 345000,
                    'paidAmount' => 345000,
                    'customerName' => 'Multi-item Buyer',
                ],
                [
                    [
                        'productId' => $this->product->id,
                        'quantity' => 2,
                    ],
                    [
                        'productId' => $product2->id,
                        'quantity' => 3, // EXCEEDS product2 available stock!
                    ]
                ],
                $this->warehouse->id,
                false, // UN-SUPPLIED
                $this->user->id,
                $this->user->name
            );
            $this->fail("Expected InsufficientStockException was not thrown.");
        } catch (InsufficientStockException $e) {
            // Expected
        }

        // Verify total rollback: Product 1's allocated stock was NOT modified!
        $stock1 = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(0, $stock1->allocated_stock, "Product 1 allocated stock must remain 0 after rollback.");
        $this->assertEquals(5, $stock1->physical_stock);

        $stock2 = StockLevel::where('product_id', $product2->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(0, $stock2->allocated_stock);
        $this->assertEquals(1, $stock2->physical_stock);

        // No orphaned sale or inventory log records created
        $this->assertEquals($initialSalesCount, Sale::count());
        $this->assertEquals($initialLogsCount, InventoryLog::count());
    }

    /**
     * ADVERSARIAL TEST 5: Supplied walk-in sale cannot take stock reserved for unsupplied customers
     */
    public function test_supplied_sale_cannot_take_stock_reserved_for_unsupplied_customers()
    {
        // Reserve 4 units unsupplied
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
        // Physical is 5, but 4 is reserved, so only 1 is available!
        $this->expectException(InsufficientStockException::class);

        $this->stockService->recordSale(
            [
                'id' => (string) Str::uuid(),
                'totalAmount' => 180000,
                'paidAmount' => 180000,
                'customerName' => 'Walk-in Buyer',
            ],
            [
                [
                    'productId' => $this->product->id,
                    'quantity' => 3, // 3 > 1 available!
                ]
            ],
            $this->warehouse->id,
            true, // SUPPLIED NOW
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
