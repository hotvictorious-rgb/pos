<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Activity;
use App\Services\Notification\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class WhatsAppDailyReconciliationReportTest extends TestCase
{
    use RefreshDatabase;

    protected WhatsAppService $whatsAppService;
    protected User $owner;
    protected Warehouse $warehouse;
    protected Setting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.enabled' => false,
            'whatsapp.enabled' => true,
            'whatsapp.gateway' => 'log',
        ]);

        $this->whatsAppService = app(WhatsAppService::class);

        $this->warehouse = Warehouse::create([
            'id' => 1,
            'tenant_id' => 'tenant-victorious',
            'name' => 'Victorious Mega Store',
            'code' => 'VIC-MEGA',
            'address' => '10 Abak Road, Uyo',
            'phone' => '08022223333',
            'manager_name' => 'Bassey Manager',
            'manager_whatsapp_phone' => '08099998888',
            'whatsapp_report_enabled' => true,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'id' => 'USR-OWNER',
            'tenant_id' => 'tenant-victorious',
            'name' => 'Elder Victorious (Owner)',
            'email' => 'owner@victoriousmarket.ng',
            'role' => 'owner',
            'password' => Hash::make('password123'),
            'warehouse_id' => 1,
            'permissions' => ['*'],
        ]);

        $this->setting = Setting::create([
            'id' => 1,
            'tenant_id' => 'tenant-victorious',
            'businessName' => 'Victorious Market Superstores',
            'businessAddress' => '10 Abak Road, Uyo, Akwa Ibom State',
            'businessPhone' => '08012345678',
            'currency' => '₦',
            'categories' => ['Groceries', 'Commodities'],
            'transactionEditLimitDays' => 3,
            'fontFamily' => 'Plus Jakarta Sans',
            'owner_whatsapp_phone' => '08012345678',
            'whatsapp_report_enabled' => true,
            'whatsapp_report_time' => '21:00',
            'whatsapp_branch_breakdown' => true,
            'whatsapp_include_reversals' => true,
            'whatsapp_include_low_stock' => true,
            'lowStockThreshold' => 5,
        ]);
    }

    /**
     * Test phone number formatting for WhatsApp (Nigerian local and international formats).
     */
    public function test_phone_number_formatting(): void
    {
        // 080... converted to 23480...
        $this->assertEquals('2348012345678', $this->whatsAppService->formatPhoneNumber('08012345678'));
        $this->assertEquals('2349033334444', $this->whatsAppService->formatPhoneNumber('09033334444'));

        // +234 stripped to 234...
        $this->assertEquals('2348012345678', $this->whatsAppService->formatPhoneNumber('+234 801 234 5678'));

        // international US number
        $this->assertEquals('14155552671', $this->whatsAppService->formatPhoneNumber('+1 (415) 555-2671'));
    }

    /**
     * Test dispatching via local log gateway.
     */
    public function test_dispatch_via_log_gateway(): void
    {
        config(['whatsapp.gateway' => 'log', 'whatsapp.enabled' => true]);

        $res = $this->whatsAppService->sendMessage('08012345678', 'Test message content', 'default-tenant');
        $this->assertTrue($res['success']);
        $this->assertEquals('log', $res['gateway']);
        $this->assertTrue($res['simulated']);
        $this->assertEquals('2348012345678', $res['to']);
    }

    /**
     * Test dispatching via Meta Cloud API using Http::fake.
     */
    public function test_dispatch_via_meta_cloud_api(): void
    {
        config([
            'whatsapp.gateway' => 'meta',
            'whatsapp.enabled' => true,
            'whatsapp.meta.phone_number_id' => '1099283746554',
            'whatsapp.meta.token' => 'meta_test_bearer_token',
            'whatsapp.meta.api_url' => 'https://graph.facebook.com/v19.0',
        ]);

        Http::fake([
            'https://graph.facebook.com/v19.0/1099283746554/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '2348012345678', 'wa_id' => '2348012345678']],
                'messages' => [['id' => 'wamid.HBgLMjM0ODAxMjM0NTY3OA==']],
            ], 200),
        ]);

        $res = $this->whatsAppService->sendMessage('08012345678', 'Hello Meta WhatsApp API', 'default-tenant');
        $this->assertTrue($res['success']);
        $this->assertEquals('meta', $res['gateway']);
        $this->assertEquals('wamid.HBgLMjM0ODAxMjM0NTY3OA==', $res['message_id']);
    }

    /**
     * Test dispatching via Termii API using Http::fake.
     */
    public function test_dispatch_via_termii_api(): void
    {
        config([
            'whatsapp.gateway' => 'termii',
            'whatsapp.enabled' => true,
            'whatsapp.termii.api_key' => 'termii_test_key_xyz',
            'whatsapp.termii.api_url' => 'https://api.ng.termii.com/api',
            'whatsapp.termii.device_id' => 'VictoriousPOS',
        ]);

        Http::fake([
            'https://api.ng.termii.com/api/sms/send' => Http::response([
                'message_id' => '91283746519283',
                'message' => 'Successfully Sent',
                'balance' => 450.0,
            ], 200),
        ]);

        $res = $this->whatsAppService->sendMessage('08012345678', 'Hello Termii API', 'default-tenant');
        $this->assertTrue($res['success']);
        $this->assertEquals('termii', $res['gateway']);
    }

    /**
     * Test end-to-end artisan pos:send-daily-whatsapp reconciliation calculation.
     */
    public function test_artisan_daily_whatsapp_report_calculation_and_dispatch(): void
    {
        $today = Carbon::today();

        // 1. Create a Product with low stock
        $product = Product::create([
            'id' => 'PRD-RICE-50',
            'tenant_id' => 'tenant-victorious',
            'name' => 'Mama Gold Rice 50kg',
            'code' => 'RICE-50KG',
            'category' => 'Commodities',
            'unitPrice' => 75000.00,
            'costPrice' => 70000.00,
            'stock' => 2, // Low stock: <= 5
            'unit' => 'bags',
        ]);

        // 2. Create Sale 1: Paid in CASH (₦75,000)
        $sale1 = Sale::create([
            'id' => 'SALE-WHATSAPP-001',
            'tenant_id' => 'tenant-victorious',
            'receipt_ref' => 'REC-WA-001',
            'warehouse_id' => 1,
            'customerName' => 'Walk-in Cash Customer',
            'totalAmount' => 75000.00,
            'paidAmount' => 75000.00,
            'cashAmount' => 75000.00,
            'posAmount' => 0.00,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => 'USR-OWNER',
            'createdAt' => $today->copy()->setHour(10),
            'updatedAt' => $today->copy()->setHour(10),
        ]);
        Payment::create([
            'id' => 'PAY-WA-001',
            'saleId' => $sale1->id,
            'method' => 'CASH',
            'amount' => 75000.00,
            'status' => 'COMPLETED',
            'recordedBy' => 'Cashier',
            'timestamp' => $today->copy()->setHour(10)->toIso8601String(),
            'payment_date' => $today->copy()->setHour(10),
        ]);

        // 3. Create Sale 2: Part payment via POS (₦50,000 of ₦75,000, ₦25,000 Debt)
        $customer = Customer::create([
            'id' => 1,
            'tenant_id' => 'tenant-victorious',
            'name' => 'Chief Okon Debt Customer',
            'phone' => '08055554444',
            'total_debt' => 25000.00,
        ]);
        $sale2 = Sale::create([
            'id' => 'SALE-WHATSAPP-002',
            'tenant_id' => 'tenant-victorious',
            'receipt_ref' => 'REC-WA-002',
            'warehouse_id' => 1,
            'customerId' => 1,
            'customerName' => 'Chief Okon Debt Customer',
            'totalAmount' => 75000.00,
            'paidAmount' => 50000.00,
            'cashAmount' => 0.00,
            'posAmount' => 50000.00,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => 'USR-OWNER',
            'createdAt' => $today->copy()->setHour(12),
            'updatedAt' => $today->copy()->setHour(12),
        ]);
        Payment::create([
            'id' => 'PAY-WA-002',
            'saleId' => $sale2->id,
            'method' => 'POS',
            'amount' => 50000.00,
            'status' => 'COMPLETED',
            'recordedBy' => 'Cashier',
            'timestamp' => $today->copy()->setHour(12)->toIso8601String(),
            'payment_date' => $today->copy()->setHour(12),
        ]);

        // 4. Create Debt Recovery payment today (₦15,000 paid by Chief Okon)
        CustomerLedger::create([
            'id' => 1,
            'tenant_id' => 'tenant-victorious',
            'customer_id' => 1,
            'warehouse_id' => 1,
            'type' => 'PAYMENT',
            'amount' => 15000.00,
            'balance_after' => 10000.00,
            'payment_method' => 'CASH',
            'recorded_by' => 'Cashier',
            'running_balance' => 10000.00,
            'notes' => 'Installment payment on debt',
            'created_at' => $today->copy()->setHour(14),
        ]);

        // 5. Create a reversal audit activity today
        Activity::create([
            'id' => 'ACT-REV-001',
            'tenant_id' => 'tenant-victorious',
            'type' => 'TRANSACTION_VOIDED',
            'description' => 'Reversal of Sale #SALE-OLD (₦30,000.00) by Manager Victor: Customer exchange mistake',
            'userId' => 'USR-OWNER',
            'userName' => 'Manager Victor',
            'timestamp' => $today->copy()->setHour(16)->toIso8601String(),
            'created_at' => $today->copy()->setHour(16),
        ]);

        // Run the artisan daily report command
        $exitCode = Artisan::call('pos:send-daily-whatsapp', [
            '--date' => $today->toDateString(),
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Verify Activity record was created for owner dispatch
        $reportActivity = Activity::where('type', 'WHATSAPP_DAILY_REPORT_SENT')->first();
        $this->assertNotNull($reportActivity);
        $this->assertStringContainsString('08012345678', $reportActivity->description);
    }

    /**
     * Test Settings Web Panel update and Test WhatsApp dispatch endpoint.
     */
    public function test_settings_web_update_and_test_dispatch(): void
    {
        // 1. Owner updates WhatsApp settings in settings panel
        $response = $this->actingAs($this->owner)
            ->withSession(['tenant_id' => 'tenant-victorious'])
            ->post(route('settings.update'), [
            'businessName' => 'Victorious Mega Store Updated',
            'businessPhone' => '08011112222',
            'currency' => '₦',
            'lowStockThreshold' => 10,
            'owner_whatsapp_phone' => '08088887777',
            'whatsapp_report_enabled' => '1',
            'whatsapp_report_time' => '21:30',
            'whatsapp_branch_breakdown' => '1',
            'whatsapp_include_reversals' => '1',
            'whatsapp_include_low_stock' => '1',
        ]);

        $response->assertRedirect(route('settings.index'));

        $this->setting->refresh();
        $this->assertEquals('08088887777', $this->setting->owner_whatsapp_phone);
        $this->assertTrue($this->setting->whatsapp_report_enabled);
        $this->assertEquals('21:30', $this->setting->whatsapp_report_time);

        // 2. Owner triggers Test WhatsApp Message via AJAX
        $testResponse = $this->actingAs($this->owner)
            ->withSession(['tenant_id' => 'tenant-victorious'])
            ->postJson(route('settings.test.whatsapp'), [
            'phone' => '08088887777',
        ]);

        $testResponse->assertStatus(200);
        $testResponse->assertJson([
            'success' => true,
            'gateway' => 'log',
            'simulated' => true,
            'to' => '2348088887777',
        ]);
    }

    public function test_daily_closing_pdf_statement_export(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['tenant_id' => 'tenant-victorious'])
            ->get('/reports/export-pdf/daily_closing?warehouse_id=1&date=' . now()->toDateString());

        $response->assertStatus(200);
        $response->assertSee('Official Daily Closing &amp; Reconciliation Statement', false);
        $response->assertSee('GROSS SALES INVOICED');
        $response->assertSee('NET CASH IN TILL (BANK DEPOSIT)');
        $response->assertSee('Cashier / Duty Officer');
        $response->assertSee('Executive Audit Sign-off');
    }

    public function test_branch_manager_whatsapp_configuration_and_isolated_alert_dispatch(): void
    {
        // 1. Owner updates warehouse via web route with manager WhatsApp phone
        $response = $this->actingAs($this->owner)
            ->withSession(['tenant_id' => 'tenant-victorious'])
            ->post("/settings/warehouse/update/{$this->warehouse->id}", [
                'name' => 'Victorious Mega Store',
                'code' => 'VIC-MEGA',
                'manager_name' => 'Bassey Manager',
                'manager_whatsapp_phone' => '08099887766',
                'whatsapp_report_enabled' => '1',
            ]);

        $response->assertRedirect(route('settings.index'));

        $this->warehouse->refresh();
        $this->assertEquals('08099887766', $this->warehouse->manager_whatsapp_phone);
        $this->assertTrue($this->warehouse->whatsapp_report_enabled);

        // 2. Dispatch command sends to manager as well as owner
        $exitCode = Artisan::call('pos:send-daily-whatsapp', [
            '--date' => now()->toDateString(),
            '--force' => true,
        ]);
        $this->assertEquals(0, $exitCode);

        // Check activity log
        $this->assertDatabaseHas('activities', [
            'type' => 'WHATSAPP_DAILY_REPORT_SENT',
        ]);
    }
}
