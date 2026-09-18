<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\Transfer;
use App\Models\SalesReturn;
use App\Models\Activity;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ReportTabsAndAiExportsExhaustiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::create([
            'name' => 'Victoria Main Hub',
            'code' => 'VMH-01',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Chief Auditor',
            'username' => 'auditor',
            'email' => 'auditor@victoriousmarket.ng',
            'password' => bcrypt('password123'),
            'role' => 'SUPER_ADMIN',
            'warehouse_id' => $this->warehouse->id,
            'permissions' => ['reports.view', 'reports.export', 'settings.manage'],
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Royal Sugar 50kg',
            'code' => 'RS-50',
            'category' => 'Commodities',
            'unitPrice' => 45000,
            'minStockLevel' => 10,
            'archived' => false,
        ]);

        StockLevel::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 100,
        ]);

        $this->customer = Customer::create([
            'name' => 'Alhaji Musa',
            'phone' => '08031234567',
            'address' => 'Shop 44 Market Square',
            'total_debt' => 15000,
        ]);

        // 1. Create a Sale
        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'customerId' => $this->customer->id,
            'customerName' => $this->customer->name,
            'totalAmount' => 90000,
            'paidAmount' => 75000,
            'cashAmount' => 25000,
            'posAmount' => 50000,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'NOT_SUPPLIED',
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'warehouse_id' => $this->warehouse->id,
            'createdAt' => now('Africa/Lagos')->toIso8601String(),
        ]);

        SaleItem::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'productName' => $this->product->name,
            'code' => $this->product->code,
            'quantity' => 2,
            'unitPrice' => 45000,
            'totalPrice' => 90000,
        ]);

        // 2. Create a Stock Adjustment
        StockAdjustment::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_code' => $this->product->code,
            'type' => 'DAMAGED',
            'quantity' => 3,
            'reason' => 'Water moisture damage during offloading',
            'recorded_by' => $this->admin->name,
            'status' => 'CONFIRMED',
        ]);

        // 3. Create a Sales Return
        SalesReturn::create([
            'id' => (string) Str::uuid(),
            'saleId' => $sale->id,
            'productId' => $this->product->id,
            'code' => $this->product->code,
            'productCode' => $this->product->code,
            'productName' => $this->product->name,
            'customerId' => $this->customer->id,
            'customerName' => $this->customer->name,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'refundAmount' => 45000,
            'reason' => 'Bag seal compromised',
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'createdAt' => now('Africa/Lagos')->toIso8601String(),
        ]);

        // 4. Create an Activity Log
        Activity::create([
            'id' => (string) Str::uuid(),
            'userId' => $this->admin->id,
            'userName' => $this->admin->name,
            'type' => 'DISPATCH_INSPECTION',
            'description' => 'Audited warehouse ground count',
            'timestamp' => now('Africa/Lagos')->toIso8601String(),
        ]);
    }

    public function test_all_report_tabs_exist_and_activate_correctly(): void
    {
        $this->actingAs($this->admin);

        $tabsToTest = [
            'day_book' => 'repDayBook',
            'sales' => 'repSales',
            'pending' => 'repPending',
            'stock' => 'repStock',
            'transfers' => 'repTransfers',
            'debts' => 'repDebts',
            'damages' => 'repDamages',
            'returns' => 'repReturns',
            'ai' => 'repAi',
        ];

        foreach ($tabsToTest as $tabParam => $containerId) {
            $response = $this->get(route('reports.index', ['tab' => $tabParam]));
            $response->assertStatus(200);

            // Assert container has active class
            $response->assertSee("id=\"{$containerId}\" class=\"report-section active\"", false);
        }
    }

    public function test_all_9_csv_exports_return_200_and_valid_csv(): void
    {
        $this->actingAs($this->admin);

        $types = [
            'day_book',
            'sales',
            'pending_orders',
            'inventory',
            'transfers',
            'debtors',
            'stock_out',
            'damages',
            'returns',
            'activities',
        ];

        foreach ($types as $t) {
            $response = $this->get(route('reports.export.csv', ['type' => $t]));
            $response->assertStatus(200);
            $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'), "Failed for type: {$t}");
        }
    }

    public function test_all_9_json_ai_exports_return_200_and_valid_json_structure(): void
    {
        $this->actingAs($this->admin);

        $types = [
            'day_book',
            'sales',
            'pending_orders',
            'inventory',
            'transfers',
            'debtors',
            'stock_out',
            'damages',
            'returns',
            'activities',
        ];

        foreach ($types as $t) {
            $response = $this->get(route('reports.export.json', ['type' => $t]));
            $response->assertStatus(200);
            $json = $response->json();
            $this->assertTrue(isset($json['data']) || isset($json['meta']) || isset($json['metadata']), "Failed JSON structure for type: {$t}");
        }
    }

    public function test_all_pdf_exports_return_200_with_executive_styling_and_isolation(): void
    {
        $this->actingAs($this->admin);

        $types = [
            'inventory',
            'sales',
            'debtors',
            'day_book',
            'transfers',
            'exchanges',
            'pending_orders',
            'damages',
            'returns',
        ];

        foreach ($types as $t) {
            $response = $this->get(route('reports.export.pdf', ['type' => $t, 'date_preset' => 'TODAY']));
            $response->assertStatus(200);
            $response->assertSee('executive-table');
            $response->assertSee('#0c2340');
            $response->assertSee('Print / Save as PDF');
            $response->assertSee('Powered by');
            $response->assertSee('Victorious Market');
            $response->assertSee('Your Trusted Online Market');
        }

        // Test Multi-Branch Inventory PDF contains specific columns and product
        $invResponse = $this->get(route('reports.export.pdf', ['type' => 'inventory']));
        $invResponse->assertStatus(200);
        $invResponse->assertSee('Multi-Branch Physical Stock Report');
        $invResponse->assertSee($this->product->code);
        $invResponse->assertSee($this->warehouse->name);
        $invResponse->assertSee('TOTAL UNITS ON GROUND');
    }

    public function test_inventory_report_and_pdf_export_filter_by_quantity_threshold(): void
    {
        $this->actingAs($this->admin);

        // Create product with 1 unit
        $prodSingle = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Single Low Item',
            'code' => 'LOW-01',
            'category' => 'Test',
            'unitPrice' => 1000,
            'archived' => false,
        ]);
        StockLevel::create([
            'product_id' => $prodSingle->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 1,
        ]);

        // Create product with 0 units
        $prodZero = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Out of Stock Item',
            'code' => 'ZERO-00',
            'category' => 'Test',
            'unitPrice' => 5000,
            'archived' => false,
        ]);
        StockLevel::create([
            'product_id' => $prodZero->id,
            'warehouse_id' => $this->warehouse->id,
            'physical_stock' => 0,
        ]);

        // 1. Filter min_qty = 2 (Should only include Royal Sugar 50kg with stock 100)
        $respQty2 = $this->get(route('reports.index', ['tab' => 'inventory', 'min_qty' => '2']));
        $respQty2->assertStatus(200);
        $respQty2->assertSee('RS-50');
        $respQty2->assertDontSee('LOW-01');
        $respQty2->assertDontSee('ZERO-00');

        $pdfQty2 = $this->get(route('reports.export.pdf', ['type' => 'inventory', 'min_qty' => '2']));
        $pdfQty2->assertStatus(200);
        $pdfQty2->assertSee('RS-50');
        $pdfQty2->assertDontSee('LOW-01');
        $pdfQty2->assertDontSee('ZERO-00');
        $pdfQty2->assertSee('Quantity ≥ 2 Units');

        // 2. Filter min_qty = 1 (Should include RS-50 and LOW-01, but NOT ZERO-00)
        $pdfQty1 = $this->get(route('reports.export.pdf', ['type' => 'inventory', 'min_qty' => '1']));
        $pdfQty1->assertStatus(200);
        $pdfQty1->assertSee('RS-50');
        $pdfQty1->assertSee('LOW-01');
        $pdfQty1->assertDontSee('ZERO-00');
        $pdfQty1->assertSee('In-Stock (≥ 1 Unit)');

        // 3. Filter min_qty = 0 (Should only include ZERO-00)
        $pdfQty0 = $this->get(route('reports.export.pdf', ['type' => 'inventory', 'min_qty' => '0']));
        $pdfQty0->assertStatus(200);
        $pdfQty0->assertSee('ZERO-00');
        $pdfQty0->assertDontSee('RS-50');
        $pdfQty0->assertDontSee('LOW-01');
        $pdfQty0->assertSee('Out of Stock (= 0 Units)');
    }
}
