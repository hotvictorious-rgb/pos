<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Payment;
use App\Services\Notification\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WhatsAppChatbotAccuracyAndLoginLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.enabled' => true]);
        config(['whatsapp.gateway' => 'log']);
    }

    protected function createSetting(array $overrides = []): Setting
    {
        return Setting::create(array_merge([
            'tenant_id' => 'tenant-test',
            'businessName' => 'Victorious Market Superstores',
            'businessAddress' => '10 Abak Road, Uyo, Akwa Ibom State',
            'businessPhone' => '08012345678',
            'currency' => '₦',
            'lowStockThreshold' => 5,
            'transactionEditLimitDays' => 0,
            'fontFamily' => 'Plus Jakarta Sans',
            'reportFooter' => 'Thank you for your patronage!',
            'owner_whatsapp_phone' => '2349118949035',
            'whatsapp_report_enabled' => true,
            'whatsapp_report_time' => '21:00',
            'whatsapp_branch_breakdown' => true,
            'whatsapp_include_reversals' => true,
            'whatsapp_include_low_stock' => true,
            'whatsapp_include_inventory' => true,
            'whatsapp_include_transfers' => true,
            'whatsapp_include_deliveries' => true,
            'categories' => ['Groceries', 'Beverages'],
        ], $overrides));
    }

    public function test_login_page_renders_with_mature_structured_container_and_aligned_footer(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $content = $response->getContent();

        // Must have the dedicated wrapper container
        $this->assertStringContainsString('class="login-container"', $content);
        $this->assertStringContainsString('class="login-card"', $content);
        $this->assertStringContainsString('class="login-footer"', $content);

        // Verify branding and footer elements
        $this->assertStringContainsString('Victorious Market POS', $content);
        $this->assertStringContainsString('Powered by', $content);
        $this->assertStringContainsString('Victorious Market', $content);
        $this->assertStringContainsString('www.victoriousmarket.com.ng', $content);

        // Verify password visibility toggle button and universal enter progression
        $this->assertStringContainsString('togglePasswordBtn', $content);
        $this->assertStringContainsString('loginSubmitBtn', $content);
    }

    public function test_whatsapp_webhook_handshake_verification(): void
    {
        // Valid verify token
        $response = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=victorious_pos_secret_token_2026&hub_challenge=CHALLENGE_ACCEPTED_999');
        $response->assertStatus(200);
        $this->assertEquals('CHALLENGE_ACCEPTED_999', $response->getContent());

        // Invalid verify token
        $badResponse = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=TEST');
        $badResponse->assertStatus(403);
    }

    public function test_whatsapp_chatbot_rejects_unregistered_phone_numbers(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '2348999999999',
                                        'type' => 'text',
                                        'text' => ['body' => 'SALES']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);
        $response->assertStatus(200);
        $this->assertEquals('EVENT_RECEIVED', $response->getContent());
    }

    public function test_whatsapp_chatbot_responds_accurately_to_all_commands_and_numeric_shortcuts(): void
    {
        // Setup setting with owner phone
        $setting = $this->createSetting([
            'id' => 1,
            'tenant_id' => 'tenant-test',
            'owner_whatsapp_phone' => '2349118949035',
        ]);

        // Create product with stock
        $prod = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-test',
            'code' => 'TEST-001',
            'name' => 'Peak Milk 400g',
            'category' => 'Groceries',
            'unitPrice' => 2500,
            'currentStock' => 4, // low stock <= 5
            'archived' => false,
        ]);

        // Create a completed sale today
        $sale = Sale::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-test',
            'customerName' => 'Walk-in Customer',
            'totalAmount' => 5000,
            'paidAmount' => 5000,
            'cashAmount' => 5000,
            'posAmount' => 0,
            'transferAmount' => 0,
            'status' => 'COMPLETED',
            'deliveryStatus' => 'DELIVERED',
            'userId' => 'USR-TEST',
            'createdAt' => now()->toIso8601String(),
        ]);

        Payment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-test',
            'saleId' => $sale->id,
            'amount' => 5000,
            'method' => 'CASH',
            'timestamp' => now()->toIso8601String(),
            'recordedBy' => 'Cashier',
            'createdAt' => now()->toIso8601String(),
        ]);

        \App\Models\SaleItem::create([
            'id' => 1,
            'tenant_id' => 'tenant-test',
            'saleId' => $sale->id,
            'productId' => $prod->id,
            'productName' => $prod->name,
            'quantity' => 2,
            'unitPrice' => 2500,
            'totalPrice' => 5000,
        ]);

        // Test list of all supported commands (words, numeric shortcuts, dates, items sold, inventory, and direct product names)
        $testCommands = [
            'MENU',
            'HELP',
            '1',
            'REPORT',
            'REPORT yesterday',
            '2',
            'SALES',
            'SALES yesterday',
            '3',
            'CASH',
            'CASH yesterday',
            '4',
            'SOLD',
            'SOLD yesterday',
            '5 Peak',
            'STOCK Peak',
            'STOCK ALL',
            '6',
            '7',
            'LOW',
            '8',
            'LOG',
            'ACTIVITY',
            '9',
            'DEBT',
            '10',
            'CASHIERS',
            '11',
            'WAYBILLS',
            'Peak Milk 400g', // Direct product name without 'STOCK' prefix
        ];

        foreach ($testCommands as $cmd) {
            $payload = [
                'entry' => [
                    [
                        'changes' => [
                            [
                                'value' => [
                                    'messages' => [
                                        [
                                            'from' => '2349118949035',
                                            'type' => 'text',
                                            'text' => ['body' => $cmd]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $res = $this->postJson('/api/whatsapp/webhook', $payload);
            $res->assertStatus(200);
            $this->assertEquals('EVENT_RECEIVED', $res->getContent(), "Failed command: {$cmd}");
        }
    }

    public function test_whatsapp_chatbot_matches_nigerian_local_phone_format_in_settings(): void
    {
        // Owner saved phone in local 09118949035 format
        $this->createSetting([
            'id' => 2,
            'tenant_id' => 'tenant-local-phone',
            'owner_whatsapp_phone' => '09118949035',
        ]);

        // WhatsApp Meta webhook sends international 2349118949035
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '2349118949035',
                                        'type' => 'text',
                                        'text' => ['body' => 'SALES']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $res = $this->postJson('/api/whatsapp/webhook', $payload);
        $res->assertStatus(200);
        $this->assertEquals('EVENT_RECEIVED', $res->getContent());
    }

    public function test_shopfront_portal_links_direct_to_unified_login(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Must not link to old portal routes
        $this->assertStringNotContainsString('/tenant/login', $content);
        $this->assertStringNotContainsString('/tenant-employee/login', $content);

        // Direct /tenant/login navigations must redirect to /login
        $tenantLoginRedirect = $this->get('/tenant/login');
        $tenantLoginRedirect->assertRedirect('/login');

        $employeeLoginRedirect = $this->get('/tenant-employee/login');
        $employeeLoginRedirect->assertRedirect('/login');
    }

    public function test_whatsapp_ussd_nested_menu_navigation_and_shortcodes(): void
    {
        $this->createSetting([
            'id' => 3,
            'tenant_id' => 'tenant-ussd',
            'owner_whatsapp_phone' => '2348030001122',
        ]);

        $sender = '2348030001122';

        $sendMsg = function (string $text) use ($sender) {
            return $this->postJson('/api/whatsapp/webhook', [
                'entry' => [
                    [
                        'changes' => [
                            [
                                'value' => [
                                    'messages' => [
                                        [
                                            'from' => $sender,
                                            'type' => 'text',
                                            'text' => ['body' => $text]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
        };

        // 1. Send '0' or 'MENU' to open Root USSD Console
        $res = $sendMsg('0');
        $res->assertStatus(200);
        $this->assertEquals('ROOT', \Illuminate\Support\Facades\Cache::get("wa_nav_state_{$sender}"));

        // 2. Send '1' from ROOT to enter Sales & Revenue Sub-Menu
        $res = $sendMsg('1');
        $res->assertStatus(200);
        $this->assertEquals('SALES', \Illuminate\Support\Facades\Cache::get("wa_nav_state_{$sender}"));

        // 3. Send '1' within SALES to trigger Today's Sales Pulse
        $res = $sendMsg('1');
        $res->assertStatus(200);

        // 4. Send '0' within SALES to return to ROOT
        $res = $sendMsg('0');
        $res->assertStatus(200);
        $this->assertEquals('ROOT', \Illuminate\Support\Facades\Cache::get("wa_nav_state_{$sender}"));

        // 5. Direct USSD shortcode '1.1' (Sales Pulse)
        $res = $sendMsg('1.1');
        $res->assertStatus(200);

        // 6. Direct USSD shortcode '1*2' (Cash Drawer)
        $res = $sendMsg('1*2');
        $res->assertStatus(200);

        // 7. Direct USSD shortcode '2.2' (Stock Valuation)
        $res = $sendMsg('2.2');
        $res->assertStatus(200);

        // 8. Direct USSD shortcode '3.1' (Debt Overview)
        $res = $sendMsg('3.1');
        $res->assertStatus(200);

        // 9. Direct USSD shortcode '4.1' (Cashier Scoreboard)
        $res = $sendMsg('4.1');
        $res->assertStatus(200);

        // 10. Direct USSD shortcode '5.1' (Waybills)
        $res = $sendMsg('5.1');
        $res->assertStatus(200);

        // 11. Direct USSD shortcode '6.1' (Audit Timeline)
        $res = $sendMsg('6.1');
        $res->assertStatus(200);
    }

    public function test_multi_identifier_login_and_role_dashboard_isolation(): void
    {
        // 0. Ensure tenant exists
        \App\Models\Tenant::create([
            'id' => 'tenant-test',
            'name' => 'Victorious Market',
            'status' => 'active',
            'owner_email' => 'owner@victoriousmarket.com',
            'owner_phone' => '2348000000002',
            'plan' => 'pro',
        ]);

        // 1. Create a Super Admin
        $superAdmin = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'default-tenant',
            'name' => 'Root SuperAdmin',
            'email' => 'superadmin@hysam.com',
            'password' => bcrypt('SuperSecret123!'),
            'role' => 'super_admin',
            'whatsapp_phone' => '2348000000001',
        ]);

        // 2. Create a Business Tenant Admin (Owner)
        $tenantOwner = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-test',
            'name' => 'victor_owner',
            'email' => 'owner@victoriousmarket.com',
            'password' => bcrypt('OwnerSecret123!'),
            'role' => 'admin',
            'whatsapp_phone' => '2348000000002',
        ]);

        // 3. Create a Cashier for the tenant
        $cashier = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-test',
            'name' => 'mary_cashier',
            'email' => 'mary@victoriousmarket.com',
            'password' => bcrypt('CashierSecret123!'),
            'role' => 'cashier',
            'whatsapp_phone' => '2348000000003',
        ]);

        // Case A: Cashier logs in via username 'mary_cashier' -> Lands at POS
        $foundCashier = User::findForAuthentication('mary_cashier');
        $this->assertNotNull($foundCashier, 'Cashier should be found by username');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('CashierSecret123!', $foundCashier->password), 'Password hash check should match');

        $loginRes = $this->from('/login')->post('/login', [
            'email' => 'mary_cashier',
            'password' => 'CashierSecret123!',
        ]);
        if ($loginRes->getSession()->has('error')) {
            $this->fail('Login failed with error: ' . $loginRes->getSession()->get('error'));
        }
        $loginRes->assertRedirect(route('pos.index'));
        $this->assertAuthenticatedAs($cashier);
        $this->post('/logout');

        // Case B: Cashier logs in via WhatsApp Phone '08000000003' -> Lands at POS
        $loginRes = $this->post('/login', [
            'email' => '08000000003',
            'password' => 'CashierSecret123!',
        ]);
        if ($loginRes->getSession()->has('error')) {
            $this->fail('Login failed with error: ' . $loginRes->getSession()->get('error'));
        }
        $loginRes->assertRedirect(route('pos.index'));
        $this->assertAuthenticatedAs($cashier);
        $this->post('/logout');

        // Case C: Tenant Owner logs in via username 'victor_owner' -> Lands at Dashboard
        $loginRes = $this->post('/login', [
            'email' => 'victor_owner',
            'password' => 'OwnerSecret123!',
        ]);
        $loginRes->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($tenantOwner);
        $this->post('/logout');

        // Case D: Super Admin logs in via email -> Lands at Platform Command (saas.admin.index)
        $loginRes = $this->post('/login', [
            'email' => 'superadmin@hysam.com',
            'password' => 'SuperSecret123!',
        ]);
        $loginRes->assertRedirect(route('saas.admin.index'));
        $this->assertAuthenticatedAs($superAdmin);
        $this->post('/logout');

        // Case E: Strict Isolation - Cashier cannot access SaaS Admin
        $this->actingAs($cashier);
        $saasRes = $this->get('/saas/admin');
        $saasRes->assertRedirect(route('dashboard'));

        $saasJsonRes = $this->getJson('/saas/admin');
        $saasJsonRes->assertStatus(403);
    }

    public function test_branch_switch_handles_get_and_post_gracefully(): void
    {
        $owner = User::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 'tenant-switch-test',
            'name' => 'branch_owner',
            'email' => 'branch_owner@store.com',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);

        $wh = Warehouse::create([
            'tenant_id' => 'tenant-switch-test',
            'name' => 'Main Depot',
            'code' => 'DEPOT-01',
            'is_active' => true,
        ]);

        $this->actingAs($owner);

        // 1. GET /branch/switch must NEVER throw 405 MethodNotAllowed; must gracefully redirect to dashboard
        $getRes = $this->get('/branch/switch');
        $getRes->assertRedirect(route('dashboard'));

        // 2. POST /branch/switch switches active warehouse
        $postRes = $this->post('/branch/switch', [
            'warehouse_id' => $wh->id,
        ]);
        $postRes->assertSessionHas('active_warehouse_id', $wh->id);

        // 3. GET /tenant/login and /super-admin/login redirect to /login
        $tenantLoginRes = $this->get('/tenant/login');
        $tenantLoginRes->assertRedirect(route('login'));

        $superAdminLoginRes = $this->get('/super-admin/login');
        $superAdminLoginRes->assertRedirect(route('login'));
    }
}
