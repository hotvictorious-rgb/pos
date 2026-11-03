<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Setting;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\CustomerLedger;
use App\Models\Customer;
use App\Models\InventoryLog;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\StockReservation;
use App\Models\Product;
use App\Console\Commands\MakeVersionOverlayCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use ZipArchive;

class VersionOverlayAndDeepAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $warehouse;
    protected User $admin;
    protected User $cashier;
    protected Setting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::create([
            'id' => 1,
            'tenant_id' => 'tenant-victorious',
            'name' => 'Main Superstore',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'id' => 'USR-ADMIN-01',
            'name' => 'Admin Manager',
            'email' => 'admin@victorious.ng',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'warehouse_id' => $this->warehouse->id,
            'tenant_id' => 'tenant-victorious',
            'permissions' => ['all', 'settings.manage'],
        ]);

        $this->cashier = User::create([
            'id' => 'USR-CASHIER-01',
            'name' => 'Frontline Cashier',
            'email' => 'cashier@victorious.ng',
            'password' => bcrypt('secret123'),
            'role' => 'cashier',
            'warehouse_id' => $this->warehouse->id,
            'tenant_id' => 'tenant-victorious',
            'permissions' => ['pos.view'],
        ]);

        $this->setting = Setting::create([
            'id' => 1,
            'tenant_id' => 'tenant-victorious',
            'businessName' => 'Victorious Enterprise',
            'businessPhone' => '08031234567',
            'owner_whatsapp_phone' => '08099887766',
            'whatsapp_report_enabled' => true,
            'whatsapp_report_time' => '21:00',
            'whatsapp_branch_breakdown' => true,
            'whatsapp_include_reversals' => true,
            'whatsapp_include_low_stock' => true,
            'whatsapp_include_inventory' => true,
            'whatsapp_include_transfers' => true,
            'whatsapp_include_deliveries' => true,
            'currency' => '₦',
            'categories' => ['General'],
            'reportFooter' => 'Thank you for your patronage',
            'lowStockThreshold' => 5,
            'transactionEditLimitDays' => 0,
            'fontFamily' => 'Plus Jakarta Sans',
        ]);

        session(['tenant_id' => 'tenant-victorious']);
    }

    /**
     * Test 1: Version increment strictly by 0.0.1.
     */
    public function test_version_increments_strictly_by_0_0_1()
    {
        $this->assertEquals('1.0.1', MakeVersionOverlayCommand::bumpVersionBy001('1.0.0'));
        $this->assertEquals('1.0.1', MakeVersionOverlayCommand::bumpVersionBy001('v1.0.0'));
        $this->assertEquals('1.0.2', MakeVersionOverlayCommand::bumpVersionBy001('1.0.1'));
        $this->assertEquals('1.0.10', MakeVersionOverlayCommand::bumpVersionBy001('1.0.9'));
        $this->assertEquals('2.1.1', MakeVersionOverlayCommand::bumpVersionBy001('2.1.0'));
    }

    /**
     * Test 2: Overlay command packages valid zip archive and strictly excludes sensitive files.
     */
    public function test_overlay_command_packages_valid_zip_archive()
    {
        $targetTag = 'v1.0.1-test';
        $outputDir = 'releases_test';

        $exitCode = $this->artisan('app:make-overlay', [
            '--target-version' => $targetTag,
            '--output-dir' => $outputDir,
            '--all' => true,
        ])->run();

        $this->assertEquals(0, $exitCode);

        $zipPath = base_path("{$outputDir}/overlay-{$targetTag}.zip");
        $this->assertFileExists($zipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath));

        // Must include OVERLAY_README.txt and overlay-runner.php
        $this->assertNotFalse($zip->locateName('OVERLAY_README.txt'));
        $this->assertNotFalse($zip->locateName('overlay-runner.php'));

        // Must NOT contain sensitive files
        $this->assertFalse($zip->locateName('.env'));
        $this->assertFalse($zip->locateName('.env.example'));
        $this->assertFalse($zip->locateName('storage/logs/laravel.log'));

        $zip->close();

        // Cleanup test directory
        File::deleteDirectory(base_path($outputDir));
    }

    /**
     * Test 3: Deep Daily Audit WhatsApp Report collects all 10+ operational categories.
     */
    public function test_deep_daily_audit_whatsapp_report_collects_all_system_metrics()
    {
        $now = now('Africa/Lagos');
        $nowIso = $now->toIso8601String();

        // 1. Retail Sale
        $sale1 = Sale::create([
            'id' => 'SALE-RETAIL-01',
            'tenant_id' => 'tenant-victorious',
            'warehouse_id' => $this->warehouse->id,
            'userId' => $this->admin->id,
            'sale_type' => 'retail',
            'totalAmount' => 50000,
            'paidAmount' => 50000,
            'cashAmount' => 50000,
            'posAmount' => 0,
            'status' => 'PAID',
            'deliveryStatus' => 'DELIVERED',
            'createdAt' => $nowIso,
        ]);

        Payment::create([
            'id' => 'PAY-01',
            'saleId' => $sale1->id,
            'amount' => 50000,
            'method' => 'CASH',
            'warehouse_id' => $this->warehouse->id,
            'recordedBy' => 'Admin Manager',
            'status' => 'COMPLETED',
            'timestamp' => $nowIso,
            'payment_date' => $now,
        ]);

        // 2. Wholesale Credit Sale (New Debt created)
        $sale2 = Sale::create([
            'id' => 'SALE-WHOLESALE-01',
            'tenant_id' => 'tenant-victorious',
            'warehouse_id' => $this->warehouse->id,
            'userId' => $this->admin->id,
            'sale_type' => 'wholesale',
            'totalAmount' => 120000,
            'paidAmount' => 20000,
            'cashAmount' => 0,
            'posAmount' => 20000,
            'status' => 'PARTIAL',
            'deliveryStatus' => 'PENDING', // Awaiting pickup
            'createdAt' => $nowIso,
        ]);

        Payment::create([
            'id' => 'PAY-02',
            'saleId' => $sale2->id,
            'amount' => 20000,
            'method' => 'POS',
            'warehouse_id' => $this->warehouse->id,
            'recordedBy' => 'Admin Manager',
            'status' => 'COMPLETED',
            'timestamp' => $nowIso,
            'payment_date' => $now,
        ]);

        // 3. Customer Debt Recovery
        $customer = Customer::create([
            'id' => 101,
            'tenant_id' => 'tenant-victorious',
            'name' => 'Chief Okon',
            'phone' => '08011223344',
            'total_debt' => 100000,
        ]);

        CustomerLedger::create([
            'id' => 10,
            'tenant_id' => 'tenant-victorious',
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'PAYMENT',
            'amount' => 30000,
            'balance_after' => 70000,
            'running_balance' => 70000,
            'recorded_by' => 'Admin Manager',
            'notes' => 'Cheque cleared / Cash collection',
            'created_at' => $now,
        ]);

        // 4. Physical Stock In
        InventoryLog::create([
            'id' => 'LOG-IN-01',
            'tenant_id' => 'tenant-victorious',
            'warehouse_id' => $this->warehouse->id,
            'productId' => 'PROD-1',
            'productName' => 'Golden Penny Sugar 50kg',
            'productCode' => 'GPS-50',
            'type' => 'STOCK_IN',
            'quantity' => 100,
            'previousStock' => 10,
            'newStock' => 110,
            'userId' => $this->admin->id,
            'userName' => 'Admin Manager',
            'timestamp' => $nowIso,
        ]);

        // 5. Stock Out & Damage
        InventoryLog::create([
            'id' => 'LOG-OUT-01',
            'tenant_id' => 'tenant-victorious',
            'warehouse_id' => $this->warehouse->id,
            'productId' => 'PROD-1',
            'productName' => 'Golden Penny Sugar 50kg',
            'productCode' => 'GPS-50',
            'type' => 'SALE_DISPATCH',
            'quantity' => -25,
            'previousStock' => 110,
            'newStock' => 85,
            'userId' => $this->admin->id,
            'userName' => 'Admin Manager',
            'timestamp' => $nowIso,
        ]);

        InventoryLog::create([
            'id' => 'LOG-DMG-01',
            'tenant_id' => 'tenant-victorious',
            'warehouse_id' => $this->warehouse->id,
            'productId' => 'PROD-1',
            'productName' => 'Golden Penny Sugar 50kg',
            'productCode' => 'GPS-50',
            'type' => 'STOCK_ADJUSTMENT_DAMAGE',
            'quantity' => -2,
            'previousStock' => 85,
            'newStock' => 83,
            'userId' => $this->admin->id,
            'userName' => 'Admin Manager',
            'timestamp' => $nowIso,
        ]);

        // 6. Inter-Branch Transfer Outbound
        $destWarehouse = Warehouse::create([
            'id' => 2,
            'tenant_id' => 'tenant-victorious',
            'name' => 'Outlet Store',
            'code' => 'WH-OUTLET',
            'is_active' => true,
        ]);

        $transfer = Transfer::create([
            'id' => 501,
            'tenant_id' => 'tenant-victorious',
            'transfer_no' => 'TRF-001',
            'source_warehouse_id' => $this->warehouse->id,
            'destination_warehouse_id' => $destWarehouse->id,
            'status' => 'DISPATCHED',
            'carrier_name' => 'Speed Delivery',
            'dispatched_by' => 'Officer Mike',
            'dispatched_at' => $now,
        ]);

        TransferItem::create([
            'tenant_id' => 'tenant-victorious',
            'transfer_id' => $transfer->id,
            'product_id' => 'PROD-1',
            'product_name' => 'Golden Penny Sugar 50kg',
            'dispatched_qty' => 15,
        ]);

        // 7. Unsupplied Stock Reservation Backlog
        StockReservation::create([
            'id' => 'RES-001',
            'tenant_id' => 'tenant-victorious',
            'sale_id' => $sale2->id,
            'product_id' => 'PROD-1',
            'warehouse_id' => $this->warehouse->id,
            'reserved_qty' => 10,
            'fulfilled_qty' => 0,
            'cancelled_qty' => 0,
            'status' => 'ACTIVE',
        ]);

        // Run Daily WhatsApp Dispatch Command
        $exitCode = $this->artisan('pos:send-daily-whatsapp', [
            '--tenant' => 'tenant-victorious',
            '--force' => true,
        ])->run();

        $this->assertEquals(0, $exitCode);

        // Verify the message built by WhatsApp service contains the deep metrics
        $whatsAppService = app(\App\Services\Notification\WhatsAppService::class);
        $command = app(\App\Console\Commands\SendDailyWhatsAppReportCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('compileDailyMetrics');
        $method->setAccessible(true);

        $metrics = $method->invoke($command, 'tenant-victorious', $now, $this->setting, 'Victorious Enterprise', $this->warehouse->id);

        $this->assertEquals(2, $metrics['total_invoices']);
        $this->assertEquals(170000.0, $metrics['gross_sales']);
        $this->assertEquals(50000.0, $metrics['cash_collected']);
        $this->assertEquals(20000.0, $metrics['pos_collected']);
        $this->assertEquals(30000.0, $metrics['debts_recovered']);
        $this->assertEquals(100000.0, $metrics['new_debts']);
        $this->assertEquals(80000.0, $metrics['expected_drawer_cash']); // 50k cash sales + 30k debt recovery

        // Inventory metrics
        $this->assertEquals(100.0, $metrics['stock_in_units']);
        $this->assertEquals(25.0, $metrics['stock_out_units']);
        $this->assertEquals(2.0, $metrics['damage_units']);
        $this->assertEquals(73.0, $metrics['net_stock_delta']); // 100 - 25 - 2

        // Transfers
        $this->assertEquals(1, $metrics['outbound_transfers_count']);
        $this->assertEquals(15.0, $metrics['outbound_units']);

        // Backlog
        $this->assertEquals(1, $metrics['pending_deliveries_count']);
        $this->assertEquals(10.0, $metrics['unsupplied_backlog_units']);

        $msg = $whatsAppService->buildDailyReportMessage($metrics);

        $this->assertStringContainsString('DEEP DAILY AUDIT SUMMARY', $msg);
        $this->assertStringContainsString('STOCK MOVEMENT (PHYSICAL UNITS)', $msg);
        $this->assertStringContainsString('+100 units', $msg);
        $this->assertStringContainsString('-25 units', $msg);
        $this->assertStringContainsString('INTER-BRANCH TRANSFERS', $msg);
        $this->assertStringContainsString('15 units across 1 transfers', $msg);
        $this->assertStringContainsString('PENDING DELIVERIES & BACKLOG', $msg);
        $this->assertStringContainsString('10 uncollected items', $msg);
        $this->assertStringContainsString('NET CASH IN TILL:* ₦80,000.00', $msg);
    }

    /**
     * Test 4: Live overlay migration runner endpoint.
     */
    public function test_admin_can_run_live_overlay_migrations_via_web_endpoint()
    {
        $response = $this->actingAs($this->admin)->postJson('/settings/run-migrations');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Cashier cannot run migrations
        $cashierResponse = $this->actingAs($this->cashier)->postJson('/settings/run-migrations');
        $cashierResponse->assertStatus(403);
    }
}
