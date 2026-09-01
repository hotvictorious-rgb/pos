<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DashboardDateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Warehouse $warehouse;
    protected Warehouse $branchB;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'DASH-WH-1'],
            ['name' => 'Dashboard Main WH', 'address' => 'HQ', 'is_active' => true]
        );

        $this->branchB = Warehouse::firstOrCreate(
            ['code' => 'DASH-WH-2'],
            ['name' => 'Branch Shop 2', 'address' => 'Market', 'is_active' => true]
        );

        $this->user = User::firstOrCreate(
            ['id' => 'ADMIN-DASH-1'],
            [
                'name' => 'Dashboard Tester',
                'email' => 'dashtest@hysam.com',
                'password' => Hash::make('secret123'),
                'role' => 'admin',
                'warehouse_id' => null,
                'disabled' => false,
            ]
        );
        $this->actingAs($this->user);
        session(['user_id' => $this->user->id, 'user_role' => 'admin']);

        $this->product = Product::firstOrCreate(
            ['code' => 'DASH-PROD-01'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Premium Rice 50kg',
                'category' => 'Food Grains',
                'unitPrice' => 35000,
                'currentStock' => 50,
                'archived' => false,
                'updatedAt' => now()->toIso8601String(),
            ]
        );

        StockLevel::firstOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id],
            ['physical_stock' => 50, 'allocated_stock' => 0, 'min_stock_alert' => 5]
        );

        StockLevel::firstOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->branchB->id],
            ['physical_stock' => 20, 'allocated_stock' => 0, 'min_stock_alert' => 5]
        );
    }

    public function test_dashboard_renders_default_today_metrics()
    {
        // Seed a sale for today
        Sale::create([
            'id' => (string) Str::uuid(),
            'customerName' => 'Alice Walker',
            'totalAmount' => 70000,
            'paidAmount' => 50000,
            'cashAmount' => 30000,
            'posAmount' => 20000,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'UNSUPPLIED',
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'createdAt' => now()->toIso8601String(),
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Gross Sales');
        $response->assertSee('₦70,000');
        $response->assertSee('₦30,000'); // Cash
        $response->assertSee('₦20,000'); // POS
        $response->assertSee('₦20,000'); // New debt
        $response->assertSee('Today');
    }

    public function test_dashboard_filters_by_custom_date_preset()
    {
        // Sale yesterday
        Sale::create([
            'id' => (string) Str::uuid(),
            'customerName' => 'Bob Yesterday',
            'totalAmount' => 105000,
            'paidAmount' => 105000,
            'cashAmount' => 105000,
            'posAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'createdAt' => Carbon::yesterday()->setHour(14)->toIso8601String(),
        ]);

        // Yesterday query
        $yesterdayResponse = $this->get(route('dashboard', ['date_preset' => 'YESTERDAY']));
        $yesterdayResponse->assertStatus(200);
        $yesterdayResponse->assertSee('₦105,000');

        // This Month query
        $monthResponse = $this->get(route('dashboard', ['date_preset' => 'THIS_MONTH']));
        $monthResponse->assertStatus(200);
        $monthResponse->assertSee('This Month');
    }

    public function test_dashboard_displays_stock_in_out_and_debt_recoveries()
    {
        // Create customer and debt payment
        $customer = Customer::create([
            'name' => 'Debtor Charlie',
            'phone' => '08011223344',
            'total_debt' => 45000,
        ]);

        CustomerLedger::create([
            'customer_id' => $customer->id,
            'type' => 'PAYMENT',
            'amount' => 15000,
            'balance_after' => 30000,
            'payment_method' => 'CASH',
            'recorded_by' => $this->user->name,
            'created_at' => now(),
        ]);

        // Create inventory log
        InventoryLog::create([
            'id' => (string) Str::uuid(),
            'productId' => $this->product->id,
            'type' => 'STOCK_IN',
            'quantity' => 100,
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'productCode' => $this->product->code,
            'productName' => $this->product->name,
            'description' => 'Supplier Delivery',
            'timestamp' => now()->toIso8601String(),
        ]);

        $response = $this->get(route('dashboard', ['date_preset' => 'TODAY']));
        $response->assertStatus(200);
        $response->assertSee('₦15,000'); // Debt recovered
        $response->assertSee('+100 units'); // Stock In
    }

    public function test_dashboard_filters_by_specific_branch_location()
    {
        // Branch B has 20 units * 35,000 = 700,000 valuation
        $response = $this->get(route('dashboard', ['warehouse_id' => $this->branchB->id]));
        $response->assertStatus(200);
        $response->assertSee('Branch Shop 2');
        $response->assertSee('₦700,000'); // Branch B Valuation
        $response->assertSee('20 units on shelves');
    }
}
