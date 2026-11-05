<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InventoryLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\Tenant;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BranchIsolationDeepAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $branch1;
    protected Warehouse $branch2;
    protected User $admin;
    protected User $cashier1;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => false]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-vmarket',
            'name' => 'Victorious Market Enterprise',
            'owner_email' => 'owner@victoriousmarket.com',
            'status' => 'active',
        ]);

        $this->branch1 = Warehouse::create([
            'id' => 1,
            'tenant_id' => $this->tenant->id,
            'name' => 'Shop 1 - Main Market',
            'code' => 'SHOP-01',
            'is_active' => true,
        ]);

        $this->branch2 = Warehouse::create([
            'id' => 2,
            'tenant_id' => $this->tenant->id,
            'name' => 'Shop 2 - Plaza Annex',
            'code' => 'SHOP-02',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Executive Director',
            'email' => 'director@victoriousmarket.com',
            'password' => Hash::make('Director2026#'),
            'role' => 'admin',
            'warehouse_id' => null,
            'disabled' => false,
        ]);

        $this->cashier1 = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Shop 1 Cashier',
            'email' => 'cashier1@victoriousmarket.com',
            'password' => Hash::make('Cashier2026#'),
            'role' => 'cashier',
            'warehouse_id' => $this->branch1->id,
            'disabled' => false,
        ]);

        $this->productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Peak Milk Powder 400g',
            'code' => 'PEAK-400',
            'category' => 'Provisions',
            'unitPrice' => 3500.00,
            'currentStock' => 50,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        $this->productB = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Golden Penny Sugar 500g',
            'code' => 'SUGAR-500',
            'category' => 'Groceries',
            'unitPrice' => 1200.00,
            'currentStock' => 80,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch1->id,
            'product_id' => $this->productA->id,
            'physical_stock' => 30,
            'allocated_stock' => 5,
            'min_stock_alert' => 5,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch2->id,
            'product_id' => $this->productA->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch1->id,
            'product_id' => $this->productB->id,
            'physical_stock' => 0,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch2->id,
            'product_id' => $this->productB->id,
            'physical_stock' => 80,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);
    }

    public function test_branch_switch_route_handles_post_and_get_cleanly(): void
    {
        // 1. POST switch to Branch 1
        $resPost = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post('/branch/switch', ['warehouse_id' => $this->branch1->id]);
        $resPost->assertStatus(302);
        $resPost->assertSessionHas('active_warehouse_id', $this->branch1->id);

        // 2. GET switch gracefully redirects without 405 error
        $resGet = $this->actingAs($this->admin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get('/branch/switch');
        $resGet->assertStatus(302);
        $resGet->assertRedirect(route('dashboard'));
    }

    public function test_pending_orders_strictly_respect_branch_isolation(): void
    {
        // Sale 1: Pending order in Branch 1
        $sale1 = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch1->id,
            'userId' => $this->admin->id,
            'userName' => 'Admin Officer',
            'customerName' => 'Alhaji Gambo (Branch 1 Order)',
            'totalAmount' => 17500.00,
            'paidAmount' => 17500.00,
            'cashAmount' => 17500.00,
            'posAmount' => 0.0,
            'transferAmount' => 0.0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'UNSUPPLIED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Sale 2: Pending order in Branch 2
        $sale2 = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch2->id,
            'userId' => $this->admin->id,
            'userName' => 'Admin Officer',
            'customerName' => 'Chief Obinna (Branch 2 Order)',
            'totalAmount' => 24000.00,
            'paidAmount' => 24000.00,
            'cashAmount' => 24000.00,
            'posAmount' => 0.0,
            'transferAmount' => 0.0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'UNSUPPLIED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // When switched to Branch 1 -> Only Sale 1 is returned
        $resBranch1 = $this->actingAs($this->admin)
            ->withSession([
                'tenant_id' => $this->tenant->id,
                'active_warehouse_id' => $this->branch1->id,
            ])
            ->get('/stock/unsupplied');

        $resBranch1->assertStatus(200);
        $resBranch1->assertSee('Alhaji Gambo (Branch 1 Order)');
        $resBranch1->assertDontSee('Chief Obinna (Branch 2 Order)');

        // When switched to Branch 2 -> Only Sale 2 is returned
        $resBranch2 = $this->actingAs($this->admin)
            ->withSession([
                'tenant_id' => $this->tenant->id,
                'active_warehouse_id' => $this->branch2->id,
            ])
            ->get('/stock/unsupplied');

        $resBranch2->assertStatus(200);
        $resBranch2->assertSee('Chief Obinna (Branch 2 Order)');
        $resBranch2->assertDontSee('Alhaji Gambo (Branch 1 Order)');

        // When switched to ALL -> Both are visible
        $resAll = $this->actingAs($this->admin)
            ->withSession([
                'tenant_id' => $this->tenant->id,
                'active_warehouse_id' => null,
            ])
            ->get('/stock/unsupplied');

        $resAll->assertStatus(200);
        $resAll->assertSee('Alhaji Gambo (Branch 1 Order)');
        $resAll->assertSee('Chief Obinna (Branch 2 Order)');
    }

    public function test_reports_and_daybook_strictly_isolate_by_active_branch(): void
    {
        // Branch 1 Sale: ₦35,000
        Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch1->id,
            'userId' => $this->admin->id,
            'userName' => 'Admin Officer',
            'totalAmount' => 35000.00,
            'paidAmount' => 35000.00,
            'cashAmount' => 35000.00,
            'posAmount' => 0.0,
            'transferAmount' => 0.0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Branch 2 Sale: ₦12,000
        Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch2->id,
            'userId' => $this->admin->id,
            'userName' => 'Admin Officer',
            'totalAmount' => 12000.00,
            'paidAmount' => 12000.00,
            'cashAmount' => 12000.00,
            'posAmount' => 0.0,
            'transferAmount' => 0.0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Request Reports while Branch 1 is active
        $resRep1 = $this->actingAs($this->admin)
            ->withSession([
                'tenant_id' => $this->tenant->id,
                'active_warehouse_id' => $this->branch1->id,
            ])
            ->get('/reports');

        $resRep1->assertStatus(200);
        $repData1 = $resRep1->viewData('periodSummary');
        $this->assertEquals(35000.00, $repData1['grossSales']);

        // Request Reports while Branch 2 is active
        $resRep2 = $this->actingAs($this->admin)
            ->withSession([
                'tenant_id' => $this->tenant->id,
                'active_warehouse_id' => $this->branch2->id,
            ])
            ->get('/reports');

        $resRep2->assertStatus(200);
        $repData2 = $resRep2->viewData('periodSummary');
        $this->assertEquals(12000.00, $repData2['grossSales']);
    }

    public function test_shop_transfers_and_stock_adjustments_respect_active_branch(): void
    {
        // Transfer from Branch 1 to Branch 2
        $transfer1 = Transfer::create([
            'transfer_no' => 'TRF-TEST-001',
            'source_warehouse_id' => $this->branch1->id,
            'destination_warehouse_id' => $this->branch2->id,
            'status' => 'DISPATCHED',
            'carrier_name' => 'Speedy Logistics',
            'dispatched_by' => 'Storekeeper 1',
        ]);

        // Adjustment in Branch 2 only
        StockAdjustment::create([
            'warehouse_id' => $this->branch2->id,
            'product_id' => $this->productB->id,
            'product_name' => $this->productB->name,
            'product_code' => $this->productB->code,
            'type' => 'DAMAGE',
            'quantity' => 2,
            'unit_cost' => 1200.00,
            'total_cost' => 2400.00,
            'reason' => 'Rat damaged packaging at Shop 2',
            'recorded_by' => 'Auditor Paul',
        ]);

        // Branch 1 Admin visits adjustments -> does NOT see Branch 2's rat damage
        $resAdj1 = $this->actingAs($this->admin)
            ->withSession([
                'tenant_id' => $this->tenant->id,
                'active_warehouse_id' => $this->branch1->id,
            ])
            ->get('/stock/adjustments');

        $resAdj1->assertStatus(200);
        $resAdj1->assertDontSee('Rat damaged packaging at Shop 2');

        // Branch 2 Admin visits adjustments -> sees Branch 2's rat damage
        $resAdj2 = $this->actingAs($this->admin)
            ->withSession([
                'tenant_id' => $this->tenant->id,
                'active_warehouse_id' => $this->branch2->id,
            ])
            ->get('/stock/adjustments');

        $resAdj2->assertStatus(200);
        $resAdj2->assertSee('Rat damaged packaging at Shop 2');
    }

    public function test_branch_scoped_cashier_cannot_switch_branch(): void
    {
        // Cashier 1 attempts to switch to Branch 2 -> Blocked with 403 / restriction
        $res = $this->actingAs($this->cashier1)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post('/branch/switch', ['warehouse_id' => $this->branch2->id]);

        $res->assertStatus(302);
        // Session active_warehouse_id remains locked to cashier's assigned branch
        $this->assertEquals($this->branch1->id, session('active_warehouse_id', $this->cashier1->warehouse_id));
    }

    public function test_pos_zen_mode_collapses_sidebar_and_provides_exit_navigation(): void
    {
        // 1. Visiting POS activates pos-zen-mode class on body and renders Exit POS button
        $resPos = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'tenant_id' => $this->tenant->id,
            ])
            ->get(route('pos.index'));

        $resPos->assertStatus(200);
        $resPos->assertSee('<body class="pos-zen-mode">', false);
        $resPos->assertSee('class="btn-pos-home"', false);
        $resPos->assertSee(route('dashboard'));

        // 2. Visiting Dashboard does NOT activate pos-zen-mode on body
        $resDash = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'tenant_id' => $this->tenant->id,
            ])
            ->get(route('dashboard'));

        $resDash->assertStatus(200);
        $resDash->assertDontSee('<body class="pos-zen-mode">', false);
        $resDash->assertDontSee('class="btn-pos-home"', false);
    }

    public function test_login_with_username_prefix_and_default_tenant_access(): void
    {
        $testUser = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch1->id,
            'name' => 'Shop Cashier User',
            'email' => 'cashieruser@victoriousmarket.com',
            'password' => bcrypt('secretpassword123'),
            'role' => 'cashier',
            'disabled' => false,
        ]);

        // 1. Logging in using the email prefix / username 'cashieruser'
        $loginRes = $this->post(route('login.post'), [
            'email' => 'cashieruser',
            'password' => 'secretpassword123',
        ]);

        $loginRes->assertRedirect(route('pos.index'));
        $loginRes->assertSessionHas('user_id', $testUser->id);

        // 2. Visiting /pos works without 403 Forbidden
        $posRes = $this->actingAs($testUser)
            ->withSession([
                'user_id' => $testUser->id,
                'tenant_id' => $this->tenant->id,
                'warehouse_id' => $this->branch1->id,
                'active_warehouse_id' => $this->branch1->id,
            ])
            ->get(route('pos.index'));

        $posRes->assertStatus(200);
    }

    public function test_branch_switch_cleans_stale_warehouse_id_from_referer(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession([
                'user_id' => $this->admin->id,
                'tenant_id' => $this->tenant->id,
                'active_warehouse_id' => $this->branch1->id,
            ])
            ->from('http://127.0.0.1:8001/dashboard?warehouse_id=' . $this->branch1->id)
            ->post(route('branch.switch'), [
                'warehouse_id' => $this->branch2->id,
            ]);

        $response->assertSessionHas('active_warehouse_id', $this->branch2->id);
        // Clean URL does not have stale ?warehouse_id=1
        $response->assertRedirect('http://127.0.0.1:8001/dashboard');
    }
}
