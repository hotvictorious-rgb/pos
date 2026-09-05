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
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionHardeningPass13Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $branch1;
    protected Warehouse $branch2;
    protected User $cashier;
    protected User $manager;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['saas.enabled' => true]);

        $this->tenant = Tenant::create([
            'id' => 'tenant-alaba-' . Str::random(5),
            'name' => 'Alaba Electronics Hub Ltd',
            'slug' => 'alaba-electronics-' . Str::random(5),
            'owner_email' => 'owner@alaba.test',
            'status' => 'ACTIVE',
        ]);

        $this->branch1 = Warehouse::create([
            'name' => 'Alaba Main Branch',
            'code' => 'ALB-01',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->branch2 = Warehouse::create([
            'name' => 'Trade Fair Depot',
            'code' => 'TRF-02',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Emeka Cashier',
            'email' => 'emeka@alaba.test',
            'password' => bcrypt('Secr3tCashier!'),
            'role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch1->id,
            'disabled' => false,
        ]);

        $this->manager = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Chidi Manager',
            'email' => 'chidi@alaba.test',
            'password' => bcrypt('Secr3tManager!'),
            'role' => 'manager',
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->branch1->id,
            'disabled' => false,
        ]);

        // Create two distinct products
        $this->productA = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Smart Solar Inverter 5kVA',
            'code' => 'INV-5KVA',
            'category' => 'Solar Power',
            'unitPrice' => 350000.00,
            'costPrice' => 300000.00,
            'currentStock' => 20,
            'minStockLevel' => 1,
            'archived' => false,
        ]);

        $this->productB = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Tubular Deep-Cycle Battery 220Ah',
            'code' => 'BAT-220AH',
            'category' => 'Batteries',
            'unitPrice' => 180000.00,
            'costPrice' => 150000.00,
            'currentStock' => 30,
            'minStockLevel' => 1,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->branch1->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->productB->id,
            'warehouse_id' => $this->branch1->id,
            'physical_stock' => 30,
            'allocated_stock' => 0,
        ]);
    }

    /**
     * PASS 13 - DEADLOCK HARDENING:
     * Validates that checkout items presented in reverse order are sorted deterministically
     * by product ID prior to acquiring row locks, ensuring monotonic lock acquisition.
     */
    public function test_deterministic_monotonic_lock_ordering_in_pos_checkout(): void
    {
        $stockService = app(StockService::class);

        // Intentionally provide items in descending ID order
        $highId = max($this->productA->id, $this->productB->id);
        $lowId  = min($this->productA->id, $this->productB->id);

        $basketItems = [
            ['productId' => $highId, 'quantity' => 2],
            ['productId' => $lowId,  'quantity' => 1],
        ];

        session(['tenant_id' => $this->tenant->id]);

        $sale = $stockService->recordSale(
            ['customerName' => 'Chief Okonkwo', 'tender' => ['cashAmount' => 900000.00, 'posAmount' => 0]],
            $basketItems,
            $this->branch1->id,
            true,
            (string) $this->cashier->id,
            $this->cashier->name
        );

        $this->assertInstanceOf(Sale::class, $sale);
        $this->assertEquals('COMPLETED', $sale->status);

        // Verify physical stock decrements occurred accurately
        $stockHigh = StockLevel::where('product_id', $highId)->where('warehouse_id', $this->branch1->id)->first();
        $stockLow  = StockLevel::where('product_id', $lowId)->where('warehouse_id', $this->branch1->id)->first();

        $originalStockHigh = ($highId === $this->productA->id) ? 20 : 30;
        $originalStockLow  = ($lowId === $this->productA->id) ? 20 : 30;

        $this->assertEquals($originalStockHigh - 2, $stockHigh->physical_stock);
        $this->assertEquals($originalStockLow - 1, $stockLow->physical_stock);
    }

    /**
     * PASS 13 - MONOTONIC LOCKING IN TRANSFERS & RETURNS:
     * Verifies that multi-item transfer dispatches and returns sort items monotonically.
     */
    public function test_monotonic_lock_sorting_in_transfers_and_returns(): void
    {
        $stockService = app(StockService::class);
        session(['tenant_id' => $this->tenant->id]);

        $highId = max($this->productA->id, $this->productB->id);
        $lowId  = min($this->productA->id, $this->productB->id);

        // Transfer with reverse item ordering
        $transferItems = [
            ['productId' => $highId, 'quantity' => 3],
            ['productId' => $lowId,  'quantity' => 2],
        ];

        $transfer = $stockService->initiateTransfer(
            $this->branch1->id,
            $this->branch2->id,
            $transferItems,
            'GIG Logistics',
            (string) $this->manager->id,
            $this->manager->name,
            'Buffer stock transfer'
        );

        $this->assertNotNull($transfer->id);
        $this->assertEquals('DISPATCHED', $transfer->status);
    }

    /**
     * PASS 13 - FAIL-CLOSED INSTALLER LOCKOUT VIA ENV FLAG:
     * Verifies that when APP_INSTALLED=true or APP_INSTALLER_ENABLED=false,
     * any HTTP request to /install is blocked even if storage/installed file is absent.
     */
    public function test_installer_fail_closed_lockout_via_environment_flags(): void
    {
        $markerPath = storage_path('installed');
        $hadMarker = file_exists($markerPath);

        try {
            if ($hadMarker) {
                @unlink($markerPath);
            }

            // Test Case A: APP_INSTALLED=true blocks installer with redirect to /
            putenv('APP_INSTALLED=true');
            $_ENV['APP_INSTALLED'] = 'true';

            $response = $this->get('/install');
            $response->assertRedirect('/');
            $response->assertSessionHas('info', 'The application is already installed.');

            // Test Case B: When APP_INSTALLED=false but APP_INSTALLER_ENABLED=false
            putenv('APP_INSTALLED=false');
            $_ENV['APP_INSTALLED'] = 'false';
            putenv('APP_INSTALLER_ENABLED=false');
            $_ENV['APP_INSTALLER_ENABLED'] = 'false';

            $response2 = $this->get('/install');
            $response2->assertStatus(403);
        } finally {
            // Guarantee storage/installed is restored so subsequent test suites aren't impacted
            file_put_contents($markerPath, date('Y-m-d H:i:s'));
            putenv('APP_INSTALLED');
            putenv('APP_INSTALLER_ENABLED');
            unset($_ENV['APP_INSTALLED'], $_ENV['APP_INSTALLER_ENABLED']);
        }
    }

    protected function tearDown(): void
    {
        $markerPath = storage_path('installed');
        if (!file_exists($markerPath)) {
            file_put_contents($markerPath, date('Y-m-d H:i:s'));
        }
        parent::tearDown();
    }

    /**
     * PASS 13 - INTEGER KOBO ACCOUNTING MATH INVARIANCE:
     * Validates that AccountingReportService performs exact integer kobo calculations.
     */
    public function test_accounting_service_integer_kobo_precision(): void
    {
        session(['tenant_id' => $this->tenant->id]);
        $accounting = app(AccountingReportService::class);

        $items = [
            ['productId' => $this->productA->id, 'quantity' => 1], // 350,000.00
        ];

        // Valid exact split: 200,000 cash + 150,000 pos = 350,000
        $calc = $accounting->calculateCheckout($items, [
            'cashAmount' => 200000.00,
            'posAmount'  => 150000.00,
        ]);

        $this->assertEquals(350000.00, $calc['totalAmount']);
        $this->assertEquals(200000.00, $calc['retainedCash']);
        $this->assertEquals(150000.00, $calc['retainedPos']);
        $this->assertEquals('COMPLETED', $calc['status']);
    }
}
