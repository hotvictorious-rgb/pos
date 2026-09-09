<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferPhysicalStockEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected User $admin;
    protected User $storekeeperA;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@vmarketplatform.ng',
        ]);

        $this->tenant = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-transfer-test',
            'name' => 'Transfer Test Corp',
            'owner_email' => 'owner@transfertest.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        $this->warehouseA = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Shop Alpha',
            'code' => 'WH-A',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Shop Beta',
            'code' => 'WH-B',
            'is_active' => true,
        ]);

        $this->admin = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Boss',
            'email' => 'admin@transfertest.ng',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'disabled' => false,
            'warehouse_id' => null,
            'permissions' => ['all' => true],
        ]);

        $this->storekeeperA = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Keeper Alpha',
            'email' => 'keeper@transfertest.ng',
            'password' => Hash::make('Secret123!'),
            'role' => 'storekeeper',
            'disabled' => false,
            'warehouse_id' => $this->warehouseA->id,
            'permissions' => ['stock.view', 'stock.transfer', 'stock.receive', 'stock.in'],
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-TEST-5',
            'name' => 'High Demand Widget',
            'category' => 'General',
            'unitPrice' => 5000,
            'currentStock' => 5,
            'minStockLevel' => 2,
            'archived' => false,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 5,
            'allocated_stock' => 0,
            'min_stock_alert' => 2,
        ]);
    }

    /**
     * Test 1: Reject transfer when requested quantity exceeds available physical stock.
     */
    public function test_transfer_rejected_when_qty_exceeds_physical_stock()
    {
        $response = $this->actingAs($this->storekeeperA)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('stock.transfer.out'), [
                'source_warehouse_id' => $this->warehouseA->id,
                'destination_warehouse_id' => $this->warehouseB->id,
                'items' => [
                    ['productId' => $this->product->id, 'quantity' => 10]
                ],
                'carrier_name' => 'Speed Delivery Van',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('5 physical unit(s) available', $response->json('error'));
        $this->assertStringContainsString('10 unit(s) were requested', $response->json('error'));

        // Ensure stock was NOT deducted
        $stock = StockLevel::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(5, $stock->physical_stock);
    }

    /**
     * Test 2: Reject transfer when origin shop has 0 physical units.
     */
    public function test_transfer_rejected_when_origin_shop_has_zero_physical_stock()
    {
        $zeroProduct = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-ZERO-0',
            'name' => 'Empty Widget',
            'category' => 'General',
            'unitPrice' => 2000,
            'currentStock' => 0,
            'minStockLevel' => 1,
            'archived' => false,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $zeroProduct->id,
            'warehouse_id' => $this->warehouseA->id,
            'physical_stock' => 0,
            'allocated_stock' => 0,
            'min_stock_alert' => 1,
        ]);

        $response = $this->actingAs($this->storekeeperA)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('stock.transfer.out'), [
                'source_warehouse_id' => $this->warehouseA->id,
                'destination_warehouse_id' => $this->warehouseB->id,
                'items' => [
                    ['productId' => $zeroProduct->id, 'quantity' => 1]
                ],
                'carrier_name' => 'Speed Delivery Van',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('0 physical unit(s) available in this shop', $response->json('error'));
    }

    /**
     * Test 3: Allow transfer when requested quantity is within available physical stock.
     */
    public function test_transfer_allowed_when_within_physical_stock()
    {
        $response = $this->actingAs($this->storekeeperA)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('stock.transfer.out'), [
                'source_warehouse_id' => $this->warehouseA->id,
                'destination_warehouse_id' => $this->warehouseB->id,
                'items' => [
                    ['productId' => $this->product->id, 'quantity' => 3]
                ],
                'carrier_name' => 'Speed Delivery Van',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Physical stock at source deducted from 5 to 2
        $stock = StockLevel::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(2, $stock->physical_stock);
    }

    /**
     * Test 4: Verify stock.index and stock.transfers provide physical stock maps to frontend.
     */
    public function test_views_receive_stock_maps_for_frontend_validation()
    {
        // 1. Stock index view has shopStockMap
        $responseIndex = $this->actingAs($this->storekeeperA)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('stock.index'));

        $responseIndex->assertStatus(200);
        $responseIndex->assertViewHas('shopStockMap');
        $shopMap = $responseIndex->viewData('shopStockMap');
        $this->assertArrayHasKey($this->product->id, $shopMap);
        $this->assertEquals(5, $shopMap[$this->product->id]);

        // 2. Transfers view has warehouseStockMap
        $responseTransfers = $this->actingAs($this->storekeeperA)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('stock.transfers'));

        $responseTransfers->assertStatus(200);
        $responseTransfers->assertViewHas('warehouseStockMap');
        $whMap = $responseTransfers->viewData('warehouseStockMap');
        $this->assertArrayHasKey($this->warehouseA->id, $whMap);
        $this->assertEquals(5, $whMap[$this->warehouseA->id][$this->product->id]);
    }
}
