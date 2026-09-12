<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\InventoryLog;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\SalesReturn;
use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionsZeroReloadAndFilterConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouseA = Warehouse::create([
            'name' => 'Main Warehouse A',
            'code' => 'WHA',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Secondary Branch B',
            'code' => 'WHB',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => 'ADMIN-TEST-001',
            'name' => 'System Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseA->id,
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => 'CASHIER-TEST-001',
            'name' => 'Cashier One',
            'email' => 'cashier@test.com',
            'password' => bcrypt('secret123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Full-page load renders tab navigation buttons and all 8 pre-rendered tab panes.
     */
    public function test_full_page_load_renders_interactive_tabs_and_panes()
    {
        $response = $this->actingAs($this->admin)->get('/transactions');

        $response->assertStatus(200);
        $response->assertSee('Universal History & Ledgers Hub', false);
        $response->assertSee('onclick="switchLedgerTab(\'sales\')"', false);
        $response->assertSee('onclick="switchLedgerTab(\'stock_in\')"', false);
        $response->assertSee('onclick="switchLedgerTab(\'stock_out\')"', false);
        $response->assertSee('onclick="switchLedgerTab(\'in_transit\')"', false);
        $response->assertSee('onclick="switchLedgerTab(\'transfers_in\')"', false);
        $response->assertSee('onclick="switchLedgerTab(\'returns\')"', false);
        $response->assertSee('onclick="switchLedgerTab(\'refunds\')"', false);
        $response->assertSee('onclick="switchLedgerTab(\'debts\')"', false);

        // Pre-rendered panes exist in DOM for instant 0ms switching
        $response->assertSee('id="pane-sales"', false);
        $response->assertSee('id="pane-stock_in"', false);
        $response->assertSee('id="pane-stock_out"', false);
        $response->assertSee('id="pane-in_transit"', false);
        $response->assertSee('id="pane-transfers_in"', false);
        $response->assertSee('id="pane-returns"', false);
        $response->assertSee('id="pane-refunds"', false);
        $response->assertSee('id="pane-debts"', false);
    }

    /**
     * Test 2: AJAX request with X-Partial-Update returns JSON with badge counts and rendered HTML panes.
     */
    public function test_ajax_filter_request_returns_json_with_counts_and_panes_html()
    {
        // Seed a sample sale
        Sale::create([
            'id' => 'SALE-AJAX-001',
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->admin->id,
            'totalAmount' => 15000,
            'paidAmount' => 15000,
            'cashAmount' => 15000,
            'posAmount' => 0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Partial-Update' => 'true',
            ])
            ->get('/transactions?tab=sales&date_preset=TODAY');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'activeTab',
            'counts' => [
                'sales',
                'stock_in',
                'stock_out',
                'in_transit',
                'transfers_in',
                'returns',
                'refunds',
                'debts',
            ],
            'panes_html',
        ]);

        $this->assertEquals('sales', $response->json('activeTab'));
        $this->assertEquals('1', $response->json('counts.sales'));
        $this->assertStringContainsString('id="pane-sales"', $response->json('panes_html'));
        $this->assertStringContainsString('SALE-AJAX-001', $response->json('panes_html'));
    }

    /**
     * Test 3: Cashier privacy scoping is enforced under AJAX requests.
     */
    public function test_cashier_privacy_scoping_enforced_under_ajax_requests()
    {
        // Sale by Admin
        Sale::create([
            'id' => 'SALE-ADMIN-001',
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->admin->id,
            'totalAmount' => 50000,
            'paidAmount' => 50000,
            'cashAmount' => 50000,
            'posAmount' => 0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // Sale by Cashier
        Sale::create([
            'id' => 'SALE-CASHIER-001',
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->cashier->id,
            'totalAmount' => 20000,
            'paidAmount' => 20000,
            'cashAmount' => 20000,
            'posAmount' => 0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        // When cashier fetches transactions via AJAX, they must ONLY see their own sale
        $response = $this->actingAs($this->cashier)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Partial-Update' => 'true',
            ])
            ->get('/transactions?tab=sales');

        $response->assertStatus(200);
        $this->assertEquals('1', $response->json('counts.sales'));
        $this->assertStringContainsString('SALE-CASHIER-001', $response->json('panes_html'));
        $this->assertStringNotContainsString('SALE-ADMIN-001', $response->json('panes_html'));
    }

    /**
     * Test 4: Dynamic date filtering parity under AJAX requests (Today vs Yesterday).
     */
    public function test_ajax_date_filtering_today_vs_yesterday()
    {
        // Sale created today
        Sale::create([
            'id' => 'SALE-TODAY-001',
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->admin->id,
            'totalAmount' => 10000,
            'paidAmount' => 10000,
            'cashAmount' => 10000,
            'posAmount' => 0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->setTimezone('Africa/Lagos')->toIso8601String(),
        ]);

        // Sale created yesterday
        Sale::create([
            'id' => 'SALE-YESTERDAY-001',
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->admin->id,
            'totalAmount' => 12000,
            'paidAmount' => 12000,
            'cashAmount' => 12000,
            'posAmount' => 0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->setTimezone('Africa/Lagos')->subDay()->toIso8601String(),
        ]);

        // Query for TODAY
        $resToday = $this->actingAs($this->admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Partial-Update' => 'true',
            ])
            ->get('/transactions?tab=sales&date_preset=TODAY');

        $resToday->assertStatus(200);
        $this->assertEquals('1', $resToday->json('counts.sales'));
        $this->assertStringContainsString('SALE-TODAY-001', $resToday->json('panes_html'));
        $this->assertStringNotContainsString('SALE-YESTERDAY-001', $resToday->json('panes_html'));

        // Query for YESTERDAY
        $resYesterday = $this->actingAs($this->admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Partial-Update' => 'true',
            ])
            ->get('/transactions?tab=sales&date_preset=YESTERDAY');

        $resYesterday->assertStatus(200);
        $this->assertEquals('1', $resYesterday->json('counts.sales'));
        $this->assertStringContainsString('SALE-YESTERDAY-001', $resYesterday->json('panes_html'));
        $this->assertStringNotContainsString('SALE-TODAY-001', $resYesterday->json('panes_html'));
    }

    /**
     * Test 5: Master Consolidated CSV export for all 8 tabs strictly respects active filters.
     */
    public function test_export_all_tabs_master_csv_with_active_filters()
    {
        // 1. Sale today
        Sale::create([
            'id' => 'SALE-EXP-TODAY',
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->admin->id,
            'totalAmount' => 50000,
            'paidAmount' => 50000,
            'cashAmount' => 50000,
            'posAmount' => 0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->setTimezone('Africa/Lagos')->toIso8601String(),
        ]);

        // 2. Sale yesterday (should be filtered out when filtering by TODAY)
        Sale::create([
            'id' => 'SALE-EXP-YEST',
            'warehouse_id' => $this->warehouseA->id,
            'userId' => $this->admin->id,
            'totalAmount' => 25000,
            'paidAmount' => 25000,
            'cashAmount' => 25000,
            'posAmount' => 0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->setTimezone('Africa/Lagos')->subDay()->toIso8601String(),
        ]);

        // 3. Stock In today
        InventoryLog::create([
            'id' => 'LOG-IN-TODAY',
            'warehouse_id' => $this->warehouseA->id,
            'productId' => 'PROD-001',
            'productCode' => 'SKU-001',
            'productName' => 'Samsung A15',
            'type' => 'STOCK_IN',
            'quantity' => 15,
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'description' => 'Supplier Delivery',
            'timestamp' => now()->setTimezone('Africa/Lagos')->toIso8601String(),
        ]);

        // 4. Stock Out today
        InventoryLog::create([
            'id' => 'LOG-OUT-TODAY',
            'warehouse_id' => $this->warehouseA->id,
            'productId' => 'PROD-001',
            'productCode' => 'SKU-001',
            'productName' => 'Samsung A15',
            'type' => 'SALE',
            'quantity' => -2,
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'description' => 'Direct counter sales',
            'timestamp' => now()->setTimezone('Africa/Lagos')->toIso8601String(),
        ]);

        // Call the export route for all tabs with date_preset=TODAY
        $response = $this->actingAs($this->admin)->get('/transactions/export-csv/all?date_preset=TODAY&warehouse_id=' . $this->warehouseA->id);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('hysam_universal_ledgers_all_tabs_filtered_', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();

        // Check top audit metadata
        $this->assertStringContainsString('HYSAM UNIVERSAL HISTORY & LEDGERS HUB - MASTER AUDIT REPORT', $content);
        $this->assertStringContainsString('Active Date Filter:', $content);
        $this->assertStringContainsString('TODAY', $content);

        // Check sections exist
        $this->assertStringContainsString('>>> SECTION 1: SALES TRANSACTIONS', $content);
        $this->assertStringContainsString('>>> SECTION 2: STOCK INFLOW & WAREHOUSE RECEIPTS', $content);
        $this->assertStringContainsString('>>> SECTION 3: STOCK OUTFLOW & INVENTORY DEDUCTIONS', $content);
        $this->assertStringContainsString('>>> SECTION 4: SHOP TRANSFERS (IN TRANSIT)', $content);
        $this->assertStringContainsString('>>> SECTION 5: SHOP TRANSFERS (RECEIVED & RECONCILED)', $content);
        $this->assertStringContainsString('>>> SECTION 6: PRODUCT & SALES RETURNS', $content);
        $this->assertStringContainsString('>>> SECTION 7: REFUNDS ISSUED', $content);
        $this->assertStringContainsString('>>> SECTION 8: CUSTOMER DEBT RECOVERIES & LEDGER', $content);

        // Check today's records are present in the filtered CSV
        $this->assertStringContainsString('SALE-EXP-TODAY', $content);
        $this->assertStringContainsString('LOG-IN-TODAY', $content);
        $this->assertStringContainsString('LOG-OUT-TODAY', $content);

        // Check yesterday's record was EXCLUDED by the active filter
        $this->assertStringNotContainsString('SALE-EXP-YEST', $content);

        // Check section summaries
        $this->assertStringContainsString('[SALES SUMMARY]', $content);
        $this->assertStringContainsString('[STOCK IN SUMMARY]', $content);
        $this->assertStringContainsString('[STOCK OUT SUMMARY]', $content);
    }
}
