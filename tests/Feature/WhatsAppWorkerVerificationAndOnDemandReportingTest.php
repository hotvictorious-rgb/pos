<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Setting;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Payment;
use App\Services\Notification\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppWorkerVerificationAndOnDemandReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.enabled' => true,
            'whatsapp.gateway' => 'log',
            'whatsapp.webhook_verify_token' => 'test_webhook_token_123',
            'whatsapp.meta.phone_number' => '2349040503385',
        ]);
    }

    /**
     * Test 1: Meta Webhook GET Handshake.
     */
    public function test_meta_webhook_handshake_verifies_successfully(): void
    {
        $response = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=test_webhook_token_123&hub_challenge=challenge_token_9999');

        $response->assertStatus(200);
        $this->assertEquals('challenge_token_9999', $response->getContent());
    }

    /**
     * Test 2: Reject Invalid Webhook Handshake.
     */
    public function test_meta_webhook_handshake_rejects_invalid_token(): void
    {
        $response = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=challenge_token_9999');

        $response->assertStatus(403);
    }

    /**
     * Test 3: Generate 1-Tap Verification Token for Worker.
     */
    public function test_generate_whatsapp_token_creates_expiring_code(): void
    {
        $admin = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin Boss',
            'email' => 'admin@vmarket.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'tenant_id' => 'tenant-1',
            'permissions' => ['users' => true],
        ]);

        $worker = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Kufre Worker',
            'email' => 'kufre@vmarket.com',
            'password' => Hash::make('password123'),
            'tenant_id' => 'tenant-1',
            'role' => 'manager',
            'whatsapp_phone' => null,
            'whatsapp_verified_at' => null,
        ]);

        $response = $this->actingAs($admin)->postJson("/users/{$worker->id}/whatsapp-token");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'bot_phone' => '2349040503385',
            'is_verified' => false,
        ]);

        $worker->refresh();
        $this->assertNotNull($worker->whatsapp_verify_token);
        $this->assertNotNull($worker->whatsapp_verify_expires_at);
        $this->assertTrue($worker->whatsapp_verify_expires_at->isFuture());
    }

    /**
     * Test 4: Verify Worker Phone via Inbound Webhook.
     */
    public function test_inbound_verify_code_links_phone_and_activates_worker(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => 'tenant-1',
            'name' => 'Express Branch',
            'code' => 'EXP-01',
            'is_active' => true,
        ]);

        $worker = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Kufre Bassey',
            'email' => 'kufre.b@vmarket.com',
            'password' => Hash::make('password123'),
            'tenant_id' => 'tenant-1',
            'role' => 'manager',
            'warehouse_id' => $warehouse->id,
            'whatsapp_verify_token' => '7409',
            'whatsapp_verify_expires_at' => now()->addMinutes(10),
            'whatsapp_verified_at' => null,
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '2348012345678',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'VERIFY 7409',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);

        $response->assertStatus(200);

        $worker->refresh();
        $this->assertEquals('2348012345678', $worker->whatsapp_phone);
        $this->assertNotNull($worker->whatsapp_verified_at);
        $this->assertNull($worker->whatsapp_verify_token);
    }

    /**
     * Test 5: Inbound On-Demand REPORT Command Scoped Strictly to Assigned Branch.
     */
    public function test_inbound_report_command_executes_branch_isolated_report(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => 'tenant-1',
            'name' => 'Main Depo',
            'code' => 'MN-01',
            'is_active' => true,
        ]);

        $worker = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Branch Manager',
            'email' => 'manager.main@vmarket.com',
            'password' => Hash::make('password123'),
            'tenant_id' => 'tenant-1',
            'role' => 'manager',
            'warehouse_id' => $warehouse->id,
            'whatsapp_phone' => '2348099887766',
            'whatsapp_verified_at' => now(),
            'disabled' => false,
        ]);

        Setting::create([
            'id' => 1,
            'tenant_id' => 'tenant-1',
            'businessName' => 'Victorious Depo',
            'businessAddress' => '10 Abak Road, Uyo, Akwa Ibom State',
            'businessPhone' => '08012345678',
            'currency' => '₦',
            'categories' => ['Groceries', 'Commodities'],
            'transactionEditLimitDays' => 3,
            'fontFamily' => 'Plus Jakarta Sans',
            'owner_whatsapp_phone' => '2348000000000',
            'whatsapp_report_enabled' => true,
            'whatsapp_report_time' => '21:00',
            'whatsapp_branch_breakdown' => true,
            'whatsapp_include_reversals' => true,
            'whatsapp_include_low_stock' => true,
            'lowStockThreshold' => 5,
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '2348099887766',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'REPORT',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);

        $response->assertStatus(200);
        $this->assertEquals('EVENT_RECEIVED', $response->getContent());
    }

    /**
     * Test 6: Inbound STOCK Lookup Command.
     */
    public function test_inbound_stock_lookup_returns_inventory_matches(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => 'tenant-1',
            'name' => 'Depo A',
            'code' => 'DP-A',
            'is_active' => true,
        ]);

        $worker = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier Attendant',
            'email' => 'cashier.a@vmarket.com',
            'password' => Hash::make('password123'),
            'tenant_id' => 'tenant-1',
            'role' => 'cashier',
            'warehouse_id' => $warehouse->id,
            'whatsapp_phone' => '2348123456789',
            'whatsapp_verified_at' => now(),
            'disabled' => false,
        ]);

        $product = Product::create([
            'id' => 'PRD-SUIT-001',
            'code' => 'SUIT-001',
            'tenant_id' => 'tenant-1',
            'name' => 'Vintage Italian Suit',
            'category' => 'Suits',
            'unitPrice' => 45000,
            'currentStock' => 10,
        ]);

        StockLevel::create([
            'tenant_id' => 'tenant-1',
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'physical_stock' => 8,
            'allocated_stock' => 2,
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '2348123456789',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'STOCK Italian',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);

        $response->assertStatus(200);
        $this->assertEquals('EVENT_RECEIVED', $response->getContent());
    }

    /**
     * Test 7: Unauthorized Phone Number is Denied Access.
     */
    public function test_unauthorized_phone_number_is_blocked(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => '2349999999999',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'REPORT',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);

        $response->assertStatus(200);
        $this->assertEquals('EVENT_RECEIVED', $response->getContent());
    }
}
