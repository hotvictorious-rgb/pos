<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProductImportExportAndFilterHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected User $tenantAdmin;
    protected User $branchWorker;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'app.key' => 'base64:' . base64_encode('12345678901234567890123456789012'),
        ]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-import-export-hardening',
            'name' => 'Hardening Ventures',
            'status' => 'active',
            'plan' => 'pro',
            'owner_email' => 'owner@hardening.com',
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lagos Mainland Depot',
            'code' => 'WH-LAGOS-01',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Abuja Central Depot',
            'code' => 'WH-ABUJA-02',
            'is_active' => true,
        ]);

        $this->tenantAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Boss',
            'email' => 'admin@hardening.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouseA->id,
            'permissions' => ['all' => true],
        ]);

        $this->branchWorker = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch Worker A',
            'email' => 'worker@hardening.com',
            'password' => bcrypt('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouseA->id,
            'permissions' => [
                'pos.access' => true,
                'pos.view' => true,
                'reports.view' => true,
                'products.view' => true,
                'products.write' => true,
            ],
        ]);
    }

    /**
     * TEST 1: CSV import rejects files missing required headers (name, code/sku, category, unitPrice/price).
     */
    public function test_csv_import_rejects_missing_required_headers()
    {
        // Missing 'code' and 'unitPrice'
        $csvContent = "name,category,initial_stock\nSamsung Galaxy A15,Phones,10\n";
        $file = UploadedFile::fake()->createWithContent('invalid_headers.csv', $csvContent);

        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.import.csv'), [
                'csv_file' => $file,
                'warehouse_id' => $this->warehouseA->id,
            ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('error');
        $errorMsg = session('error');
        $this->assertStringContainsString('missing required column headers', $errorMsg);
        $this->assertStringContainsString('code', $errorMsg);
        $this->assertStringContainsString('unitPrice', $errorMsg);

        // Assert nothing was imported
        $this->assertNull(Product::where('name', 'Samsung Galaxy A15')->first());
    }

    /**
     * TEST 2: CSV import rejects rows with missing name, code, category, or non-numeric/negative price.
     */
    public function test_csv_import_rejects_missing_required_row_values()
    {
        // Row 2 is missing code, Row 3 has negative unitPrice, Row 4 is missing category
        $csvContent = "name,code,category,unitPrice\n"
            . "Product No Code,,Electronics,5000\n"
            . "Product Bad Price,CODE-BAD,Electronics,-200\n"
            . "Product No Category,CODE-NOCAT,,15000\n";
        $file = UploadedFile::fake()->createWithContent('bad_rows.csv', $csvContent);

        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.import.csv'), [
                'csv_file' => $file,
                'warehouse_id' => $this->warehouseA->id,
            ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('error');
        $errorMsg = session('error');
        $this->assertStringContainsString('CSV Import Failed', $errorMsg);
        $this->assertStringContainsString("Row 2: 'code' / 'sku' is required", $errorMsg);
        $this->assertStringContainsString("Row 3: 'unitPrice' must be a valid non-negative number", $errorMsg);
        $this->assertStringContainsString("Row 4: 'category' is required", $errorMsg);

        // Assert 0 products were saved due to rollback
        $this->assertDatabaseMissing('products', ['code' => 'CODE-BAD']);
        $this->assertDatabaseMissing('products', ['code' => 'CODE-NOCAT']);
    }

    /**
     * TEST 3: CSV import rejects duplicate SKUs within the same CSV upload.
     */
    public function test_csv_import_rejects_duplicate_sku_in_same_file()
    {
        $csvContent = "name,code,category,unitPrice\n"
            . "First Item,DUP-SKU-99,Provisions,2500\n"
            . "Second Item With Same Code,DUP-SKU-99,Provisions,3000\n";
        $file = UploadedFile::fake()->createWithContent('dup_skus.csv', $csvContent);

        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.import.csv'), [
                'csv_file' => $file,
                'warehouse_id' => $this->warehouseA->id,
            ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('error');
        $errorMsg = session('error');
        $this->assertStringContainsString("Duplicate SKU 'DUP-SKU-99' found in upload", $errorMsg);

        $this->assertDatabaseMissing('products', ['code' => 'DUP-SKU-99']);
    }

    /**
     * TEST 4: CSV import rejects negative initial stock values.
     */
    public function test_csv_import_rejects_negative_stock()
    {
        $csvContent = "name,code,category,unitPrice,initial_stock\n"
            . "Negative Stock Item,NEG-STK-01,Hardware,4500,-15\n";
        $file = UploadedFile::fake()->createWithContent('negative_stock.csv', $csvContent);

        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.import.csv'), [
                'csv_file' => $file,
                'warehouse_id' => $this->warehouseA->id,
            ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString("Row 2: 'initial_stock' must be a non-negative integer", session('error'));

        $this->assertDatabaseMissing('products', ['code' => 'NEG-STK-01']);
    }

    /**
     * TEST 5: CSV import successfully creates products with description, stock, and min alert.
     */
    public function test_csv_import_success_with_description()
    {
        $csvContent = "name,code,category,brand,size,unitPrice,minStockLevel,initial_stock,description\n"
            . "Premium Jasmine Rice,JAS-RICE-25,Grains,Golden Crop,25kg,35000,10,20,Fragrant imported jasmine long grain rice\n";
        $file = UploadedFile::fake()->createWithContent('valid_products.csv', $csvContent);

        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.import.csv'), [
                'csv_file' => $file,
                'warehouse_id' => $this->warehouseA->id,
            ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');

        $product = Product::where('code', 'JAS-RICE-25')->first();
        $this->assertNotNull($product);
        $this->assertEquals('Premium Jasmine Rice', $product->name);
        $this->assertEquals('Fragrant imported jasmine long grain rice', $product->description);
        $this->assertEquals(35000.0, (float)$product->unitPrice);
        $this->assertEquals(10, $product->minStockLevel);

        $stockLevel = StockLevel::where('product_id', $product->id)->where('warehouse_id', $this->warehouseA->id)->first();
        $this->assertNotNull($stockLevel);
        $this->assertEquals(20, $stockLevel->physical_stock);
    }

    /**
     * TEST 6: CSV Template includes description header.
     */
    public function test_csv_template_includes_description_header()
    {
        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('products.template.csv'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('description', $content);
        $this->assertStringContainsString('name,code,category,brand,size,unitPrice,minStockLevel,initial_stock,description', $content);
    }

    /**
     * TEST 7: Product CSV & JSON export inherits active filters (category, price, search, stock_status).
     */
    public function test_product_exports_inherit_active_filters()
    {
        $p1 = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'High End TV 65 Inch',
            'code' => 'TV-65-INCH',
            'category' => 'Electronics',
            'unitPrice' => 250000.0,
            'minStockLevel' => 5,
            'archived' => false,
        ]);
        $p2 = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Budget Radio',
            'code' => 'RAD-BUDGET',
            'category' => 'Electronics',
            'unitPrice' => 5000.0,
            'minStockLevel' => 5,
            'archived' => false,
        ]);
        $p3 = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Corn Flakes 500g',
            'code' => 'CORN-500G',
            'category' => 'Provisions',
            'unitPrice' => 3200.0,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        StockLevel::create(['tenant_id' => $this->tenant->id, 'product_id' => $p1->id, 'warehouse_id' => $this->warehouseA->id, 'physical_stock' => 10, 'allocated_stock' => 0]);
        StockLevel::create(['tenant_id' => $this->tenant->id, 'product_id' => $p2->id, 'warehouse_id' => $this->warehouseA->id, 'physical_stock' => 50, 'allocated_stock' => 0]);
        StockLevel::create(['tenant_id' => $this->tenant->id, 'product_id' => $p3->id, 'warehouse_id' => $this->warehouseA->id, 'physical_stock' => 100, 'allocated_stock' => 0]);

        // Filter: Category = Electronics, Min Price = 50000 (only p1 matches)
        $csvResponse = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('products.export.csv', [
                'category' => 'Electronics',
                'min_price' => 50000,
            ]));

        $csvResponse->assertStatus(200);
        $csvContent = $csvResponse->streamedContent();

        $this->assertStringContainsString('TV-65-INCH', $csvContent);
        $this->assertStringNotContainsString('RAD-BUDGET', $csvContent);
        $this->assertStringNotContainsString('CORN-500G', $csvContent);

        // JSON export with same filter
        $jsonResponse = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('products.export.json', [
                'category' => 'Electronics',
                'min_price' => 50000,
            ]));

        $jsonResponse->assertStatus(200);
        $json = $jsonResponse->json();
        $this->assertEquals(1, $json['metadata']['total_skus']);
        $this->assertEquals('TV-65-INCH', $json['products'][0]['code']);
    }

    /**
     * TEST 8: Branch-scoped staff export CSV and JSON strictly scoped to their assigned warehouse.
     */
    public function test_product_export_strictly_enforces_branch_scoping()
    {
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Palm Oil Keg 25L',
            'code' => 'PALM-OIL-25L',
            'category' => 'Oils',
            'unitPrice' => 40000.0,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        // Branch A has 15 units, Branch B has 85 units. Total across tenant = 100 units.
        StockLevel::create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'warehouse_id' => $this->warehouseA->id, 'physical_stock' => 15, 'allocated_stock' => 0]);
        StockLevel::create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'warehouse_id' => $this->warehouseB->id, 'physical_stock' => 85, 'allocated_stock' => 0]);

        // Branch Worker A (assigned to warehouse A) exports CSV
        $csvResponse = $this->actingAs($this->branchWorker)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('products.export.csv'));

        $csvResponse->assertStatus(200);
        $csvContent = $csvResponse->streamedContent();

        // Must export 15 units (Warehouse A) with valuation 600,000, NOT 100 units / 4,000,000
        $this->assertStringContainsString('PALM-OIL-25L', $csvContent);
        $this->assertStringContainsString(',15,600000', $csvContent);
        $this->assertStringNotContainsString(',100,4000000', $csvContent);

        // Branch Worker A exports JSON
        $jsonResponse = $this->actingAs($this->branchWorker)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('products.export.json'));

        $jsonResponse->assertStatus(200);
        $json = $jsonResponse->json();
        $this->assertTrue($json['metadata']['branch_scoped']);
        $productExport = $json['products'][0];

        $this->assertEquals(15, $productExport['total_physical_stock']);
        $this->assertEquals(600000.0, $productExport['total_asset_value']);
        // Breakdown only contains warehouse A
        $this->assertEquals([$this->warehouseA->id => 15], $productExport['branch_stock_breakdown']);
    }

    /**
     * TEST 9: Reports inventory status dynamically respects each product's minStockLevel threshold.
     */
    public function test_report_inventory_uses_product_min_stock_level()
    {
        // Product A: minStockLevel = 25, Stock = 10 -> Should be LOW_STOCK (<= 25)
        $prodA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'High Velocity Item',
            'code' => 'HVI-100',
            'category' => 'Beverages',
            'unitPrice' => 1000.0,
            'minStockLevel' => 25,
            'archived' => false,
        ]);
        StockLevel::create(['tenant_id' => $this->tenant->id, 'product_id' => $prodA->id, 'warehouse_id' => $this->warehouseA->id, 'physical_stock' => 10, 'allocated_stock' => 0]);

        // Product B: minStockLevel = 2, Stock = 3 -> Should be IN_STOCK (> 2) even though stock <= 5
        $prodB = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Low Velocity Generator',
            'code' => 'LVG-200',
            'category' => 'Generators',
            'unitPrice' => 500000.0,
            'minStockLevel' => 2,
            'archived' => false,
        ]);
        StockLevel::create(['tenant_id' => $this->tenant->id, 'product_id' => $prodB->id, 'warehouse_id' => $this->warehouseA->id, 'physical_stock' => 3, 'allocated_stock' => 0]);

        $reportCsv = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('reports.export.csv', ['type' => 'inventory']));

        $reportCsv->assertStatus(200);
        $csvContent = $reportCsv->streamedContent();

        // Assert Prod A (stock 10, min 25) is LOW_STOCK
        $this->assertStringContainsString('HVI-100', $csvContent);
        $this->assertStringContainsString('10,LOW_STOCK,10000', $csvContent);

        // Assert Prod B (stock 3, min 2) is IN_STOCK (not LOW_STOCK from old hardcoded 5)
        $this->assertStringContainsString('LVG-200', $csvContent);
        $this->assertStringContainsString('3,IN_STOCK,1500000', $csvContent);
    }

    /**
     * TEST 10: Real catalog import test for restructured hysam_products_import_nwaniba.csv.
     * Verifies that all 529 items import with unitPrice = 20000 and initial_stock = 0.
     */
    public function test_actual_nwaniba_products_csv_imports_successfully_with_authoritative_prices_and_zero_initial_stock(): void
    {
        $csvPath = base_path('hysam_products_import_nwaniba.csv');
        $this->assertFileExists($csvPath);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $csvPath,
            'hysam_products_import_nwaniba.csv',
            'text/csv',
            null,
            true
        );

        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('products.import.csv'), [
                'csv_file' => $uploadedFile,
                'warehouse_id' => $this->warehouseA->id,
            ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();

        // Verify total imported count
        $this->assertEquals(529, Product::where('tenant_id', $this->tenant->id)->count());

        // Exhaustive verification: ALL 529 products must have unitPrice = 20000.0 and currentStock = 0
        $this->assertEquals(
            529,
            Product::where('tenant_id', $this->tenant->id)->where('unitPrice', 20000)->count(),
            'Not all imported products have unitPrice = 20000.'
        );
        $this->assertEquals(
            0,
            Product::where('tenant_id', $this->tenant->id)->where('unitPrice', '!=', 20000)->count(),
            'Found imported products with unitPrice != 20000.'
        );
        $this->assertEquals(
            529,
            Product::where('tenant_id', $this->tenant->id)->where('currentStock', 0)->count(),
            'Not all imported products have currentStock = 0.'
        );

        // Exhaustive verification: ALL 529 StockLevels at warehouseA must have physical_stock = 0 and allocated_stock = 0
        $this->assertEquals(
            529,
            StockLevel::where('tenant_id', $this->tenant->id)->where('warehouse_id', $this->warehouseA->id)->count(),
            'Expected exactly 529 branch stock level records created.'
        );
        $this->assertEquals(
            529,
            StockLevel::where('tenant_id', $this->tenant->id)
                ->where('warehouse_id', $this->warehouseA->id)
                ->where('physical_stock', 0)
                ->where('allocated_stock', 0)
                ->count(),
            'Not all branch stock levels have physical_stock = 0 and allocated_stock = 0.'
        );

        // Sample check first product M15DE
        $deluxe = Product::where('tenant_id', $this->tenant->id)->where('code', 'M15DE')->first();
        $this->assertNotNull($deluxe);
        $this->assertEquals('Deluxe Mattress (75x30x3)', $deluxe->name);
        $this->assertEquals('Standard Foam Mattresses', $deluxe->category);
        $this->assertEquals('Deluxe', $deluxe->brand);
        $this->assertEquals('75x30x3', $deluxe->size);
        $this->assertEquals(20000.0, (float)$deluxe->unitPrice);
        $this->assertEquals(0, $deluxe->currentStock);

        $deluxeStock = StockLevel::where('tenant_id', $this->tenant->id)
            ->where('product_id', $deluxe->id)
            ->where('warehouse_id', $this->warehouseA->id)
            ->first();
        $this->assertNotNull($deluxeStock);
        $this->assertEquals(0, $deluxeStock->physical_stock);
        $this->assertEquals(0, $deluxeStock->allocated_stock);

        // Sample check pillow PVL
        $vitalite = Product::where('tenant_id', $this->tenant->id)->where('code', 'PVL')->first();
        $this->assertNotNull($vitalite);
        $this->assertEquals(20000.0, (float)$vitalite->unitPrice);
        $this->assertEquals(0, $vitalite->currentStock);
    }

    /**
     * Test: Product update via AJAX returns JSON payload for zero-reload in-page updates.
     */
    public function test_product_update_via_ajax_returns_json_and_updates_model()
    {
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Energy Drink 500ml',
            'code' => 'ED-500',
            'category' => 'Beverages',
            'unitPrice' => 1200,
            'currentStock' => 10,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->post("/products/{$product->id}", [
                'name' => 'Energy Drink 500ml Gold',
                'category' => 'Beverages',
                'unitPrice' => 1500,
                'brand' => 'PowerCharge',
                'size' => '500ml Can',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => 'Energy Drink 500ml Gold',
                'category' => 'Beverages',
                'brand' => 'PowerCharge',
                'size' => '500ml Can',
                'unitPrice' => 1500,
            ]
        ]);

        $product->refresh();
        $this->assertEquals('Energy Drink 500ml Gold', $product->name);
        $this->assertEquals(1500, (float)$product->unitPrice);
    }

    /**
     * Test: Product update via standard form preserves active category return_url.
     */
    public function test_product_update_via_form_preserves_category_return_url()
    {
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Solar Battery 12V 200AH',
            'code' => 'SOL-BAT-200',
            'category' => 'Solar Equipment',
            'unitPrice' => 250000,
            'currentStock' => 5,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $returnUrl = 'http://localhost/products?category=' . urlencode('Solar Equipment') . '&stock_status=IN_STOCK';

        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post("/products/{$product->id}", [
                'name' => 'Solar Battery 12V 200AH Tubular',
                'category' => 'Solar Equipment',
                'unitPrice' => 265000,
                'return_url' => $returnUrl,
            ]);

        $response->assertRedirect($returnUrl);
        $response->assertSessionHas('success');

        $product->refresh();
        $this->assertEquals(265000, (float)$product->unitPrice);
    }
}
