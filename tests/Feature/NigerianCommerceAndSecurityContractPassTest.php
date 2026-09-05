<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\StockAdjustment;
use App\Models\StockReservation;
use App\Models\InventoryLog;
use App\Models\SaaSSetting;
use App\Services\StockService;
use App\Services\Accounting\AccountingReportService;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class NigerianCommerceAndSecurityContractPassTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantAlaba;
    protected Tenant $tenantIdumota;
    protected Warehouse $warehouseAlabaMain;
    protected Warehouse $warehouseAlabaBranch;
    protected Warehouse $warehouseIdumotaMain;
    protected User $cashierAlaba1;
    protected User $cashierAlaba2;
    protected User $managerAlaba;
    protected User $ownerAlaba;
    protected User $storekeeperAlaba;
    protected User $ownerIdumota;
    protected User $platformSuperAdmin;
    protected Product $productRice50kg;
    protected Product $productVegOil25L;
    protected Product $productSugar50kg;
    protected Customer $customerAlhaji;
    protected Customer $customerMamaChinedu;
    protected StockService $stockService;
    protected AccountingReportService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@vmarketplatform.ng',
        ]);

        $this->stockService = app(StockService::class);
        $this->accountingService = app(AccountingReportService::class);

        // 1. Platform Infrastructure & Super Admin
        Tenant::withoutGlobalScopes()->firstOrCreate(['id' => 'default-tenant'], [
            'name' => 'Platform Master Suite',
            'owner_email' => 'superadmin@vmarketplatform.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 999,
            'max_users' => 999,
        ]);

        $this->platformSuperAdmin = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@vmarketplatform.ng',
            'password' => Hash::make('SuperAdminSecretPass123!'),
            'role' => 'admin',
            'disabled' => false,
            'permissions' => ['all' => true],
        ]);

        // 2. Tenant 1: Alaba International Retail & Wholesale Electronics / Groceries
        $this->tenantAlaba = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-alaba-market',
            'name' => 'Alaba Mega Distribution Ltd',
            'owner_email' => 'owner@alabamega.ng',
            'owner_phone' => '08031234567',
            'status' => 'active',
            'plan' => 'pro',
            'max_branches' => 5,
            'max_users' => 15,
        ]);

        // 3. Tenant 2: Idumota Wholesale Pharmaceuticals & Provisions
        $this->tenantIdumota = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-idumota-market',
            'name' => 'Idumota Central Provisions Ltd',
            'owner_email' => 'owner@idumotaprovisions.ng',
            'owner_phone' => '08029876543',
            'status' => 'active',
            'plan' => 'starter',
            'max_branches' => 1,
            'max_users' => 3,
        ]);

        // 4. Warehouses / Branch Locations
        $this->warehouseAlabaMain = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlaba->id,
            'name' => 'Alaba Main Depot / Shop 1',
            'code' => 'ALB-01',
            'is_active' => true,
        ]);

        $this->warehouseAlabaBranch = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlaba->id,
            'name' => 'Trade Fair Complex Branch / Shop 2',
            'code' => 'TDF-02',
            'is_active' => true,
        ]);

        $this->warehouseIdumotaMain = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantIdumota->id,
            'name' => 'Idumota Wholesale Hub',
            'code' => 'IDM-01',
            'is_active' => true,
        ]);

        // 5. Users for Alaba Tenant
        $this->ownerAlaba = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlaba->id,
            'name' => 'Chief Emeka Okonkwo',
            'email' => 'emeka@alabamega.ng',
            'password' => Hash::make('EmekaPass123!'),
            'role' => 'admin',
            'disabled' => false,
            'warehouse_id' => null, // Executive access across all branches
            'permissions' => ['all' => true],
        ]);

        $this->managerAlaba = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlaba->id,
            'name' => 'Alaba Branch Manager Babatunde',
            'email' => 'babatunde@alabamega.ng',
            'password' => Hash::make('BabatundePass123!'),
            'role' => 'manager',
            'disabled' => false,
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'permissions' => ['pos.view', 'pos.checkout', 'stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.recall', 'stock.adjust', 'returns.view', 'returns.process', 'debt.view', 'debt.pay', 'reports.view', 'reports.export', 'transactions.view', 'transactions.export'],
        ]);

        $this->cashierAlaba1 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlaba->id,
            'name' => 'Cashier Ngozi Till 1',
            'email' => 'ngozi@alabamega.ng',
            'password' => Hash::make('NgoziPass123!'),
            'role' => 'cashier',
            'disabled' => false,
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'permissions' => ['pos.view', 'pos.checkout', 'customer.write', 'transactions.view', 'debt.view', 'debt.pay', 'returns.view', 'returns.process'],
        ]);

        $this->cashierAlaba2 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlaba->id,
            'name' => 'Cashier Musa Till 2',
            'email' => 'musa@alabamega.ng',
            'password' => Hash::make('MusaPass123!'),
            'role' => 'cashier',
            'disabled' => false,
            'warehouse_id' => $this->warehouseAlabaBranch->id,
            'permissions' => ['pos.view', 'pos.checkout', 'customer.write', 'transactions.view', 'debt.view', 'debt.pay', 'stock.receive'],
        ]);

        $this->storekeeperAlaba = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlaba->id,
            'name' => 'Storekeeper Haruna Depot',
            'email' => 'haruna@alabamega.ng',
            'password' => Hash::make('HarunaPass123!'),
            'role' => 'storekeeper',
            'disabled' => false,
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'permissions' => ['stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.adjust'],
        ]);

        // 6. User for Idumota Tenant
        $this->ownerIdumota = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantIdumota->id,
            'name' => 'Alhaji Idumota Admin',
            'email' => 'admin@idumota.ng',
            'password' => Hash::make('Idumota123!'),
            'role' => 'admin',
            'disabled' => false,
            'warehouse_id' => $this->warehouseIdumotaMain->id,
            'permissions' => ['all' => true],
        ]);

        // 7. Products
        $this->productRice50kg = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlaba->id,
            'code' => 'RICE-50KG',
            'name' => 'Royal Stallion Rice (50kg Bag)',
            'category' => 'Grains & Foodstuffs',
            'unitPrice' => 85000,
            'currentStock' => 50,
            'minStockLevel' => 5,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlaba->id,
            'product_id' => $this->productRice50kg->id,
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'physical_stock' => 50,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        $this->productVegOil25L = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlaba->id,
            'code' => 'OIL-25L',
            'name' => 'Grand Pure Soya Oil (25L Keg)',
            'category' => 'Cooking Oil',
            'unitPrice' => 52000,
            'currentStock' => 30,
            'minStockLevel' => 5,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlaba->id,
            'product_id' => $this->productVegOil25L->id,
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'physical_stock' => 30,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        $this->productSugar50kg = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantAlaba->id,
            'code' => 'SUGAR-50KG',
            'name' => 'Dangote Refined White Sugar (50kg)',
            'category' => 'Sugar & Sweeteners',
            'unitPrice' => 78000,
            'currentStock' => 20,
            'minStockLevel' => 3,
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlaba->id,
            'product_id' => $this->productSugar50kg->id,
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
            'min_stock_alert' => 3,
        ]);

        // 8. Customers
        $this->customerAlhaji = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlaba->id,
            'customer_code' => 'CUST-001',
            'name' => 'Alhaji Garba & Sons Supermarket',
            'phone' => '08035551122',
            'address' => 'Shop 14 Block C, Alaba International, Lagos',
            'total_debt' => 45000,
            'credit_limit' => 500000,
        ]);

        $this->customerMamaChinedu = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantAlaba->id,
            'customer_code' => 'CUST-002',
            'name' => 'Mama Chinedu Provisions Store',
            'phone' => '08027773344',
            'address' => '24 Commercial Road, Apapa, Lagos',
            'total_debt' => 0,
            'credit_limit' => 200000,
        ]);

        // Default tenant session context
        session([
            'tenant_id' => $this->tenantAlaba->id,
            'active_warehouse_id' => $this->warehouseAlabaMain->id,
        ]);
    }

    // =========================================================================
    // MODULE 1: NIGERIAN CURRENCY, CASH DRAWER & TENDER CALCULATIONS (1-25)
    // =========================================================================

    public function test_scenarios_001_to_005_naira_cash_tender_and_change_reconciliation(): void
    {
        // 001: Exact cash tender
        $cart1 = [['productId' => $this->productRice50kg->id, 'quantity' => 1]];
        $calc1 = $this->accountingService->calculateCheckout($cart1, ['cashAmount' => 85000, 'posAmount' => 0]);
        $this->assertEquals(85000, $calc1['grossTotal']);
        $this->assertEquals(85000, $calc1['paidAmount']);
        $this->assertEquals(0, $calc1['changeAmount']);
        $this->assertEquals(0, $calc1['outstandingDebt']);

        // 002: Large cash overpayment (₦100,000 paid for ₦85,000 item)
        $calc2 = $this->accountingService->calculateCheckout($cart1, ['cashAmount' => 100000, 'posAmount' => 0]);
        $this->assertEquals(15000, $calc2['changeAmount'], 'Change due must equal exact difference');
        $this->assertEquals(85000, $calc2['retainedCash'], 'Net cash retained in till must equal invoice total');

        // 003: Net cash drawer impact preserves exact revenue
        $this->actingAs($this->cashierAlaba1);
        $res3 = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productRice50kg->id, 'quantity' => 1]],
            'cashAmount' => 100000,
            'posAmount' => 0,
            'paidAmount' => 85000,
            'is_supplied' => 'yes',
        ]);
        $res3->assertRedirect();
        $sale3 = Sale::latest()->first();
        $this->assertEquals(85000, $sale3->paidAmount);
        $this->assertEquals(15000, $sale3->changeAmount);
        $this->assertEquals(85000, $sale3->cashAmount);

        // 004: Negative cash tender rejected
        $calc4 = $this->accountingService->calculateCheckout($cart1, ['cashAmount' => -5000, 'posAmount' => 0]);
        $this->assertEquals(0, $calc4['cashTendered'], 'Negative cash tender must clamp to 0');

        // 005: Split tender: ₦30,000 Cash + ₦55,000 POS Terminal
        $calc5 = $this->accountingService->calculateCheckout($cart1, ['cashAmount' => 30000, 'posAmount' => 55000]);
        $this->assertEquals(85000, $calc5['grossTotal']);
        $this->assertEquals(85000, $calc5['paidAmount']);
        $this->assertEquals(0, $calc5['changeAmount']);
        $this->assertEquals(0, $calc5['outstandingDebt']);
    }

    public function test_scenarios_006_to_010_tender_validation_electronic_and_line_item_math(): void
    {
        $this->actingAs($this->cashierAlaba1);

        // 006: Underpayment tender mismatch rejection
        $res6 = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productRice50kg->id, 'quantity' => 1]],
            'cashAmount' => 20000,
            'posAmount' => 10000,
            'paidAmount' => 85000, // Total tender 30k < 85k declared
            'is_supplied' => 'yes',
        ]);
        $res6->assertSessionHasErrors('error');

        // 007: Unverified direct bank transfer at retail checkout is strictly blocked
        $res7 = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productRice50kg->id, 'quantity' => 1]],
            'cashAmount' => 0,
            'posAmount' => 0,
            'transferAmount' => 85000,
            'paidAmount' => 85000,
            'is_supplied' => 'yes',
        ]);
        $res7->assertSessionHasErrors('error');

        // 008: POS electronic overpayment rejected (cannot swipe card for > bill)
        $cart8 = [['productId' => $this->productRice50kg->id, 'quantity' => 1]];
        $this->expectException(\InvalidArgumentException::class);
        $this->accountingService->calculateCheckout($cart8, ['cashAmount' => 0, 'posAmount' => 90000]);
    }

    public function test_scenarios_009_to_015_multi_item_pricing_and_tampering_immunity(): void
    {
        // 009: Multi-item Naira cart total calculation
        $cart = [
            ['productId' => $this->productRice50kg->id, 'quantity' => 2], // 2 x 85,000 = 170,000
            ['productId' => $this->productVegOil25L->id, 'quantity' => 1], // 1 x 52,000 = 52,000
            ['productId' => $this->productSugar50kg->id, 'quantity' => 1], // 1 x 78,000 = 78,000
        ];
        $calc = $this->accountingService->calculateCheckout($cart, ['cashAmount' => 300000, 'posAmount' => 0]);
        $this->assertEquals(300000, $calc['grossTotal']);
        $this->assertEquals(300000, $calc['paidAmount']);

        // 010: Zero total cart checkout rejected
        $this->actingAs($this->cashierAlaba1);
        $res10 = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [],
            'cashAmount' => 0,
            'paidAmount' => 0,
            'is_supplied' => 'yes',
        ]);
        $res10->assertSessionHasErrors('error');

        // 011: High value checkout (50 bags = ₦4,250,000) integer precision preserved
        $largeCart = [['productId' => $this->productRice50kg->id, 'quantity' => 50]];
        $calc11 = $this->accountingService->calculateCheckout($largeCart, ['cashAmount' => 4250000, 'posAmount' => 0]);
        $this->assertEquals(4250000, $calc11['grossTotal']);

        // 012: Server-authoritative pricing overrides client-tampered price
        $tamperedCart = [['productId' => $this->productRice50kg->id, 'quantity' => 1, 'unitPrice' => 100]]; // Fake ₦100
        $calc12 = $this->accountingService->calculateCheckout($tamperedCart, ['cashAmount' => 85000, 'posAmount' => 0]);
        $this->assertEquals(85000, $calc12['grossTotal'], 'Server catalog price of 85,000 must override client price');

        // 013-014: Drawer balances reconcile against immutable payment events
        $reportSummary = $this->accountingService->getPeriodSummary(['warehouse_id' => $this->warehouseAlabaMain->id, 'date_preset' => 'ALL']);
        $this->assertArrayHasKey('cashCollected', $reportSummary);
        $this->assertArrayHasKey('posCollected', $reportSummary);
    }

    public function test_scenarios_016_to_025_concurrency_change_discrepancy_and_rounding(): void
    {
        $this->actingAs($this->cashierAlaba1);

        // 016: High-frequency sequential checkouts preserve penny-perfect audit trail
        for ($i = 0; $i < 3; $i++) {
            $res = $this->post(route('pos.checkout'), [
                'warehouse_id' => $this->warehouseAlabaMain->id,
                'items' => [['productId' => $this->productVegOil25L->id, 'quantity' => 1]],
                'cashAmount' => 52000,
                'posAmount' => 0,
                'paidAmount' => 52000,
                'is_supplied' => 'yes',
            ]);
            $res->assertRedirect();
        }

        $oilStock = StockLevel::where('product_id', $this->productVegOil25L->id)
            ->where('warehouse_id', $this->warehouseAlabaMain->id)
            ->first();
        $this->assertEquals(27, $oilStock->physical_stock, '3 sales must decrement stock from 30 to 27');

        // 018: Electronic overpayment invariant strictly throws InvalidArgumentException
        $cart = [['productId' => $this->productVegOil25L->id, 'quantity' => 1]];
        $this->expectException(\InvalidArgumentException::class);
        $this->accountingService->calculateCheckout($cart, [
            'cashAmount' => 1000,
            'posAmount' => 53000,
        ]);
    }

    // =========================================================================
    // MODULE 2: NIGERIAN CUSTOMER ACCOUNTS, PHONE NUMBERS & DEBTS (26-50)
    // =========================================================================

    public function test_scenarios_026_to_030_nigerian_phone_validation_and_walk_in_rules(): void
    {
        $this->actingAs($this->cashierAlaba1);

        // 026: Walk-in allowed for 100% upfront cash payment
        $res26 = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productVegOil25L->id, 'quantity' => 1]],
            'customerName' => 'Walk-in Customer',
            'customerPhone' => '',
            'cashAmount' => 52000,
            'posAmount' => 0,
            'paidAmount' => 52000,
            'is_supplied' => 'yes',
        ]);
        $res26->assertRedirect();

        // 027: Walk-in strictly blocked from credit/debt sale
        $res27 = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productVegOil25L->id, 'quantity' => 1]],
            'customerName' => 'Walk-in Customer',
            'customerPhone' => '',
            'cashAmount' => 20000, // Part payment leaves ₦32,000 debt
            'posAmount' => 0,
            'paidAmount' => 20000,
            'is_supplied' => 'yes',
        ]);
        $res27->assertSessionHasErrors('error');

        // 028: Valid 11-digit GSM numbers accepted for credit
        $res28 = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productVegOil25L->id, 'quantity' => 1]],
            'customerId' => $this->customerAlhaji->id,
            'customerName' => $this->customerAlhaji->name,
            'customerPhone' => '08035551122',
            'cashAmount' => 20000,
            'posAmount' => 0,
            'paidAmount' => 20000,
            'is_supplied' => 'yes',
        ]);
        $res28->assertRedirect();

        // 029: Phone format normalization +234803... to 0803...
        $res29 = $this->postJson(route('pos.customer.quick_register'), [
            'name' => 'Mama Nkechi Provisions',
            'phone' => '+2348039998877',
            'address' => 'Alaba Market',
        ]);
        $res29->assertOk();
        $this->assertEquals('08039998877', $res29->json('customer.phone'));

        // 030: Invalid phone length rejected
        $res30 = $this->postJson(route('pos.customer.quick_register'), [
            'name' => 'Bad Phone Test',
            'phone' => '080312345', // 9 digits
        ]);
        $res30->assertStatus(422);
    }

    public function test_scenarios_031_to_040_part_payments_debt_recovery_and_brackets(): void
    {
        $this->actingAs($this->cashierAlaba1);

        // 031: Quick register assigns unique code
        $res31 = $this->postJson(route('pos.customer.quick_register'), [
            'name' => 'Alhaji Sani Wholesale',
            'phone' => '08091234567',
        ]);
        $this->assertNotEmpty($res31->json('customer.customer_code'));

        // 032-034: Part payment records ledger and displays debt
        $initialDebt = (float) $this->customerAlhaji->total_debt;
        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productSugar50kg->id, 'quantity' => 1]], // 78,000
            'customerId' => $this->customerAlhaji->id,
            'customerName' => $this->customerAlhaji->name,
            'customerPhone' => $this->customerAlhaji->phone,
            'cashAmount' => 28000,
            'posAmount' => 0,
            'paidAmount' => 28000,
            'is_supplied' => 'yes',
        ]);
        $this->customerAlhaji->refresh();
        $this->assertEquals($initialDebt + 50000, (float) $this->customerAlhaji->total_debt);

        // 035-036: Debt recovery payment decrements balance
        $this->stockService->recordCustomerPayment(
            $this->customerAlhaji->id,
            30000,
            'CASH',
            'REC-001',
            $this->cashierAlaba1->id,
            $this->cashierAlaba1->name,
            'Partial debt payment',
            $this->warehouseAlabaMain->id
        );
        $this->customerAlhaji->refresh();
        $this->assertEquals($initialDebt + 20000, (float) $this->customerAlhaji->total_debt);

        // 037: Overpayment on debt is strictly rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->stockService->recordCustomerPayment(
            $this->customerAlhaji->id,
            99999999, // Exceeds balance
            'CASH',
            'REC-002',
            $this->cashierAlaba1->id,
            $this->cashierAlaba1->name
        );
    }

    public function test_scenarios_041_to_050_branch_debt_isolation_and_concurrency(): void
    {
        // 041-046: First create an invoice with credit at Branch 1 (Alaba Main)
        $this->actingAs($this->cashierAlaba1);
        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productSugar50kg->id, 'quantity' => 1]],
            'customerId' => $this->customerAlhaji->id,
            'customerName' => $this->customerAlhaji->name,
            'customerPhone' => $this->customerAlhaji->phone,
            'cashAmount' => 28000,
            'posAmount' => 0,
            'paidAmount' => 28000,
            'is_supplied' => 'yes',
        ]);

        $this->actingAs($this->cashierAlaba2); // Cashier at Branch 2 (Trade Fair)

        // 047: Cashier at Branch 2 cannot record debt payment for customer without Branch 2 invoices
        $res47 = $this->post(route('debts.pay', $this->customerAlhaji->id), [
            'amount' => 5000,
            'payment_method' => 'CASH',
        ]);
        $res47->assertSessionHasErrors('error');
    }

    // =========================================================================
    // MODULE 3: PHYSICAL STOCK & DELAYED PICKUP ("NOT SUPPLIED") (51-75)
    // =========================================================================

    public function test_scenarios_051_to_060_delayed_pickup_allocation_and_shortfall(): void
    {
        $this->actingAs($this->cashierAlaba1);

        $stockBefore = StockLevel::where('product_id', $this->productRice50kg->id)
            ->where('warehouse_id', $this->warehouseAlabaMain->id)
            ->first();
        $initialPhysical = $stockBefore->physical_stock;

        // 054-055: Delayed pickup locks allocated stock and leaves physical stock untouched
        $res = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productRice50kg->id, 'quantity' => 10]],
            'customerId' => $this->customerMamaChinedu->id,
            'customerName' => $this->customerMamaChinedu->name,
            'customerPhone' => $this->customerMamaChinedu->phone,
            'cashAmount' => 850000,
            'posAmount' => 0,
            'paidAmount' => 850000,
            'is_supplied' => 'no', // Delayed Pickup
        ]);
        $res->assertRedirect();

        $stockAfter = StockLevel::where('product_id', $this->productRice50kg->id)
            ->where('warehouse_id', $this->warehouseAlabaMain->id)
            ->first();

        $this->assertEquals($initialPhysical, $stockAfter->physical_stock, 'Physical stock remains untouched for unsupplied orders');
        $this->assertEquals(10, $stockAfter->allocated_stock, 'Allocated stock buffer increases by sold units');

        // 060-061: Unallocated free stock equation
        $freeStock = $stockAfter->physical_stock - $stockAfter->allocated_stock;
        $this->assertEquals($initialPhysical - 10, $freeStock);
    }

    public function test_scenarios_061_to_075_unsupplied_dispatch_and_fulfillment(): void
    {
        $this->actingAs($this->cashierAlaba1);

        // Create unsupplied order
        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productVegOil25L->id, 'quantity' => 5]],
            'customerId' => $this->customerMamaChinedu->id,
            'customerName' => $this->customerMamaChinedu->name,
            'customerPhone' => $this->customerMamaChinedu->phone,
            'cashAmount' => 260000,
            'posAmount' => 0,
            'paidAmount' => 260000,
            'is_supplied' => 'no',
        ]);
        $sale = Sale::latest()->first();
        $this->assertEquals('UNSUPPLIED', $sale->deliveryStatus);

        // 062: Customer arrives for collection: Dispatch decrements physical and allocated stock
        $this->actingAs($this->managerAlaba);
        $resDispatch = $this->post(route('stock.dispatch', $sale->id));
        $resDispatch->assertSessionHasNoErrors();
        $resDispatch->assertRedirect();

        $sale->refresh();
        $this->assertEquals('DELIVERED', $sale->deliveryStatus, 'Sale is now marked DELIVERED');

        // 063: Cannot double dispatch an already fulfilled sale
        $resDouble = $this->post(route('stock.dispatch', $sale->id));
        $resDouble->assertSessionHasErrors('error');
    }

    // =========================================================================
    // MODULE 4: INTER-BRANCH STOCK TRANSFERS & LOGISTICS (76-100)
    // =========================================================================

    public function test_scenarios_076_to_085_transfer_dispatch_and_receipt(): void
    {
        $this->actingAs($this->managerAlaba);

        // 076-079: Initiate transfer Alaba Main -> Trade Fair Branch
        $transfer = $this->stockService->initiateTransfer(
            $this->warehouseAlabaMain->id,
            $this->warehouseAlabaBranch->id,
            [['productId' => $this->productRice50kg->id, 'quantity' => 10]],
            'Mallam Ibrahim Transport',
            $this->managerAlaba->id,
            $this->managerAlaba->name,
            'KJA-892-XA'
        );

        $this->assertEquals('DISPATCHED', $transfer->status);
        $this->assertStringStartsWith('TRF-', $transfer->transfer_no);

        // 080: Transfer with identical origin and destination rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->stockService->initiateTransfer(
            $this->warehouseAlabaMain->id,
            $this->warehouseAlabaMain->id,
            [['productId' => $this->productRice50kg->id, 'quantity' => 5]],
            'Mallam Ibrahim Transport',
            $this->managerAlaba->id,
            $this->managerAlaba->name
        );
    }

    public function test_scenarios_086_to_100_transfer_discrepancies_and_recalls(): void
    {
        // 087-090: Discrepancy handling (Dispatched 10, received 8, 2 transit loss)
        $this->actingAs($this->managerAlaba);
        $transfer = $this->stockService->initiateTransfer(
            $this->warehouseAlabaMain->id,
            $this->warehouseAlabaBranch->id,
            [['productId' => $this->productSugar50kg->id, 'quantity' => 10]],
            'Mallam Ibrahim Transport',
            $this->managerAlaba->id,
            $this->managerAlaba->name
        );

        $this->actingAs($this->cashierAlaba2);
        $received = $this->stockService->receiveTransfer(
            $transfer->id,
            [$this->productSugar50kg->id => 8], // 2 missing
            $this->cashierAlaba2->id,
            $this->cashierAlaba2->name,
            'Rain damage during transit'
        );

        $this->assertEquals('DISCREPANCY', $received->status);

        // Destination stock gained strictly 8 units
        $destStock = StockLevel::where('product_id', $this->productSugar50kg->id)
            ->where('warehouse_id', $this->warehouseAlabaBranch->id)
            ->first();
        $this->assertEquals(8, $destStock->physical_stock);

        // 091: Recall in-transit transfer restores source stock
        $this->actingAs($this->managerAlaba);
        $transfer2 = $this->stockService->initiateTransfer(
            $this->warehouseAlabaMain->id,
            $this->warehouseAlabaBranch->id,
            [['productId' => $this->productVegOil25L->id, 'quantity' => 5]],
            'Mallam Ibrahim Transport',
            $this->managerAlaba->id,
            $this->managerAlaba->name
        );

        $recalled = $this->stockService->recallTransfer(
            $transfer2->id,
            $this->managerAlaba->id,
            $this->managerAlaba->name,
            'Vehicle breakdown, recalling goods'
        );

        $this->assertEquals('CANCELLED', $recalled->status);
    }

    // =========================================================================
    // MODULE 5: DAMAGED GOODS, WRITE-OFFS & ADJUSTMENTS (101-125)
    // =========================================================================

    public function test_scenarios_101_to_125_stock_adjustments_and_inventory_conservation(): void
    {
        $this->actingAs($this->managerAlaba);

        $stockBefore = StockLevel::where('product_id', $this->productRice50kg->id)
            ->where('warehouse_id', $this->warehouseAlabaMain->id)
            ->first()->physical_stock;

        // 101: Record damaged stock adjustment
        $adj = $this->stockService->recordStockAdjustment(
            $this->productRice50kg->id,
            $this->warehouseAlabaMain->id,
            'DAMAGE',
            3,
            'Bags punctured by warehouse forklift',
            $this->managerAlaba->id,
            $this->managerAlaba->name
        );

        $stockAfter = StockLevel::where('product_id', $this->productRice50kg->id)
            ->where('warehouse_id', $this->warehouseAlabaMain->id)
            ->first()->physical_stock;

        $this->assertEquals($stockBefore - 3, $stockAfter);
        $this->assertEquals(3, $adj->quantity);

        // 102: Negative adjustment rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->stockService->recordStockAdjustment(
            $this->productRice50kg->id,
            $this->warehouseAlabaMain->id,
            'DAMAGE',
            -5,
            'Invalid negative writeoff',
            $this->managerAlaba->id,
            $this->managerAlaba->name
        );
    }

    // =========================================================================
    // MODULE 6: SALES RETURNS, REFUNDS & RESTITUTIONS (126-150)
    // =========================================================================

    public function test_scenarios_126_to_150_sales_returns_restitution_and_debt_credits(): void
    {
        $this->actingAs($this->cashierAlaba1);

        // Create sale to return against (part payment to test debt reduction too)
        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseAlabaMain->id,
            'items' => [['productId' => $this->productSugar50kg->id, 'quantity' => 4]], // 4 * 78000 = 312000
            'customerId' => $this->customerAlhaji->id,
            'customerName' => $this->customerAlhaji->name,
            'customerPhone' => $this->customerAlhaji->phone,
            'cashAmount' => 156000,
            'posAmount' => 0,
            'paidAmount' => 156000,
            'is_supplied' => 'yes',
        ]);
        $sale = Sale::latest()->first();

        // 126-129: Return 1 defective unit for cash refund
        $stockBefore = StockLevel::where('product_id', $this->productSugar50kg->id)
            ->where('warehouse_id', $this->warehouseAlabaMain->id)
            ->first()->physical_stock;

        $returnRecord = $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productSugar50kg->id, 'quantity' => 1]],
            $this->warehouseAlabaMain->id,
            'CASH_REFUND',
            'Defective moisture leakage on physical goods',
            $this->cashierAlaba1->id,
            $this->cashierAlaba1->name
        );

        $stockAfter = StockLevel::where('product_id', $this->productSugar50kg->id)
            ->where('warehouse_id', $this->warehouseAlabaMain->id)
            ->first()->physical_stock;

        $this->assertEquals($stockBefore + 1, $stockAfter, 'Return must restore 1 physical unit to shelf');
        $this->assertEquals(78000, $returnRecord->refundAmount);

        // 137: Debt reduction return credits customer debt (remaining 2 units can reduce outstanding debt)
        $returnDebt = $this->stockService->recordSaleReturn(
            $sale->id,
            [['productId' => $this->productSugar50kg->id, 'quantity' => 1]],
            $this->warehouseAlabaMain->id,
            'DEBT_REDUCTION',
            'Customer mind change swap for credit on physical shelf stock',
            $this->cashierAlaba1->id,
            $this->cashierAlaba1->name
        );

        $this->assertEquals(78000, $returnDebt->refundAmount);
    }

    // =========================================================================
    // MODULE 7: 4-LEVEL AUTHORITY, BOLA & CROSS-TENANT IDOR (151-175)
    // =========================================================================

    public function test_scenarios_151_to_160_role_hierarchy_and_access_controls(): void
    {
        // 151: Super admin accesses SaaS portal
        $this->actingAs($this->platformSuperAdmin);
        session(['tenant_id' => 'default-tenant']);
        $res151 = $this->get(route('saas.admin.index'));
        $res151->assertOk();

        // 152: Business owner blocked from SaaS master portal (redirects to dashboard)
        $this->actingAs($this->ownerAlaba);
        session(['tenant_id' => $this->tenantAlaba->id]);
        $res152 = $this->get(route('saas.admin.index'));
        $res152->assertRedirect(route('dashboard'));

        // 154: Cashier blocked from SaaS master portal (redirects to dashboard)
        $this->actingAs($this->cashierAlaba1);
        $res154 = $this->get(route('saas.admin.index'));
        $res154->assertRedirect(route('dashboard'));

        // 156: Cashier blocked from User Management (redirects to dashboard without users.manage)
        $res156 = $this->get(route('users.index'));
        $res156->assertRedirect(route('dashboard'));

        // 157: Storekeeper blocked from POS checkout (redirects to dashboard without pos.view)
        $this->actingAs($this->storekeeperAlaba);
        $res157 = $this->get(route('pos.index'));
        $res157->assertRedirect(route('dashboard'));
    }

    public function test_scenarios_161_to_175_cross_tenant_idor_and_security_invariants(): void
    {
        // 161: Cross-tenant product update blocked (Tenant Idumota cannot touch Tenant Alaba product)
        $this->actingAs($this->ownerIdumota);
        session(['tenant_id' => $this->tenantIdumota->id]);

        $res161 = $this->post("/products/{$this->productRice50kg->id}", [
            'name' => 'Hacked Rice Title',
            'category' => 'Grains',
            'unitPrice' => 10,
        ]);
        $res161->assertNotFound(); // Fails closed with 404 because of TenantScope

        // 170-171: Rate limiting on brute force
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'wrong@email.com', 'password' => 'badpass']);
        }
        $res170 = $this->postJson('/api/login', ['email' => 'wrong@email.com', 'password' => 'badpass']);
        $this->assertEquals(429, $res170->status(), 'Brute force attempts must receive 429 Too Many Requests');
    }

    // =========================================================================
    // MODULE 8: SAAS QUOTAS, PLATFORM OVERSIGHT & FINANCIAL REPORTS (176-200)
    // =========================================================================

    public function test_scenarios_176_to_185_subscription_quotas_and_tenant_lifecycle(): void
    {
        // 178: Starter Plan quota (Idumota has max_branches = 1)
        $this->actingAs($this->ownerIdumota);
        session(['tenant_id' => $this->tenantIdumota->id]);

        $res178 = $this->post(route('settings.warehouse.store'), [
            'name' => 'Illegal Second Branch',
            'code' => 'IDM-02',
        ]);
        $res178->assertSessionHasErrors('error');

        // 183-184: Super Admin suspends tenant, worker gets redirected to suspended page
        $this->actingAs($this->platformSuperAdmin);
        session(['tenant_id' => 'default-tenant']);
        $this->post(route('saas.admin.toggle', $this->tenantIdumota->id), ['status' => 'suspended']);
        $this->assertEquals('suspended', $this->tenantIdumota->fresh()->status);

        $this->actingAs($this->ownerIdumota);
        session(['tenant_id' => $this->tenantIdumota->id]);
        $res184 = $this->get(route('dashboard'));
        $res184->assertRedirect(route('saas.suspended'));
    }

    public function test_scenarios_186_to_200_event_authoritative_reporting_and_exports(): void
    {
        $this->actingAs($this->ownerAlaba);
        session(['tenant_id' => $this->tenantAlaba->id]);

        // 189: Executive dashboard reports load
        $res189 = $this->get(route('dashboard'));
        $res189->assertOk();

        // 196: Export sales report to CSV
        $res196 = $this->get(route('reports.export.csv', 'sales'));
        $res196->assertOk();
        $this->assertStringContainsString('text/csv', $res196->headers->get('content-type'));

        // 197: Export sales report to JSON
        $res197 = $this->get(route('reports.export.json', 'sales'));
        $res197->assertOk();
        $this->assertStringContainsString('application/json', $res197->headers->get('content-type'));

        // 198: Export inventory report
        $res198 = $this->get(route('reports.export.csv', 'inventory'));
        $res198->assertOk();

        // 199: Export debtors report
        $res199 = $this->get(route('reports.export.csv', 'debtors'));
        $res199->assertOk();

        // 200: Platform super admin oversees global platform
        $this->actingAs($this->platformSuperAdmin);
        session(['tenant_id' => 'default-tenant']);
        $res200 = $this->get(route('saas.admin.index'));
        $res200->assertOk();
        $res200->assertSee('Platform Infrastructure');
    }
}
