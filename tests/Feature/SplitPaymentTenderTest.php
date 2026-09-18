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
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SplitPaymentTenderTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected User $cashier;
    protected Product $product;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@test.com',
        ]);

        Tenant::withoutGlobalScopes()->firstOrCreate(['id' => 'default-tenant'], [
            'name' => 'Platform HQ',
            'owner_email' => 'superadmin@test.com',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenant = Tenant::firstOrCreate(['id' => 'tenant-split-' . Str::random(4)], [
            'name' => 'Victory Market Ltd',
            'owner_email' => 'owner-' . Str::random(4) . '@victory.ng',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nwaniba Branch',
            'code' => 'NW-01',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Elizabeth',
            'email' => 'elizabeth-' . Str::random(4) . '@victory.ng',
            'password' => Hash::make('password123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Corona Mattress (75x54x14)',
            'code' => 'M14CP',
            'category' => 'Mattresses',
            'unitPrice' => 100000.00,
            'costPrice' => 75000.00,
            'currentStock' => 20,
            'archived' => false,
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chief Okon',
            'phone' => '08031234567',
            'total_debt' => 0.00,
            'credit_limit' => 500000.00,
        ]);
    }

    /**
     * Test 1: Exact Split Payment (Cash ₦40,000 + POS ₦60,000 on ₦100,000 invoice).
     * Reconciles to exactly ₦0 debt with 2 granular payment records.
     */
    public function test_exact_split_payment_checkout()
    {
        $response = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id])
            ->post(route('pos.checkout'), [
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'productId' => $this->product->id,
                        'quantity' => 1,
                        'unitPrice' => 100000.00,
                    ]
                ],
                'cashAmount' => 40000.00,
                'posAmount' => 60000.00,
                'paidAmount' => 100000.00,
                'totalAmount' => 100000.00,
                'is_supplied' => 'yes',
                'customerName' => 'Walk-in Customer',
            ]);

        $response->assertSessionHasNoErrors();

        $sale = Sale::where('warehouse_id', $this->warehouse->id)->latest('createdAt')->first();
        $this->assertNotNull($sale);
        $this->assertEquals(100000.00, (float) $sale->totalAmount);
        $this->assertEquals(100000.00, (float) $sale->paidAmount);
        $this->assertEquals(40000.00, (float) $sale->cashAmount);
        $this->assertEquals(60000.00, (float) $sale->posAmount);
        $this->assertEquals(0.00, (float) $sale->changeAmount);

        // Verify two separate payments materialized
        $cashPayment = Payment::where('saleId', $sale->id)->where('method', 'CASH')->first();
        $posPayment = Payment::where('saleId', $sale->id)->where('method', 'POS')->first();

        $this->assertNotNull($cashPayment, 'Cash payment record must be created');
        $this->assertEquals(40000.00, (float) $cashPayment->amount);

        $this->assertNotNull($posPayment, 'POS payment record must be created');
        $this->assertEquals(60000.00, (float) $posPayment->amount);

        // Authoritative invoice balance must be 0
        $this->assertEquals(0.00, $sale->invoice_balance);
    }

    /**
     * Test 2: Split Payment with Cash Over-tender and Change.
     * Cash ₦50,000 + POS ₦60,000 on ₦100,000 invoice => Change = ₦10,000, Retained Cash = ₦40,000.
     */
    public function test_split_payment_with_cash_change()
    {
        $response = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id])
            ->post(route('pos.checkout'), [
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'productId' => $this->product->id,
                        'quantity' => 1,
                        'unitPrice' => 100000.00,
                    ]
                ],
                'cashAmount' => 50000.00,
                'posAmount' => 60000.00,
                'paidAmount' => 100000.00,
                'totalAmount' => 100000.00,
                'is_supplied' => 'yes',
                'customerName' => 'Walk-in Customer',
            ]);

        $response->assertSessionHasNoErrors();

        $sale = Sale::where('warehouse_id', $this->warehouse->id)->latest('createdAt')->first();
        $this->assertNotNull($sale);
        $this->assertEquals(100000.00, (float) $sale->totalAmount);
        $this->assertEquals(100000.00, (float) $sale->paidAmount);
        $this->assertEquals(10000.00, (float) $sale->changeAmount, '₦10,000 cash change must be recorded');
        $this->assertEquals(40000.00, (float) $sale->cashAmount, 'Retained cash must be ₦40,000 after change');
        $this->assertEquals(60000.00, (float) $sale->posAmount);

        // Cash payment in drawer must reflect net cash retained (₦40,000)
        $cashPayment = Payment::where('saleId', $sale->id)->where('method', 'CASH')->first();
        $this->assertNotNull($cashPayment);
        $this->assertEquals(40000.00, (float) $cashPayment->amount);

        $posPayment = Payment::where('saleId', $sale->id)->where('method', 'POS')->first();
        $this->assertNotNull($posPayment);
        $this->assertEquals(60000.00, (float) $posPayment->amount);
    }

    /**
     * Test 3: Split Payment with Partial Debt.
     * Cash ₦20,000 + POS ₦30,000 on ₦100,000 invoice => Paid ₦50,000, Debt ₦50,000 on customer account.
     */
    public function test_split_payment_with_partial_debt()
    {
        $response = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id])
            ->post(route('pos.checkout'), [
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'productId' => $this->product->id,
                        'quantity' => 1,
                        'unitPrice' => 100000.00,
                    ]
                ],
                'cashAmount' => 20000.00,
                'posAmount' => 30000.00,
                'paidAmount' => 50000.00,
                'totalAmount' => 100000.00,
                'is_supplied' => 'yes',
                'customerId' => $this->customer->id,
                'customerName' => $this->customer->name,
                'customerPhone' => $this->customer->phone,
                'receipt_ref' => 'BOOKLET-889',
            ]);

        $response->assertSessionHasNoErrors();

        $sale = Sale::where('warehouse_id', $this->warehouse->id)->latest('createdAt')->first();
        $this->assertNotNull($sale);
        $this->assertEquals(100000.00, (float) $sale->totalAmount);
        $this->assertEquals(50000.00, (float) $sale->paidAmount);
        $this->assertEquals(20000.00, (float) $sale->cashAmount);
        $this->assertEquals(30000.00, (float) $sale->posAmount);
        $this->assertEquals('PARTIAL', $sale->status);

        // Customer debt must increase by ₦50,000
        $this->customer->refresh();
        $this->assertEquals(50000.00, (float) $this->customer->total_debt);

        // Invoice balance must show ₦50,000
        $this->assertEquals(50000.00, $sale->invoice_balance);
    }

    /**
     * Test 4: Under-tender Mismatch Rejected.
     * Declared paidAmount is ₦100,000, but tender sum is only ₦20,000 => must error out.
     */
    public function test_underpaid_tender_mismatch_rejected()
    {
        $response = $this->actingAs($this->cashier)
            ->withSession(['tenant_id' => $this->tenant->id, 'active_warehouse_id' => $this->warehouse->id])
            ->post(route('pos.checkout'), [
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    [
                        'productId' => $this->product->id,
                        'quantity' => 1,
                        'unitPrice' => 100000.00,
                    ]
                ],
                'cashAmount' => 10000.00,
                'posAmount' => 10000.00,
                'paidAmount' => 100000.00,
                'totalAmount' => 100000.00,
                'is_supplied' => 'yes',
                'customerName' => 'Walk-in Customer',
            ]);

        $response->assertSessionHasErrors(['error']);
    }
}
