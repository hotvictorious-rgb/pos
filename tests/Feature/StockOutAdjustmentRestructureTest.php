<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockOutAdjustmentRestructureTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $storekeeper;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@vmarketplatform.ng',
        ]);

        $this->tenant = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-stock-out-test',
            'name' => 'Stock Out Test Enterprise',
            'owner_email' => 'owner@stockout.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Victoria Island Store',
            'code' => 'VIS-01',
            'is_active' => true,
        ]);

        $this->storekeeper = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Keeper Emeka',
            'email' => 'emeka@stockout.ng',
            'password' => Hash::make('Secret123!'),
            'role' => 'storekeeper',
            'disabled' => false,
            'warehouse_id' => $this->warehouse->id,
            'permissions' => ['stock.view', 'stock.adjust', 'stock.in', 'stock.transfer'],
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-SO-100',
            'name' => 'Multi-Purpose Cleaning Agent (1L)',
            'category' => 'Household',
            'unitPrice' => 4500,
            'currentStock' => 20,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);
    }

    /**
     * Test 1: Reason is optional - defaults cleanly to friendly type name when blank.
     */
    public function test_stock_out_with_empty_reason_defaults_to_type_title(): void
    {
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('stock.adjustments.record'), [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
                'type' => 'INTERNAL_USE',
                'quantity' => 2,
                'reason' => '', // Left completely blank
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $adj = StockAdjustment::latest('id')->first();
        $this->assertEquals('INTERNAL_USE', $adj->type);
        $this->assertEquals('Internal Store Use / Staff Consumption', $adj->reason);
        $this->assertEquals(2, $adj->quantity);

        // Check stock deducted from 20 to 18
        $stock = StockLevel::where('warehouse_id', $this->warehouse->id)->where('product_id', $this->product->id)->first();
        $this->assertEquals(18, $stock->physical_stock);
    }

    /**
     * Test 2: Custom reason is preserved when provided.
     */
    public function test_stock_out_with_custom_reason_is_preserved(): void
    {
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('stock.adjustments.record'), [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
                'type' => 'SAMPLE',
                'quantity' => 1,
                'reason' => 'Given to prospective corporate client as product demo',
            ]);

        $response->assertStatus(200);

        $adj = StockAdjustment::latest('id')->first();
        $this->assertEquals('SAMPLE', $adj->type);
        $this->assertEquals('Given to prospective corporate client as product demo', $adj->reason);
    }

    /**
     * Test 3: Rejects adjustment when requested deduction exceeds physical ground stock.
     */
    public function test_stock_out_rejected_when_qty_exceeds_physical_stock(): void
    {
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('stock.adjustments.record'), [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
                'type' => 'DAMAGE',
                'quantity' => 999, // Exceeds available stock
                'reason' => 'Carton flood damage',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('Insufficient available stock', $response->json('error'));
    }

    /**
     * Test 4: View data contracts - stock.adjustments receives warehouseStockMap for frontend live badges.
     */
    public function test_adjustments_view_receives_warehouse_stock_map(): void
    {
        $response = $this->actingAs($this->storekeeper)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('stock.adjustments'));

        $response->assertStatus(200);
        $response->assertViewHas('warehouseStockMap');
        $map = $response->viewData('warehouseStockMap');
        $this->assertArrayHasKey($this->warehouse->id, $map);
        $this->assertEquals(20, $map[$this->warehouse->id][$this->product->id]);
    }
}
