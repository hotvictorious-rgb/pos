<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MultiBranchSelectionAndScopingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $branchA1;
    protected Warehouse $branchA2;
    protected Warehouse $branchB1;
    protected User $adminA;
    protected User $cashierA1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['saas.enabled' => true]);

        // Tenant A with 2 branches
        $this->tenantA = Tenant::create([
            'id' => 'tenant-alpha-' . Str::random(5),
            'name' => 'Alpha Supermarket',
            'owner_email' => 'owner@alpha.com',
            'status' => 'active',
        ]);

        $this->branchA1 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Main Branch',
            'code' => 'ALPHA-MAIN',
            'is_active' => true,
        ]);

        $this->branchA2 = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Nwaniba Branch',
            'code' => 'ALPHA-NWANIBA',
            'is_active' => true,
        ]);

        // Tenant B with 1 branch
        $this->tenantB = Tenant::create([
            'id' => 'tenant-beta-' . Str::random(5),
            'name' => 'Beta Electronics',
            'owner_email' => 'owner@beta.com',
            'status' => 'active',
        ]);

        $this->branchB1 = Warehouse::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Head Office',
            'code' => 'BETA-HQ',
            'is_active' => true,
        ]);

        // Admin of Tenant A (Multi-Branch Access)
        $this->adminA = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha General Manager',
            'email' => 'gm@alpha.com',
            'password' => Hash::make('Secret123#'),
            'role' => 'admin',
            'warehouse_id' => null,
            'disabled' => false,
        ]);

        // Cashier locked to Branch A1
        $this->cashierA1 = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cashier Rose',
            'email' => 'rose@alpha.com',
            'password' => Hash::make('Secret123#'),
            'role' => 'cashier',
            'warehouse_id' => $this->branchA1->id,
            'disabled' => false,
        ]);
    }

    public function test_admin_can_switch_active_branch_to_specific_branch(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
                'active_warehouse_id' => $this->branchA1->id,
            ])
            ->post(route('branch.switch'), [
                'warehouse_id' => $this->branchA2->id,
            ]);

        $response->assertSessionHas('active_warehouse_id', $this->branchA2->id);
        $response->assertSessionHas('success');
    }

    public function test_admin_can_switch_to_all_branches_consolidated_view(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
                'active_warehouse_id' => $this->branchA1->id,
            ])
            ->post(route('branch.switch'), [
                'warehouse_id' => 'ALL',
            ]);

        $response->assertSessionMissing('active_warehouse_id');
        $response->assertSessionHas('success');
    }

    public function test_branch_scoped_cashier_cannot_switch_away_from_assigned_branch(): void
    {
        $response = $this->actingAs($this->cashierA1)
            ->withSession([
                'user_id' => $this->cashierA1->id,
                'tenant_id' => $this->tenantA->id,
                'warehouse_id' => $this->branchA1->id,
                'active_warehouse_id' => $this->branchA1->id,
            ])
            ->post(route('branch.switch'), [
                'warehouse_id' => $this->branchA2->id,
            ]);

        // Must be rejected with error and remain clamped to branchA1
        $response->assertSessionHas('error');
        $this->assertEquals($this->branchA1->id, session('active_warehouse_id'));
    }

    public function test_admin_cannot_switch_to_branch_belonging_to_another_tenant(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
            ])
            ->post(route('branch.switch'), [
                'warehouse_id' => $this->branchB1->id,
            ]);

        $response->assertSessionHas('error');
        $this->assertNotEquals($this->branchB1->id, session('active_warehouse_id'));
    }

    public function test_pos_resolves_and_binds_to_selected_branch_with_live_inventory(): void
    {
        // Create product with different stock at Branch A1 vs Branch A2
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Castrol GTX 20W-50 4L',
            'code' => 'CASTROL-20W50',
            'unitPrice' => 15000,
            'category' => 'LUBRICANTS',
            'archived' => false,
        ]);


        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->branchA1->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->branchA2->id,
            'physical_stock' => 12,
            'allocated_stock' => 0,
        ]);

        // When Admin visits POS with Branch A2 in session
        $response = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
                'active_warehouse_id' => $this->branchA2->id,
            ])
            ->get(route('pos.index'));

        $response->assertStatus(200);
        $response->assertSee('Selling from:');
        $response->assertSee('Alpha Nwaniba Branch');
        $response->assertSee('Select Selling Branch');
    }

    public function test_pos_safely_defaults_to_valid_branch_if_session_was_all_or_empty(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
                'active_warehouse_id' => null, // e.g. was on Consolidated overview
            ])
            ->get(route('pos.index'));

        $response->assertStatus(200);
        // Should safely bind to the first valid branch
        $this->assertNotNull(session('active_warehouse_id'));
        $this->assertContains(session('active_warehouse_id'), [$this->branchA1->id, $this->branchA2->id]);
    }

    public function test_pdf_export_strictly_enforces_tenant_and_branch_isolation(): void
    {
        // 1. Create a product in Tenant A
        $prodA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Special Mattress',
            'code' => 'ASM-01',
            'category' => 'Mattresses',
            'unitPrice' => 50000,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $prodA->id,
            'warehouse_id' => $this->branchA1->id,
            'physical_stock' => 15,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $prodA->id,
            'warehouse_id' => $this->branchA2->id,
            'physical_stock' => 25,
        ]);

        // 2. Create a product in Tenant B
        $prodB = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Secret Gadget',
            'code' => 'BSG-99',
            'category' => 'Gadgets',
            'unitPrice' => 100000,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenantB->id,
            'product_id' => $prodB->id,
            'warehouse_id' => $this->branchB1->id,
            'physical_stock' => 50,
        ]);

        $this->adminA->update(['role' => 'admin']);
        $this->cashierA1->update(['role' => 'branch_manager', 'warehouse_id' => $this->branchA1->id]);

        // Tenant A Admin: Exports Consolidated PDF
        $responseAdmin = $this->actingAs($this->adminA)
            ->withSession([
                'user_id' => $this->adminA->id,
                'tenant_id' => $this->tenantA->id,
            ])
            ->get(route('reports.export.pdf', ['type' => 'inventory']));

        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('ALPHA MAIN BRANCH');
        $responseAdmin->assertSee('ALPHA NWANIBA BRANCH');
        $responseAdmin->assertSee('Alpha Special Mattress');
        // Tenant A must NEVER see Tenant B's product or warehouse!
        $responseAdmin->assertDontSee('Beta Secret Gadget');
        $responseAdmin->assertDontSee('BETA HEAD OFFICE');

        // Tenant A Branch Manager (Branch Scoped to Branch A1) tries to export with warehouse_id=ALL
        $responseCashier = $this->actingAs($this->cashierA1)
            ->withSession([
                'user_id' => $this->cashierA1->id,
                'tenant_id' => $this->tenantA->id,
                'warehouse_id' => $this->branchA1->id,
            ])
            ->get(route('reports.export.pdf', ['type' => 'inventory', 'warehouse_id' => 'ALL']));

        $responseCashier->assertStatus(200);
        $responseCashier->assertSee('ALPHA MAIN BRANCH');
        // Branch-scoped user must be clamped to branchA1 only - cannot see Branch A2 column or Tenant B!
        $responseCashier->assertDontSee('ALPHA NWANIBA BRANCH');
        $responseCashier->assertDontSee('Alpha Nwaniba Branch');
        $responseCashier->assertDontSee('Beta Secret Gadget');
    }
}
