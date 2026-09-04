<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\SalesReturn;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InventoryLog;
use App\Models\IdempotencyRecord;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use App\Services\IdempotencyService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;

class FinancialEventAndAuthorityConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $stockService;
    protected AccountingReportService $accountingService;
    protected IdempotencyService $idempotencyService;
    protected Tenant $tenant;
    protected Warehouse $branchA;
    protected Warehouse $branchB;
    protected User $cashierA;
    protected User $cashierB;
    protected User $adminUser;
    protected Product $productRice;
    protected Product $productOil;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@hysam.com',
        ]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);
        $this->idempotencyService = app(IdempotencyService::class);

        $this->tenant = Tenant::create([
            'id' => 'tenant-fin-consolidation',
            'name' => 'Consolidated Holdings Ltd',
            'owner_email' => 'finance@consolidated.ng',
            'plan' => 'enterprise',
            'status' => 'active',
            'max_branches' => 5,
            'max_users' => 10,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $this->branchA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch Central',
            'code' => 'WH-CTR-01',
            'is_active' => true,
        ]);

        $this->branchB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch Suburb',
            'code' => 'WH-SUB-02',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'id' => 'user-admin-fin',
            'tenant_id' => $this->tenant->id,
            'name' => 'Finance Director',
            'email' => 'fd@consolidated.ng',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'warehouse_id' => null,
            'disabled' => false,
        ]);

        $this->cashierA = User::create([
            'id' => 'user-cashier-a',
            'tenant_id' => $this->tenant->id,
            'name' => 'Cashier Alpha',
            'email' => 'cashierA@consolidated.ng',
            'password' => Hash::make('secret123'),
            'role' => 'cashier',
            'warehouse_id' => $this->branchA->id,
            'disabled' => false,
        ]);

        $this->cashierB = User::create([
            'id' => 'user-cashier-b',
            'tenant_id' => $this->tenant->id,
            'name' => 'Cashier Beta',
            'email' => 'cashierB@consolidated.ng',
            'password' => Hash::make('secret123'),
            'role' => 'cashier',
            'warehouse_id' => $this->branchB->id,
            'disabled' => false,
        ]);

        $this->productRice = Product::create([
            'id' => 'prod-rice-50kg',
            'tenant_id' => $this->tenant->id,
            'name' => 'Royal Stallion Rice 50kg',
            'code' => 'RICE-STALLION',
            'unitPrice' => 40000.00,
            'costPrice' => 35000.00,
            'category' => 'Grains',
            'currentStock' => 100,
            'minStockLevel' => 5,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $this->productOil = Product::create([
            'id' => 'prod-oil-25l',
            'tenant_id' => $this->tenant->id,
            'name' => 'Golden Penny Pure Oil 25L',
            'code' => 'OIL-GOLDEN-25L',
            'unitPrice' => 30000.00,
            'costPrice' => 26000.00,
            'category' => 'Edibles',
            'currentStock' => 50,
            'minStockLevel' => 5,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::updateOrCreate(
            ['product_id' => $this->productRice->id, 'warehouse_id' => $this->branchA->id],
            ['tenant_id' => $this->tenant->id, 'physical_stock' => 100, 'allocated_stock' => 0]
        );

        StockLevel::updateOrCreate(
            ['product_id' => $this->productOil->id, 'warehouse_id' => $this->branchA->id],
            ['tenant_id' => $this->tenant->id, 'physical_stock' => 50, 'allocated_stock' => 0]
        );

        StockLevel::updateOrCreate(
            ['product_id' => $this->productRice->id, 'warehouse_id' => $this->branchB->id],
            ['tenant_id' => $this->tenant->id, 'physical_stock' => 100, 'allocated_stock' => 0]
        );

        StockLevel::updateOrCreate(
            ['product_id' => $this->productOil->id, 'warehouse_id' => $this->branchB->id],
            ['tenant_id' => $this->tenant->id, 'physical_stock' => 50, 'allocated_stock' => 0]
        );

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // ─────────────────────────────────────────────────────────────
    // 1. RETURN DEBT REDUCTION DOUBLE-COUNTING PREVENTION
    // ─────────────────────────────────────────────────────────────

    public function test_debt_reduction_return_preserves_historical_gross_invoice_and_avoids_double_counting()
    {
        // Customer purchases 3 bags of rice @ ₦40,000 = ₦120,000 gross
        // Tender: ₦50,000 cash, leaving ₦70,000 debt
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alhaji Gambo',
            'phone' => '08031112233',
            'total_debt' => 0.0,
        ]);

        $sale = $this->stockService->recordSale(
            [
                'customerId' => $customer->id,
                'customerName' => $customer->name,
                'customerPhone' => $customer->phone,
                'cashAmount' => 50000.00,
                'posAmount' => 0.00,
            ],
            [['productId' => $this->productRice->id, 'quantity' => 3]],
            $this->branchA->id,
            true,
            $this->cashierA->id,
            $this->cashierA->name
        );

        $this->assertEquals(120000.00, (float) $sale->totalAmount);
        $this->assertEquals(50000.00, (float) $sale->paidAmount);
        $this->assertEquals(70000.00, (float) $customer->fresh()->total_debt);
        $this->assertEquals(70000.00, $this->accountingService->calculateInvoiceBalance($sale));

        // Return 1 bag (₦40,000) using DEBT_REDUCTION
        $return = $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productRice->id, 'quantity' => 1]],
            $this->branchA->id,
            'DEBT_REDUCTION',
            'Excess stock returned',
            $this->cashierA->id,
            $this->cashierA->name
        );

        $this->assertEquals(40000.00, (float) $return->refundAmount);

        // Core Invariant: Historical gross invoice amount ($sale->totalAmount) MUST NOT be mutated!
        $sale->refresh();
        $this->assertEquals(120000.00, (float) $sale->totalAmount, "Gross invoice MUST remain invariant ₦120,000.");
        $this->assertEquals(50000.00, (float) $sale->paidAmount, "Paid money amount MUST remain invariant ₦50,000.");

        // Authoritative accounting balance:
        // Net Invoice = ₦120,000 - ₦40,000 (return credit) = ₦80,000
        // Net Money Applied = ₦50,000
        // Remaining Balance = ₦80,000 - ₦50,000 = ₦30,000
        $derivedBalance = $this->accountingService->calculateInvoiceBalance($sale);
        $this->assertEquals(30000.00, $derivedBalance, "Invoice balance must be exactly ₦30,000 (NO double-counting reduction to -₦10,000).");

        // Customer debt must match derived invoice balance exactly
        $customer->refresh();
        $this->assertEquals(30000.00, (float) $customer->total_debt);

        $recon = $this->accountingService->reconcileCustomerDebt($customer);
        $this->assertTrue($recon['balanced'], "Customer debt must reconcile with zero variance.");
        $this->assertEquals(0.00, $recon['variance']);

        // Settle remaining ₦30,000 of debt via second return of 1 bag (where only ₦30,000 can reduce debt)
        // Attempting to reduce debt by more than ₦30,000 must be rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Outstanding balance on Sale #{$sale->id} is only ₦30,000.00");

        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productRice->id, 'quantity' => 1]], // ₦40,000 > ₦30,000 balance!
            $this->branchA->id,
            'DEBT_REDUCTION',
            'Excess return attempt',
            $this->cashierA->id,
            $this->cashierA->name
        );
    }

    // ─────────────────────────────────────────────────────────────
    // 2. CHECKOUT CONTROLLER SERVER-AUTHORITATIVE PRICING & DEBT
    // ─────────────────────────────────────────────────────────────

    public function test_checkout_discards_client_forged_total_and_enforces_debt_rules()
    {
        // Attacker sends forged totalAmount: 1, paidAmount: 1, items: 1 bag of ₦40,000 rice
        // as an anonymous 'Walk-in Customer' to bypass credit customer requirements
        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', [
            'warehouse_id' => $this->branchA->id,
            'is_supplied' => 'yes',
            'totalAmount' => 1.00, // Forged total!
            'paidAmount' => 1.00,
            'cashAmount' => 1.00,
            'customerName' => 'Walk-in Customer',
            'items' => [
                ['productId' => $this->productRice->id, 'quantity' => 1],
            ],
        ]);

        // Server authoritative calculation calculates ₦40,000 total, ₦1 paid, ₦39,999 debt!
        // Because debt exists, zero-bypass rule triggers: anonymous checkout is REJECTED with 422!
        $response->assertStatus(422);
        $this->assertStringContainsString('Registered Customer required', $response->json('error'));

        // Now provide valid 11-digit phone and customer name
        $responseValid = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->postJson('/pos/checkout', [
            'warehouse_id' => $this->branchA->id,
            'is_supplied' => 'yes',
            'totalAmount' => 1.00, // Forged total ignored
            'paidAmount' => 1.00,
            'cashAmount' => 1.00,
            'customerName' => 'Alhaji Registered',
            'customerPhone' => '08099887766',
            'items' => [
                ['productId' => $this->productRice->id, 'quantity' => 1],
            ],
        ]);

        $responseValid->assertStatus(200);
        $saleId = $responseValid->json('saleId');
        $sale = Sale::findOrFail($saleId);

        // Server-calculated total ₦40,000 was enforced over client's ₦1!
        $this->assertEquals(40000.00, (float) $sale->totalAmount);
        $this->assertEquals(1.00, (float) $sale->paidAmount);
        $this->assertEquals('PARTIAL', $sale->status);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. CRASH-SAFE DURABLE IDEMPOTENCY
    // ─────────────────────────────────────────────────────────────

    public function test_idempotency_commits_record_atomically_with_business_mutation()
    {
        $idempotencyKey = 'IDEM-FIN-ATOMIC-01';

        $payload = [
            'warehouse_id' => $this->branchA->id,
            'items' => [['productId' => $this->productRice->id, 'quantity' => 1]],
            'paidAmount' => 40000.00,
            'cashAmount' => 40000.00,
            'customerName' => 'Walk-in Customer',
            'is_supplied' => true,
        ];

        // 1. First execution
        $sale = $this->idempotencyService->execute(
            'pos_checkout',
            $idempotencyKey,
            $this->tenant->id,
            $this->cashierA->id,
            $payload,
            function () use ($payload) {
                return $this->stockService->recordSale(
                    ['cashAmount' => 40000.00, 'customerName' => 'Walk-in Customer'],
                    [['productId' => $this->productRice->id, 'quantity' => 1]],
                    $this->branchA->id,
                    true,
                    $this->cashierA->id,
                    $this->cashierA->name
                );
            }
        );

        $this->assertNotNull($sale);

        // Check L2 persistent record state
        $record = IdempotencyRecord::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals('COMPLETED', $record->status);

        // 2. Replay with same key returns cached result without creating duplicate sale
        $initialSaleCount = Sale::where('tenant_id', $this->tenant->id)->count();

        $replayedSale = $this->idempotencyService->execute(
            'pos_checkout',
            $idempotencyKey,
            $this->tenant->id,
            $this->cashierA->id,
            $payload,
            function () {
                $this->fail("Callback must never be executed on idempotent replay!");
            }
        );

        $this->assertEquals($sale->id, $replayedSale->id);
        $this->assertEquals($initialSaleCount, Sale::where('tenant_id', $this->tenant->id)->count());
    }

    // ─────────────────────────────────────────────────────────────
    // 4. CANONICAL PRODUCT CSV IMPORT STOCK MUTATION
    // ─────────────────────────────────────────────────────────────

    public function test_csv_product_import_routes_stock_through_canonical_stock_service()
    {
        $csvContent = "name,code,category,brand,size,unitPrice,minStockLevel,initial_stock\n" .
                      "Premium Brown Beans (50kg),BEANS-BR-50,Legumes,Dangote,50kg Bag,65000,5,25\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->actingAs($this->adminUser)->withSession([
            'user_id' => $this->adminUser->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant',
        ])->post('/products/import/csv', [
            'csv_file' => $file,
            'warehouse_id' => $this->branchA->id,
        ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::where('code', 'BEANS-BR-50')->first();
        $this->assertNotNull($product);
        $this->assertEquals(25, $product->currentStock);

        // Verify StockLevel was updated
        $stock = StockLevel::where('product_id', $product->id)->where('warehouse_id', $this->branchA->id)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(25, $stock->physical_stock);

        // Verify canonical InventoryLog was created by StockService
        $log = InventoryLog::where('productId', $product->id)->where('type', 'STOCK_IN')->first();
        $this->assertNotNull($log, "Canonical InventoryLog must be recorded for CSV imported stock.");
        $this->assertEquals(25, $log->quantity);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. BRANCH-SCOPED DEBT CONTROLLER IS STRICTLY ID-BASED
    // ─────────────────────────────────────────────────────────────

    public function test_debt_controller_branch_scoping_is_strictly_id_based()
    {
        // Customer Alpha buys at Branch A on credit
        $customerA = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Debtor Branch A',
            'phone' => '08011223344',
            'total_debt' => 40000.0,
        ]);

        $this->stockService->recordSale(
            ['customerId' => $customerA->id, 'customerName' => $customerA->name, 'customerPhone' => $customerA->phone, 'cashAmount' => 0],
            [['productId' => $this->productRice->id, 'quantity' => 1]],
            $this->branchA->id,
            true,
            $this->cashierA->id,
            $this->cashierA->name
        );

        // Customer Beta buys at Branch B on credit
        $customerB = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Debtor Branch B',
            'phone' => '08055667788',
            'total_debt' => 30000.0,
        ]);

        $this->stockService->recordSale(
            ['customerId' => $customerB->id, 'customerName' => $customerB->name, 'customerPhone' => $customerB->phone, 'cashAmount' => 0],
            [['productId' => $this->productOil->id, 'quantity' => 1]],
            $this->branchB->id,
            true,
            $this->cashierB->id,
            $this->cashierB->name
        );

        // Cashier A views /debts (scoped to branch A)
        $response = $this->actingAs($this->cashierA)->withSession([
            'user_id' => $this->cashierA->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenant->id,
            'portal' => 'tenant-employee',
        ])->get('/debts');

        $response->assertStatus(200);
        $debtors = $response->viewData('debtors');

        // Must see Customer A
        $debtorIds = collect($debtors->items())->pluck('id')->all();
        $this->assertContains($customerA->id, $debtorIds);
        // Must NOT see Customer B from Branch B!
        $this->assertNotContains($customerB->id, $debtorIds, "Branch A staff must not see Branch B debtors.");
    }

    // ─────────────────────────────────────────────────────────────
    // 6. RESERVATION LIFECYCLE RECONCILIATION INVARIANT
    // ─────────────────────────────────────────────────────────────

    public function test_reservation_lifecycle_and_reconciliation_invariant()
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Malam Sani',
            'phone' => '08077665544',
            'total_debt' => 0.0,
        ]);

        // 1. Customer purchases 5 bags unsupplied (buffer goods)
        $sale = $this->stockService->recordSale(
            ['customerId' => $customer->id, 'customerName' => $customer->name, 'customerPhone' => $customer->phone, 'cashAmount' => 200000.00],
            [['productId' => $this->productRice->id, 'quantity' => 5]],
            $this->branchA->id,
            false, // Unsupplied sale
            $this->cashierA->id,
            $this->cashierA->name
        );

        $stock = StockLevel::where('product_id', $this->productRice->id)->where('warehouse_id', $this->branchA->id)->first();
        $this->assertEquals(5, $stock->allocated_stock);

        // Initial reconciliation
        $recon1 = $this->accountingService->reconcileReservationAllocations($this->productRice->id, $this->branchA->id);
        $this->assertTrue($recon1['balanced']);
        $this->assertEquals(0, $recon1['variance']);
        $this->assertEquals(5, $recon1['allocatedStock']);
        $this->assertEquals(5, $recon1['sumOutstanding']);

        // 2. Customer collects 2 units
        $this->stockService->fulfillStockReservation(
            $sale->id,
            $this->productRice->id,
            $this->branchA->id,
            2,
            $this->cashierA->id,
            $this->cashierA->name
        );

        $stock->refresh();
        $this->assertEquals(3, $stock->allocated_stock);

        $recon2 = $this->accountingService->reconcileReservationAllocations($this->productRice->id, $this->branchA->id);
        $this->assertTrue($recon2['balanced']);
        $this->assertEquals(0, $recon2['variance']);
        $this->assertEquals(3, $recon2['allocatedStock']);
        $this->assertEquals(3, $recon2['sumOutstanding']);

        // 3. Customer returns/cancels 1 unsupplied unit
        $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productRice->id, 'quantity' => 1]],
            $this->branchA->id,
            'CASH_REFUND',
            'Customer cancelled 1 buffer bag',
            $this->cashierA->id,
            $this->cashierA->name
        );

        $stock->refresh();
        $this->assertEquals(2, $stock->allocated_stock);

        $recon3 = $this->accountingService->reconcileReservationAllocations($this->productRice->id, $this->branchA->id);
        $this->assertTrue($recon3['balanced']);
        $this->assertEquals(0, $recon3['variance']);
        $this->assertEquals(2, $recon3['allocatedStock']);
        $this->assertEquals(2, $recon3['sumOutstanding']);
    }
}
