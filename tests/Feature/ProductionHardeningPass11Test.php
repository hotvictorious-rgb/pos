<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Activity;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningPass11Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Warehouse $warehouseA1;
    protected Warehouse $warehouseA2;
    protected Warehouse $warehouseB1;
    protected User $adminA;
    protected User $cashierA1;
    protected User $cashierA2;
    protected User $storekeeperA1;
    protected User $adminB;
    protected User $cashierB1;
    protected Product $productA;
    protected Product $productB;
    protected Customer $customerA;
    protected Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => true,
            'saas.super_admin_email' => 'superadmin@vmarketplatform.ng',
        ]);

        // 1. Tenant A (Mega Retail)
        $this->tenantA = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-a-enterprise',
            'name' => 'Tenant Alpha Corp',
            'owner_email' => 'owner@alpha.ng',
            'status' => 'active',
            'plan' => 'enterprise',
            'max_branches' => 5,
            'max_users' => 20,
        ]);

        // 2. Tenant B (Competitor)
        $this->tenantB = Tenant::withoutGlobalScopes()->create([
            'id' => 'tenant-b-competitor',
            'name' => 'Tenant Beta Ltd',
            'owner_email' => 'owner@beta.ng',
            'status' => 'active',
            'plan' => 'starter',
            'max_branches' => 1,
            'max_users' => 3,
        ]);

        // 3. Warehouses for Tenant A
        $this->warehouseA1 = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Main Warehouse (Branch 1)',
            'code' => 'ALP-01',
            'is_active' => true,
        ]);

        $this->warehouseA2 = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Sub Depot (Branch 2)',
            'code' => 'ALP-02',
            'is_active' => true,
        ]);

        // 4. Warehouse for Tenant B
        $this->warehouseB1 = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Central Store (Branch 1)',
            'code' => 'BET-01',
            'is_active' => true,
        ]);

        // 5. Workers for Tenant A
        $this->adminA = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Executive Admin',
            'email' => 'admin@alpha.ng',
            'password' => Hash::make('AdminPass123!'),
            'role' => 'admin',
            'disabled' => false,
            'warehouse_id' => null, // Executive unscoped
            'permissions' => ['all' => true],
        ]);

        $this->cashierA1 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Cashier Branch 1',
            'email' => 'cashier1@alpha.ng',
            'password' => Hash::make('CashierPass123!'),
            'role' => 'cashier',
            'disabled' => false,
            'warehouse_id' => $this->warehouseA1->id,
            'permissions' => ['pos.view', 'pos.checkout', 'customer.write', 'transactions.view', 'debt.view', 'debt.pay', 'returns.view', 'returns.process', 'stock.view', 'stock.transfer', 'stock.receive'],
        ]);

        $this->cashierA2 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Cashier Branch 2',
            'email' => 'cashier2@alpha.ng',
            'password' => Hash::make('CashierPass123!'),
            'role' => 'cashier',
            'disabled' => false,
            'warehouse_id' => $this->warehouseA2->id,
            'permissions' => ['pos.view', 'pos.checkout', 'customer.write', 'transactions.view', 'debt.view', 'debt.pay'],
        ]);

        $this->storekeeperA1 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Storekeeper Branch 1',
            'email' => 'storekeeper1@alpha.ng',
            'password' => Hash::make('StorekeeperPass123!'),
            'role' => 'storekeeper',
            'disabled' => false,
            'warehouse_id' => $this->warehouseA1->id,
            'permissions' => ['stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.adjust'],
        ]);

        // 6. Workers for Tenant B
        $this->adminB = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Admin Attacker',
            'email' => 'admin@beta.ng',
            'password' => Hash::make('BetaAdmin123!'),
            'role' => 'admin',
            'disabled' => false,
            'warehouse_id' => $this->warehouseB1->id,
            'permissions' => ['all' => true],
        ]);

        $this->cashierB1 = User::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Cashier Attacker',
            'email' => 'cashier1@beta.ng',
            'password' => Hash::make('BetaCashier123!'),
            'role' => 'cashier',
            'disabled' => false,
            'warehouse_id' => $this->warehouseB1->id,
            'permissions' => ['pos.view', 'pos.checkout', 'customer.write', 'transactions.view', 'debt.view', 'debt.pay'],
        ]);

        // 7. Products
        $this->productA = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'code' => 'PROD-ALPHA-01',
            'name' => 'Alpha Premium Engine Oil (5L)',
            'category' => 'Automotive',
            'unitPrice' => 25000,
            'currentStock' => 40,
            'minStockLevel' => 5,
            'archived' => false,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseA1->id,
            'physical_stock' => 30,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseA2->id,
            'physical_stock' => 10,
            'allocated_stock' => 0,
            'min_stock_alert' => 2,
        ]);

        $this->productB = Product::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'code' => 'PROD-BETA-01',
            'name' => 'Beta Standard Lubricant',
            'category' => 'Automotive',
            'unitPrice' => 18000,
            'currentStock' => 20,
            'minStockLevel' => 3,
            'archived' => false,
        ]);

        StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'product_id' => $this->productB->id,
            'warehouse_id' => $this->warehouseB1->id,
            'physical_stock' => 20,
            'allocated_stock' => 0,
            'min_stock_alert' => 3,
        ]);

        // 8. Customers
        $this->customerA = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_code' => 'CUST-A-01',
            'name' => 'Alhaji Musa Transport Ltd',
            'phone' => '08031112233',
            'total_debt' => 0,
        ]);

        $this->customerB = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_code' => 'CUST-B-01',
            'name' => 'Chief Okoro Motors',
            'phone' => '08029998877',
            'total_debt' => 0,
        ]);

        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA1->id,
        ]);
    }

    /**
     * PASS 11: HTTP PENETRATION 1 - Cross-Tenant IDOR/BOLA Attack Surface.
     * Attacks routes using Tenant B credentials against Tenant A entities.
     */
    public function test_http_cross_tenant_idor_penetration_attacks_fail_closed(): void
    {
        $this->actingAs($this->adminB);
        session(['tenant_id' => $this->tenantB->id]);

        // Attack 1: Tenant B Admin attacks Tenant A Product via POST /products/{id}
        $resProduct = $this->post("/products/{$this->productA->id}", [
            'name' => 'Attacker Overwritten Title',
            'category' => 'Hacked',
            'unitPrice' => 10,
        ]);
        $resProduct->assertNotFound();
        $this->assertEquals('Alpha Premium Engine Oil (5L)', $this->productA->fresh()->name);

        // Attack 2: Tenant B Cashier attacks POS checkout referencing Tenant A Product ID
        $this->actingAs($this->cashierB1);
        $resCheckout = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseB1->id,
            'items' => [['productId' => $this->productA->id, 'quantity' => 1]],
            'cashAmount' => 25000,
            'posAmount' => 0,
            'paidAmount' => 25000,
            'is_supplied' => 'yes',
        ]);
        $resCheckout->assertSessionHasErrors('error');

        // Attack 3: Tenant B Cashier attacks Tenant A Customer Debt Payment via POST /debts/pay/{id}
        $resDebt = $this->post(route('debts.pay', $this->customerA->id), [
            'amount' => 5000,
            'payment_method' => 'CASH',
        ]);
        // Since TenantScope is active for Tenant B, CustomerA cannot be found or has no branch debt
        $this->assertTrue($resDebt->isRedirect() || $resDebt->status() === 404);

        // Attack 4: Tenant B Admin attacks Tenant A User via POST /users/toggle-status/{id}
        $this->actingAs($this->adminB);
        $resToggle = $this->post(route('users.toggle', $this->cashierA1->id));
        $resToggle->assertNotFound();
        $this->assertFalse((bool) $this->cashierA1->fresh()->disabled);

        // Attack 5: Tenant B Admin attacks Tenant A User Password Reset via POST /users/reset-password/{id}
        $resReset = $this->post(route('users.reset.password', $this->cashierA1->id), [
            'new_password' => 'HackedPassword123!',
        ]);
        $resReset->assertNotFound();
    }

    /**
     * PASS 11: HTTP PENETRATION 2 - Strict Cross-Branch BOLA & NULL-Sale Boundary.
     * Cashier at Branch 1 cannot mutate resources belonging exclusively to Branch 2.
     */
    public function test_http_cross_branch_bola_penetration_and_strict_null_debt_boundary(): void
    {
        // 1. Create a legitimate debt sale at Branch 2
        $this->actingAs($this->cashierA2);
        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA2->id,
        ]);
        $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseA2->id,
            'items' => [['productId' => $this->productA->id, 'quantity' => 2]], // 50,000
            'customerId' => $this->customerA->id,
            'customerName' => $this->customerA->name,
            'customerPhone' => $this->customerA->phone,
            'cashAmount' => 10000,
            'posAmount' => 0,
            'paidAmount' => 10000,
            'is_supplied' => 'yes',
        ]);
        $this->customerA->refresh();
        $this->assertEquals(40000, (float) $this->customerA->total_debt);

        // 2. Attack: Cashier at Branch 1 attempts to record debt payment for Branch 2 customer
        $this->actingAs($this->cashierA1);
        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA1->id,
        ]);
        $resBranchDebt = $this->post(route('debts.pay', $this->customerA->id), [
            'amount' => 5000,
            'payment_method' => 'CASH',
        ]);
        $resBranchDebt->assertSessionHasErrors('error');
        $this->assertStringContainsString('no outstanding invoices at your assigned branch', session('errors')->first('error'));

        // 3. Attack: Legacy/Unassigned Sale (warehouse_id IS NULL) must NOT be collectable by Branch 1 Cashier!
        $customerNull = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_code' => 'CUST-NULL-01',
            'name' => 'Legacy NULL Customer',
            'phone' => '08039991122',
            'total_debt' => 20000,
        ]);
        Sale::withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'warehouse_id' => null, // NULL legacy warehouse
            'userId' => $this->adminA->id,
            'userName' => $this->adminA->name,
            'customerId' => $customerNull->id,
            'customerName' => $customerNull->name,
            'totalAmount' => 20000,
            'paidAmount' => 0,
            'tenderedAmount' => 0,
            'changeAmount' => 0,
            'cashAmount' => 0,
            'posAmount' => 0,
            'status' => 'PENDING',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => now()->toIso8601String(),
        ]);

        $resNullPay = $this->post(route('debts.pay', $customerNull->id), [
            'amount' => 5000,
            'payment_method' => 'CASH',
        ]);
        // Strict isolation rejects branch cashiers on NULL warehouse sales
        $resNullPay->assertSessionHasErrors('error');
        $this->assertStringContainsString('no outstanding invoices at your assigned branch', session('errors')->first('error'));

        // 4. Verification: Executive unscoped Admin CAN collect on legacy NULL warehouse debt
        $this->actingAs($this->adminA);
        $resAdminPay = $this->post(route('debts.pay', $customerNull->id), [
            'amount' => 5000,
            'payment_method' => 'CASH',
        ]);
        $resAdminPay->assertSessionHasNoErrors();
        $this->assertEquals(15000, (float) $customerNull->fresh()->total_debt);
    }

    /**
     * PASS 11: HTTP PENETRATION 3 - Stock Transfer & Pickup Dispatch Authority.
     */
    public function test_http_cross_branch_transfer_and_dispatch_boundaries(): void
    {
        $this->actingAs($this->cashierA1);
        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA1->id,
        ]);

        // 1. Attack: Cashier at Branch 1 tries to dispatch transfer OUT of Branch 2
        $resBadTransfer = $this->post(route('stock.transfer.out'), [
            'source_warehouse_id' => $this->warehouseA2->id,
            'destination_warehouse_id' => $this->warehouseA1->id,
            'carrier_name' => 'Unauthorized Carrier',
            'items' => [['productId' => $this->productA->id, 'quantity' => 2]],
        ]);
        // Branch-scoped user source_warehouse_id is strictly overridden or rejected
        $this->assertTrue($resBadTransfer->isRedirect());

        // 2. Legitimate transfer from Branch 1 to Branch 2
        $resGoodTransfer = $this->post(route('stock.transfer.out'), [
            'destination_warehouse_id' => $this->warehouseA2->id,
            'carrier_name' => 'Licensed Carrier',
            'items' => [['productId' => $this->productA->id, 'quantity' => 2]],
        ]);
        $resGoodTransfer->assertSessionHasNoErrors();
        $transfer = Transfer::latest()->first();

        // 3. Attack: Cashier at Branch 1 tries to RECEIVE the transfer destined for Branch 2
        $resBadReceive = $this->post(route('stock.transfers.receive', $transfer->id), [
            'counted_items' => [$this->productA->id => 2],
        ]);
        $resBadReceive->assertSessionHasErrors('error');
        $this->assertEquals('DISPATCHED', $transfer->fresh()->status);
    }

    /**
     * PASS 11: HTTP PENETRATION 4 - Privileged Audit Trail Captures Exact Client IP.
     */
    public function test_http_privileged_audit_trail_captures_client_ip_address(): void
    {
        $this->actingAs($this->adminA);
        session(['tenant_id' => $this->tenantA->id]);

        // 1. Toggle status from IP 192.168.1.105
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.105'])
            ->post(route('users.toggle', $this->cashierA2->id));

        $activityToggle = Activity::where('type', 'USER_STATUS_CHANGED')->latest()->first();
        $this->assertNotNull($activityToggle);
        $this->assertStringContainsString('from IP 192.168.1.105', $activityToggle->description);

        // 2. Password reset from IP 10.0.5.99
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.5.99'])
            ->post(route('users.reset.password', $this->cashierA2->id), [
                'new_password' => 'SafeNewPassword99!',
            ]);

        $activityReset = Activity::where('type', 'PASSWORD_RESET')->latest()->first();
        $this->assertNotNull($activityReset);
        $this->assertStringContainsString('from IP 10.0.5.99', $activityReset->description);
    }

    /**
     * PASS 11: HTTP PENETRATION 5 - Price Tampering Immunity & Dead Hook Defense.
     */
    public function test_http_checkout_zero_bypass_price_tampering_immunity(): void
    {
        $this->actingAs($this->cashierA1);
        session([
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA1->id,
        ]);

        // Attacker attempts to checkout ₦25,000 item for ₦100 by tampering unitPrice and authorized_unit_price
        $res = $this->post(route('pos.checkout'), [
            'warehouse_id' => $this->warehouseA1->id,
            'items' => [[
                'productId' => $this->productA->id,
                'quantity' => 1,
                'unitPrice' => 100, // Tampered client price
                'authorized_unit_price' => 100, // Malicious injected key
            ]],
            'cashAmount' => 25000,
            'posAmount' => 0,
            'paidAmount' => 25000,
            'is_supplied' => 'yes',
        ]);
        $res->assertRedirect();

        $sale = Sale::latest()->first();
        // Server catalog price of ₦25,000 MUST be enforced, ignoring both unitPrice and authorized_unit_price
        $this->assertEquals(25000.00, (float) $sale->totalAmount);
        $saleItem = SaleItem::where('saleId', $sale->id)->first();
        $this->assertEquals(25000.00, (float) $saleItem->unitPrice);
        $this->assertEquals(25000.00, (float) $saleItem->totalPrice);
    }

    /**
     * PASS 11: TEST 6 - Real-Time Stale Session Invalidation when User is Disabled.
     */
    public function test_real_time_session_invalidation_when_user_disabled(): void
    {
        // 1. Log in legitimately
        $this->actingAs($this->cashierA1);
        session([
            'user_id' => $this->cashierA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA1->id,
        ]);

        $res1 = $this->get(route('pos.index'));
        $res1->assertOk();

        // 2. Administrator disables account in database mid-session
        $this->cashierA1->disabled = true;
        $this->cashierA1->save();

        // 3. Next immediate request must be rejected and session cleared
        $res2 = $this->get(route('pos.index'));
        $res2->assertRedirect(route('login'));
        $this->assertNull(session('user_id'));
        $this->assertNull(session('tenant_id'));
        $this->assertFalse(\Illuminate\Support\Facades\Auth::check());
    }

    /**
     * PASS 11: TEST 7 - Real-Time Stale Session Invalidation when Tenant is Suspended.
     */
    public function test_real_time_session_invalidation_when_tenant_suspended(): void
    {
        // 1. Log in legitimately with active tenant
        $this->actingAs($this->cashierA1);
        session([
            'user_id' => $this->cashierA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            'active_warehouse_id' => $this->warehouseA1->id,
        ]);

        $res1 = $this->get(route('pos.index'));
        $res1->assertOk();

        // 2. Platform Administrator suspends tenant mid-session
        $this->tenantA->status = 'suspended';
        $this->tenantA->save();

        // 3. Next immediate request must be blocked and redirected to saas.suspended
        $res2 = $this->get(route('pos.index'));
        $res2->assertRedirect(route('saas.suspended'));
        $this->assertNull(session('user_id'));
        $this->assertNull(session('tenant_id'));
        $this->assertFalse(\Illuminate\Support\Facades\Auth::check());
    }

    /**
     * PASS 11: TEST 8 - Real-Time Role Demotion Enforces Immediate Forbidden.
     */
    public function test_real_time_role_demotion_enforces_immediate_forbidden(): void
    {
        // 1. User starts as Admin with session access to users management
        $this->actingAs($this->adminA);
        session([
            'user_id' => $this->adminA->id,
            'user_role' => 'admin',
            'tenant_id' => $this->tenantA->id,
        ]);

        $res1 = $this->get(route('users.index'));
        $res1->assertOk();

        // 2. Super admin demotes user to Cashier mid-session
        $this->adminA->role = 'cashier';
        $this->adminA->save();

        // 3. Next immediate Web request to /users must be restricted and redirected without needing manual re-login
        $res2 = $this->get(route('users.index'));
        $res2->assertRedirect(route('dashboard'));
        $res2->assertSessionHas('warning');

        // And API/JSON access must immediately return 403 Forbidden
        $resJson = $this->getJson(route('users.index'));
        $resJson->assertForbidden();

        // Session user_role must be updated to cashier on the fly
        $this->assertEquals('cashier', session('user_role'));
    }

    /**
     * PASS 11: TEST 9 - Real-Time Branch Assignment Clamping for Branch-Scoped User.
     */
    public function test_real_time_branch_assignment_clamping_for_branch_scoped_user(): void
    {
        // Cashier assigned to Branch 1
        $this->actingAs($this->cashierA1);
        session([
            'user_id' => $this->cashierA1->id,
            'user_role' => 'cashier',
            'tenant_id' => $this->tenantA->id,
            // Attacker sets stale or spoofed session active_warehouse_id to Branch 2
            'active_warehouse_id' => $this->warehouseA2->id,
            'warehouse_id' => $this->warehouseA2->id,
        ]);

        // Accessing any authenticated route must forcefully clamp session branch to Branch 1
        $this->get(route('pos.index'));

        $this->assertEquals($this->warehouseA1->id, session('active_warehouse_id'));
        $this->assertEquals($this->warehouseA1->id, session('warehouse_id'));
    }

    /**
     * PASS 11: TEST 10 - Structured Security Telemetry Captures Metadata JSON.
     */
    public function test_structured_security_telemetry_captures_metadata_json(): void
    {
        $this->actingAs($this->adminA);
        session(['tenant_id' => $this->tenantA->id]);

        // 1. Execute password reset with specific client headers
        $this->withHeaders([
            'X-Request-ID' => 'req-trace-pass11-999',
            'User-Agent'   => 'AntigravitySecurityAudit/1.0',
        ])->withServerVariables(['REMOTE_ADDR' => '172.16.0.42'])
            ->post(route('users.reset.password', $this->cashierA1->id), [
                'new_password' => 'Pass11VerifiedPassword99!',
            ]);

        $resetActivity = Activity::where('type', 'PASSWORD_RESET')->latest()->first();
        $this->assertNotNull($resetActivity);
        $this->assertIsArray($resetActivity->metadata);
        $this->assertEquals('172.16.0.42', $resetActivity->metadata['ip']);
        $this->assertEquals('AntigravitySecurityAudit/1.0', $resetActivity->metadata['user_agent']);
        $this->assertEquals('req-trace-pass11-999', $resetActivity->metadata['request_id']);
        $this->assertEquals($this->cashierA1->id, $resetActivity->metadata['target_user_id']);
        $this->assertEquals('PASSWORD_RESET', $resetActivity->metadata['action']);

        // 2. Execute status toggle
        $this->withHeaders([
            'X-Request-ID' => 'req-trace-toggle-123',
        ])->withServerVariables(['REMOTE_ADDR' => '172.16.0.88'])
            ->post(route('users.toggle', $this->cashierA1->id));

        $toggleActivity = Activity::where('type', 'USER_STATUS_CHANGED')->latest()->first();
        $this->assertNotNull($toggleActivity);
        $this->assertIsArray($toggleActivity->metadata);
        $this->assertEquals('172.16.0.88', $toggleActivity->metadata['ip']);
        $this->assertEquals('req-trace-toggle-123', $toggleActivity->metadata['request_id']);
        $this->assertEquals($this->cashierA1->id, $toggleActivity->metadata['target_user_id']);
    }
}
